<?php
/**
 * Plantilla de las páginas legales públicas: /privacidad/, /terminos/ y /eliminar-datos/.
 * Son las URL que pide Meta en la configuración de la app (y Google en la pantalla de consentimiento).
 */
require_once __DIR__ . '/datos_personales.php';

const LEGAL_PAGINAS = [
    'privacidad'     => ['Política de privacidad',        '/privacidad/'],
    'terminos'       => ['Condiciones del servicio',      '/terminos/'],
    'eliminar-datos' => ['Eliminación de datos',          '/eliminar-datos/'],
];

/** Escapa texto para HTML (no depende del config de la landing). */
function le(?string $t): string
{
    return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}

/** Conexión a la base de datos si está disponible; las páginas legales se muestran aun sin ella. */
function legal_db(): ?PDO
{
    static $db = false;
    if ($db === false) {
        try { $db = function_exists('db') ? db() : null; } catch (Throwable $e) { $db = null; }
    }
    return $db;
}

/** Línea de contacto del responsable (correo y WhatsApp solo si están configurados). */
function legal_contacto(array $L): string
{
    $partes = [];
    if ($L['legal_email'] !== '') $partes[] = 'correo <a href="mailto:' . le($L['legal_email']) . '">' . le($L['legal_email']) . '</a>';
    if ($L['legal_telefono'] !== '') {
        $wa = preg_replace('/\D/', '', $L['legal_telefono']);
        if (strlen($wa) === 10) $wa = '57' . $wa;
        $partes[] = 'WhatsApp <a href="https://wa.me/' . le($wa) . '">' . le($L['legal_telefono']) . '</a>';
    }
    $partes[] = 'el formulario de <a href="/eliminar-datos/#solicitud">solicitudes de datos personales</a>';
    return implode(', ', $partes);
}

