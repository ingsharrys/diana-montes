<?php
/**
 * /conversemos/ — Chat con aspecto de WhatsApp. Dos modos:
 *
 * ?t=LLAVE  Confirmar datos: llega a quien ya está en la red y respondió "Sí"
 *           al mensaje "¿Conversamos?". Pregunta solo lo que falta.
 * ?r=LLAVE  Registro ("Quiero conocerte"): llega a un celular que aún no está
 *           en la red. Su número ya viene del enlace; se le pregunta el resto,
 *           se pide la autorización de datos y se crea el registro.
 *
 * Cada respuesta se revisa en el servidor y, si hay un error, Diana lo explica
 * en el chat. GET -> la página. POST (JSON) -> {campo, valor, csrf}.
 */
require __DIR__ . '/../admin/bootstrap.php';   // misma configuración del admin (base de datos y WhatsApp)

session_start();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$db = Core\Database::conexion();

/* Las llaves viajan en el enlace solo la primera vez: luego quedan en la sesión y se quitan de la barra */
foreach (['t' => 'conv_t', 'r' => 'conv_r'] as $param => $clave) {
    if (is_string($_GET[$param] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_GET[$param])) {
        $_SESSION[$clave] = $_GET[$param];
        if ($param === 't') unset($_SESSION['conv_r']); else unset($_SESSION['conv_t'], $_SESSION['conv_reg']);
    }
}
$invitacion = !empty($_SESSION['conv_r']) ? conv_por_invitacion($db, (string)$_SESSION['conv_r']) : null;
// Si el celular ya está en la red (o acaba de registrarse), sigue con su chat de datos
if ($invitacion && ($t = conv_invitacion_a_datos($db, $invitacion))) {
    $_SESSION['conv_t'] = $t;
    unset($_SESSION['conv_r']);
    $invitacion = null;
}
$token = (string)($_SESSION['conv_t'] ?? '');
$persona = $token !== '' ? conv_por_token($db, $token) : null;

/** Lo que el chat necesita para preguntar: datos de la persona y las listas. */
function conv_paquete(PDO $db, ?array $persona): array
{
    $d = $persona ? conv_datos_chat($persona) : [];
    $d['zonas'] = array_map(fn($z) => [(int)$z['id'], $z['nombre'], zona_grupo_etiqueta($z)], zonas_listar($db));
    $d['profesiones'] = array_map(fn($p) => [(int)$p['id'], $p['nombre']],
        $db->query("SELECT id, nombre FROM profesiones ORDER BY (nombre = 'Otra'), nombre")->fetchAll(PDO::FETCH_ASSOC));
    $d['generos'] = !$persona || $persona['pide_genero'] ? GENEROS : [];
    return $d;
}

/* ---------- POST: guardar una respuesta ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $responder = function (bool $ok, string $msg = '', int $http = 200, array $extra = []) {
        http_response_code($http);
        echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!$persona && !$invitacion) $responder(false, 'Este enlace ya no está activo. Responde "Sí" a mi mensaje en WhatsApp y te envío uno nuevo.', 403);
    if (!hash_equals((string)($_SESSION['conv_csrf'] ?? ''), (string)($in['csrf'] ?? ''))) $responder(false, 'La página estuvo abierta mucho tiempo. Recárgala, por favor.', 403);
    $_SESSION['conv_n'] = ($_SESSION['conv_n'] ?? 0) + 1;
    if ($_SESSION['conv_n'] > 150) $responder(false, 'Recibí muchas respuestas seguidas. Intenta de nuevo más tarde.', 429);
    $campo = (string)($in['campo'] ?? '');
    $valor = is_scalar($in['valor'] ?? null) ? (string)$in['valor'] : '';
    try {
        if ($persona) {
            [$ok, $msg] = conv_guardar($db, $persona, $campo, $valor);
            $responder($ok, $msg);
        }
        // Registro: cada respuesta se revisa y queda en la sesión hasta la autorización
        $reg = $_SESSION['conv_reg'] ?? [];
        if ($campo === 'registrar') {
            [$ok, $msg, $t] = conv_registrar($db, $invitacion, $reg);
            if (!$ok) $responder(false, $msg);
            $_SESSION['conv_t'] = $t;
            unset($_SESSION['conv_r'], $_SESSION['conv_reg']);
            $responder(true, $msg, 200, ['persona' => conv_paquete($db, conv_por_token($db, $t))]);
        }
        $r = conv_registro_campo($db, $campo, $valor, $reg);
        $_SESSION['conv_reg'] = $reg;
        $responder($r[0], (string)$r[1], 200, isset($r[2]) ? ['fin' => $r[2]] : []);
    } catch (Throwable $e) {
        error_log('[conversemos] ' . $e->getMessage());
        $responder(false, 'No pude guardar eso ahora 😕 Intenta de nuevo en un momento.', 500);
    }
}

/* ---------- GET: la página ---------- */
if (empty($_SESSION['conv_csrf'])) $_SESSION['conv_csrf'] = bin2hex(random_bytes(16));
$modo = $persona ? 'datos' : ($invitacion ? 'registro' : null);
$datos = null;
if ($persona) {
    $db->prepare('UPDATE wa_conversemos SET abierto_at = NOW() WHERE id = :id AND abierto_at IS NULL')->execute(['id' => $persona['conv_id']]);
    $datos = conv_paquete($db, $persona);
} elseif ($invitacion) {
    $db->prepare('UPDATE wa_invitaciones SET abierto_at = NOW() WHERE id = :id AND abierto_at IS NULL')->execute(['id' => $invitacion['id']]);
    $datos = conv_paquete($db, null) + ['pide_clave' => esquema_tiene($db, 'simpatizantes', 'clave_hash')];
}
$L = legal_datos($db);
$wa = preg_replace('/\D/', '', (string)$L['legal_telefono']);
if (strlen($wa) === 10) $wa = '57' . $wa;
$config = ['csrf' => $_SESSION['conv_csrf'], 'modo' => $modo, 'persona' => $datos, 'whatsapp' => strlen($wa) >= 12 ? "https://wa.me/$wa" : null,
           'sitio' => wa_sitio()];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#008069" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#202C33" media="(prefers-color-scheme: dark)">
