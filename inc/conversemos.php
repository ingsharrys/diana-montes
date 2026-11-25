<?php
/**
 * "Conversemos": mensaje masivo con botones Sí / No y formulario tipo chat.
 *
 * 1. Se envía la plantilla saludo_para_conversar (Mensajes masivos) con dos
 *    respuestas rápidas: "Sí" y "No".
 * 2. Cuando la persona toca "Sí", el webhook responde solo con el texto de
 *    saludo_para_conversar_datos y un botón a su enlace personal
 *    /conversemos/?t=LLAVE. Si la plantilla está aprobada en Meta (con el
 *    botón de enlace dinámico) se usa; si no, va como mensaje normal, que
 *    WhatsApp permite porque la persona acaba de escribir (y no se cobra).
 *    Si toca "No", recibe un agradecimiento y nada más.
 * 3. El enlace abre un chat que pregunta uno a uno los datos que faltan y
 *    los guarda en su registro.
 *
 * Lo usan el webhook (admin) y la página pública /conversemos/.
 */

const CONV_PLANTILLA       = 'saludo_para_conversar';
const CONV_PLANTILLA_DATOS = 'saludo_para_conversar_datos';
const CONV_DIAS_ENLACE     = 30;   // días que sirve el enlace personal
const CONV_PIE             = 'Responde SALIR si no deseas más mensajes';
const CONV_BOTON           = 'Confirmar mis datos'; // máximo 20 caracteres (botón de mensaje normal)

const CONV_TEXTO = "Hola, soy Diana Montes.\n\n"
    . "Quiero contarte de corazón qué me inspira y por qué hago las cosas. Pero, sobre todo, quiero escucharte, saber cómo estás, qué piensas y qué sueñas.\n\n"
    . "Me encantaría que sigamos cerquita, compartiendo como amigos.\n\n"
    . "¿Me regalas la oportunidad de conversar contigo por aquí?";

const CONV_TEXTO_DATOS = "Ay, qué alegría 💜\n\n"
    . "Gracias por este espacio. Me encanta que sigamos cerquita y que pronto podamos encontrarnos y conversar.\n\n"
    . "¿Me confirmas algunos daticos tuyos para seguir en contacto y poder invitarte personalmente cuando nos reunamos?\n\n"
    . "Me haría muy feliz verte.";

const CONV_TEXTO_CONOCERTE = "Ay, qué alegría 💜\n\n"
    . "Gracias por este espacio. Me encanta que sigamos cerquita y que pronto podamos encontrarnos y conversar.\n\n"
    . "¿Me cuentas un poquito de ti para seguir en contacto y poder invitarte personalmente cuando nos reunamos?\n\n"
    . "Me haría muy feliz verte.";
const CONV_BOTON_CONOCERTE = 'Quiero conocerte';   // máximo 20 caracteres

const CONV_TEXTO_NO = "Gracias por responderme, de verdad 💜 Respeto mucho tu tiempo. Aquí estaré si algún día quieres conversar. Un abrazo, Diana.";

/** Respuestas escritas a mano que cuentan como Sí o No (ya normalizadas, sin tildes). */
const CONV_SI = ['si', 'sii', 'siii', 'si claro', 'claro', 'claro que si', 'dale', 'de una', 'ok', 'listo', 'con gusto', 'si senora', 'si senor', 'si quiero', 'por supuesto'];
const CONV_NO = ['no', 'no gracias', 'ahora no', 'en otro momento', 'no por ahora'];

function conv_listo(PDO $db): bool
{
    return esquema_tiene($db, 'wa_conversemos');
}

/** Encabezado, pie y botones de las dos plantillas (los usa Admin › WhatsApp › Plantillas). */
function conv_extras_plantilla(string $nombre): array
{
    if ($nombre === CONV_PLANTILLA) {
        return ['encabezado' => '', 'pie' => CONV_PIE, 'botones' => [
            ['tipo' => 'QUICK_REPLY', 'texto' => 'Sí'], ['tipo' => 'QUICK_REPLY', 'texto' => 'No'],
        ]];
    }
    if ($nombre === CONV_PLANTILLA_DATOS) {
        return ['encabezado' => '', 'pie' => CONV_PIE, 'botones' => [
            ['tipo' => 'URL', 'texto' => CONV_BOTON, 'valor' => wa_sitio() . '/conversemos/?t={{1}}'],
        ]];
    }
    return [];
}

