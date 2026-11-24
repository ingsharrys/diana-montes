<?php
/**
 * /conversemos/?t=LLAVE — Chat para confirmar los datos de contacto.
 *
 * Llega por WhatsApp cuando la persona responde "Sí" al mensaje masivo
 * "¿Conversamos?" (ver inc/conversemos.php). Se ve como una conversación:
 * Diana pregunta uno a uno solo los datos que faltan y cada respuesta se
 * guarda al momento en el registro de la persona.
 *
 * GET  -> la página. POST (JSON) -> guarda una respuesta: {campo, valor, csrf}.
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/promotores.php';

session_start();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$db = db();

/* La llave viaja en el enlace solo la primera vez: luego queda en la sesión y se quita de la barra */
if (is_string($_GET['t'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_GET['t'])) $_SESSION['conv_t'] = $_GET['t'];
$token = (string)($_SESSION['conv_t'] ?? '');
$persona = $token !== '' ? conv_por_token($db, $token) : null;

/* ---------- POST: guardar una respuesta ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $responder = function (bool $ok, string $msg = '', int $http = 200) {
        http_response_code($http);
        echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!$persona) $responder(false, 'Este enlace ya no está activo. Responde "Sí" a mi mensaje en WhatsApp y te envío uno nuevo.', 403);
    if (!hash_equals((string)($_SESSION['conv_csrf'] ?? ''), (string)($in['csrf'] ?? ''))) $responder(false, 'La página estuvo abierta mucho tiempo. Recárgala, por favor.', 403);
    $_SESSION['conv_n'] = ($_SESSION['conv_n'] ?? 0) + 1;
    if ($_SESSION['conv_n'] > 120) $responder(false, 'Recibí muchas respuestas seguidas. Intenta de nuevo más tarde.', 429);
    try {
        [$ok, $msg] = conv_guardar($db, $persona, (string)($in['campo'] ?? ''), is_scalar($in['valor'] ?? null) ? (string)$in['valor'] : '');
    } catch (Throwable $e) {
        error_log('[conversemos] ' . $e->getMessage());
        $responder(false, 'No pude guardar eso ahora 😕 Intenta de nuevo en un momento.', 500);
    }
    $responder($ok, $msg);
}

/* ---------- GET: la página ---------- */
if (empty($_SESSION['conv_csrf'])) $_SESSION['conv_csrf'] = bin2hex(random_bytes(16));
$datos = null;
if ($persona) {
    $db->prepare('UPDATE wa_conversemos SET abierto_at = NOW() WHERE id = :id AND abierto_at IS NULL')->execute(['id' => $persona['conv_id']]);
    $datos = conv_datos_chat($persona);
    $datos['zonas'] = array_map(fn($z) => [(int)$z['id'], $z['nombre'], zona_grupo_etiqueta($z)], zonas_listar($db));
    $datos['profesiones'] = array_map(fn($p) => [(int)$p['id'], $p['nombre']],
        $db->query("SELECT id, nombre FROM profesiones ORDER BY (nombre = 'Otra'), nombre")->fetchAll(PDO::FETCH_ASSOC));
    $datos['generos'] = $persona['pide_genero'] ? GENEROS : [];
}
$L = legal_datos($db);
$wa = preg_replace('/\D/', '', (string)$L['legal_telefono']);
if (strlen($wa) === 10) $wa = '57' . $wa;
$config = ['csrf' => $_SESSION['conv_csrf'], 'persona' => $datos, 'whatsapp' => strlen($wa) >= 12 ? "https://wa.me/$wa" : null];
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
  --entra:#fff; --sale:#D9FDD3; --txt:#111B21; --suave:#667781; --acento:#00A884; --enlace:#027EB5;
  --chip:#fff; --chip-txt:#008069; --aviso:#FFEECD; --aviso-txt:#54656F; --compositor:#F0F2F5; --campo:#fff; --visto:#53BDEB;
  --sombra:0 1px .5px rgba(11,20,26,.13);
}
@media (prefers-color-scheme: dark){:root{
  --barra:#202C33; --barra-txt:#E9EDEF; --fondo:#0B141A; --patron:rgba(255,255,255,.03);
  --entra:#202C33; --sale:#005C4B; --txt:#E9EDEF; --suave:#8696A0; --acento:#00A884; --enlace:#53BDEB;
  --chip:#202C33; --chip-txt:#00A884; --aviso:#182229; --aviso-txt:#FFD279; --compositor:#202C33; --campo:#2A3942;
}}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font:15px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--txt);background:var(--fondo);
  display:flex;flex-direction:column;height:100dvh;max-width:760px;margin:0 auto;overflow:hidden}
