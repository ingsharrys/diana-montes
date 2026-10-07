<?php
/**
 * WhatsApp Business (Cloud API de Meta): saludos automáticos con plantillas
 * aprobadas, notificaciones de la red y métricas de cada mensaje.
 *
 * Flujo:
 *   1. Cada día (cron) se encolan los saludos de las ocasiones activas:
 *      cumpleaños, día de cada profesión y fechas especiales (Día de la
 *      Mujer, del Hombre, Amor y Amistad…). Los eventos (bienvenida, "alguien
 *      se unió a tu red", "subiste de nivel") se encolan al registrarse.
 *   2. El cron envía la cola con la plantilla de cada ocasión (tope diario,
 *      horario, reintentos). Nunca se repite: un mensaje por persona,
 *      ocasión y año (clave única).
 *   3. Meta avisa por webhook cada estado (enviado, entregado, leído, con
 *      error) y las respuestas. Quien responda SALIR queda dado de baja.
 *
 * Solo se escribe a quien dio su consentimiento (Ley 1581) y no se ha dado de baja.
 * Las funciones reciben el PDO para servir a la landing, el admin, el cron y el webhook.
 */

/** Datos del simpatizante que una plantilla puede usar en sus variables {{1}}, {{2}}… */
const WA_VARIABLES = [
    'primer_nombre'     => 'Primer nombre',
    'nombre'            => 'Nombre completo',
    'profesion'         => 'Profesión u oficio',
    'zona'              => 'Barrio o vereda',
    'lider'             => 'Líder de su red',
    'enlace_panel'      => 'Enlace de su panel de promotor',
    'enlace_invitacion' => 'Su enlace para invitar',
    'invitado'          => 'Quién se unió a su red (ocasión "Alguien se unió a tu red")',
    'nivel_promotor'    => 'Nivel alcanzado (ocasión "Subiste de nivel")',
    'tarea'             => 'Título de la tarea o evento (ocasiones de tareas)',
    'fecha_tarea'       => 'Fecha de la tarea o evento',
    'lugar_tarea'       => 'Lugar de la tarea o evento',
    'enlace_portal'     => 'Enlace para entrar a su panel (/mi)',
];

/** Palabras con las que una persona se da de baja o vuelve a suscribirse. */
const WA_PALABRAS_BAJA   = ['salir', 'baja', 'stop', 'cancelar', 'no mas', 'no quiero'];
const WA_PALABRAS_VOLVER = ['volver', 'alta', 'suscribir'];

/** Valor de configuración (constantes de admin/config/config.php). */
function wa_cfg(string $clave, $defecto = null)
{
    return defined($clave) && constant($clave) !== '' ? constant($clave) : $defecto;
}

/** ¿Hay credenciales para enviar? */
function wa_configurado(): bool
{
    return wa_cfg('WA_PHONE_NUMBER_ID') && wa_cfg('WA_TOKEN');
}

/** ¿Ya existen las tablas de WhatsApp? (se crean con "Actualizar plataforma") */
function wa_disponible(PDO $db): bool
{
    return esquema_tiene($db, 'wa_mensajes') && esquema_tiene($db, 'wa_ocasiones');
}

/** Condición común: solo personas con consentimiento, sin baja y con celular válido. */
function wa_sql_contactable(string $s = 's'): string
{
    return "$s.consentimiento_datos = 1 AND $s.wa_baja_at IS NULL AND $s.telefono REGEXP '^3[0-9]{9}$'";
}

/* =========================================================================
   Encolar
   ========================================================================= */

/**
 * Encola un mensaje de una ocasión tipo evento para un simpatizante (si la
 * ocasión está activa y tiene plantilla). $sufijo distingue eventos repetibles,
 * p. ej. "nuevo_invitado:123". Devuelve true si quedó en cola.
 */
