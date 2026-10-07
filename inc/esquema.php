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
 * de sentencias), descripción, verificación propia opcional fn(PDO): bool,
 * revisión previa opcional fn(PDO): ?string (si devuelve un aviso, el paso no se aplica)].
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

        // ---------- WhatsApp: plantillas, ocasiones, cola de mensajes y respuestas ----------
        ['simpatizantes', 'wa_baja_at', 'ALTER TABLE simpatizantes ADD COLUMN wa_baja_at DATETIME NULL', 'bajas de WhatsApp'],
        ['wa_plantillas', null, "CREATE TABLE IF NOT EXISTS wa_plantillas (
              id INT AUTO_INCREMENT PRIMARY KEY,
              nombre VARCHAR(120) NOT NULL,
              idioma VARCHAR(10) NOT NULL DEFAULT 'es',
              categoria VARCHAR(20) NULL,
              estado_meta VARCHAR(20) NULL,
              cuerpo TEXT NULL,
              num_variables TINYINT UNSIGNED NOT NULL DEFAULT 0,
              variables VARCHAR(255) NULL,
              compatible TINYINT(1) NOT NULL DEFAULT 1,
              actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY uq_wa_plantilla (nombre, idioma)
            ) DEFAULT CHARSET=utf8mb4", 'plantillas de WhatsApp'],
        ['wa_ocasiones', null, [
            "CREATE TABLE IF NOT EXISTS wa_ocasiones (
              id INT AUTO_INCREMENT PRIMARY KEY,
              clave VARCHAR(40) NOT NULL,
              nombre VARCHAR(80) NOT NULL,
              tipo ENUM('cumpleanos','profesion','fecha','evento') NOT NULL,
              regla VARCHAR(20) NULL,
              genero ENUM('mujer','hombre') NULL,
              plantilla_id INT NULL,
              activa TINYINT(1) NOT NULL DEFAULT 0,
              UNIQUE KEY uq_wa_ocasion (clave)
            ) DEFAULT CHARSET=utf8mb4",
            // regla: 'MM-DD' fecha fija, o 'n-d-MM' = n-ésimo día d de la semana (0 = domingo) del mes MM
            "INSERT IGNORE INTO wa_ocasiones (clave, nombre, tipo, regla, genero) VALUES
              ('cumpleanos',     'Cumpleaños',                      'cumpleanos', NULL,     NULL),
              ('profesion',      'Día de su profesión u oficio',    'profesion',  NULL,     NULL),
              ('mujer',          'Día de la Mujer',                 'fecha',      '03-08',  'mujer'),
              ('hombre',         'Día del Hombre',                  'fecha',      '03-19',  'hombre'),
              ('madre',          'Día de la Madre',                 'fecha',      '2-0-05', 'mujer'),
              ('padre',          'Día del Padre',                   'fecha',      '3-0-06', 'hombre'),
              ('amor_amistad',   'Amor y Amistad',                  'fecha',      '3-6-09', NULL),
              ('navidad',        'Navidad',                         'fecha',      '12-24',  NULL),
              ('anio_nuevo',     'Año nuevo',                       'fecha',      '12-31',  NULL),
              ('bienvenida',     'Bienvenida al registrarse',       'evento',     NULL,     NULL),
              ('nuevo_invitado', 'Alguien se unió a tu red',        'evento',     NULL,     NULL),
              ('sube_nivel',     'Subiste de nivel como promotor',  'evento',     NULL,     NULL)",
        ], 'ocasiones de saludo de WhatsApp'],
        ['wa_mensajes', null, "CREATE TABLE IF NOT EXISTS wa_mensajes (
              id INT AUTO_INCREMENT PRIMARY KEY,
              simpatizante_id INT NOT NULL,
              ocasion_id INT NOT NULL,
              clave VARCHAR(60) NOT NULL,
              telefono VARCHAR(15) NULL,
              plantilla VARCHAR(120) NULL,
              estado ENUM('pendiente','enviado','entregado','leido','error','omitido') NOT NULL DEFAULT 'pendiente',
              wamid VARCHAR(128) NULL,
              error_codigo VARCHAR(20) NULL,
              error_detalle VARCHAR(255) NULL,
              intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
              creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              enviado_at DATETIME NULL,
              entregado_at DATETIME NULL,
              leido_at DATETIME NULL,
              error_at DATETIME NULL,
              UNIQUE KEY uq_wa_msg (simpatizante_id, ocasion_id, clave),
              UNIQUE KEY uq_wa_wamid (wamid),
              KEY idx_wa_estado (estado),
              KEY idx_wa_creado (creado_at)
            ) DEFAULT CHARSET=utf8mb4", 'cola y métricas de WhatsApp'],
        ['wa_entrantes', null, "CREATE TABLE IF NOT EXISTS wa_entrantes (
              id INT AUTO_INCREMENT PRIMARY KEY,
              telefono VARCHAR(15) NOT NULL,
              simpatizante_id INT NULL,
              tipo VARCHAR(20) NOT NULL,
              texto TEXT NULL,
              wamid VARCHAR(128) NULL,
              recibido_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_wa_ent_wamid (wamid),
              KEY idx_wa_ent_tel (telefono)
            ) DEFAULT CHARSET=utf8mb4", 'respuestas recibidas por WhatsApp'],

        // ---------- Portal del simpatizante: acceso con clave, puntos y tareas ----------
        ['simpatizantes', 'clave_hash',     'ALTER TABLE simpatizantes ADD COLUMN clave_hash VARCHAR(255) NULL', 'acceso de simpatizantes a su panel'],
        ['simpatizantes', 'clave_cambiar',  'ALTER TABLE simpatizantes ADD COLUMN clave_cambiar TINYINT(1) NOT NULL DEFAULT 0', 'acceso de simpatizantes a su panel'],
        ['simpatizantes', 'clave_intentos', 'ALTER TABLE simpatizantes ADD COLUMN clave_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN clave_bloqueo DATETIME NULL', 'acceso de simpatizantes a su panel'],
        ['simpatizantes', 'ultimo_acceso',  'ALTER TABLE simpatizantes ADD COLUMN ultimo_acceso DATETIME NULL', 'acceso de simpatizantes a su panel'],
        ['simpatizantes', 'puntos',         'ALTER TABLE simpatizantes ADD COLUMN puntos INT UNSIGNED NOT NULL DEFAULT 0, ADD KEY idx_simp_puntos (puntos)', 'puntos y niveles de promotor'],
        ['tareas', null, "CREATE TABLE IF NOT EXISTS tareas (
              id INT AUTO_INCREMENT PRIMARY KEY,
              tipo VARCHAR(20) NOT NULL,
              titulo VARCHAR(150) NOT NULL,
              descripcion TEXT NULL,
              lugar VARCHAR(200) NULL,
              fecha DATETIME NULL,
              meta INT UNSIGNED NULL,
              puntos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              puntos_asistencia SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              alcance ENUM('asignada','abierta','red') NOT NULL DEFAULT 'asignada',
              nivel_minimo TINYINT UNSIGNED NOT NULL DEFAULT 0,
              zona_id INT NULL,
              lider_id INT NULL,
              creada_por_usuario INT NULL,
              creada_por_simpatizante INT NULL,
              estado ENUM('abierta','cerrada','cancelada') NOT NULL DEFAULT 'abierta',
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_tarea_estado (estado),
              KEY idx_tarea_creador_s (creada_por_simpatizante),
              KEY idx_tarea_lider (lider_id)
            ) DEFAULT CHARSET=utf8mb4", 'tareas y convocatorias'],
        ['tarea_asignaciones', null, "CREATE TABLE IF NOT EXISTS tarea_asignaciones (
              id INT AUTO_INCREMENT PRIMARY KEY,
              tarea_id INT NOT NULL,
              simpatizante_id INT NOT NULL,
              rol ENUM('responsable','asistente') NOT NULL DEFAULT 'responsable',
              estado ENUM('pendiente','aceptada','rechazada','hecha','validada','no_valida') NOT NULL DEFAULT 'pendiente',
              resultado INT UNSIGNED NULL,
              nota VARCHAR(500) NULL,
              asignada_por_usuario INT NULL,
              asignada_por_simpatizante INT NULL,
              validada_por INT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              actualizada_at DATETIME NULL,
              UNIQUE KEY uq_tarea_persona (tarea_id, simpatizante_id),
              KEY idx_asig_simp (simpatizante_id, estado)
            ) DEFAULT CHARSET=utf8mb4", 'tareas y convocatorias'],
        ['wa_ocasiones', 'tarea_asignada', "INSERT IGNORE INTO wa_ocasiones (clave, nombre, tipo) VALUES
              ('tarea_asignada',    'Te asignaron una tarea',        'evento'),
              ('invitacion_evento', 'Invitación a reunión o evento', 'evento')",
            'avisos de tareas por WhatsApp',
            fn(PDO $db) => !esquema_tiene($db, 'wa_ocasiones') ? false
                : (bool)$db->query("SELECT COUNT(*) FROM wa_ocasiones WHERE clave = 'invitacion_evento'")->fetchColumn()],

        ['wa_plantillas', 'categoria_solicitada', 'ALTER TABLE wa_plantillas ADD COLUMN categoria_solicitada VARCHAR(20) NULL', 'categoría pedida de las plantillas de WhatsApp'],

        // ---------- Sin duplicados: la base de datos rechaza un documento o celular repetido ----------
        // Si ya hay repetidos, el paso no se aplica y se listan para que el equipo los corrija.
        ['simpatizantes', null, 'ALTER TABLE simpatizantes ADD UNIQUE KEY uq_simp_documento (documento)', 'documento único (sin duplicados)',
            fn(PDO $db) => esquema_indice_unico($db, 'simpatizantes', 'documento'), fn(PDO $db) => esquema_aviso_duplicados($db, 'documento')],
        ['simpatizantes', null, 'ALTER TABLE simpatizantes ADD UNIQUE KEY uq_simp_telefono (telefono)', 'celular único (sin duplicados)',
            fn(PDO $db) => esquema_indice_unico($db, 'simpatizantes', 'telefono'), fn(PDO $db) => esquema_aviso_duplicados($db, 'telefono')],
    ];
}

