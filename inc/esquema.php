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

require_once __DIR__ . '/zonas_garzon.php';
require_once __DIR__ . '/profesiones_lista.php';

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

        // ---------- Barrios y veredas de Garzón agrupados por zona urbana y corregimiento ----------
        ['zonas', 'clase', 'ALTER TABLE zonas ADD COLUMN grupo VARCHAR(60) NULL, ADD COLUMN clase VARCHAR(40) NULL', 'clasificación de cada barrio y vereda'],
        ['zonas', null, fn(PDO $db) => zonas_reemplazar($db), 'barrios, veredas y corregimientos de Garzón (Excel 2026)', fn(PDO $db) => zonas_garzon_cargadas($db)],
        ['profesiones', null, fn(PDO $db) => profesiones_cargar($db), 'lista completa de profesiones y oficios', fn(PDO $db) => profesiones_cargadas($db)],
        // ---------- App móvil: sesiones (tokens) e inicio con código por WhatsApp ----------
        ['api_tokens', null, "CREATE TABLE IF NOT EXISTS api_tokens (
              id INT AUTO_INCREMENT PRIMARY KEY,
              token_hash CHAR(64) NOT NULL,
              tipo ENUM('usuario','simpatizante') NOT NULL,
              sujeto_id INT NOT NULL,
              dispositivo VARCHAR(80) NULL,
              creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              ultimo_uso DATETIME NULL,
              expira_at DATETIME NOT NULL,
              revocado TINYINT(1) NOT NULL DEFAULT 0,
              UNIQUE KEY uq_api_token (token_hash),
              KEY idx_api_sujeto (tipo, sujeto_id)
            ) DEFAULT CHARSET=utf8mb4", 'sesiones de la app móvil'],
        ['otp_codigos', null, "CREATE TABLE IF NOT EXISTS otp_codigos (
              id INT AUTO_INCREMENT PRIMARY KEY,
              telefono VARCHAR(10) NOT NULL,
              codigo_hash CHAR(64) NOT NULL,
              creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              expira_at DATETIME NOT NULL,
              intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
              usado TINYINT(1) NOT NULL DEFAULT 0,
              ip VARCHAR(45) NULL,
              KEY idx_otp_tel (telefono, creado_at),
              KEY idx_otp_ip (ip, creado_at)
            ) DEFAULT CHARSET=utf8mb4", 'inicio de sesión con código por WhatsApp (app)'],
        // ---------- Datos personales: solicitudes de los titulares (eliminar, revocar, corregir, consultar) ----------
        ['solicitudes_datos', null, "CREATE TABLE IF NOT EXISTS solicitudes_datos (
              id INT AUTO_INCREMENT PRIMARY KEY,
              radicado VARCHAR(12) NOT NULL,
              tipo ENUM('eliminar','revocar','actualizar','consultar') NOT NULL,
              canal ENUM('web','whatsapp','correo','otro') NOT NULL DEFAULT 'web',
              nombre VARCHAR(120) NULL,
              documento VARCHAR(20) NULL,
              telefono VARCHAR(10) NULL,
              correo VARCHAR(150) NULL,
              detalle TEXT NULL,
              simpatizante_id INT NULL,
              coincide ENUM('total','documento','telefono','ninguna') NOT NULL DEFAULT 'ninguna',
              estado ENUM('pendiente','atendida','cerrada') NOT NULL DEFAULT 'pendiente',
              respuesta VARCHAR(500) NULL,
              ip VARCHAR(45) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              atendida_at DATETIME NULL,
              atendida_por INT NULL,
              UNIQUE KEY uq_solicitud_radicado (radicado),
              KEY idx_solicitud_estado (estado, created_at),
              KEY idx_solicitud_ip (ip, created_at)
            ) DEFAULT CHARSET=utf8mb4", 'solicitudes de datos personales (eliminar, corregir, revocar)'],
        ['wa_plantillas', 'categoria_solicitada','ALTER TABLE wa_plantillas ADD COLUMN categoria_solicitada VARCHAR(20) NULL', 'categoría pedida de las plantillas de WhatsApp'],

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
        if ($sql instanceof Closure) $sql($db);                 // paso con lógica propia (p. ej. zonas)
        else foreach ((array)$sql as $sentencia) $db->exec($sentencia);
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