<meta name="robots" content="noindex, nofollow">
<title>Conversemos · Diana Montes</title>
<link rel="icon" href="/img/diana-avatar.jpg">
<style>
:root{
  --barra:#008069; --barra-txt:#fff; --fondo:#EFEAE2; --patron:rgba(0,0,0,.045);
  --doodle:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22180%22 height=%22180%22 viewBox=%220 0 180 180%22 fill=%22none%22 stroke=%22rgba%280,0,0,0.07%29%22 stroke-width=%221.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpath d=%22M28 38c-7-6-12-9-12-14a6 6 0 0 1 12-2 6 6 0 0 1 12 2c0 5-5 8-12 14z%22/%3E%3Cpath d=%22M120 18l3.5 7 7.5 1-5.5 5.3 1.3 7.5-6.8-3.6-6.8 3.6 1.3-7.5-5.5-5.3 7.5-1z%22/%3E%3Cpath d=%22M70 70h28a6 6 0 0 1 6 6v14a6 6 0 0 1-6 6H82l-8 7v-7h-4a6 6 0 0 1-6-6V76a6 6 0 0 1 6-6z%22/%3E%3Cpath d=%22M150 80v20a5 5 0 1 1-3-4.6V84l14-4v16a5 5 0 1 1-3-4.6%22/%3E%3Cpath d=%22M22 120h22v10a11 11 0 0 1-22 0zM44 123h4a4 4 0 0 1 0 8h-4M28 112c0-3 3-3 3-6M36 112c0-3 3-3 3-6%22/%3E%3Ccircle cx=%22112%22 cy=%22140%22 r=%227%22/%3E%3Cpath d=%22M112 125v4M112 151v4M97 140h4M123 140h4M101 129l3 3M120 148l3 3M123 129l-3 3M104 148l-3 3%22/%3E%3Cpath d=%22M160 150c-14 0-22-8-22-20 12 0 22 6 22 20zM160 150l-14-14%22/%3E%3Cpath d=%22M60 160l6-6M66 160l-6-6%22/%3E%3Ccircle cx=%22160%22 cy=%2240%22 r=%223%22/%3E%3Ccircle cx=%2280%22 cy=%2230%22 r=%222%22/%3E%3C/svg%3E");
  --entra:#fff; --sale:#D9FDD3; --txt:#111B21; --suave:#667781; --acento:#00A884; --enlace:#027EB5;
  --chip:#fff; --chip-txt:#008069; --aviso:#FFEECD; --aviso-txt:#54656F; --compositor:#F0F2F5; --campo:#fff; --visto:#53BDEB;
  --sombra:0 1px .5px rgba(11,20,26,.13);
}
@media (prefers-color-scheme: dark){:root{
  --barra:#202C33; --barra-txt:#E9EDEF; --fondo:#0B141A; --patron:rgba(255,255,255,.03);
  --doodle:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22180%22 height=%22180%22 viewBox=%220 0 180 180%22 fill=%22none%22 stroke=%22rgba%28255,255,255,0.045%29%22 stroke-width=%221.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpath d=%22M28 38c-7-6-12-9-12-14a6 6 0 0 1 12-2 6 6 0 0 1 12 2c0 5-5 8-12 14z%22/%3E%3Cpath d=%22M120 18l3.5 7 7.5 1-5.5 5.3 1.3 7.5-6.8-3.6-6.8 3.6 1.3-7.5-5.5-5.3 7.5-1z%22/%3E%3Cpath d=%22M70 70h28a6 6 0 0 1 6 6v14a6 6 0 0 1-6 6H82l-8 7v-7h-4a6 6 0 0 1-6-6V76a6 6 0 0 1 6-6z%22/%3E%3Cpath d=%22M150 80v20a5 5 0 1 1-3-4.6V84l14-4v16a5 5 0 1 1-3-4.6%22/%3E%3Cpath d=%22M22 120h22v10a11 11 0 0 1-22 0zM44 123h4a4 4 0 0 1 0 8h-4M28 112c0-3 3-3 3-6M36 112c0-3 3-3 3-6%22/%3E%3Ccircle cx=%22112%22 cy=%22140%22 r=%227%22/%3E%3Cpath d=%22M112 125v4M112 151v4M97 140h4M123 140h4M101 129l3 3M120 148l3 3M123 129l-3 3M104 148l-3 3%22/%3E%3Cpath d=%22M160 150c-14 0-22-8-22-20 12 0 22 6 22 20zM160 150l-14-14%22/%3E%3Cpath d=%22M60 160l6-6M66 160l-6-6%22/%3E%3Ccircle cx=%22160%22 cy=%2240%22 r=%223%22/%3E%3Ccircle cx=%2280%22 cy=%2230%22 r=%222%22/%3E%3C/svg%3E");
  --entra:#202C33; --sale:#005C4B; --txt:#E9EDEF; --suave:#8696A0; --acento:#00A884; --enlace:#53BDEB;
  --chip:#202C33; --chip-txt:#00A884; --aviso:#182229; --aviso-txt:#FFD279; --compositor:#202C33; --campo:#2A3942;
}}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font:15px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--txt);background:var(--fondo);
  display:flex;flex-direction:column;height:100dvh;max-width:760px;margin:0 auto;overflow:hidden;overflow-x:hidden}