/** ¿La columna tiene un índice único propio (de una sola columna)? */
function esquema_indice_unico(PDO $db, string $tabla, string $columna): bool
{
    try {
        $st = $db->prepare(
            'SELECT s.INDEX_NAME FROM information_schema.STATISTICS s
             WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = :t AND s.COLUMN_NAME = :c AND s.NON_UNIQUE = 0
               AND (SELECT COUNT(*) FROM information_schema.STATISTICS x
                    WHERE x.TABLE_SCHEMA = s.TABLE_SCHEMA AND x.TABLE_NAME = s.TABLE_NAME AND x.INDEX_NAME = s.INDEX_NAME) = 1
             LIMIT 1'
        );
        $st->execute(['t' => $tabla, 'c' => $columna]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** Valores repetidos de una columna de simpatizantes: [[valor, veces, nombres], ...] (máximo 10). */
function esquema_duplicados(PDO $db, string $columna): array
{
    $columna = $columna === 'telefono' ? 'telefono' : 'documento'; // nunca viene del usuario
    return $db->query(
        "SELECT $columna AS valor, COUNT(*) AS veces, GROUP_CONCAT(nombre ORDER BY id SEPARATOR ' / ') AS nombres
         FROM simpatizantes GROUP BY $columna HAVING COUNT(*) > 1 ORDER BY COUNT(*) DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Aviso legible si una columna tiene repetidos (null si está limpia). */
function esquema_aviso_duplicados(PDO $db, string $columna): ?string
{
    $dup = esquema_duplicados($db, $columna);
    if (!$dup) return null;
    $lista = array_map(fn($d) => $d['valor'] . ' (' . $d['nombres'] . ')', $dup);
    return 'Hay ' . ($columna === 'telefono' ? 'celulares' : 'documentos') . ' repetidos: ' . implode('; ', $lista)
         . '. Elimina los sobrantes en Simpatizantes › ⧉ Duplicados y vuelve a pulsar "Actualizar plataforma".';
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
function esquema_actualizar(PDO $db, array &$avisos = []): array
{
    $aplicados = [];
    foreach (esquema_pasos() as $paso) {
        if (esquema_paso_aplicado($db, $paso)) continue;
        // Revisión previa (p. ej. datos repetidos): si falla, el paso espera y los demás siguen
        if (isset($paso[5]) && ($aviso = ($paso[5])($db))) { $avisos[] = $aviso; continue; }
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
    $n = red_recalcular_todos($db);
    if ($n > 0) $aplicados[] = "puntos de $n promotores";
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
    $id = (int)$db->lastInsertId();

    // WhatsApp: bienvenida, aviso a quien lo invitó y su posible subida de nivel
    // (solo se encolan; el envío lo hace el cron, nunca frena el registro)
    try {
        wa_eventos_registro($db, $id, isset($d['referido_por']) ? (int)$d['referido_por'] : null);
    } catch (Throwable $e) { /* un fallo de la cola no puede impedir el registro */ }
    // Puntos de quien invitó (si sube de nivel, se encola su felicitación)
    if (!empty($d['referido_por'])) {
        try { red_actualizar_puntos($db, (int)$d['referido_por']); } catch (Throwable $e) { /* nunca frena el registro */ }
    }

    return [
        'id'     => $id,
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