/* ======================= Barrios, veredas y corregimientos ======================= */

const ZONA_VERSION = 'excel-2026-v2';

/** Clasificación tal cual el Excel → grupo del formulario (en este orden). */
const ZONAS_GRUPOS = [
    'Barrio'        => 'Barrios',
    'Urbanización'  => 'Urbanizaciones',
    'Asentamiento'  => 'Asentamientos',
    'Corregimiento' => 'Corregimientos (centros poblados)',
    'Vereda'        => 'Veredas',
    'Sector de vereda' => 'Sectores de vereda',
];

function zona_texto_normal(string $t): string
{
    $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $t)), 'UTF-8');
    return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

/** ¿Ya se reemplazaron las zonas con la lista vigente del Excel? */
function zonas_garzon_cargadas(PDO $db): bool
{
    if (!esquema_tiene($db, 'configuracion') || !esquema_tiene($db, 'zonas', 'clase')) return false;
    $st = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'zonas_version'");
    $st->execute();
    return $st->fetchColumn() === ZONA_VERSION . ':' . count(ZONAS_GARZON);
}

/**
 * Reemplaza las zonas por las del Excel (nombre + clasificación) SIN perder datos:
 *  - la zona que ya existe y coincide con una del Excel conserva su id (y sus registros);
 *  - si dos zonas viejas caen en la misma, sus registros se pasan a una y la otra se borra;
 *  - las zonas viejas que no están en el Excel se borran si nadie las usa; si alguien las usa
 *    (o es "Otra") se conservan para no dañar sus datos;
 *  - el nombre deja de ser único: lo único es nombre + clasificación (Las Mercedes barrio y vereda).
 * Devuelve un resumen.
 */