header{background:var(--barra);color:var(--barra-txt);display:flex;align-items:center;gap:10px;padding:8px 12px;padding-top:max(8px,env(safe-area-inset-top));flex:none}
header img{width:40px;height:40px;border-radius:50%;object-fit:cover}
header .atras{color:inherit;display:grid;place-items:center;margin-right:-6px}
header .iconos{margin-left:auto;display:flex;gap:22px;padding-right:4px}
header svg{width:23px;height:23px;display:block}
header b{display:block;font-size:16px;font-weight:600}
header small{display:block;font-size:12.5px;opacity:.85;min-height:16px}
#chat{flex:1;overflow-y:auto;padding:10px 4% 14px;scroll-behavior:smooth;
  background-color:var(--fondo);
  background-image:var(--doodle);background-size:180px 180px}
.dia{align-self:center;text-align:center;margin:6px auto 10px;background:var(--entra);color:var(--suave);font-size:12.5px;padding:5px 12px;border-radius:8px;box-shadow:var(--sombra);width:max-content}
.sistema{background:var(--aviso);color:var(--aviso-txt);font-size:12.5px;text-align:center;padding:6px 12px;border-radius:8px;margin:0 auto 12px;max-width:92%;box-shadow:var(--sombra);line-height:1.45}
.sistema a{color:inherit;font-weight:600}
.fila{display:flex;margin:2px 0}
.fila.sale{justify-content:flex-end}
.burbuja{position:relative;max-width:min(82%,520px);padding:6px 8px 8px 9px;border-radius:8px;box-shadow:var(--sombra);white-space:pre-wrap;overflow-wrap:anywhere}
.entra .burbuja{background:var(--entra)}
.sale .burbuja{background:var(--sale)}
.fila.primera{margin-top:8px}
.entra.primera .burbuja{border-top-left-radius:0}
.sale.primera .burbuja{border-top-right-radius:0}
.entra.primera .burbuja::before,.sale.primera .burbuja::before{content:"";position:absolute;top:0;width:8px;height:13px}
.entra.primera .burbuja::before{left:-8px;background:var(--entra);clip-path:polygon(100% 0,0 0,100% 100%)}
.sale.primera .burbuja::before{right:-8px;background:var(--sale);clip-path:polygon(0 0,100% 0,0 100%)}
.hora{float:right;margin:6px 0 -4px 10px;font-size:11px;color:var(--suave);display:inline-flex;align-items:center;gap:3px;white-space:nowrap}
.check{width:16px;height:11px;color:var(--suave)}
.check.visto{color:var(--visto)}
.botones{display:flex;flex-direction:column;gap:2px;margin:2px 0 4px;max-width:min(82%,520px)}
.botones button,.botones a{background:var(--chip);color:var(--chip-txt);border:0;border-radius:8px;box-shadow:var(--sombra);font:inherit;font-weight:500;
  padding:10px 12px;text-align:center;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px}
