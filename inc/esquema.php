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
 * Escala de compromiso del simpatizante, de menor a mayor. Es lo que convierte
 * registros en votos: el equipo la confirma llamando a cada persona.
 */
const COMPROMISOS = [
    'indeciso'     => 'Indeciso',
    'simpatizante' => 'Simpatizante',
    'voto_seguro'  => 'Voto seguro',
    'voluntario'   => 'Voluntario',
    'testigo'      => 'Testigo electoral',
];
/** Escala anterior (vigente hasta pulsar "Actualizar plataforma"). */
const COMPROMISOS_ANTERIORES = [
    'simpatizante'       => 'Simpatizante',
    'voluntario'         => 'Voluntario/a',
    'votante_confirmado' => 'Votante confirmado',
];
/** Niveles que ya cuentan como voto (en las dos escalas). */
const COMPROMISOS_SEGUROS = ['voto_seguro', 'voluntario', 'testigo', 'votante_confirmado'];

/**
 * Pasos de actualización: [tabla, columna (null = crear la tabla), SQL (o lista
 * de sentencias), descripción, verificación propia opcional fn(PDO): bool].
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
        // Escala de compromiso: se amplía la lista, se pasa "votante confirmado" a "voto seguro" y se deja la lista final
        ['simpatizantes', 'nivel', [
            "UPDATE simpatizantes SET nivel = 'simpatizante' WHERE nivel IS NULL OR nivel = ''",
            "ALTER TABLE simpatizantes MODIFY nivel ENUM('simpatizante','voluntario','votante_confirmado','indeciso','voto_seguro','testigo') NOT NULL DEFAULT 'simpatizante'",
            "UPDATE simpatizantes SET nivel = 'voto_seguro' WHERE nivel = 'votante_confirmado'",
            "ALTER TABLE simpatizantes MODIFY nivel ENUM('indeciso','simpatizante','voto_seguro','voluntario','testigo') NOT NULL DEFAULT 'simpatizante'",
        ], 'escala de compromiso de 5 niveles', fn(PDO $db) => compromiso_escala_nueva($db)],
        ['simpatizantes', 'verificado_at',   'ALTER TABLE simpatizantes ADD COLUMN verificado_at DATETIME NULL', 'verificación por llamada'],
        ['simpatizantes', 'verificado_por',  'ALTER TABLE simpatizantes ADD COLUMN verificado_por INT NULL', 'verificación por llamada'],
        ['puestos_votacion', 'mesas',        'ALTER TABLE puestos_votacion ADD COLUMN mesas SMALLINT UNSIGNED NULL', 'mesas y potencial de cada puesto'],
        ['puestos_votacion', 'potencial',    'ALTER TABLE puestos_votacion ADD COLUMN potencial INT UNSIGNED NULL', 'mesas y potencial de cada puesto'],
    ];
}

/** ¿Un paso ya está aplicado? (su verificación propia, o que exista la columna/tabla) */
function esquema_paso_aplicado(PDO $db, array $paso): bool
{
    return isset($paso[4]) ? (bool)($paso[4])($db) : esquema_tiene($db, $paso[0], $paso[1]);
}

/** ¿La columna nivel ya usa la escala de compromiso de 5 niveles? */
function compromiso_escala_nueva(PDO $db, bool $refrescar = false): bool
{
    static $nueva = null;
    if ($nueva === null || $refrescar) {
        try {
            $st = $db->prepare(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simpatizantes' AND COLUMN_NAME = 'nivel'"
            );
            $st->execute();
            $tipo = (string)$st->fetchColumn();
            $nueva = str_contains($tipo, "'voto_seguro'") && !str_contains($tipo, "'votante_confirmado'");
        } catch (Throwable $e) {
            $nueva = false;
        }
    }
    return $nueva;
}

/** Niveles de compromiso que la base acepta hoy (valor => etiqueta), de menor a mayor. */
function compromisos_disponibles(PDO $db): array
{
    return compromiso_escala_nueva($db) ? COMPROMISOS : COMPROMISOS_ANTERIORES;
}

function compromiso_etiqueta(?string $valor): string
{
    return COMPROMISOS[$valor] ?? COMPROMISOS_ANTERIORES[$valor] ?? (string)$valor;
}

/** Posición 0-4 en la escala nueva (el "votante confirmado" anterior equivale a voto seguro). */
function compromiso_indice(?string $valor): int
{
    if ($valor === 'votante_confirmado') $valor = 'voto_seguro';
    $i = array_search($valor, array_keys(COMPROMISOS), true);
    return $i === false ? 1 : $i;
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
    foreach (esquema_pasos() as $paso) {
        if (!esquema_paso_aplicado($db, $paso)) $pendientes[$paso[3]] = $paso[3];
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
    foreach (esquema_pasos() as $paso) {
        if (esquema_paso_aplicado($db, $paso)) continue;
        [$tabla, $columna, $sql] = $paso;
        foreach ((array)$sql as $sentencia) $db->exec($sentencia);
        esquema_columnas($db, $tabla, true);
        compromiso_escala_nueva($db, true);
        $aplicados[] = isset($paso[4]) ? $paso[3] : ($columna ?? "tabla $tabla");
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