/** "Sí" / "No" según lo que escribió o tocó la persona; null si es otra cosa. */
function conv_respuesta(string $palabraNormalizada): ?string
{
    if (in_array($palabraNormalizada, CONV_SI, true)) return 'si';
    if (in_array($palabraNormalizada, CONV_NO, true)) return 'no';
    return null;
}

/**
 * ¿Esta respuesta es al mensaje "Conversemos"? Busca el mensaje citado (botón)
 * o, si escribió a mano, el último que recibió en los 7 días anteriores.
 * Si tocó un botón de un mensaje que no salió por la cola (por ejemplo,
 * "Enviar prueba" en Plantillas), también cuenta: solo esta plantilla tiene
 * botones Sí / No. Devuelve ['campana_id' => int] o null.
 */
function conv_origen(PDO $db, ?int $simpId, ?string $contexto, bool $esBoton = false): ?array
{
    if ($contexto) {
        $st = $db->prepare('SELECT clave, plantilla FROM wa_mensajes WHERE wamid = :w LIMIT 1');
        $st->execute(['w' => $contexto]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if ($m) return $m['plantilla'] === CONV_PLANTILLA ? ['campana_id' => conv_campana_de_clave($m['clave'])] : null;
    }
    if ($esBoton) return ['campana_id' => 0];
    if (!$simpId) return null;
    $st = $db->prepare("SELECT clave FROM wa_mensajes WHERE simpatizante_id = :s AND plantilla = :p
                          AND enviado_at >= NOW() - INTERVAL 7 DAY ORDER BY enviado_at DESC LIMIT 1");
    $st->execute(['s' => $simpId, 'p' => CONV_PLANTILLA]);
    $clave = $st->fetchColumn();
    return $clave === false ? null : ['campana_id' => conv_campana_de_clave((string)$clave)];
}

/**
 * Sí / No de un número que no está en la base (por ejemplo, una prueba a un
 * celular sin registro): con Sí se le invita a registrarse en el formulario Súmate.
 */
function conv_responder_sin_registro(PDO $db, string $telefono, string $respuesta): string
{
    // Doble toque: si ya escribió hace menos de un minuto, no se repite
    $st = $db->prepare("SELECT COUNT(*) FROM wa_entrantes WHERE telefono = :t AND recibido_at >= NOW() - INTERVAL 60 SECOND");
    $st->execute(['t' => $telefono]);
    if ((int)$st->fetchColumn() > 1) return 'repetido';
    if (!wa_configurado()) return 'error';
    if ($respuesta === 'no') return wa_enviar_texto($telefono, CONV_TEXTO_NO)['ok'] ? 'gracias' : 'error';
    // Enlace personal al chat de registro: su celular ya queda asociado (no se le pregunta)
    $url = conv_invitacion_listo($db) ? wa_sitio() . '/conversemos/?r=' . conv_invitacion($db, $telefono) : wa_sitio() . '/#sumate';
    $res = wa_enviar_mensaje($telefono, ['type' => 'interactive', 'interactive' => [
        'type'   => 'cta_url',
        'body'   => ['text' => CONV_TEXTO_CONOCERTE],
        'footer' => ['text' => 'Diana Montes'],
        'action' => ['name' => 'cta_url', 'parameters' => ['display_text' => CONV_BOTON_CONOCERTE, 'url' => $url]],
    ]]);
    if (!$res['ok']) error_log('[conversemos] No se pudo responder a ' . substr($telefono, 0, 3) . '···: ' . $res['codigo'] . ' ' . $res['detalle']);
    return $res['ok'] ? 'registro' : 'error';
}

function conv_campana_de_clave(string $clave): int
{
    return str_starts_with($clave, 'campana:') ? (int)substr($clave, 8) : 0;
}

/** Fila de la conversación (una por persona y envío masivo). */
function conv_fila(PDO $db, int $simpId, int $campanaId): ?array
{
    $st = $db->prepare('SELECT * FROM wa_conversemos WHERE simpatizante_id = :s AND campana_id = :c');
    $st->execute(['s' => $simpId, 'c' => $campanaId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Responde al Sí o al No. Con Sí envía el enlace personal al chat de datos.
 * Devuelve lo que hizo: 'enlace', 'gracias', 'repetido' o 'error'.
 */
function conv_responder(PDO $db, int $simpId, string $telefono, string $respuesta, int $campanaId): string
{
    $antes = conv_fila($db, $simpId, $campanaId);
    // Doble toque en el mismo botón en menos de un minuto: no se repite el mensaje
    if ($antes && $antes['respuesta'] === $respuesta && $antes['respondio_at'] && strtotime($antes['respondio_at']) > time() - 60) return 'repetido';

    $db->prepare('INSERT INTO wa_conversemos (simpatizante_id, campana_id, respuesta, respondio_at) VALUES (:s, :c, :r, NOW())
                  ON DUPLICATE KEY UPDATE respuesta = VALUES(respuesta), respondio_at = NOW()')
       ->execute(['s' => $simpId, 'c' => $campanaId, 'r' => $respuesta]);

    if ($respuesta === 'no') {
        if ($antes && $antes['respuesta'] === 'no') return 'repetido';   // ya se le agradeció
        if (wa_configurado()) wa_enviar_texto($telefono, CONV_TEXTO_NO);
        return 'gracias';
    }

    $token = ($antes['token'] ?? null) ?: bin2hex(random_bytes(16));
    $res = wa_configurado() ? conv_enviar_enlace($db, $telefono, $token) : ['ok' => false, 'detalle' => 'WhatsApp sin configurar'];
    $db->prepare('UPDATE wa_conversemos SET token = :t, enlace_at = NOW(), enlace_estado = :e, enlace_error = :d
                  WHERE simpatizante_id = :s AND campana_id = :c')
       ->execute(['t' => $token, 'e' => $res['ok'] ? 'enviado' : 'error', 'd' => $res['ok'] ? null : mb_substr((string)$res['detalle'], 0, 255),
                  's' => $simpId, 'c' => $campanaId]);
    if (!$res['ok']) error_log('[conversemos] No se pudo enviar el enlace a ' . substr($telefono, 0, 3) . '···: ' . $res['codigo'] . ' ' . $res['detalle']);
    return $res['ok'] ? 'enlace' : 'error';
}

/** Envía el texto de "datos" con el botón al enlace personal: plantilla aprobada o, si no hay, mensaje normal. */
function conv_enviar_enlace(PDO $db, string $telefono, string $token): array
{
    $url = wa_sitio() . '/conversemos/?t=' . $token;
    $st = $db->prepare("SELECT nombre, idioma, extras FROM wa_plantillas WHERE nombre = :n AND estado_meta = 'APPROVED' LIMIT 1");
    $st->execute(['n' => CONV_PLANTILLA_DATOS]);
    if ($tpl = $st->fetch(PDO::FETCH_ASSOC)) {
        $botones = json_decode((string)($tpl['extras'] ?? ''), true)['botones'] ?? [];
        foreach ($botones as $i => $b) {
            if (($b['tipo'] ?? '') === 'URL' && str_ends_with((string)($b['valor'] ?? ''), '{{1}}')) {
                $res = wa_enviar_mensaje($telefono, ['type' => 'template', 'template' => [
                    'name' => $tpl['nombre'], 'language' => ['code' => $tpl['idioma'] ?: 'es'],
                    'components' => [['type' => 'button', 'sub_type' => 'url', 'index' => (string)$i,
                                      'parameters' => [['type' => 'text', 'text' => $token]]]],
                ]]);
                if ($res['ok'] || $res['credenciales']) return $res;
                break;   // la plantilla falló: se intenta como mensaje normal
            }
        }
    }
    return wa_enviar_mensaje($telefono, ['type' => 'interactive', 'interactive' => [
        'type'   => 'cta_url',
        'body'   => ['text' => CONV_TEXTO_DATOS],
        'footer' => ['text' => 'Diana Montes'],
        'action' => ['name' => 'cta_url', 'parameters' => ['display_text' => CONV_BOTON, 'url' => $url]],
    ]]);
}

/* ======================= Página /conversemos/ ======================= */

/** Persona y conversación por el enlace personal; null si no existe o venció. */
function conv_por_token(PDO $db, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || !conv_listo($db)) return null;
    $conGenero = esquema_tiene($db, 'simpatizantes', 'genero');
    $st = $db->prepare('SELECT c.id AS conv_id, c.completado_at, c.enlace_at, c.respondio_at, c.campana_id, s.telefono, s.id, s.nombre, s.fecha_nacimiento, s.zona_id, s.direccion, s.email,
                               s.profesion_id, ' . ($conGenero ? 's.genero' : 'NULL AS genero') . ', z.nombre AS zona, p.nombre AS profesion
                        FROM wa_conversemos c
                        JOIN simpatizantes s ON s.id = c.simpatizante_id
                        LEFT JOIN zonas z ON z.id = s.zona_id
                        LEFT JOIN profesiones p ON p.id = s.profesion_id
                        WHERE c.token = :t LIMIT 1');
    $st->execute(['t' => $token]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f || !$f['enlace_at'] || strtotime($f['enlace_at']) < time() - CONV_DIAS_ENLACE * 86400) return null;
    $f['pide_genero'] = $conGenero;
    return $f;
}

/** Datos que ve el chat (solo lo necesario para preguntar lo que falta). */
function conv_datos_chat(array $p): array
{
    $primer = mb_convert_case(mb_strtolower((string)strtok((string)$p['nombre'], ' ')), MB_CASE_TITLE);
    return [
        'primer'     => $primer,
        'nombre'     => (string)$p['nombre'],
        'zona_id'    => $p['zona_id'] ? (int)$p['zona_id'] : null,
        'zona'       => $p['zona'],
        'tiene'      => [
            'cumple'    => !empty($p['fecha_nacimiento']),
            'direccion' => trim((string)$p['direccion']) !== '',
            'email'     => trim((string)$p['email']) !== '',
            'profesion' => !empty($p['profesion_id']) && $p['profesion'] !== 'Otra',
            'genero'    => !$p['pide_genero'] || !empty($p['genero']),
        ],
        'completado' => (bool)$p['completado_at'],
    ];
}

/**
 * Guarda una respuesta del chat. Devuelve [ok, mensaje de error o texto a mostrar].
 * Campos: nombre, cumple, zona, direccion, profesion, genero, email, sueno, fin.
 */
function conv_guardar(PDO $db, array $p, string $campo, string $valor): array
{
    $valor = trim($valor);
    $simp = (int)$p['id'];
    $set = function (string $col, $v) use ($db, $simp, $p) {
        $db->prepare("UPDATE simpatizantes SET $col = :v WHERE id = :id")->execute(['v' => $v, 'id' => $simp]);
        conv_marcar($db, (int)$p['conv_id'], $col);
    };
    switch ($campo) {
        case 'nombre':
            $n = normalizar_nombre($valor);
            if ($e = error_nombre_persona($n)) return [false, $e];
            $set('nombre', $n);
            return [true, $n];
        case 'cumple':
            if ($e = simpatizante_error_nacimiento($valor)) return [false, $e];
            $set('fecha_nacimiento', $valor);
            return [true, (new DateTime($valor))->format('d/m/Y')];
        case 'zona':
            $st = $db->prepare('SELECT nombre FROM zonas WHERE id = :id');
            $st->execute(['id' => (int)$valor]);
            if (!($nom = $st->fetchColumn())) return [false, 'Elige tu barrio o vereda de la lista.'];
            $set('zona_id', (int)$valor);
            return [true, $nom];
        case 'profesion':
            $st = $db->prepare('SELECT nombre FROM profesiones WHERE id = :id');
            $st->execute(['id' => (int)$valor]);
            if (!($nom = $st->fetchColumn())) return [false, 'Elige tu profesión u oficio de la lista.'];
            $set('profesion_id', (int)$valor);
            return [true, $nom];
        case 'direccion':
            $d = preg_replace('/\s+/u', ' ', $valor);
            if (mb_strlen($d) < 4 || mb_strlen($d) > 200) return [false, 'Escríbela un poquito más completa, por ejemplo: Calle 5 # 8-20.'];
            $set('direccion', $d);
            return [true, $d];
        case 'email':
            $c = mb_strtolower($valor);
            if ($e = error_correo($c)) return [false, $e];
            $set('email', $c);
            return [true, $c];
        case 'genero':
            if (!$p['pide_genero'] || !isset(GENEROS[$valor])) return [false, 'Elige una de las opciones.'];
            $set('genero', $valor);
            return [true, GENEROS[$valor]];
        case 'sueno':
            if (mb_strlen($valor) < 2) return [false, 'Cuéntame un poquito más 😊'];
            $db->prepare('UPDATE wa_conversemos SET sueno = :v WHERE id = :id')->execute(['v' => mb_substr($valor, 0, 1000), 'id' => $p['conv_id']]);
            return [true, ''];
        case 'fin':
            $db->prepare('UPDATE wa_conversemos SET completado_at = NOW() WHERE id = :id')->execute(['id' => $p['conv_id']]);
            $st = $db->prepare('SELECT campos FROM wa_conversemos WHERE id = :id');
            $st->execute(['id' => $p['conv_id']]);
            $db->prepare("INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (NULL, 'conversemos_datos', :d, :ip)")
               ->execute(['d' => 'Registro #' . $simp . ' confirmó sus datos por el chat' . (($c = $st->fetchColumn()) ? " ($c)" : ''),
                          'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            return [true, ''];
    }
    return [false, 'Pregunta no válida.'];
}

/** Anota qué datos actualizó la persona (para el admin). */
function conv_marcar(PDO $db, int $convId, string $col): void
{
    $nombres = ['nombre' => 'nombre', 'fecha_nacimiento' => 'cumpleaños', 'zona_id' => 'barrio', 'direccion' => 'dirección',
                'profesion_id' => 'profesión', 'genero' => 'género', 'email' => 'correo'];
    $st = $db->prepare('SELECT campos FROM wa_conversemos WHERE id = :id');
    $st->execute(['id' => $convId]);
    $lista = array_filter(explode(', ', (string)$st->fetchColumn()));
    if (!in_array($nombres[$col] ?? $col, $lista, true)) $lista[] = $nombres[$col] ?? $col;
    $db->prepare('UPDATE wa_conversemos SET campos = :c WHERE id = :id')->execute(['c' => implode(', ', $lista), 'id' => $convId]);
}

/* ======================= Registro por el chat (números que aún no están en la red) ======================= */

function conv_invitacion_listo(PDO $db): bool
{
    return esquema_tiene($db, 'wa_invitaciones');
}

/** Llave del chat de registro para un celular (reutiliza la vigente si ya tiene una). */
function conv_invitacion(PDO $db, string $telefono): string
{
    $st = $db->prepare('SELECT token FROM wa_invitaciones WHERE telefono = :t AND simpatizante_id IS NULL
                          AND created_at >= NOW() - INTERVAL ' . CONV_DIAS_ENLACE . ' DAY ORDER BY id DESC LIMIT 1');
    $st->execute(['t' => $telefono]);
    if ($t = $st->fetchColumn()) return (string)$t;
    $t = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO wa_invitaciones (token, telefono) VALUES (:k, :t)')->execute(['k' => $t, 't' => $telefono]);
    return $t;
}

/** Invitación vigente por su llave; null si no existe o venció. */
function conv_por_invitacion(PDO $db, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || !conv_invitacion_listo($db)) return null;
    $st = $db->prepare('SELECT * FROM wa_invitaciones WHERE token = :k AND created_at >= NOW() - INTERVAL ' . CONV_DIAS_ENLACE . ' DAY');
    $st->execute(['k' => $token]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Si el celular de la invitación ya está en la red (se registró luego por otro medio,
 * o terminó el registro por el chat), devuelve la llave de su chat de datos.
 */
function conv_invitacion_a_datos(PDO $db, array $inv): ?string
{
    $st = $db->prepare('SELECT id FROM simpatizantes WHERE telefono = :t LIMIT 1');
    $st->execute(['t' => $inv['telefono']]);
    $simp = $st->fetchColumn();
    if (!$simp) return null;
    $fila = conv_fila($db, (int)$simp, 0);
    $token = ($fila['token'] ?? null) ?: bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO wa_conversemos (simpatizante_id, campana_id, token, respuesta, respondio_at, enlace_at, enlace_estado)
                  VALUES (:s, 0, :k, 'si', NOW(), NOW(), 'enviado')
                  ON DUPLICATE KEY UPDATE token = VALUES(token), enlace_at = NOW()")
       ->execute(['s' => $simp, 'k' => $token]);
    return $token;
}

/**
 * Revisa una respuesta del registro y la guarda en $datos (la sesión) ya limpia.
 * Devuelve [ok, mensaje]. Con documento repetido: [false, mensaje, 'registrado'].
 */
function conv_registro_campo(PDO $db, string $campo, string $valor, array &$datos): array
{
    $valor = trim($valor);
    switch ($campo) {
        case 'nombre':
            $n = normalizar_nombre($valor);
            if ($e = error_nombre_persona($n)) return [false, $e];
            $datos['nombre'] = $n;
            return [true, $n];
        case 'documento':
            $d = normalizar_documento($valor);
            if ($e = error_documento($d)) return [false, $e];
            $st = $db->prepare('SELECT 1 FROM simpatizantes WHERE documento = :d LIMIT 1');
            $st->execute(['d' => $d]);
            if ($st->fetchColumn()) return [false, '¡Esa cédula ya está registrada en la red! 💜 Gracias por acompañarnos. Si te registraste con otro celular, entra a tu panel en ' . wa_sitio() . '/mi', 'registrado'];
            $datos['documento'] = $d;
            return [true, $d];
        case 'cumple':
            if ($e = simpatizante_error_nacimiento($valor)) return [false, $e];
            $datos['fecha_nacimiento'] = $valor;
            return [true, $valor];
        case 'genero':
            if (!isset(GENEROS[$valor])) return [false, 'Elige una de las opciones.'];
            $datos['genero'] = $valor;
            return [true, GENEROS[$valor]];
        case 'zona':
        case 'profesion':
            $tabla = $campo === 'zona' ? 'zonas' : 'profesiones';
            $st = $db->prepare("SELECT nombre FROM $tabla WHERE id = :id");
            $st->execute(['id' => (int)$valor]);
            if (!($nom = $st->fetchColumn())) return [false, $campo === 'zona' ? 'Elige tu barrio o vereda de la lista.' : 'Elige tu profesión u oficio de la lista.'];
            $datos[$campo === 'zona' ? 'zona_id' : 'profesion_id'] = (int)$valor;
            return [true, $nom];
        case 'clave':
            if ($e = error_clave($valor, (string)($datos['documento'] ?? ''))) return [false, $e];
            $datos['clave_hash'] = password_hash($valor, PASSWORD_DEFAULT);
            return [true, ''];
    }
    return [false, 'Pregunta no válida.'];
}

/** Líder de quien se registra por el chat: el de la red directa de Diana. */
function conv_lider_raiz(PDO $db): ?int
{
    if (defined('LIDER_ID_REGISTRO')) return (int)LIDER_ID_REGISTRO;
    $st = $db->prepare('SELECT id FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => defined('LIDER_RAIZ_REF') ? LIDER_RAIZ_REF : 'diana']);
    if ($id = $st->fetchColumn()) return (int)$id;
    $id = $db->query("SELECT id FROM usuarios WHERE rol = 'direccion' AND activo = 1 ORDER BY id LIMIT 1")->fetchColumn();
    return $id ? (int)$id : null;
}

/**
 * Crea el registro con lo respondido en el chat (ya autorizado el tratamiento de datos).
 * Devuelve [ok, mensaje, llave del chat de datos].
 */
function conv_registrar(PDO $db, array $inv, array $datos): array
{
    foreach (['nombre', 'documento', 'fecha_nacimiento', 'genero', 'zona_id', 'profesion_id'] as $c) {
        if (empty($datos[$c])) return [false, 'Nos faltó un dato. Recarga la página para empezar de nuevo.', null];
    }
    if (esquema_tiene($db, 'simpatizantes', 'clave_hash') && empty($datos['clave_hash'])) return [false, 'Nos faltó tu clave. Recarga la página para empezar de nuevo.', null];
    if (simpatizante_duplicado($db, $datos['documento'], $inv['telefono'])) {
        $token = conv_invitacion_a_datos($db, $inv);
        return $token ? [true, 'ya', $token] : [false, '¡Esa cédula ya está registrada en la red! 💜', null];
    }
    $lider = conv_lider_raiz($db);
    if (!$lider) return [false, 'El registro no está disponible en este momento. Intenta más tarde.', null];
    try {
        $nuevo = simpatizante_insertar($db, [
            'nombre' => $datos['nombre'], 'documento' => $datos['documento'], 'telefono' => $inv['telefono'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'], 'genero' => $datos['genero'],
            'zona_id' => $datos['zona_id'], 'profesion_id' => $datos['profesion_id'],
            'nivel' => 'simpatizante', 'lider_id' => $lider, 'referido_por' => null,
            'clave_hash' => $datos['clave_hash'] ?? null, 'origen' => 'whatsapp',
        ]);
    } catch (PDOException $e) {
        if (es_error_duplicado($e)) return [false, 'Ese documento o celular ya está registrado en la red.', null];
        throw $e;
    }
    $db->prepare('UPDATE wa_invitaciones SET simpatizante_id = :s, registrado_at = NOW() WHERE id = :id')->execute(['s' => $nuevo['id'], 'id' => $inv['id']]);
    $db->prepare("INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (NULL, 'registro_whatsapp', :d, :ip)")
       ->execute(['d' => 'Doc ' . substr($datos['documento'], 0, 3) . '··· por el chat de WhatsApp', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    return [true, '', conv_invitacion_a_datos($db, $inv)];
}

/* ======================= La conversación que ya hubo en WhatsApp ======================= */

/** Hora como la muestra WhatsApp: "2:07 p. m." */
function conv_hora(string $fecha): string
{
    $t = strtotime($fecha);
    return date('g:i', $t) . ((int)date('G', $t) < 12 ? ' a. m.' : ' p. m.');
}

/** Lo que la persona respondió por WhatsApp ("Si", "Sí", "si claro"…), o "Sí". */
function conv_respuesta_escrita(PDO $db, string $telefono): string
{
    $st = $db->prepare('SELECT texto FROM wa_entrantes WHERE telefono = :t ORDER BY id DESC LIMIT 10');
    $st->execute(['t' => $telefono]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) {
        if (conv_respuesta(wa_normalizar((string)$x)) === 'si') return mb_substr(trim((string)$x), 0, 60);
    }
    return 'Sí';
}

/**
 * Los tres mensajes que ya se cruzaron en WhatsApp, para que el chat siga esa
 * conversación: el "¿Conversamos?" de Diana (con sus botones), el Sí de la
 * persona y la respuesta con el botón que la trajo aquí.
 * $modo = 'datos' (ya en la red) o 'registro' (Quiero conocerte).
 */
function conv_historial(PDO $db, string $modo, string $telefono, string $respondio, string $enlace, ?int $simpId = null): array
{
    // Texto y botones de la plantilla tal como está en Meta (si ya se trajo), si no, el sugerido
    $st = $db->prepare("SELECT cuerpo, extras FROM wa_plantillas WHERE nombre = :n ORDER BY (estado_meta = 'APPROVED') DESC LIMIT 1");
    $st->execute(['n' => CONV_PLANTILLA]);
    $tpl = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $botones = array_values(array_map(fn($b) => (string)$b['texto'],
        array_filter(json_decode((string)($tpl['extras'] ?? ''), true)['botones'] ?? [], fn($b) => ($b['tipo'] ?? '') === 'QUICK_REPLY'))) ?: ['Sí', 'No'];
    // Hora en que le llegó el mensaje masivo (si salió por la cola)
    $enviado = null;
    if ($simpId) {
        $st = $db->prepare('SELECT enviado_at FROM wa_mensajes WHERE simpatizante_id = :s AND plantilla = :p AND enviado_at <= :r ORDER BY enviado_at DESC LIMIT 1');
        $st->execute(['s' => $simpId, 'p' => CONV_PLANTILLA, 'r' => $respondio]);
        $enviado = $st->fetchColumn() ?: null;
    }
    $si = conv_respuesta_escrita($db, $telefono);
    $elegido = 0;
    foreach ($botones as $i => $b) if (conv_respuesta(wa_normalizar($b)) === 'si') { $elegido = $i; break; }
    return [
        'fecha' => date('Y-m-d', strtotime($enviado ?: $respondio)),
        'mensajes' => [
            ['lado' => 'entra', 'texto' => (string)($tpl['cuerpo'] ?? '') ?: CONV_TEXTO, 'hora' => conv_hora($enviado ?: $respondio),
             'botones' => $botones, 'elegido' => $elegido],
            ['lado' => 'sale', 'texto' => $si, 'hora' => conv_hora($respondio)],
            ['lado' => 'entra', 'texto' => $modo === 'registro' ? CONV_TEXTO_CONOCERTE : CONV_TEXTO_DATOS, 'hora' => conv_hora($enlace),
             'enlace' => $modo === 'registro' ? CONV_BOTON_CONOCERTE : CONV_BOTON],
        ],
    ];
}