.botones button:disabled{opacity:.55;cursor:default}
.botones button.elegido{opacity:1;font-weight:700}
.escribiendo .burbuja{padding:12px 14px}
.puntos{display:flex;gap:4px}
.puntos i{width:7px;height:7px;border-radius:50%;background:var(--suave);opacity:.5;animation:p 1.2s infinite}
.puntos i:nth-child(2){animation-delay:.2s}.puntos i:nth-child(3){animation-delay:.4s}
@keyframes p{0%,60%,100%{transform:none;opacity:.4}30%{transform:translateY(-4px);opacity:1}}
.aparece{animation:ap .18s ease-out}
@keyframes ap{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
footer{flex:none;background:var(--compositor);padding:6px 8px;padding-bottom:max(6px,env(safe-area-inset-bottom));position:relative}
form{display:flex;align-items:flex-end;gap:6px;min-width:0}
.campo{flex:1;min-width:0;background:var(--campo);border-radius:22px;display:flex;align-items:center;min-height:44px;padding:0 14px}
.campo input,.campo textarea{flex:1;border:0;outline:0;background:transparent;color:var(--txt);font:inherit;font-size:16px;padding:10px 0;resize:none;max-height:120px;min-width:0}
.campo input::placeholder,.campo textarea::placeholder{color:var(--suave)}
.campo input[type=date]{color-scheme:light dark}
.enviar{width:44px;height:44px;border-radius:50%;border:0;background:var(--acento);color:#fff;display:grid;place-items:center;cursor:pointer;flex:none}
.enviar:disabled{cursor:default}
.enviar svg{width:22px;height:22px}
.enviar .ico-env,.enviar.listo .ico-mic{display:none}
.enviar.listo .ico-env{display:block}
.mas{width:30px;height:44px;display:grid;place-items:center;color:var(--suave);flex:none}
.mas svg,.campo .emoji{width:24px;height:24px}
.campo .emoji{color:var(--suave);margin-right:10px;flex:none}
.sugerencias{position:absolute;left:8px;right:60px;bottom:100%;margin-bottom:6px;background:var(--entra);border-radius:10px;box-shadow:0 4px 18px rgba(0,0,0,.18);
  max-height:min(48vh,300px);overflow-y:auto;display:none}
.sugerencias.ver{display:block}
.sugerencias button{display:block;width:100%;text-align:left;background:none;border:0;border-bottom:1px solid rgba(134,150,160,.15);color:var(--txt);font:inherit;padding:10px 14px;cursor:pointer}
.sugerencias button small{display:block;color:var(--suave);font-size:12px}
.sugerencias button:hover,.sugerencias button:focus{background:rgba(0,168,132,.1);outline:0}
.sugerencias p{padding:12px 14px;color:var(--suave);font-size:14px}
.oculto{display:none!important}
</style>
</head>
<body>
<header>
  <a class="atras" id="atras" href="#" aria-label="Volver"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 4l1.4 1.4L7.8 11H20v2H7.8l5.6 5.6L12 20l-8-8z"/></svg></a>
  <img src="/img/diana-avatar.jpg" alt="" width="40" height="40">
  <div><b>Diana Montes</b>
    <small id="estado">en línea</small></div>
  <div class="iconos" aria-hidden="true">
    <svg viewBox="0 0 24 24"><path fill="currentColor" d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11z"/></svg>
    <svg viewBox="0 0 24 24"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
  </div>
</header>

<main id="chat" aria-live="polite">
  <div class="dia">Hoy</div>
  <p class="sistema">🔒 Tus datos se guardan de forma segura y solo los usa el equipo de Diana Montes, según la
    <a href="/privacidad/" target="_blank" rel="noopener">política de datos</a> (Ley 1581 de 2012).</p>
</main>

<footer id="pie">
  <div class="sugerencias" id="sugerencias" role="listbox"></div>
  <form id="compositor" autocomplete="off" novalidate>
    <span class="mas" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg></span>
    <div class="campo"><svg class="emoji" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-3.5-9A1.5 1.5 0 1 0 8.5 8a1.5 1.5 0 0 0 0 3zm7 0A1.5 1.5 0 1 0 15.5 8a1.5 1.5 0 0 0 0 3zM12 17.5c2.3 0 4.3-1.5 5-3.5H7c.7 2 2.7 3.5 5 3.5z"/></svg><input id="entrada" placeholder="Elige una opción ☝️" disabled aria-label="Tu respuesta"></div>
    <button class="enviar" id="enviar" type="submit" disabled aria-label="Enviar">
      <svg class="ico-mic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 15a3.5 3.5 0 0 0 3.5-3.5v-6a3.5 3.5 0 0 0-7 0v6A3.5 3.5 0 0 0 12 15zm6-3.5a6 6 0 0 1-12 0H4.5a7.5 7.5 0 0 0 6.75 7.46V22h1.5v-3.04a7.5 7.5 0 0 0 6.75-7.46z"/></svg>
      <svg class="ico-env" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a.99.99 0 0 0-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
    </button>
  </form>
</footer>

<script>
const CFG = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
// La llave ya quedó en la sesión: se quita de la barra de direcciones
if (location.search) history.replaceState(null, '', location.pathname);

const chat = document.getElementById('chat'), estado = document.getElementById('estado');
const form = document.getElementById('compositor'), btnEnviar = document.getElementById('enviar');
const sug = document.getElementById('sugerencias');
let entrada = document.getElementById('entrada');
let ultimo = null;   // quién habló último (para la colita de la burbuja)

const espera = ms => new Promise(r => setTimeout(r, ms));
const hora = () => new Date().toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit' }).replace(/\s?([ap])\.?\s?m\.?/i, ' $1. m.');
const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const formato = t => esc(t).replace(/\*([^*\n]+)\*/g, '<b>$1</b>');   // *negrita* como en WhatsApp
const normal = t => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const CHECK = '<svg class="check" viewBox="0 0 16 11" aria-hidden="true"><path fill="currentColor" d="M11.07.65l-6.6 8.13-2.7-2.49-.9.97 3.73 3.45L12.1 1.5zM15.1.65l-6.6 8.13-.94-.86-.85 1.05 1.94 1.79L16.1 1.5z"/></svg>';

function fila(lado, html) {
  const f = document.createElement('div');
  f.className = `fila ${lado} aparece` + (ultimo !== lado ? ' primera' : '');
  f.innerHTML = `<div class="burbuja">${html}</div>`;
  ultimo = lado;
  chat.appendChild(f);
  bajar();
  return f;
}
const bajar = () => requestAnimationFrame(() => chat.scrollTop = chat.scrollHeight);

/** Diana escribe: "escribiendo…" un momento y luego el mensaje. */
async function bot(texto) {
  estado.textContent = 'escribiendo…';
  const e = document.createElement('div');
  e.className = 'fila entra escribiendo aparece' + (ultimo !== 'entra' ? ' primera' : '');
  e.innerHTML = '<div class="burbuja"><span class="puntos"><i></i><i></i><i></i></span></div>';
  chat.appendChild(e); bajar();
  await espera(Math.min(450 + texto.length * 16, 1500));
  e.remove();
  estado.textContent = 'en línea';
  return fila('entra', `${formato(texto)}<span class="hora">${hora()}</span>`);
}

/** Lo que responde la persona (con los chulos: grises al enviar, azules al guardar). */
function yo(texto) {
  const f = fila('sale', `${formato(texto)}<span class="hora">${hora()} ${CHECK}</span>`);
  return { visto: () => f.querySelector('.check').classList.add('visto') };
}

/** Botones de respuesta debajo del último mensaje de Diana. Devuelve el texto elegido. */
function botones(opciones) {
  bloquear('Elige una opción ☝️');
  return new Promise(resolve => {
    const caja = document.createElement('div');
    caja.className = 'botones aparece';
    opciones.forEach(o => {
      const b = document.createElement('button');
      b.type = 'button'; b.textContent = o;
      b.onclick = () => {
        caja.querySelectorAll('button').forEach(x => x.disabled = true);
        b.classList.add('elegido');
        resolve(o);
      };
      caja.appendChild(b);
    });
    chat.appendChild(caja); ultimo = null; bajar();
  });
}

function bloquear(texto) {
  cambiarEntrada('input');
  entrada.disabled = true; entrada.value = ''; entrada.placeholder = texto;
  btnEnviar.disabled = true; btnEnviar.classList.remove('listo');
  sug.classList.remove('ver');
}

/** Cambia el campo de escritura (input de una línea o textarea). */
function cambiarEntrada(tipo) {
  if (entrada.tagName.toLowerCase() === tipo) return;
  const nuevo = document.createElement(tipo);
  nuevo.id = 'entrada'; nuevo.setAttribute('aria-label', 'Tu respuesta');
  if (tipo === 'textarea') { nuevo.rows = 1; nuevo.oninput = () => { nuevo.style.height = 'auto'; nuevo.style.height = nuevo.scrollHeight + 'px'; }; }
  entrada.replaceWith(nuevo); entrada = nuevo;
}

/**
 * Espera que la persona escriba. op: {tipo: text|email|date|largo, placeholder, saltar: 'texto del botón para omitir'}.
 * Devuelve el texto o null si tocó "saltar".
 */
function escribir(op = {}) {
  return new Promise(resolve => {
    let caja = null;
    if (op.saltar) {
      caja = document.createElement('div');
      caja.className = 'botones aparece';
      const b = document.createElement('button');
      b.type = 'button'; b.textContent = op.saltar;
      b.onclick = () => { terminar(); resolve(null); };
      caja.appendChild(b); chat.appendChild(caja); ultimo = null; bajar();
    }
    cambiarEntrada(op.tipo === 'largo' ? 'textarea' : 'input');
    if (entrada.tagName === 'INPUT') {
      entrada.type = { date: 'date', email: 'email', clave: 'password' }[op.tipo] || 'text';
      entrada.inputMode = { email: 'email', numero: 'numeric' }[op.tipo] || 'text';
      entrada.autocomplete = op.tipo === 'clave' ? 'new-password' : 'off';
      if (op.tipo === 'date') { entrada.max = new Date().toISOString().slice(0, 10); entrada.min = '1915-01-01'; }
    }
    entrada.disabled = false; entrada.value = op.valor || ''; entrada.placeholder = op.placeholder || 'Escribe aquí…';
    const actualizar = () => { const v = entrada.value.trim() !== ''; btnEnviar.disabled = !v; btnEnviar.classList.toggle('listo', v); };
    entrada.addEventListener('input', actualizar); actualizar();
    setTimeout(() => entrada.focus({ preventScroll: true }), 50);
    const enviar = ev => {
      ev.preventDefault();
      const v = entrada.value.trim();
      if (!v) return;
      terminar(); resolve(v);
    };
    // En computador Enter envía; en el celular hace salto de línea (como en WhatsApp)
    const tecla = ev => { if (ev.key === 'Enter' && !ev.shiftKey && entrada.tagName === 'TEXTAREA' && matchMedia('(pointer: fine)').matches) enviar(ev); };
    form.addEventListener('submit', enviar);
    entrada.addEventListener('keydown', tecla);
    function terminar() {
      form.removeEventListener('submit', enviar);
      entrada.removeEventListener('keydown', tecla);
      entrada.removeEventListener('input', actualizar);
      if (caja) caja.querySelectorAll('button').forEach(x => x.disabled = true);
      bloquear('Un momento…');
    }
  });
}

/** Busca en una lista (barrios, profesiones) mientras escribe. Devuelve [id, nombre]. */
function elegir(lista, placeholder) {
  return new Promise(resolve => {
    cambiarEntrada('input');
    entrada.type = 'text'; entrada.disabled = false; entrada.value = ''; entrada.placeholder = placeholder;
    btnEnviar.disabled = true;
    const indice = lista.map(x => [x, normal(x[1])]);
    const pintar = () => {
      const q = normal(entrada.value);
      if (q.length < 2) { sug.classList.remove('ver'); return; }
      const res = indice.filter(([, n]) => n.includes(q))
                        .sort((a, b) => (b[1].startsWith(q) - a[1].startsWith(q)) || a[1].localeCompare(b[1])).slice(0, 30);
      sug.innerHTML = res.length
        ? res.map(([x], i) => `<button type="button" role="option" data-i="${lista.indexOf(x)}">${esc(x[1])}${x[2] ? `<small>${esc(x[2])}</small>` : ''}</button>`).join('')
        : '<p>No lo encuentro 🤔 Prueba con otra palabra.</p>';
      sug.classList.add('ver'); sug.scrollTop = 0;
    };
    const elegido = ev => {
      const b = ev.target.closest('button[data-i]');
      if (!b) return;
      terminar(); resolve(lista[+b.dataset.i]);
    };
    const enviar = ev => {   // Enter: si hay una sola coincidencia (o la primera), se elige
      ev.preventDefault();
      const b = sug.querySelector('button[data-i]');
      if (b && sug.classList.contains('ver')) { terminar(); resolve(lista[+b.dataset.i]); }
    };
    entrada.addEventListener('input', pintar);
    sug.addEventListener('click', elegido);
    form.addEventListener('submit', enviar);
    setTimeout(() => entrada.focus({ preventScroll: true }), 50);
    function terminar() {
      entrada.removeEventListener('input', pintar);
      sug.removeEventListener('click', elegido);
      form.removeEventListener('submit', enviar);
      bloquear('Un momento…');
    }
  });
}

/** Guarda una respuesta. Si el servidor la rechaza, Diana explica y se vuelve a preguntar. */
async function guardar(campo, valor) {
  try {
    const r = await fetch(location.pathname, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ campo, valor, csrf: CFG.csrf }),
    });
    const j = await r.json();
    return j;
  } catch (e) {
    return { ok: false, msg: 'Parece que se fue la conexión 📶 Intenta de nuevo.' };
  }
}