header{background:var(--barra);color:var(--barra-txt);display:flex;align-items:center;gap:10px;padding:8px 12px;padding-top:max(8px,env(safe-area-inset-top));flex:none}
header img{width:40px;height:40px;border-radius:50%;object-fit:cover}
header b{display:block;font-size:16px;font-weight:600}
header small{display:block;font-size:12.5px;opacity:.85;min-height:16px}
#chat{flex:1;overflow-y:auto;padding:10px 4% 14px;scroll-behavior:smooth;
  background-color:var(--fondo);
  background-image:radial-gradient(var(--patron) 1.2px,transparent 1.3px),radial-gradient(var(--patron) 1.2px,transparent 1.3px);
  background-size:26px 26px;background-position:0 0,13px 13px}
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
form{display:flex;align-items:flex-end;gap:6px}
.campo{flex:1;background:var(--campo);border-radius:22px;display:flex;align-items:center;min-height:44px;padding:0 14px}
.campo input,.campo textarea{flex:1;border:0;outline:0;background:transparent;color:var(--txt);font:inherit;font-size:16px;padding:10px 0;resize:none;max-height:120px;min-width:0}
.campo input::placeholder,.campo textarea::placeholder{color:var(--suave)}
.campo input[type=date]{color-scheme:light dark}
.enviar{width:44px;height:44px;border-radius:50%;border:0;background:var(--acento);color:#fff;display:grid;place-items:center;cursor:pointer;flex:none}
.enviar:disabled{opacity:.45;cursor:default}
.enviar svg{width:22px;height:22px}
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
  <img src="/img/diana-avatar.jpg" alt="" width="40" height="40">
  <div><b>Diana Montes</b>
    <small id="estado">en línea</small></div>
</header>

<main id="chat" aria-live="polite">
  <div class="dia">Hoy</div>
  <p class="sistema">🔒 Tus datos se guardan de forma segura y solo los usa el equipo de Diana Montes, según la
    <a href="/privacidad/" target="_blank" rel="noopener">política de datos</a> (Ley 1581 de 2012).</p>
</main>

<footer id="pie">
  <div class="sugerencias" id="sugerencias" role="listbox"></div>
  <form id="compositor" autocomplete="off" novalidate>
    <div class="campo"><input id="entrada" placeholder="Elige una opción ☝️" disabled aria-label="Tu respuesta"></div>
    <button class="enviar" id="enviar" type="submit" disabled aria-label="Enviar">
      <svg viewBox="0 0 24 24"><path fill="currentColor" d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a.99.99 0 0 0-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
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
  btnEnviar.disabled = true;
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
      entrada.type = op.tipo === 'date' ? 'date' : (op.tipo === 'email' ? 'email' : 'text');
      entrada.inputMode = op.tipo === 'email' ? 'email' : 'text';
      if (op.tipo === 'date') { entrada.max = new Date().toISOString().slice(0, 10); entrada.min = '1915-01-01'; }
    }
    entrada.disabled = false; entrada.value = op.valor || ''; entrada.placeholder = op.placeholder || 'Escribe aquí…';
    const actualizar = () => btnEnviar.disabled = entrada.value.trim() === '';
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
  const p = CFG.persona;
  await espera(500);
  if (!p) {
    await bot('¡Hola! 💜');
    await bot('Este enlace ya no está activo o no es válido 😕');
    await bot('Responde *Sí* a mi mensaje en WhatsApp y te envío uno nuevo. Y si aún no haces parte de la red, puedes sumarte aquí:');
    const c = document.createElement('div'); c.className = 'botones aparece';
    c.innerHTML = '<a href="/#sumate">💜 Quiero sumarme</a>';
    chat.appendChild(c); bajar();
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

  // Dirección
  if (!tiene.direccion) {
    await bot('¿Me compartes tu dirección? 🏡 Así puedo invitarte personalmente cuando esté por tu sector.');
    await preguntar('direccion', { placeholder: 'Ej. Calle 5 # 8-20, casa azul', saltar: 'Prefiero no decirla' });
  }

  // Profesión u oficio
  if (!tiene.profesion) {
    await bot('¿A qué te dedicas? 💼');
    await preguntarLista('profesion', p.profesiones, 'Escribe tu profesión u oficio…');
  }

  // Género
  if (!tiene.genero && Object.keys(p.generos).length) {
    await bot('¿Cómo te identificas?');
    const etiquetas = Object.values(p.generos);
    for (;;) {
      const g = await botones(etiquetas);
      const clave = Object.keys(p.generos).find(k => p.generos[k] === g);
      const m = yo(g);
      const res = await guardar('genero', clave);
      if (res.ok) { m.visto(); break; }
      await bot(res.msg);
    }
  }

  // Correo
  if (!tiene.email) {
    await bot('¿Tienes correo electrónico? 📧 Es opcional.');
    await preguntar('email', { tipo: 'email', placeholder: 'tucorreo@gmail.com', saltar: 'No tengo' });
  }

  // Lo más importante
  await bot('Y ahora lo más importante para mí 💜');
  await bot('Cuéntame, ¿cómo estás y qué sueñas para Garzón?');
  const s = await preguntar('sueno', { tipo: 'largo', placeholder: 'Escríbeme lo que quieras…', saltar: 'Ahora no' });
  if (s !== null) await bot('Gracias por contármelo. Lo voy a leer con mucho cariño 🙏');

  await guardar('fin', '');
  return despedida(p, true);
}

async function despedida(p, guardado) {
  if (guardado) await bot(`¡Listo, ${p.primer}! Ya quedaron guardados tus datos ✅`);
  await bot('Pronto te escribo para invitarte a nuestro próximo encuentro. Me haría muy feliz verte 💜');
  await bot('Un abrazo,\n*Diana Montes*');
  document.getElementById('pie').classList.add('oculto');
  if (CFG.whatsapp) {
    const c = document.createElement('div'); c.className = 'botones aparece';
    c.innerHTML = `<a href="${esc(CFG.whatsapp)}">💬 Volver a WhatsApp</a>`;
    chat.appendChild(c); bajar();
  }
}

conversar();
</script>
</body>
</html>