function zonas_reemplazar(PDO $db): array
{
    // Columnas que apuntan a una zona (simpatizantes, puestos, usuarios, reuniones, tareas…)
    $refs = $db->query(
        "SELECT TABLE_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'zona_id' AND TABLE_NAME <> 'zonas'"
    )->fetchAll(PDO::FETCH_COLUMN);

    // 1) Índice: de "nombre único" a "nombre + clasificación únicos"
    $indices = $db->query(
        "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols, MAX(NON_UNIQUE) AS nu
         FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zonas'
         GROUP BY INDEX_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);
    $tieneNuevo = false;
    foreach ($indices as $i) {
        if ($i['INDEX_NAME'] === 'PRIMARY' || (int)$i['nu'] === 1) continue;
        if ($i['cols'] === 'nombre') $db->exec('ALTER TABLE zonas DROP INDEX `' . str_replace('`', '', $i['INDEX_NAME']) . '`');
        if ($i['cols'] === 'nombre,clase') $tieneNuevo = true;
    }

    // 2) Qué zona nueva le toca a cada zona vieja
    $nuevas = [];
    foreach (ZONAS_GARZON as $k => [$nombre, $clase]) $nuevas[$k] = ['nombre' => $nombre, 'clase' => $clase, 'n' => zona_texto_normal($nombre)];
    $viejas = $db->query('SELECT id, nombre, tipo, clase FROM zonas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $uso = array_fill_keys(array_column($viejas, 'id'), 0);
    foreach ($refs as $t) {
        foreach ($db->query("SELECT zona_id, COUNT(*) FROM `$t` WHERE zona_id IS NOT NULL GROUP BY zona_id")->fetchAll(PDO::FETCH_NUM) as [$z, $c]) {
            if (isset($uso[$z])) $uso[$z] += (int)$c;
        }
    }
    $claseVieja = ['Centro poblado' => 'Corregimiento'];
    $destino = [];   // id viejo => índice en $nuevas
    foreach ($viejas as $v) {
        // quita los sufijos de la carga anterior: "Las Mercedes (vereda)", "Providencia (centro poblado)"
        $nombre = preg_replace('/\s*\((vereda|centro poblado)\)$/iu', '', $v['nombre'], 1, $quitado);
        $clase  = $v['clase'] ? ($claseVieja[$v['clase']] ?? $v['clase']) : null;
        if ($quitado && !$clase) $clase = stripos($v['nombre'], 'centro poblado') !== false ? 'Corregimiento' : 'Vereda';
        $cands = array_keys(array_filter($nuevas, fn($z) => $z['n'] === zona_texto_normal($nombre)));
        if (!$cands) continue;
        $elegido = null;
        foreach ($cands as $k) if ($clase && $nuevas[$k]['clase'] === $clase) $elegido = $k;
        if ($elegido === null) {
            // sin clasificación: lo urbano va al barrio (o lo urbano que haya); lo rural al corregimiento, luego a la vereda
            $orden = $v['tipo'] === 'rural' ? ['Corregimiento', 'Vereda', 'Sector de vereda'] : ['Barrio'];
            foreach ($orden as $c) foreach ($cands as $k) if ($elegido === null && $nuevas[$k]['clase'] === $c) $elegido = $k;
            if ($elegido === null) {
                $mismoTipo = array_filter($cands, fn($k) => in_array($nuevas[$k]['clase'], ZONAS_CLASES_RURALES, true) === ($v['tipo'] === 'rural'));
                $elegido = $mismoTipo ? reset($mismoTipo) : $cands[0];
            }
        }
        $destino[(int)$v['id']] = $elegido;
    }

    $r = ['conservadas' => 0, 'agregadas' => 0, 'unidas' => 0, 'borradas' => 0, 'fuera_del_excel' => []];
    $db->beginTransaction();
    try {
        // 3) Nombres temporales para poder renombrar sin choques
        $db->exec("UPDATE zonas SET nombre = CONCAT('~tmp~', id) WHERE id IN (" . implode(',', array_keys($destino) ?: [0]) . ')');
        $porNueva = [];
        foreach ($destino as $id => $k) $porNueva[$k][] = $id;

        $act = $db->prepare('UPDATE zonas SET nombre = :n, clase = :c, tipo = :t, grupo = NULL WHERE id = :id');
        $ins = $db->prepare('INSERT INTO zonas (nombre, clase, tipo) VALUES (:n, :c, :t)');
        foreach ($nuevas as $k => $z) {
            $tipo = in_array($z['clase'], ZONAS_CLASES_RURALES, true) ? 'rural' : 'urbano';
            $ids = $porNueva[$k] ?? [];
            if (!$ids) { $ins->execute(['n' => $z['nombre'], 'c' => $z['clase'], 't' => $tipo]); $r['agregadas']++; continue; }
            // se queda la zona vieja con más registros; las demás le pasan los suyos
            usort($ids, fn($a, $b) => [$uso[$b], $a] <=> [$uso[$a], $b]);
            $queda = array_shift($ids);
            $act->execute(['n' => $z['nombre'], 'c' => $z['clase'], 't' => $tipo, 'id' => $queda]);
            $r['conservadas']++;
            foreach ($ids as $sobra) {
                foreach ($refs as $t) $db->prepare("UPDATE `$t` SET zona_id = :a WHERE zona_id = :b")->execute(['a' => $queda, 'b' => $sobra]);
                $db->prepare('DELETE FROM zonas WHERE id = :id')->execute(['id' => $sobra]);
                $r['unidas']++;
            }
        }

        // 4) Zonas viejas que no están en el Excel
        $borrar = $db->prepare('DELETE FROM zonas WHERE id = :id');
        $otra = $db->prepare("UPDATE zonas SET clase = 'Otra', grupo = NULL WHERE id = :id");
        foreach ($viejas as $v) {
            $id = (int)$v['id'];
            if (isset($destino[$id])) continue;
            if ($uso[$id] > 0 || zona_texto_normal($v['nombre']) === 'otra') {
                $otra->execute(['id' => $id]);
                $r['fuera_del_excel'][] = $v['nombre'] . ' (' . $uso[$id] . ' registro' . ($uso[$id] === 1 ? '' : 's') . ')';
            } else {
                $borrar->execute(['id' => $id]);
                $r['borradas']++;
            }
        }
        // "Otra" siempre disponible para quien no encuentra su barrio o vereda
        if (!(int)$db->query("SELECT COUNT(*) FROM zonas WHERE nombre = 'Otra'")->fetchColumn()) {
            $db->exec("INSERT INTO zonas (nombre, clase, tipo) VALUES ('Otra', 'Otra', 'urbano')");
        }

        $db->prepare("INSERT INTO configuracion (clave, valor) VALUES ('zonas_version', :v) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
           ->execute(['v' => ZONA_VERSION . ':' . count(ZONAS_GARZON)]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    if (!$tieneNuevo) $db->exec('ALTER TABLE zonas ADD UNIQUE KEY uq_zona_nombre_clase (nombre, clase)');
    return $r;
}

/** Zonas en orden de presentación: barrios, urbanizaciones…, corregimientos, veredas; "Otra" al final. */
function zonas_listar(PDO $db): array
{
    if (!esquema_tiene($db, 'zonas', 'clase')) {
        return $db->query("SELECT id, nombre, tipo, NULL AS clase FROM zonas ORDER BY (nombre = 'Otra'), tipo, nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
    $orden = implode(', ', array_map(fn($c) => $db->quote($c), array_keys(ZONAS_GRUPOS)));
    return $db->query(
        "SELECT id, nombre, tipo, clase FROM zonas
         ORDER BY (nombre = 'Otra'), tipo, FIELD(clase, $orden) = 0, FIELD(clase, $orden), (clase = 'Otra'), nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** Grupo (optgroup) de una zona según su clasificación. */
function zona_grupo_etiqueta(array $z): string
{
    if (zona_texto_normal((string)$z['nombre']) === 'otra') return 'Otra';
    $clase = (string)($z['clase'] ?? '');
    if (isset(ZONAS_GRUPOS[$clase])) return ZONAS_GRUPOS[$clase];
    if ($clase === 'Otra') return 'Otras zonas';
    if ($clase === '') return $z['tipo'] === 'rural' ? 'Zona rural' : 'Casco urbano';
    return $z['tipo'] === 'rural' ? 'Otros rurales' : 'Otros desarrollos urbanos';
}

/** Nombre visible: tal cual el Excel (la clasificación la muestra el buscador al lado). */
function zona_etiqueta(array $z): string
{
    return (string)$z['nombre'];
}

/** <option> agrupados por clasificación; data-clase la muestra el buscador junto al nombre. */
function zonas_opciones(array $zonas, $seleccionada = null): string
{
    $html = '';
    $grupo = null;
    foreach ($zonas as $z) {
        $g = zona_grupo_etiqueta($z);
        if ($g !== $grupo) {
            if ($grupo !== null) $html .= '</optgroup>';
            $html .= '<optgroup label="' . e($g) . '">';
            $grupo = $g;
        }
        $html .= '<option value="' . (int)$z['id'] . '"' . ((int)$seleccionada === (int)$z['id'] ? ' selected' : '')
               . ($z['clase'] && $z['clase'] !== 'Otra' ? ' data-clase="' . e($z['clase']) . '"' : '') . '>'
               . e(zona_etiqueta($z)) . '</option>';
    }
    return $html . ($grupo !== null ? '</optgroup>' : '');
}

/** Expresión SQL del nombre de una zona para listados: aclara veredas y corregimientos ("La Jagua · vereda"). */
function zona_sql_nombre(PDO $db, string $alias = 'z'): string
{
    if (!esquema_tiene($db, 'zonas', 'clase')) return "$alias.nombre";
    return "CONCAT($alias.nombre, CASE WHEN $alias.clase IN ('Vereda', 'Corregimiento', 'Sector de vereda')"
         . " THEN CONCAT(' · ', LOWER($alias.clase)) ELSE '' END)";
}