/** Pregunta con escritura libre hasta que la respuesta sea válida. Devuelve lo guardado o null si la omitió. */
async function preguntar(campo, op, mostrar = v => v) {
  for (;;) {
    const v = await escribir(op);
    if (v === null) { yo(op.saltar).visto(); return null; }
    const m = yo(mostrar(v));
    const r = await guardar(campo, v);
    if (r.ok) { m.visto(); return r.msg || v; }
    await bot(r.msg || 'Mmm, revisemos eso otra vez.');
  }
}

async function preguntarLista(campo, lista, placeholder) {
  for (;;) {
    const [id, nombre] = await elegir(lista, placeholder);
    const m = yo(nombre);
    const r = await guardar(campo, id);
    if (r.ok) { m.visto(); return nombre; }
    await bot(r.msg);
  }
}

const fechaBonita = v => { const [a, m, d] = v.split('-'); return `${+d} de ${['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][+m - 1]} de ${a}`; };

async function conversar() {
  const atras = document.getElementById('atras');
  atras.href = CFG.whatsapp || '/';
  await espera(500);
  if (CFG.modo === 'registro') return registro(CFG.persona);
  const p = CFG.persona;
  if (!p) {
    await bot('¡Hola! 💜');
    await bot('Este enlace ya no está activo o no es válido 😕');
    await bot('Responde *Sí* a mi mensaje en WhatsApp y te envío uno nuevo. Y si aún no haces parte de la red, puedes sumarte aquí:');
    enlace('💜 Quiero sumarme', '/#sumate');
    document.getElementById('pie').classList.add('oculto');
    return;
  }

  let tiene = p.tiene;
  await bot(`¡Hola, ${p.primer}! 💜`);
  if (p.completado) {
    await bot('Qué alegría verte otra vez por aquí. Ya tengo tus datos guardados.');
    const r = await botones(['Actualizar mis datos', 'Así están bien']);
    yo(r).visto();
    if (r === 'Así están bien') return despedida(p, false);
    tiene = { ...tiene, direccion: false, email: false, profesion: false };
  } else {
    await bot('Qué alegría que estés aquí. Te voy a hacer unas preguntas cortitas para que sigamos en contacto y pueda invitarte personalmente 😊');
    yo(await botones(['¡Empecemos!'])).visto();
  }

  // Nombre
  await bot(`¿Tu nombre completo es *${p.nombre}*?`);
  let r = await botones(['Sí, así es', 'Corregirlo']);
  yo(r).visto();
  if (r === 'Corregirlo') {
    await bot('Escríbeme tu nombre completo, por favor ✍️');
    const n = await preguntar('nombre', { placeholder: 'Nombres y apellidos' });
    p.primer = n.split(' ')[0];
  }

  // Cumpleaños
  if (!tiene.cumple) {
    await bot('¿Cuándo es tu cumpleaños? 🎂 Me encantaría saludarte ese día.');
    await preguntar('cumple', { tipo: 'date', placeholder: 'Fecha de nacimiento' }, fechaBonita);
    await bot('¡Anotado! 🎉');
  }

  // Barrio o vereda
  let mudo = !p.zona_id;
  if (p.zona_id) {
    await bot(`¿Sigues viviendo en *${p.zona}*?`);
    r = await botones(['Sí', 'Me mudé']);
    yo(r).visto();
    mudo = r === 'Me mudé';
  }
  if (mudo) {
    await bot('¿En qué barrio o vereda vives? Escribe el nombre y elígelo de la lista 👇');
    await preguntarLista('zona', p.zonas, 'Escribe tu barrio o vereda…');
  }
  if (!tiene.profesion) {
    await bot('¿A qué te dedicas? 💼');
    await preguntarLista('profesion', p.profesiones, 'Escribe tu profesión u oficio…');
  }
  if (!tiene.genero && Object.keys(p.generos).length) await preguntarGenero(p.generos);
  return completar(p, tiene);
}

