<?php
/**
 * Actualizaciones de la base de datos de la plataforma (migraciones) y
 * operaciones de simpatizantes compartidas entre la landing y el admin.
 *
 * La dirección aplica las actualizaciones con un clic desde el Panel
 * ("Actualizar plataforma"). Cada paso es idempotente: si ya está aplicado
 * se salta, así que repetirlo nunca rompe nada. Mientras un paso no se
 * aplique, el código sigue funcionando sin esa columna.
 */

/** Opciones de género: valor guardado => etiqueta visible. */
const GENEROS = [
    'mujer'   => 'Mujer',
    'hombre'  => 'Hombre',
    'otro'    => 'Otro',
    'no_dice' => 'Prefiero no decir',
];

/** Edad mínima para entrar a la red (Ley 1581: los datos de menores requieren autorización de sus representantes). */
const EDAD_MINIMA = 18;

/**
 * Pasos de actualización: [tabla, columna (null = crear la tabla), SQL, descripción].
 * El orden importa: cada paso se aplica una sola vez.
 */
function esquema_pasos(): array
{
    return [
        ['simpatizantes', 'codigo_promotor', 'ALTER TABLE simpatizantes ADD COLUMN codigo_promotor VARCHAR(16) NULL, ADD UNIQUE KEY uq_simp_codigo_promotor (codigo_promotor)', 'enlace personal de cada simpatizante'],
        ['simpatizantes', 'token_panel',     'ALTER TABLE simpatizantes ADD COLUMN token_panel CHAR(32) NULL, ADD UNIQUE KEY uq_simp_token_panel (token_panel)', 'llave del panel del promotor'],
        ['simpatizantes', 'referido_por',    'ALTER TABLE simpatizantes ADD COLUMN referido_por INT NULL, ADD KEY idx_simp_referido_por (referido_por)', 'quién invitó a quién'],
        ['simpatizantes', 'lat',             'ALTER TABLE simpatizantes ADD COLUMN lat DECIMAL(9,6) NULL', 'ubicación aproximada'],
        ['simpatizantes', 'lng',             'ALTER TABLE simpatizantes ADD COLUMN lng DECIMAL(9,6) NULL', 'ubicación aproximada'],
        ['simpatizantes', 'genero',          "ALTER TABLE simpatizantes ADD COLUMN genero ENUM('mujer','hombre','otro','no_dice') NULL", 'género'],
        ['simpatizantes', 'profundidad',     'ALTER TABLE simpatizantes ADD COLUMN profundidad TINYINT UNSIGNED NULL, ADD KEY idx_simp_profundidad (profundidad)', 'nivel en la red de contactos'],
        ['configuracion', null,              'CREATE TABLE IF NOT EXISTS configuracion (
                                                 clave VARCHAR(60) NOT NULL PRIMARY KEY,
                                                 valor VARCHAR(255) NULL,
                                                 actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                               ) DEFAULT CHARSET=utf8mb4', 'configuración (meta de la campaña)'],
    ];
}

/** Columnas de una tabla (cacheadas por petición; [] si la tabla no existe). */
function esquema_columnas(PDO $db, string $tabla, bool $refrescar = false): array
{
    static $cache = [];
    if ($refrescar) $cache = [];
    if (!isset($cache[$tabla])) {
        try {
            $st = $db->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
            );
            $st->execute(['t' => $tabla]);
            $cache[$tabla] = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $cache[$tabla] = [];
        }
    }
    return $cache[$tabla];
}

function esquema_tiene(PDO $db, string $tabla, ?string $columna = null): bool
{
    $cols = esquema_columnas($db, $tabla);
    return $columna === null ? $cols !== [] : in_array($columna, $cols, true);
}

/** Pasos aún no aplicados (descripciones únicas, para mostrar en el aviso). */
function esquema_pendientes(PDO $db): array
{
    $pendientes = [];
    foreach (esquema_pasos() as [$tabla, $columna, , $desc]) {
        if (!esquema_tiene($db, $tabla, $columna)) $pendientes[$desc] = $desc;
    }
    return array_values($pendientes);
}

/**
 * Aplica lo pendiente y completa los datos derivados (códigos de promotor,
 * nivel en la red). Devuelve lo aplicado. Lanza la excepción si algo falla.
 */