function wa_evento(PDO $db, string $ocasion, int $simpatizanteId, string $sufijo = ''): bool
{
    if (!wa_disponible($db)) return false;
    $st = $db->prepare(
        "INSERT IGNORE INTO wa_mensajes (simpatizante_id, ocasion_id, clave)
         SELECT s.id, o.id, :clave
         FROM simpatizantes s JOIN wa_ocasiones o ON o.clave = :ocasion AND o.activa = 1 AND o.plantilla_id IS NOT NULL
         WHERE s.id = :id AND " . wa_sql_contactable()
    );
    $st->execute(['clave' => $ocasion . ($sufijo !== '' ? ':' . $sufijo : ''), 'ocasion' => $ocasion, 'id' => $simpatizanteId]);
    return $st->rowCount() > 0;
}

/** Eventos de un registro nuevo: bienvenida, aviso al promotor y, si aplica, su subida de nivel. */
function wa_eventos_registro(PDO $db, int $nuevoId, ?int $referidoPor): void
{
    if (!wa_disponible($db)) return;
    wa_evento($db, 'bienvenida', $nuevoId);
    if ($referidoPor) {
        wa_evento($db, 'nuevo_invitado', $referidoPor, (string)$nuevoId);
        // La subida de nivel la encola red_actualizar_puntos() al sumar los puntos del invitado
    }
}

/**
 * ¿La regla de una fecha especial cae hoy?
 *   'MM-DD'  -> fecha fija (03-08 = 8 de marzo)
 *   'n-d-MM' -> n-ésimo día d de la semana del mes MM (0 = domingo … 6 = sábado);
 *               '3-6-09' = tercer sábado de septiembre (Amor y Amistad)
 */
function wa_regla_hoy(string $regla, DateTimeImmutable $hoy): bool
{
    if (preg_match('/^(\d{2})-(\d{2})$/', $regla, $m)) {
        return $hoy->format('m-d') === "$m[1]-$m[2]";
    }
    if (preg_match('/^([1-5])-([0-6])-(\d{2})$/', $regla, $m)) {
        if ($hoy->format('m') !== $m[3] || (int)$hoy->format('w') !== (int)$m[2]) return false;
        return (int)ceil((int)$hoy->format('j') / 7) === (int)$m[1];
    }
    return false;
}

/** La regla en palabras: "8 de marzo", "tercer sábado de septiembre". */
function wa_regla_texto(?string $regla): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $orden = ['', 'primer', 'segundo', 'tercer', 'cuarto', 'quinto'];
    if (preg_match('/^(\d{2})-(\d{2})$/', (string)$regla, $m)) return (int)$m[2] . ' de ' . ($meses[(int)$m[1]] ?? '?');
    if (preg_match('/^([1-5])-([0-6])-(\d{2})$/', (string)$regla, $m)) return $orden[(int)$m[1]] . ' ' . $dias[(int)$m[2]] . ' de ' . ($meses[(int)$m[3]] ?? '?');
    return '';
}

/** Próxima fecha (desde $desde, incluido) en que cae una regla. */
function wa_regla_proxima(string $regla, DateTimeImmutable $desde): ?DateTimeImmutable
{
    for ($i = 0; $i < 400; $i++) {
        $d = $desde->modify("+$i day");
        if (wa_regla_hoy($regla, $d)) return $d;
    }
    return null;
}

/**
 * Encola los saludos del día de todas las ocasiones activas. Idempotente:
 * correrlo varias veces el mismo día no duplica nada. Devuelve [ocasión => encolados].
 */