/** Últimas preguntas (dirección, correo y qué sueña) y despedida: las usan los dos modos. */
async function completar(p, tiene) {
  if (!tiene.direccion) {
    await bot('¿Me compartes tu dirección? 🏡 Así puedo invitarte personalmente cuando esté por tu sector.');
    await preguntar('direccion', { placeholder: 'Ej. Calle 5 # 8-20, casa azul', saltar: 'Prefiero no decirla' });
  }
  if (!tiene.email) {
    await bot('¿Tienes correo electrónico? 📧 Es opcional.');
    await preguntar('email', { tipo: 'email', placeholder: 'tucorreo@gmail.com', saltar: 'No tengo' });
  }
  await bot('Y ahora lo más importante para mí 💜');
  await bot('Cuéntame, ¿cómo estás y qué sueñas para Garzón?');
  const s = await preguntar('sueno', { tipo: 'largo', placeholder: 'Escríbeme lo que quieras…', saltar: 'Ahora no' });
  if (s !== null) await bot('Gracias por contármelo. Lo voy a leer con mucho cariño 🙏');
  await guardar('fin', '');
  return despedida(p, true);
}

async function preguntarGenero(generos) {
  await bot('¿Cómo te identificas?');
  const etiquetas = Object.values(generos);
  for (;;) {
    const g = await botones(etiquetas);
    const clave = Object.keys(generos).find(k => generos[k] === g);
    const m = yo(g);
    const res = await guardar('genero', clave);
    if (res.ok) { m.visto(); return; }
    await bot(res.msg);
  }
}