function esquema_actualizar(PDO $db): array
{
    $aplicados = [];
    foreach (esquema_pasos() as [$tabla, $columna, $sql, $desc]) {
        if (esquema_tiene($db, $tabla, $columna)) continue;
        $db->exec($sql);
        esquema_columnas($db, $tabla, true);
        $aplicados[] = $columna ?? "tabla $tabla";
    }

    $n = promotor_completar_codigos($db);
    if ($n > 0) $aplicados[] = "enlaces para $n simpatizantes";
    $n = esquema_completar_profundidad($db);
    if ($n > 0) $aplicados[] = "nivel de red para $n simpatizantes";
    return $aplicados;
}

/**
 * Calcula el nivel en la red de quienes aún no lo tienen:
 * nivel 1 = entró directo (por la candidata o un líder); nivel n+1 = invitado
 * por alguien de nivel n. Se recorre capa por capa.
 */
function esquema_completar_profundidad(PDO $db): int
{
    $total = $db->exec('UPDATE simpatizantes SET profundidad = 1 WHERE profundidad IS NULL AND referido_por IS NULL');
    $capa = $db->prepare(
        'UPDATE simpatizantes s JOIN simpatizantes p ON p.id = s.referido_por
         SET s.profundidad = LEAST(p.profundidad + 1, 255)
         WHERE s.profundidad IS NULL AND p.profundidad IS NOT NULL'
    );
    do {
        $capa->execute();
        $n = $capa->rowCount();
        $total += $n;
    } while ($n > 0);
    // Huérfanos (quien invitó ya no existe): se cuentan como entrada directa
    $total += $db->exec('UPDATE simpatizantes SET profundidad = 1 WHERE profundidad IS NULL');
    return $total;
}

/** Nivel en la red de quien entra invitado por $referidoPor (o 1 si entró directo). */
function simpatizante_profundidad(PDO $db, ?int $referidoPor): int
{
    if (!$referidoPor) return 1;
    $st = $db->prepare('SELECT profundidad FROM simpatizantes WHERE id = :id');
    $st->execute(['id' => $referidoPor]);
    return min(255, (int)($st->fetchColumn() ?: 1) + 1);
}

/**
 * Inserta un simpatizante. Completa lo automático (código y llave de
 * promotor, nivel en la red, fecha de consentimiento) y descarta las
 * columnas que la base todavía no tiene, así funciona antes y después de
 * "Actualizar plataforma".
 *
 * Devuelve ['id', 'codigo' (o null), 'token' (o null)].
 */
function simpatizante_insertar(PDO $db, array $d): array
{
    $cols = esquema_columnas($db, 'simpatizantes');

    $d['consentimiento_datos'] = 1;
    $d['consentimiento_fecha'] = $db->query('SELECT NOW()')->fetchColumn();
    if (in_array('codigo_promotor', $cols, true)) $d['codigo_promotor'] = promotor_nuevo_codigo($db);
    if (in_array('token_panel', $cols, true))     $d['token_panel']     = promotor_nuevo_token();
    if (in_array('profundidad', $cols, true))     $d['profundidad']     = simpatizante_profundidad($db, $d['referido_por'] ?? null);

    // Solo columnas que existen de verdad (los nombres salen de este código, nunca del usuario)
    $d = array_intersect_key($d, array_flip($cols));
    $campos = array_keys($d);
    $db->prepare(
        'INSERT INTO simpatizantes (' . implode(', ', $campos) . ')
         VALUES (:' . implode(', :', $campos) . ')'
    )->execute($d);

    return [
        'id'     => (int)$db->lastInsertId(),
        'codigo' => $d['codigo_promotor'] ?? null,
        'token'  => $d['token_panel'] ?? null,
    ];
}

/** Valida la fecha de nacimiento (obligatoria). Devuelve el mensaje de error o null. */
function simpatizante_error_nacimiento(string $fecha): ?string
{
    $f = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$f || $f->format('Y-m-d') !== $fecha) return 'Escribe tu fecha de nacimiento.';
    $edad = $f->diff(new DateTime('today'))->y;
    if ($f > new DateTime('today') || $edad > 110) return 'Revisa la fecha de nacimiento.';
    if ($edad < EDAD_MINIMA) return 'La red de la campaña es para mayores de ' . EDAD_MINIMA . ' años.';
    return null;
}
