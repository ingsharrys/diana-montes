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
 * Devuelve ['campana_id' => int] o null.
 */
function conv_origen(PDO $db, int $simpId, ?string $contexto): ?array
{
    if ($contexto) {
        $st = $db->prepare('SELECT clave, plantilla FROM wa_mensajes WHERE wamid = :w AND simpatizante_id = :s LIMIT 1');
        $st->execute(['w' => $contexto, 's' => $simpId]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if ($m) return $m['plantilla'] === CONV_PLANTILLA ? ['campana_id' => conv_campana_de_clave($m['clave'])] : null;
    }
    $st = $db->prepare("SELECT clave FROM wa_mensajes WHERE simpatizante_id = :s AND plantilla = :p
                          AND enviado_at >= NOW() - INTERVAL 7 DAY ORDER BY enviado_at DESC LIMIT 1");
    $st->execute(['s' => $simpId, 'p' => CONV_PLANTILLA]);
    $clave = $st->fetchColumn();
    return $clave === false ? null : ['campana_id' => conv_campana_de_clave((string)$clave)];
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
    $st = $db->prepare('SELECT c.id AS conv_id, c.completado_at, c.enlace_at, s.id, s.nombre, s.fecha_nacimiento, s.zona_id, s.direccion, s.email,
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