/** Registro de alguien que aún no está en la red ("Quiero conocerte"). */
async function registro(p) {
  await bot('¡Hola! 💜 Soy Diana Montes.');
  await bot('Qué alegría que quieras conocerme. Te hago unas preguntas cortitas para conocerte y poder invitarte personalmente cuando nos reunamos 😊');
  yo(await botones(['¡Empecemos!'])).visto();

  await bot('¿Cómo te llamas? Escríbeme tu nombre completo ✍️');
  const nombre = await preguntar('nombre', { placeholder: 'Nombres y apellidos' });
  const primer = nombre.split(' ')[0];
  await bot(`¡Mucho gusto, ${primer}! 🤗`);

  await bot('¿Cuál es tu número de cédula? Lo necesito para tu registro en la red.');
  for (;;) {
    const v = await escribir({ tipo: 'numero', placeholder: 'Solo números, sin puntos' });
    const m = yo(v);
    const r = await guardar('documento', v);
    if (r.ok) { m.visto(); break; }
    await bot(r.msg);
    if (r.fin === 'registrado') return terminarSinGuardar();
  }

  await bot('¿Cuándo es tu cumpleaños? 🎂 Me encantaría saludarte ese día.');
  await preguntar('cumple', { tipo: 'date', placeholder: 'Fecha de nacimiento' }, fechaBonita);
  await bot('¡Anotado! 🎉');

  await preguntarGenero(p.generos);

  await bot('¿En qué barrio o vereda vives? Escribe el nombre y elígelo de la lista 👇');
  await preguntarLista('zona', p.zonas, 'Escribe tu barrio o vereda…');

  await bot('¿A qué te dedicas? 💼');
  await preguntarLista('profesion', p.profesiones, 'Escribe tu profesión u oficio…');

  if (p.pide_clave) {
    await bot('Ahora crea una clave 🔑 Con tu cédula y esa clave entras a tu panel en la página de Diana.');
    await preguntar('clave', { tipo: 'clave', placeholder: 'Mínimo 6 caracteres' }, v => '•'.repeat(Math.min(v.length, 12)));
  }

  await bot('Para guardar tus datos necesito tu autorización 🔒 Los usamos solo para mantenernos en contacto contigo y nunca los compartimos.');
  enlace('📄 Ver la política de datos', '/privacidad/', true);
  await bot('¿Me autorizas a guardar tus datos? (Ley 1581 de 2012)');
  const a = await botones(['Sí, autorizo', 'No autorizo']);
  yo(a).visto();
  if (a === 'No autorizo') {
    await bot('Entiendo 💜 Sin tu autorización no puedo guardar tus datos.');
    await bot('Si cambias de opinión, abre otra vez el enlace que te envié por WhatsApp. Un abrazo, Diana.');
    return terminarSinGuardar();
  }
  const r = await guardar('registrar', 'si');
  if (!r.ok) { await bot(r.msg); return terminarSinGuardar(); }
  const persona = r.persona;
  if (r.msg === 'ya') {
    await bot(`¡${primer}, ya haces parte de la red! 💜`);
  } else {
    await bot(`¡Listo, ${primer}! Ya haces parte de la red 💜`);
    await bot(`Con tu cédula y tu clave puedes entrar a tu panel en *${CFG.sitio.replace(/^https?:\/\//, '')}/mi* 🙌`);
  }
  return completar({ ...persona, primer }, persona.tiene);
}

function terminarSinGuardar() {
  document.getElementById('pie').classList.add('oculto');
}

/** Botón con enlace debajo del último mensaje. */
function enlace(texto, url, nuevaPestana = false) {
  const c = document.createElement('div'); c.className = 'botones aparece';
  c.innerHTML = `<a href="${esc(url)}"${nuevaPestana ? ' target="_blank" rel="noopener"' : ''}>${esc(texto)}</a>`;
  chat.appendChild(c); ultimo = null; bajar();
}

async function despedida(p, guardado) {
  if (guardado) await bot(`¡Listo, ${p.primer}! Ya quedaron guardados tus datos ✅`);
  await bot('Pronto te escribo para invitarte a nuestro próximo encuentro. Me haría muy feliz verte 💜');
  await bot('Un abrazo,\n*Diana Montes*');
  document.getElementById('pie').classList.add('oculto');
  if (CFG.whatsapp) enlace('💬 Volver a WhatsApp', CFG.whatsapp);
}

conversar();
</script>
</body>
</html>