function wa_generar_del_dia(PDO $db, DateTimeImmutable $hoy): array
{
    if (!wa_disponible($db)) return [];
    $md   = $hoy->format('m-d');
    $anio = $hoy->format('Y');
    // En años no bisiestos, quien cumple el 29 de febrero recibe su saludo el 28
    $mdCumple = ($md === '02-28' && $hoy->format('L') === '0') ? ['02-28', '02-29'] : [$md, $md];
    $base = "INSERT IGNORE INTO wa_mensajes (simpatizante_id, ocasion_id, clave) ";
    $r = [];

    $ocasiones = $db->query(
        "SELECT * FROM wa_ocasiones WHERE activa = 1 AND plantilla_id IS NOT NULL AND tipo <> 'evento'"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ocasiones as $o) {
        $st = null; $p = ['o' => $o['id']];
        if ($o['tipo'] === 'cumpleanos') {
            $st = $db->prepare($base . "SELECT s.id, :o, :clave FROM simpatizantes s
                  WHERE DATE_FORMAT(s.fecha_nacimiento, '%m-%d') IN (:md1, :md2) AND " . wa_sql_contactable());
            $p += ['clave' => "cumpleanos:$anio", 'md1' => $mdCumple[0], 'md2' => $mdCumple[1]];
        } elseif ($o['tipo'] === 'profesion') {
            $st = $db->prepare($base . "SELECT s.id, :o, CONCAT('profesion:', :anio, ':', pr.id) FROM simpatizantes s
                  JOIN profesiones pr ON pr.id = s.profesion_id
                  WHERE DATE_FORMAT(pr.dia_celebracion, '%m-%d') = :md AND " . wa_sql_contactable());
            $p += ['anio' => $anio, 'md' => $md];
        } elseif ($o['tipo'] === 'fecha' && $o['regla'] && wa_regla_hoy($o['regla'], $hoy)) {
            $st = $db->prepare($base . "SELECT s.id, :o, :clave FROM simpatizantes s
                  WHERE " . wa_sql_contactable() . ($o['genero'] ? ' AND s.genero = :g' : ''));
            $p += ['clave' => $o['clave'] . ":$anio"];
            if ($o['genero']) $p['g'] = $o['genero'];
        }
        if ($st) {
            $st->execute($p);
            $r[$o['nombre']] = $st->rowCount();
        }
    }
    return $r;
}

/* =========================================================================
   Cliente de la API (Graph)
   ========================================================================= */

/** Llamada a la Graph API. Devuelve [código HTTP, respuesta decodificada]. */
function wa_api(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    $url = rtrim((string)wa_cfg('WA_API_BASE', 'https://graph.facebook.com'), '/') . '/'
         . wa_cfg('WA_API_VERSION', 'v23.0') . '/' . ltrim($ruta, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . wa_cfg('WA_TOKEN'), 'Content-Type: application/json'],
    ]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $red  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) return [0, ['error' => ['code' => 'red', 'message' => $red ?: 'Sin conexión con Meta']]];
    return [$http, json_decode((string)$resp, true) ?: []];
}

/** Meta no acepta saltos de línea, tabulaciones ni más de 4 espacios seguidos en una variable. */
function wa_limpiar_parametro(string $texto): string
{
    $texto = preg_replace('/[\r\n\t]+/', ' ', $texto);
    $texto = preg_replace('/ {4,}/', '   ', $texto);
    return mb_substr(trim($texto), 0, 900) ?: '-';
}

/**
 * Envía una plantilla. Devuelve ['ok', 'wamid', 'codigo', 'detalle', 'reintentar', 'credenciales'].
 */
function wa_enviar_plantilla(string $telefono, string $plantilla, string $idioma, array $parametros): array
{
    $tpl = ['name' => $plantilla, 'language' => ['code' => $idioma]];
    if ($parametros) {
        $tpl['components'] = [[
            'type' => 'body',
            'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => wa_limpiar_parametro((string)$v)], $parametros),
        ]];
    }
    return wa_resultado(wa_api('POST', wa_cfg('WA_PHONE_NUMBER_ID') . '/messages', [
        'messaging_product' => 'whatsapp',
        'to'                => '57' . $telefono,
        'type'              => 'template',
        'template'          => $tpl,
    ]));
}

/** Texto libre (solo se permite dentro de las 24 h siguientes a un mensaje de la persona). */
function wa_enviar_texto(string $telefono, string $texto): array
{
    return wa_resultado(wa_api('POST', wa_cfg('WA_PHONE_NUMBER_ID') . '/messages', [
        'messaging_product' => 'whatsapp',
        'to'   => '57' . $telefono,
        'type' => 'text',
        'text' => ['body' => $texto],
    ]));
}

/** Interpreta la respuesta de Meta: éxito, error definitivo, error temporal o credenciales caídas. */
function wa_resultado(array $r): array
{
    [$http, $j] = $r;
    if ($http >= 200 && $http < 300 && !empty($j['messages'][0]['id'])) {
        return ['ok' => true, 'wamid' => $j['messages'][0]['id'], 'codigo' => null, 'detalle' => null, 'reintentar' => false, 'credenciales' => false];
    }
    $e = $j['error'] ?? [];
    $codigo = (string)($e['code'] ?? $http);
    $detalle = trim(($e['error_data']['details'] ?? '') ?: ($e['message'] ?? 'Error desconocido'));
    // Temporales: red, servidor de Meta caído o límites de velocidad
    $temporales = ['red', '0', '1', '2', '4', '80007', '130429', '131000', '131016', '131056', '133004'];
    return [
        'ok' => false, 'wamid' => null, 'codigo' => $codigo, 'detalle' => mb_substr($detalle, 0, 250),
        'reintentar'   => $http >= 500 || in_array($codigo, $temporales, true),
        'credenciales' => $http === 401 || $codigo === '190',
    ];
}

/* =========================================================================
   Envío de la cola
   ========================================================================= */

/** Valores de las variables de la plantilla para un mensaje de la cola. */
function wa_valores(PDO $db, array $m, ?string $variables): array
{
    $campos = array_values(array_filter(array_map('trim', explode(',', (string)$variables))));
    if (!$campos) return [];
    $landing = rtrim((string)(defined('LANDING_URL') ? LANDING_URL : ''), '/');
    $partes  = explode(':', $m['clave']);
    $valores = [];
    foreach ($campos as $campo) {
        switch ($campo) {
            case 'primer_nombre': $v = mb_convert_case(mb_strtolower(strtok($m['nombre'], ' ')), MB_CASE_TITLE); break;
            case 'nombre':        $v = $m['nombre']; break;
            case 'profesion':     $v = $m['profesion'] ?? ''; break;
            case 'zona':          $v = $m['zona'] ?? ''; break;
            case 'lider':         $v = $m['lider'] ?? ''; break;
            case 'enlace_panel':  $v = $m['token_panel'] ? "$landing/mi/?t={$m['token_panel']}" : $landing; break;
            case 'enlace_invitacion': $v = $m['codigo_promotor'] ? "$landing/?ref={$m['codigo_promotor']}#sumate" : $landing; break;
            case 'invitado':
                $st = $db->prepare('SELECT nombre FROM simpatizantes WHERE id = :id');
                $st->execute(['id' => (int)($partes[1] ?? 0)]);
                $v = promotor_nombre_corto((string)$st->fetchColumn()) ?: 'una persona';
                break;
            case 'nivel_promotor':
                // Mensajes encolados antes de los puntos guardaban invitados (3, 10, 25)
                $umbral = (int)($partes[1] ?? 0);
                $umbral = [3 => 30, 10 => 100, 25 => 250][$umbral] ?? $umbral;
                $v = promotor_nivel($umbral)['actual'][1];
                break;
            case 'tarea': case 'fecha_tarea': case 'lugar_tarea':
                $t = red_tarea_de_asignacion($db, (int)($partes[1] ?? 0));
                $v = !$t ? '' : ($campo === 'tarea' ? $t['titulo']
                     : ($campo === 'fecha_tarea' ? (red_fecha($t['fecha']) ?: 'por definir') : ($t['lugar'] ?: 'por definir')));
                break;
            case 'enlace_portal': $v = "$landing/mi/"; break;
            default: $v = '';
        }
        $valores[] = $v;
    }
    return $valores;
}

/**
 * Envía hasta $max mensajes pendientes. Respeta el tope diario
 * (WA_LIMITE_DIARIO), vence lo encolado hace más de 2 días, reintenta los
 * errores temporales (3 intentos) y se detiene si el token dejó de servir.
 * Un candado evita que dos procesos envíen a la vez.
 */
function wa_procesar_cola(PDO $db, int $max = 50): array
{
    $r = ['enviados' => 0, 'errores' => 0, 'reintentos' => 0, 'vencidos' => 0, 'omitidos' => 0, 'detenido' => null];
    if (!wa_disponible($db)) { $r['detenido'] = 'Falta actualizar la plataforma.'; return $r; }
    if (!wa_configurado())   { $r['detenido'] = 'Faltan las credenciales de WhatsApp en config.php.'; return $r; }
    if ((int)$db->query("SELECT GET_LOCK('wa_cola', 0)")->fetchColumn() !== 1) { $r['detenido'] = 'Otro envío está en curso.'; return $r; }

    try {
        $r['vencidos'] = $db->exec(
            "UPDATE wa_mensajes SET estado = 'omitido', error_detalle = 'Vencido: no se envió a tiempo'
             WHERE estado = 'pendiente' AND creado_at < NOW() - INTERVAL 2 DAY"
        );
        $hoy = (int)$db->query("SELECT COUNT(*) FROM wa_mensajes WHERE enviado_at >= CURDATE()")->fetchColumn();
        $cupo = max(0, (int)wa_cfg('WA_LIMITE_DIARIO', 250) - $hoy);
        if ($cupo === 0) { $r['detenido'] = 'Se alcanzó el tope diario de envíos.'; return $r; }

        $lote = $db->query(
            "SELECT m.id, m.clave, m.intentos, s.nombre, s.telefono, s.token_panel, s.codigo_promotor, s.wa_baja_at,
                    pr.nombre AS profesion, z.nombre AS zona, u.nombre AS lider,
                    pl.nombre AS plantilla, pl.idioma, pl.variables
             FROM wa_mensajes m
             JOIN simpatizantes s     ON s.id = m.simpatizante_id
             JOIN wa_ocasiones o      ON o.id = m.ocasion_id
             LEFT JOIN wa_plantillas pl ON pl.id = o.plantilla_id
             LEFT JOIN profesiones pr ON pr.id = s.profesion_id
             LEFT JOIN zonas z        ON z.id = s.zona_id
             LEFT JOIN usuarios u     ON u.id = s.lider_id
             WHERE m.estado = 'pendiente'
             ORDER BY m.id LIMIT " . min($max, $cupo)
        )->fetchAll(PDO::FETCH_ASSOC);

        $omitir = $db->prepare("UPDATE wa_mensajes SET estado = 'omitido', error_detalle = :d WHERE id = :id");
        $ok     = $db->prepare("UPDATE wa_mensajes SET estado = 'enviado', wamid = :w, telefono = :t, plantilla = :p,
                                       enviado_at = NOW(), intentos = intentos + 1 WHERE id = :id");
        $error  = $db->prepare("UPDATE wa_mensajes SET estado = :e, error_codigo = :c, error_detalle = :d, telefono = :t,
                                       plantilla = :p, intentos = intentos + 1, error_at = IF(:e2 = 'error', NOW(), error_at)
                                WHERE id = :id");

        foreach ($lote as $m) {
            if ($m['wa_baja_at'] || !$m['plantilla']) {
                $omitir->execute(['d' => $m['wa_baja_at'] ? 'Se dio de baja' : 'La ocasión ya no tiene plantilla', 'id' => $m['id']]);
                $r['omitidos']++;
                continue;
            }
            $res = wa_enviar_plantilla($m['telefono'], $m['plantilla'], $m['idioma'] ?: 'es', wa_valores($db, $m, $m['variables']));
            if ($res['ok']) {
                $ok->execute(['w' => $res['wamid'], 't' => $m['telefono'], 'p' => $m['plantilla'], 'id' => $m['id']]);
                $r['enviados']++;
                continue;
            }
            if ($res['credenciales']) { $r['detenido'] = 'Meta rechazó el token (' . $res['detalle'] . '). Revisa WA_TOKEN.'; break; }
            $definitivo = !$res['reintentar'] || (int)$m['intentos'] + 1 >= 3;
            $estado = $definitivo ? 'error' : 'pendiente';
            $error->execute(['e' => $estado, 'e2' => $estado, 'c' => $res['codigo'], 'd' => $res['detalle'],
                             't' => $m['telefono'], 'p' => $m['plantilla'], 'id' => $m['id']]);
            $definitivo ? $r['errores']++ : $r['reintentos']++;
            if (!$definitivo && in_array($res['codigo'], ['80007', '130429'], true)) { $r['detenido'] = 'Límite de velocidad de Meta: se reanuda en el próximo ciclo.'; break; }
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('wa_cola')");
    }
    return $r;
}

/* =========================================================================
   Webhook: estados, respuestas y bajas
   ========================================================================= */

/** Verifica la firma X-Hub-Signature-256 que Meta calcula con el App Secret. */
function wa_firma_valida(string $cuerpoCrudo, ?string $firma): bool
{
    $secreto = (string)wa_cfg('WA_APP_SECRET', '');
    if ($secreto === '' || !$firma || !str_starts_with($firma, 'sha256=')) return false;
    return hash_equals('sha256=' . hash_hmac('sha256', $cuerpoCrudo, $secreto), $firma);
}

/** Normaliza un texto para comparar palabras clave ("Salír!" -> "salir"). */
function wa_normalizar(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim(preg_replace('/[^a-z0-9 ]+/', '', $t));
}

/** Procesa una notificación del webhook. Devuelve un resumen de lo aplicado. */
function wa_procesar_webhook(PDO $db, array $payload): array
{
    $r = ['estados' => 0, 'mensajes' => 0, 'bajas' => 0, 'altas' => 0];
    if (!wa_disponible($db)) return $r;

    $sql = [
        'sent'      => "UPDATE wa_mensajes SET enviado_at = COALESCE(enviado_at, FROM_UNIXTIME(:ts)),
                               estado = IF(estado = 'pendiente', 'enviado', estado) WHERE wamid = :w",
        'delivered' => "UPDATE wa_mensajes SET entregado_at = COALESCE(entregado_at, FROM_UNIXTIME(:ts)),
                               estado = IF(estado IN ('pendiente', 'enviado'), 'entregado', estado) WHERE wamid = :w",
        'read'      => "UPDATE wa_mensajes SET leido_at = COALESCE(leido_at, FROM_UNIXTIME(:ts)),
                               entregado_at = COALESCE(entregado_at, FROM_UNIXTIME(:ts2)), estado = 'leido' WHERE wamid = :w",
        'failed'    => "UPDATE wa_mensajes SET estado = 'error', error_at = FROM_UNIXTIME(:ts),
                               error_codigo = :c, error_detalle = :d WHERE wamid = :w",
    ];

    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $cambio) {
            $v = $cambio['value'] ?? [];

            foreach ($v['statuses'] ?? [] as $s) {
                $tipo = $s['status'] ?? '';
                if (!isset($sql[$tipo]) || empty($s['id'])) continue;
                $p = ['ts' => (int)($s['timestamp'] ?? time()), 'w' => $s['id']];
                if ($tipo === 'read') $p['ts2'] = $p['ts'];
                if ($tipo === 'failed') {
                    $e = $s['errors'][0] ?? [];
                    $p['c'] = (string)($e['code'] ?? '');
                    $p['d'] = mb_substr(trim(($e['error_data']['details'] ?? '') ?: ($e['title'] ?? $e['message'] ?? 'Falló la entrega')), 0, 250);
                }
                $st = $db->prepare($sql[$tipo]);
                $st->execute($p);
                $r['estados'] += $st->rowCount();
            }

            foreach ($v['messages'] ?? [] as $msg) {
                $tel = substr(preg_replace('/\D/', '', (string)($msg['from'] ?? '')), -10);
                if (strlen($tel) !== 10) continue;
                $texto = $msg['text']['body'] ?? ($msg['button']['text'] ?? ($msg['interactive']['button_reply']['title'] ?? null));
                $st = $db->prepare('SELECT id FROM simpatizantes WHERE telefono = :t LIMIT 1');
                $st->execute(['t' => $tel]);
                $simpId = $st->fetchColumn() ?: null;

                $ins = $db->prepare('INSERT IGNORE INTO wa_entrantes (telefono, simpatizante_id, tipo, texto, wamid, recibido_at)
                                     VALUES (:t, :s, :tipo, :x, :w, FROM_UNIXTIME(:ts))');
                $ins->execute(['t' => $tel, 's' => $simpId, 'tipo' => mb_substr((string)($msg['type'] ?? 'texto'), 0, 20),
                               'x' => $texto, 'w' => $msg['id'] ?? null, 'ts' => (int)($msg['timestamp'] ?? time())]);
                if ($ins->rowCount() === 0) continue;     // Meta reenvió algo ya procesado
                $r['mensajes']++;

                $palabra = wa_normalizar((string)$texto);
                if ($simpId && in_array($palabra, WA_PALABRAS_BAJA, true)) {
                    $db->prepare('UPDATE simpatizantes SET wa_baja_at = NOW() WHERE id = :id AND wa_baja_at IS NULL')->execute(['id' => $simpId]);
                    $db->prepare("UPDATE wa_mensajes SET estado = 'omitido', error_detalle = 'Se dio de baja' WHERE simpatizante_id = :id AND estado = 'pendiente'")->execute(['id' => $simpId]);
                    $r['bajas']++;
                    if (wa_configurado()) wa_enviar_texto($tel, 'Listo, no volverás a recibir mensajes automáticos de la campaña. Si cambias de opinión, responde VOLVER. ¡Gracias!');
                } elseif ($simpId && in_array($palabra, WA_PALABRAS_VOLVER, true)) {
                    $db->prepare('UPDATE simpatizantes SET wa_baja_at = NULL WHERE id = :id')->execute(['id' => $simpId]);
                    $r['altas']++;
                    if (wa_configurado()) wa_enviar_texto($tel, '¡Qué bueno tenerte de vuelta! Volverás a recibir nuestros saludos.');
                }
            }
        }
    }
    return $r;
}

/* =========================================================================
   Plantillas
   ========================================================================= */

/**
 * Trae las plantillas de la cuenta (WA_WABA_ID) y las guarda conservando el
 * mapeo de variables. Solo se usan plantillas con variables numeradas en el
 * cuerpo ({{1}}, {{2}}…) y sin encabezado multimedia ni botones dinámicos.
 * Devuelve [cuántas, error o null].
 */
function wa_sincronizar_plantillas(PDO $db): array
{
    if (!wa_cfg('WA_WABA_ID')) return [0, 'Falta WA_WABA_ID en config.php.'];
    $ruta = wa_cfg('WA_WABA_ID') . '/message_templates?fields=name,language,status,category,components&limit=100';
    $n = 0;
    $guardar = $db->prepare(
        'INSERT INTO wa_plantillas (nombre, idioma, categoria, estado_meta, cuerpo, num_variables, compatible)
         VALUES (:n, :i, :c, :e, :b, :v, :ok)
         ON DUPLICATE KEY UPDATE categoria = :c2, estado_meta = :e2, cuerpo = :b2, num_variables = :v2, compatible = :ok2'
    );
    for ($pagina = 0; $ruta && $pagina < 20; $pagina++) {
        [$http, $j] = wa_api('GET', $ruta);
        if ($http !== 200) return [$n, 'Meta respondió: ' . ($j['error']['message'] ?? "HTTP $http")];
        foreach ($j['data'] ?? [] as $t) {
            $cuerpo = ''; $compatible = true;
            foreach ($t['components'] ?? [] as $c) {
                if ($c['type'] === 'BODY') $cuerpo = $c['text'] ?? '';
                if ($c['type'] === 'HEADER' && (($c['format'] ?? 'TEXT') !== 'TEXT' || str_contains($c['text'] ?? '', '{{'))) $compatible = false;
                if ($c['type'] === 'BUTTONS') foreach ($c['buttons'] ?? [] as $b) if (str_contains($b['url'] ?? '', '{{')) $compatible = false;
            }
            preg_match_all('/\{\{\s*([^}\s]+)\s*\}\}/', $cuerpo, $m);
            $vars = array_unique($m[1]);
            if (array_filter($vars, fn($x) => !ctype_digit($x))) $compatible = false;   // variables con nombre: no soportadas
            $p = ['n' => $t['name'], 'i' => $t['language'], 'c' => $t['category'] ?? null, 'e' => $t['status'] ?? null,
                  'b' => $cuerpo, 'v' => count($vars), 'ok' => $compatible ? 1 : 0];
            $guardar->execute($p + ['c2' => $p['c'], 'e2' => $p['e'], 'b2' => $p['b'], 'v2' => $p['v'], 'ok2' => $p['ok']]);
            $n++;
        }
        $siguiente = $j['paging']['next'] ?? null;
        $ruta = $siguiente ? preg_replace('#^https?://[^/]+/v[\d.]+/#', '', $siguiente) : null;
    }
    return [$n, null];
}