function legal_abrir(string $clave, string $descripcion): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    [$titulo] = LEGAL_PAGINAS[$clave];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= le($titulo) ?> · Diana Lucía Montes</title>
<meta name="description" content="<?= le($descripcion) ?>">
<link rel="icon" href="/img/diana-montes.png">
<style>
  :root{--rosa:#E0186C;--rosa-osc:#C0105A;--rosa-soft:#FDEBF3;--violeta:#7C3AED;--violeta-soft:#F3EEFD;--ink:#101226;--gris:#5A6072;--linea:#E9EBF2;--fondo:#F8F9FC;--verde:#16A34A;--grad:linear-gradient(100deg,var(--rosa) 0%,var(--violeta) 120%)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Inter,sans-serif;background:var(--fondo);color:var(--ink);line-height:1.65;font-size:16px;-webkit-font-smoothing:antialiased}
  a{color:var(--rosa-osc)}
  .barra{background:var(--grad);color:#fff;padding:18px 16px 70px}
  .barra-in{max-width:880px;margin:0 auto;display:flex;align-items:center;gap:12px}
  .logo{width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.18);display:grid;place-items:center;font-weight:800;color:#fff;text-decoration:none;flex:none}
  .marca{color:#fff;text-decoration:none;line-height:1.25}
  .marca b{display:block;font-size:16px}.marca span{font-size:13px;opacity:.85}
  .hoja{max-width:880px;margin:-50px auto 40px;padding:0 16px}
  .tabs{display:flex;gap:6px;overflow-x:auto;margin-bottom:14px;scrollbar-width:none}
  .tabs::-webkit-scrollbar{display:none}
  .tabs a{flex:none;white-space:nowrap;background:#fff;border:1px solid var(--linea);border-radius:99px;padding:7px 14px;font-size:14px;font-weight:600;color:var(--gris);text-decoration:none}
  .tabs a.activa{background:var(--ink);border-color:var(--ink);color:#fff}
  .papel{background:#fff;border-radius:20px;box-shadow:0 1px 2px rgba(16,18,38,.05),0 12px 32px rgba(16,18,38,.07);padding:32px clamp(18px,5vw,48px)}
  h1{font-size:clamp(26px,5vw,34px);line-height:1.2;letter-spacing:-.02em;margin-bottom:6px}
  .vigencia{color:var(--gris);font-size:14px;margin-bottom:22px}
  h2{font-size:20px;margin:30px 0 8px;letter-spacing:-.01em;scroll-margin-top:16px}
  h3{font-size:16px;margin:18px 0 6px}
  p,li{color:#2b2f45}
  p+p{margin-top:10px}
  ul,ol{padding-left:22px;margin:8px 0}
  li{margin:5px 0}
  .resumen{background:var(--violeta-soft);border-radius:14px;padding:16px 18px;margin:8px 0 6px}
  .resumen li{margin:3px 0}
  .aviso{background:var(--rosa-soft);border-radius:14px;padding:14px 16px;margin:14px 0}
  .ok{background:#E9F9EF;color:#14532d;border-radius:14px;padding:14px 16px;margin:14px 0}
  .error{background:#FDECEF;color:#8f0e33;border-radius:14px;padding:12px 16px;margin:12px 0;font-weight:600}
  table{width:100%;border-collapse:collapse;margin:10px 0;font-size:15px}
  th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--linea);vertical-align:top}
  th{background:var(--fondo);font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--gris)}
  .tabla{overflow-x:auto}
  .pasos{counter-reset:p;list-style:none;padding:0}
  .pasos>li{counter-increment:p;position:relative;padding:14px 16px 14px 58px;border:1px solid var(--linea);border-radius:14px;margin:10px 0}
  .pasos>li::before{content:counter(p);position:absolute;left:16px;top:14px;width:28px;height:28px;border-radius:50%;background:var(--grad);color:#fff;font-weight:800;display:grid;place-items:center;font-size:14px}
  form{display:grid;gap:14px;margin-top:12px}
  .campo{display:grid;gap:5px}
  .campo span{font-weight:600;font-size:14px}
  .campo em{color:#B4123F;font-style:normal;font-size:13px;font-weight:600}
  .campo small{color:var(--gris);font-size:13px}
  input,select,textarea{font:inherit;padding:11px 13px;border:1.5px solid var(--linea);border-radius:12px;background:#fff;width:100%;color:var(--ink)}
  input:focus,select:focus,textarea:focus{outline:none;border-color:var(--violeta);box-shadow:0 0 0 3px var(--violeta-soft)}
  .con-error input,.con-error select,.con-error textarea{border-color:#B4123F}
  .doble{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media (max-width:600px){.doble{grid-template-columns:1fr}}
  .check{display:flex;gap:10px;align-items:flex-start;font-size:14px}
  .check input{width:20px;height:20px;flex:none;margin-top:2px;accent-color:var(--rosa)}
  .btn{display:inline-block;border:0;border-radius:12px;background:var(--rosa);color:#fff;font-weight:700;font-size:16px;padding:13px 20px;cursor:pointer;text-decoration:none;text-align:center}
  .btn:hover{background:var(--rosa-osc)}
  .btn-sec{background:var(--ink)}
  .oculto{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .etq{display:inline-block;font-size:13px;font-weight:700;border-radius:99px;padding:3px 10px}
  .etq.oro{background:#FFF6E0;color:#B7791F}.etq.verde{background:#E9F9EF;color:#16A34A}.etq.gris{background:#F0EEF6;color:#6B6580}
  footer{max-width:880px;margin:0 auto 40px;padding:0 16px;color:var(--gris);font-size:14px;display:flex;flex-wrap:wrap;gap:8px 18px;justify-content:space-between}
  footer a{color:var(--gris)}
</style>
</head>
<body>
<header class="barra">
  <div class="barra-in">
    <a class="logo" href="/" aria-label="Inicio">DM</a>
    <a class="marca" href="/"><b>Diana Lucía Montes</b><span>Alcaldía de Garzón 2027</span></a>
  </div>
</header>
<main class="hoja">
  <nav class="tabs" aria-label="Documentos legales">
    <?php foreach (LEGAL_PAGINAS as $k => [$t, $u]): ?>
      <a href="<?= $u ?>" class="<?= $k === $clave ? 'activa' : '' ?>" <?= $k === $clave ? 'aria-current="page"' : '' ?>><?= le($t) ?></a>
    <?php endforeach; ?>
  </nav>
  <article class="papel">
<?php
}

function legal_cerrar(): void
{
    ?>
  </article>
</main>
<footer>
  <span>© <?= date('Y') ?> Campaña Diana Lucía Montes · Garzón, Huila</span>
  <span><a href="/privacidad/">Privacidad</a> · <a href="/terminos/">Condiciones</a> · <a href="/eliminar-datos/">Eliminar mis datos</a> · <a href="/">Volver al inicio</a></span>
</footer>
</body>
</html>
<?php
}
