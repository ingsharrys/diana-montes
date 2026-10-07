<?php
/**
 * index.php — Landing oficial dianamontes.com
 * Conectada a la base de datos de la Plataforma Electoral:
 * los selects de zona/profesión se cargan del catálogo real y el
 * formulario guarda vía registrar.php (fetch, sin recargar la página).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/inc/promotores.php';
session_start();

/* Catálogos desde la BD (con respaldo por si la BD no responde) */
try {
    $zonas = db()->query('SELECT id, nombre, tipo FROM zonas ORDER BY tipo, nombre')->fetchAll();
    $profesiones = db()->query('SELECT id, nombre FROM profesiones ORDER BY id')->fetchAll();
    $pideGenero = esquema_tiene(db(), 'simpatizantes', 'genero');
    $pideClave  = esquema_tiene(db(), 'simpatizantes', 'clave_hash'); // acceso al panel /mi/
} catch (Throwable $e) {
    $zonas = []; $profesiones = []; $pideGenero = false; $pideClave = false;
}
$fechaMaxima = date('Y-m-d', strtotime('-' . EDAD_MINIMA . ' years'));
$fechaMinima = date('Y-m-d', strtotime('-110 years'));

/* Invitación (?ref=) de un líder o de un promotor ciudadano:
   se resuelve en el servidor para pintar el chip "Te invita…" */
$refCodigo = '';
$refLider  = '';
if (!empty($_GET['ref']) && preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $_GET['ref'])) {
    $refCodigo = $_GET['ref'];
    try {
        $refLider = promotor_resolver_ref(db(), $refCodigo)['nombre'] ?? '';
    } catch (Throwable $e) { /* silencioso */ }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diana Lucía Montes · Alcaldía de Garzón 2027</title>
<meta name="description" content="Diana Lucía Montes, candidata a la Alcaldía de Garzón, Huila. Súmate a la red que está transformando el municipio.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --rosa:#E0186C; --rosa-osc:#C0105A; --rosa-soft:#FDEBF3;
    --violeta:#7C3AED; --violeta-soft:#F3EEFD;
    --ink:#101226; --gris:#5A6072; --linea:#E9EBF2;
    --fondo:#FFFFFF; --fondo-2:#F8F9FC; --verde:#16A34A;
    --sombra:0 1px 2px rgba(16,18,38,.05),0 12px 32px rgba(16,18,38,.07);
    --sombra-lg:0 24px 60px rgba(16,18,38,.13);
    --head:'Plus Jakarta Sans',system-ui,sans-serif;
    --body:'Inter',system-ui,sans-serif;
    --grad:linear-gradient(100deg,var(--rosa) 0%,var(--violeta) 120%);
  }
  *{margin:0;padding:0;box-sizing:border-box}
  html{scroll-behavior:smooth}
  @media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
  body{font-family:var(--body);background:var(--fondo);color:var(--ink);font-size:16px;line-height:1.6;-webkit-font-smoothing:antialiased}
  h1,h2,h3,h4{font-family:var(--head);letter-spacing:-.02em}
  a{color:inherit}
  button{font-family:inherit;cursor:pointer}
  :focus-visible{outline:3px solid var(--rosa);outline-offset:3px;border-radius:6px}
  .wrap{max-width:1120px;margin:0 auto;padding:0 clamp(18px,4vw,32px)}

  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;text-decoration:none;font-family:var(--head);font-weight:700;font-size:15px;padding:14px 26px;border-radius:14px;transition:transform .12s, box-shadow .2s, background .2s}
  .btn:active{transform:scale(.97)}
  .btn-rosa{background:var(--rosa);color:#fff;box-shadow:0 8px 20px rgba(224,24,108,.30)}
  .btn-rosa:hover{background:var(--rosa-osc);transform:translateY(-1px)}
  .btn-soft{background:var(--fondo-2);color:var(--ink);border:1.5px solid var(--linea)}
  .btn-soft:hover{border-color:#CBD0DE}
  .btn-sm{padding:10px 18px;font-size:13.5px;border-radius:11px}

  /* header */
  header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--linea)}
  .nav{display:flex;align-items:center;justify-content:space-between;height:68px;gap:16px}
  .logo{display:flex;align-items:center;gap:11px;text-decoration:none}
  .logo-mark{width:40px;height:40px;border-radius:13px;background:var(--grad);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--head);font-weight:800;font-size:17px;flex:none}
  .logo b{font-family:var(--head);font-size:16px;font-weight:800;line-height:1.15;display:block}
  .logo span{font-size:10px;letter-spacing:1.8px;text-transform:uppercase;color:var(--rosa);font-weight:700}
  .nav-links{display:flex;gap:4px;align-items:center;list-style:none;background:var(--fondo-2);border:1px solid var(--linea);border-radius:99px;padding:5px}
  .nav-links a{text-decoration:none;font-weight:600;font-size:13.5px;color:var(--gris);padding:8px 15px;border-radius:99px}
  .nav-links a:hover{background:#fff;color:var(--ink);box-shadow:var(--sombra)}

  /* ---------- hero ---------- */
  .hero{position:relative;padding:clamp(36px,6vw,64px) 0 clamp(40px,7vw,72px);overflow:hidden}
  .hero::before{content:"";position:absolute;top:-220px;right:-180px;width:620px;height:620px;border-radius:50%;background:radial-gradient(circle,rgba(224,24,108,.09),transparent 65%)}
  .hero-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:clamp(24px,5vw,56px);align-items:center;position:relative;z-index:1}
  .pill{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--linea);box-shadow:var(--sombra);border-radius:99px;padding:8px 16px;font-size:12.5px;font-weight:700;color:var(--gris);margin-bottom:20px}
  .pill i{font-style:normal;width:8px;height:8px;border-radius:50%;background:var(--verde)}
  h1{font-weight:800;font-size:clamp(36px,5.6vw,60px);line-height:1.07}
  h1 .grad{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
  .hero p.lead{font-size:clamp(15.5px,1.9vw,18px);color:var(--gris);margin:18px 0 26px;max-width:44ch}
  .hero-ctas{display:flex;gap:12px;flex-wrap:wrap;align-items:center}

  .video-card{position:relative;border-radius:26px;overflow:hidden;aspect-ratio:3/4;max-height:540px;width:100%;background:linear-gradient(160deg,var(--rosa-soft),var(--violeta-soft));box-shadow:var(--sombra-lg);border:1px solid var(--linea);justify-self:end}
  .video-card video{width:100%;height:100%;object-fit:cover;display:block}
  .btn-sonido{position:absolute;right:14px;bottom:14px;z-index:3;width:46px;height:46px;border-radius:50%;border:none;background:rgba(16,18,38,.55);backdrop-filter:blur(6px);color:#fff;font-size:19px;display:flex;align-items:center;justify-content:center;transition:transform .12s, background .2s}
  .btn-sonido:hover{background:var(--rosa);transform:scale(1.06)}
  .video-tag{position:absolute;left:14px;top:14px;z-index:3;background:rgba(255,255,255,.9);backdrop-filter:blur(6px);border-radius:99px;padding:6px 13px;font-size:11px;font-weight:800;color:var(--rosa-osc);display:flex;align-items:center;gap:6px}
  .video-tag i{font-style:normal;width:7px;height:7px;border-radius:50%;background:var(--rosa);animation:pulsa 1.6s infinite}
  @keyframes pulsa{0%,100%{opacity:1}50%{opacity:.35}}
  @media (prefers-reduced-motion:reduce){.video-tag i{animation:none}}

  /* ---------- secciones ---------- */
  section{padding:clamp(52px,8vw,88px) 0}
  .sec-alt{background:var(--fondo-2);border-top:1px solid var(--linea);border-bottom:1px solid var(--linea)}
  .sec-head{max-width:600px;margin:0 auto clamp(26px,4vw,42px);text-align:center}
  .kicker{display:inline-block;font-size:11.5px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--rosa);background:var(--rosa-soft);border-radius:99px;padding:7px 15px;margin-bottom:14px}
  h2{font-weight:800;font-size:clamp(25px,3.9vw,38px);line-height:1.14}
  .sec-head p{color:var(--gris);margin-top:12px;font-size:15px}

  /* ---------- acordeón retos ---------- */
  .acordeon{max-width:760px;margin:0 auto;display:grid;gap:12px}
  .ac-item{background:#fff;border:1px solid var(--linea);border-radius:18px;box-shadow:var(--sombra);overflow:hidden}
  .ac-btn{width:100%;background:none;border:none;display:flex;align-items:center;gap:14px;padding:18px 20px;text-align:left}
  .ac-btn .a-ico{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex:none}
  .ac-btn h3{font-size:16px;font-weight:800;flex:1}
  .ac-btn .chev{width:30px;height:30px;border-radius:50%;background:var(--fondo-2);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--gris);transition:transform .25s;flex:none}
  .ac-item.abierto .chev{transform:rotate(180deg);background:var(--rosa-soft);color:var(--rosa)}
  .ac-panel{max-height:0;overflow:hidden;transition:max-height .3s ease}
  .ac-panel-in{padding:0 20px 20px 78px}
  .ac-panel .problema{font-size:14px;color:var(--gris);margin-bottom:10px}
  .ac-panel .respuesta{font-size:14px;background:var(--fondo-2);border-left:3px solid var(--rosa);border-radius:0 10px 10px 0;padding:10px 13px}
  .ac-panel .respuesta b{color:var(--rosa-osc)}
  @media (prefers-reduced-motion:reduce){.ac-panel{transition:none}.ac-btn .chev{transition:none}}

  /* ---------- tabs propuestas ---------- */
  .tabs{max-width:820px;margin:0 auto}
  .tab-btns{display:flex;gap:8px;background:#fff;border:1px solid var(--linea);border-radius:99px;padding:6px;box-shadow:var(--sombra);width:fit-content;margin:0 auto 22px;flex-wrap:wrap;justify-content:center}
  .tab-btn{border:none;background:none;font-family:var(--head);font-weight:700;font-size:13.5px;color:var(--gris);padding:11px 20px;border-radius:99px;display:flex;align-items:center;gap:7px}
  .tab-btn.activo{background:var(--grad);color:#fff;box-shadow:0 6px 16px rgba(224,24,108,.3)}
  .tab-panel{display:none;background:#fff;border:1px solid var(--linea);border-radius:22px;box-shadow:var(--sombra);padding:clamp(22px,4vw,36px);animation:fadeIn .3s ease}
  .tab-panel.activo{display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:start}
  @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
  @media (prefers-reduced-motion:reduce){.tab-panel{animation:none}}
  .tab-panel .t-ico{width:60px;height:60px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:28px;flex:none}
  .tab-panel h3{font-size:19px;font-weight:800;margin-bottom:8px}
  .tab-panel p{font-size:14.5px;color:var(--gris);margin-bottom:12px}
  .t-lista{display:grid;gap:8px}
  .t-lista span{display:flex;gap:9px;font-size:13.5px;align-items:flex-start}
  .t-lista i{font-style:normal;color:var(--rosa);font-weight:800}

  /* ---------- diana (cita + modal) ---------- */
  .quote-card{background:#fff;border:1px solid var(--linea);border-radius:26px;box-shadow:var(--sombra);display:grid;grid-template-columns:.72fr 1.28fr;overflow:hidden;max-width:920px;margin:0 auto}
  .quote-foto{background:linear-gradient(160deg,var(--rosa-soft),var(--violeta-soft));display:flex;align-items:flex-end;justify-content:center;padding:22px 22px 0}
  .quote-foto img{width:min(260px,100%);filter:drop-shadow(0 16px 30px rgba(16,18,38,.16))}
  .quote-body{padding:clamp(24px,3.5vw,40px)}
  blockquote{font-family:var(--head);font-weight:700;font-size:clamp(18px,2.2vw,22px);line-height:1.42}
  blockquote .grad{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
  .firma{margin:16px 0 20px;display:flex;align-items:center;gap:11px}
  .firma .f-dot{width:9px;height:9px;border-radius:50%;background:var(--rosa)}
  .firma b{font-family:var(--head);display:block;font-size:14.5px}
  .firma span{font-size:12px;color:var(--gris)}

  /* modal */
  .modal-bg{position:fixed;inset:0;background:rgba(16,18,38,.55);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:90;padding:18px}
  .modal-bg.abierto{display:flex}
  .modal{background:#fff;border-radius:24px;max-width:520px;width:100%;max-height:86vh;overflow-y:auto;padding:clamp(24px,4vw,34px);box-shadow:0 40px 90px rgba(0,0,0,.35);position:relative;animation:fadeIn .25s ease}
  .modal-x{position:absolute;right:16px;top:16px;width:36px;height:36px;border-radius:50%;border:none;background:var(--fondo-2);font-size:16px;color:var(--gris)}
  .modal-x:hover{background:var(--rosa-soft);color:var(--rosa)}
  .modal h3{font-size:20px;font-weight:800;margin-bottom:14px}
  .check{display:flex;gap:10px;font-size:14px;color:var(--gris);align-items:flex-start;margin-bottom:11px}
  .check i{font-style:normal;width:22px;height:22px;border-radius:8px;background:#E9F9EF;color:var(--verde);font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex:none;margin-top:1px}

  /* ---------- registro ---------- */
  .registro{position:relative;overflow:hidden}
  .registro::before{content:"";position:absolute;inset:0;background:
    radial-gradient(560px 320px at 10% 0%, rgba(224,24,108,.07), transparent 60%),
    radial-gradient(560px 320px at 92% 100%, rgba(124,58,237,.07), transparent 60%)}
  .reg-grid{display:grid;grid-template-columns:.95fr 1.05fr;gap:clamp(26px,5vw,56px);align-items:center;position:relative;z-index:1}
  .paso{display:flex;gap:13px;align-items:flex-start;background:#fff;border:1px solid var(--linea);border-radius:15px;padding:13px 15px;box-shadow:var(--sombra);margin-top:11px}
  .paso .p-n{width:30px;height:30px;border-radius:10px;background:var(--grad);color:#fff;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;flex:none}
  .paso b{display:block;font-size:14px;font-family:var(--head)}
  .paso span{font-size:12.5px;color:var(--gris)}
  .red-nota{margin-top:16px;font-size:12.5px;color:var(--gris);background:var(--violeta-soft);border:1px solid #E4D9FB;border-radius:12px;padding:11px 14px}
  .red-nota b{color:var(--violeta)}

  .form-card{background:#fff;border:1px solid var(--linea);border-radius:24px;padding:clamp(20px,3vw,30px);box-shadow:var(--sombra-lg)}
  .form-card h3{font-size:20px;font-weight:800;margin-bottom:3px}
  .form-card .sub{font-size:13px;color:var(--gris);margin-bottom:16px}
  .ref-chip{display:flex;align-items:center;gap:10px;background:var(--rosa-soft);border:1px solid #F7C6DC;border-radius:12px;padding:10px 13px;font-size:13px;margin-bottom:14px}
  .ref-chip .avatar{width:32px;height:32px;border-radius:50%;background:var(--grad);color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;flex:none}
  .ref-chip b{color:var(--rosa-osc)}
  .campo{margin-bottom:12px}
  .campo label{display:block;font-size:11.5px;font-weight:700;color:var(--gris);margin-bottom:5px}
  .campo input,.campo select{width:100%;border:1.5px solid var(--linea);border-radius:12px;padding:12px 13px;font-family:var(--body);font-size:16px;background:var(--fondo-2);color:var(--ink)}
  .campo input:focus,.campo select:focus{border-color:var(--rosa);outline:none;background:#fff;box-shadow:0 0 0 4px rgba(224,24,108,.10)}
  .campos-2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
  .consent{display:flex;gap:10px;align-items:flex-start;background:var(--fondo-2);border:1px solid var(--linea);border-radius:12px;padding:11px 13px;font-size:11.5px;line-height:1.5;color:var(--gris);margin:2px 0 14px}
  .consent input{width:17px;height:17px;margin-top:2px;flex:none;accent-color:var(--rosa)}
  .consent a{color:var(--rosa);font-weight:700}
  .campo.has-error label{color:#B4123F}
  .politica p{font-size:13.5px;color:var(--gris);margin-bottom:10px;line-height:1.55}
  .politica b{color:var(--ink)}
  .btn[disabled]{opacity:.75;cursor:progress;transform:none}
  .giro{width:16px;height:16px;border-radius:50%;border:2.5px solid rgba(255,255,255,.45);border-top-color:#fff;animation:gira .8s linear infinite}
  @keyframes gira{to{transform:rotate(360deg)}}
  .error-msg{display:none;background:#FDECEF;border:1px solid #F7C4CE;color:#B4123F;font-size:12.5px;font-weight:600;border-radius:11px;padding:10px 13px;margin-bottom:12px}
  .form-ok{display:none;text-align:center;padding:26px 8px}
  .form-ok .ok-ico{width:64px;height:64px;border-radius:20px;background:#E9F9EF;color:var(--verde);font-size:29px;display:flex;align-items:center;justify-content:center;margin:0 auto 13px}
  .form-ok h4{font-size:20px;margin-bottom:7px}
  .form-ok p{font-size:14px;color:var(--gris)}
  .lock-note{font-size:11px;color:var(--gris);text-align:center;margin-top:11px}
  .hp{position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden}
  /* ubicación opcional */
  .consent.geo{background:var(--violeta-soft);border-color:#E0D4FB;margin-bottom:6px}
  .consent.geo input{accent-color:var(--violeta)}
  .geo-nota{font-size:11.5px;color:var(--gris);min-height:14px;margin:0 2px 10px}
  /* Súper Promotores: enlace personal tras registrarse */
  .promo-box{margin-top:20px;text-align:left;background:var(--rosa-soft);border:1px solid #F7C6DC;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:10px}
  .promo-box[hidden]{display:none}
  .promo-tit{font-family:var(--head);font-size:16px;color:var(--rosa-osc)}
  .promo-sub,.promo-nota{font-size:12.5px;color:var(--gris);line-height:1.5}
  .promo-link{background:#fff;border:1px dashed #F0A9C8;border-radius:10px;padding:9px 11px;font-size:12.5px;font-weight:600;word-break:break-all;color:var(--ink)}
  .promo-btns{display:flex;gap:8px;flex-wrap:wrap}
  .promo-btns .btn{flex:1;min-width:150px;padding:11px 14px;font-size:13.5px}
  .btn-wa{background:#1FAF5A;color:#fff}
  .btn-wa:hover{background:#128C46}
  .promo-qr{align-self:center;background:#fff;border-radius:12px;padding:8px;width:170px;height:170px}
  .promo-qr:empty{display:none}
  .promo-qr svg{width:100%;height:100%;display:block}
  .promo-panel{width:100%}
  .acceso-box{background:#fff;border:1px solid #E4D9FB;border-radius:12px;padding:11px 13px;display:flex;flex-direction:column;gap:3px;font-size:12.5px;color:var(--gris)}
  .acceso-box[hidden]{display:none}
  .acceso-box b{color:var(--ink)}
  .acceso-box > b{color:var(--violeta);font-size:13.5px}
  .clave-wrap{position:relative}
  .clave-wrap input{padding-right:52px}
  .ver-clave{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:18px;padding:6px 8px}

  /* footer */
  footer{background:var(--fondo-2);border-top:1px solid var(--linea);padding:38px 0 24px;color:var(--gris)}
  .foot-grid{display:flex;justify-content:space-between;gap:22px;flex-wrap:wrap}
  .foot-logo b{font-family:var(--head);color:var(--ink);font-size:16px;display:block}
  .foot-logo span{font-size:10.5px;letter-spacing:1.8px;text-transform:uppercase;color:var(--rosa);font-weight:700}
  .foot-col{font-size:13px;line-height:2.1}
  .foot-col b{color:var(--ink);font-family:var(--head)}
  .foot-col a{text-decoration:none}
  .foot-col a:hover{color:var(--rosa)}
  .foot-legal{border-top:1px solid var(--linea);margin-top:24px;padding-top:15px;font-size:11px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}

  .rv{opacity:0;transform:translateY(18px);transition:opacity .5s ease, transform .5s ease}
  .rv.vis{opacity:1;transform:none}
  @media (prefers-reduced-motion:reduce){.rv{opacity:1;transform:none;transition:none}}

  @media(max-width:920px){
    .hero-grid{grid-template-columns:1fr}
    .video-card{justify-self:center;max-width:400px;aspect-ratio:4/5;max-height:480px}
    .hero .pill{margin-top:4px}
    .quote-card,.reg-grid{grid-template-columns:1fr}
    .quote-foto{padding-top:18px}
    .nav-links{display:none}
    .campos-2{grid-template-columns:1fr}
    .tab-panel.activo{grid-template-columns:1fr}
    .ac-panel-in{padding-left:20px}
  }
</style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a class="logo" href="#inicio" aria-label="Inicio">
      <span class="logo-mark">D</span>
      <span><b>Diana Lucía Montes</b><span>Alcaldía de Garzón</span></span>
    </a>
    <ul class="nav-links">
      <li><a href="#retos">Retos</a></li>
      <li><a href="#propuestas">Propuestas</a></li>
      <li><a href="#diana">¿Quién es Diana?</a></li>
    </ul>
    <a class="btn btn-rosa btn-sm" href="#sumate">Quiero sumarme</a>
  </div>
</header>

<main id="inicio">

<!-- ==================== HERO ==================== -->
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="pill"><i></i> Garzón, Huila · Elecciones 2027</span>
      <h1>Garzón está listo para su <span class="grad">primera alcaldesa.</span></h1>
      <p class="lead">Un municipio donde el campo prospere, las familias estén cuidadas y los jóvenes no tengan que irse. Construyámoslo juntos.</p>
      <div class="hero-ctas">
        <a class="btn btn-rosa" href="#sumate">Sumarme a la red ➜</a>
        <a class="btn btn-soft" href="#retos">Conocer más</a>
      </div>
    </div>
    <div class="video-card rv">
      <span class="video-tag"><i></i> Conoce a Diana</span>
      <video id="heroVideo" autoplay muted loop playsinline preload="metadata"
             poster="img/diana-montes.png"
             aria-label="Video de presentación de Diana Lucía Montes">
        <source src="https://dianamontes.com/video/diana.mp4" type="video/mp4">
      </video>
      <button class="btn-sonido" id="btnSonido" type="button" aria-label="Activar sonido" title="Activar sonido">🔇</button>
    </div>
  </div>
</section>

<!-- ==================== RETOS (ACORDEÓN) ==================== -->
<section id="retos" class="sec-alt">
  <div class="wrap">
    <div class="sec-head rv">
      <span class="kicker">Hablemos claro</span>
      <h2>Los retos que Garzón no puede seguir aplazando</h2>
      <p>Toca cada reto para ver cómo lo vamos a enfrentar.</p>
    </div>
    <div class="acordeon rv" id="acordeon">
      <div class="ac-item">
        <button class="ac-btn" aria-expanded="false">
          <span class="a-ico" style="background:#E3F2FD">💧</span>
          <h3>Agua que llega… cuando el clima lo permite</h3>
          <span class="chev">▾</span>
        </button>
        <div class="ac-panel"><div class="ac-panel-in">
          <p class="problema">Cada temporada de lluvias golpea los acueductos rurales: una sola creciente puede dejar sin agua a más de mil familias de nuestros centros poblados. Y cuando llega el verano, el riesgo es la escasez.</p>
          <p class="respuesta"><b>Nuestra respuesta:</b> acueductos rurales protegidos y optimizados, plan serio de gestión del riesgo y cuidado de las quebradas que nos dan el agua.</p>
        </div></div>
      </div>
      <div class="ac-item">
        <button class="ac-btn" aria-expanded="false">
          <span class="a-ico" style="background:var(--rosa-soft)">🛣</span>
          <h3>Vías terciarias que se llevan la cosecha</h3>
          <span class="chev">▾</span>
        </button>
        <div class="ac-panel"><div class="ac-panel-in">
          <p class="problema">Las lluvias deterioran año tras año los caminos veredales. Cuando la vía se cae, el café se queda en la finca y el campesino pierde su trabajo de meses.</p>
          <p class="respuesta"><b>Nuestra respuesta:</b> plan de choque de placa huellas y mantenimiento permanente con las comunidades, priorizando las rutas de la cosecha.</p>
        </div></div>
      </div>
      <div class="ac-item">
        <button class="ac-btn" aria-expanded="false">
          <span class="a-ico" style="background:#FFF3E0">🛡</span>
          <h3>Tranquilidad en el campo y en el barrio</h3>
          <span class="chev">▾</span>
        </button>
        <div class="ac-panel"><div class="ac-panel-in">
          <p class="problema">Mientras el casco urbano pide frenar los hurtos, en la zona rural la extorsión asfixia a cafeteros y comerciantes. Nadie debería pagar por trabajar en paz.</p>
          <p class="respuesta"><b>Nuestra respuesta:</b> trabajo articulado con la fuerza pública, frentes de seguridad comunitarios, cámaras en puntos críticos y acompañamiento a quien denuncia.</p>
        </div></div>
      </div>
      <div class="ac-item">
        <button class="ac-btn" aria-expanded="false">
          <span class="a-ico" style="background:#E9F9EF">🎓</span>
          <h3>Jóvenes que se van porque aquí no hay campo</h3>
          <span class="chev">▾</span>
        </button>
        <div class="ac-panel"><div class="ac-panel-in">
          <p class="problema">Nuestros muchachos terminan el colegio y la única salida que ven es Neiva o Bogotá. Garzón forma talento que se va a construir el futuro de otras ciudades.</p>
          <p class="respuesta"><b>Nuestra respuesta:</b> formación técnica en el municipio, primer empleo con el comercio local y apoyo a emprendimientos ligados al café y al turismo.</p>
        </div></div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== PROPUESTAS (TABS) ==================== -->
<section id="propuestas">
  <div class="wrap">
    <div class="sec-head rv">
      <span class="kicker">Propuestas</span>
      <h2>Tres compromisos, un solo Garzón</h2>
    </div>
    <div class="tabs rv">
      <div class="tab-btns" role="tablist">
        <button class="tab-btn activo" role="tab" data-tab="t1">☕ Campo</button>
        <button class="tab-btn" role="tab" data-tab="t2">👩‍👧‍👦 Familias</button>
        <button class="tab-btn" role="tab" data-tab="t3">🎓 Jóvenes</button>
      </div>
      <div class="tab-panel activo" id="t1">
        <span class="t-ico" style="background:var(--rosa-soft)">☕</span>
        <div>
          <h3>Campo que prospera</h3>
          <p>El café y el campo son el corazón de Garzón. Que la cosecha valga lo que merece.</p>
          <div class="t-lista">
            <span><i>✓</i> Vías terciarias dignas para sacar la cosecha</span>
            <span><i>✓</i> Asistencia técnica directa al caficultor</span>
            <span><i>✓</i> Compras públicas locales: primero lo nuestro</span>
          </div>
        </div>
      </div>
      <div class="tab-panel" id="t2">
        <span class="t-ico" style="background:var(--violeta-soft)">👩‍👧‍👦</span>
        <div>
          <h3>Familias cuidadas</h3>
          <p>Gobernar es cuidar: salud cercana y respaldo a quienes sostienen el hogar.</p>
          <div class="t-lista">
            <span><i>✓</i> Brigadas de salud permanentes en la zona rural</span>
            <span><i>✓</i> Prioridad para madres cabeza de hogar y adultos mayores</span>
            <span><i>✓</i> Cero tolerancia con la violencia hacia la mujer</span>
          </div>
        </div>
      </div>
      <div class="tab-panel" id="t3">
        <span class="t-ico" style="background:#E9F9EF">🎓</span>
        <div>
          <h3>Jóvenes con futuro</h3>
          <p>Que ningún joven tenga que irse de Garzón para salir adelante.</p>
          <div class="t-lista">
            <span><i>✓</i> Formación técnica en el municipio</span>
            <span><i>✓</i> Programa de primer empleo con el comercio local</span>
            <span><i>✓</i> Deporte, cultura y emprendimiento en cada comuna</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== QUIÉN ES DIANA ==================== -->
<section id="diana" class="sec-alt">
  <div class="wrap">
    <div class="quote-card rv">
      <div class="quote-foto"><img src="img/diana-montes.png" alt="Diana Lucía Montes" loading="lazy"></div>
      <div class="quote-body">
        <span class="kicker">¿Quién es Diana?</span>
        <blockquote>“De mi madre aprendí que <span class="grad">gobernar es cuidar</span>: con carácter, con honestidad y sin dejar a nadie atrás.”</blockquote>
        <div class="firma">
          <span class="f-dot"></span>
          <span><b>Diana Lucía Montes</b><span>Candidata a la Alcaldía de Garzón</span></span>
        </div>
        <button class="btn btn-soft btn-sm" id="btnBio" type="button">Conocer su historia ➜</button>
      </div>
    </div>
  </div>
</section>

<!-- modal bio -->
<div class="modal-bg" id="modalBio" role="dialog" aria-modal="true" aria-labelledby="modalBioTitulo">
  <div class="modal">
    <button class="modal-x" id="modalBioX" aria-label="Cerrar">✕</button>
    <h3 id="modalBioTitulo">La historia de Diana</h3>
    <div class="check"><i>✓</i><span>Garzoneña de nacimiento, criada entre el esfuerzo del campo y el valor de la palabra.</span></div>
    <div class="check"><i>✓</i><span>Profesional con trayectoria de servicio a la comunidad del centro del Huila.</span></div>
    <div class="check"><i>✓</i><span>Ha trabajado de la mano con juntas de acción comunal, madres comunitarias y asociaciones cafeteras.</span></div>
    <div class="check"><i>✓</i><span>Cree en la política de puertas abiertas: gobernar con la gente, no a espaldas de ella.</span></div>
    <a class="btn btn-rosa" href="#sumate" id="modalBioCta" style="width:100%;margin-top:10px">Quiero acompañarla</a>
  </div>
</div>

<!-- modal política de datos (Ley 1581 de 2012) -->
<div class="modal-bg" id="modalPolitica" role="dialog" aria-modal="true" aria-labelledby="modalPoliticaTitulo">
  <div class="modal">
    <button class="modal-x" type="button" data-cerrar aria-label="Cerrar">✕</button>
    <h3 id="modalPoliticaTitulo">Tratamiento de datos personales</h3>
    <div class="politica">
      <p><b>Responsable:</b> Campaña de Diana Lucía Montes a la Alcaldía de Garzón (Huila).</p>
      <p><b>Datos que pedimos:</b> nombre, documento, celular, barrio o vereda, ocupación, fecha de nacimiento, género y, solo si lo autorizas, tu ubicación aproximada.</p>
      <p><b>Para qué los usamos:</b> contactarte e informarte sobre la campaña y sus actividades, enviarte saludos en fechas especiales por WhatsApp y organizar el trabajo territorial. No vendemos ni cedemos tus datos.</p>
      <p><b>Tu red:</b> quien te invitó verá tu nombre en su panel y, si es Promotor o más, podrá escribirte por WhatsApp para actividades de la campaña. Tú verás igual a las personas que invites.</p>
      <p><b>Tus derechos:</b> conocer, actualizar, rectificar y pedir que se eliminen tus datos, y revocar esta autorización en cualquier momento. Para dejar de recibir mensajes responde <b>SALIR</b> a cualquier mensaje de WhatsApp de la campaña.</p>
      <p><b>Seguridad:</b> los datos viajan cifrados y solo los consulta el equipo autorizado de la campaña.</p>
    </div>
    <button class="btn btn-rosa" type="button" data-cerrar style="width:100%;margin-top:14px">Entendido</button>
  </div>
</div>

<!-- ==================== REGISTRO ==================== -->
<section id="sumate" class="registro">
  <div class="wrap reg-grid">
    <div class="rv">
      <span class="kicker">Súmate a la red</span>
      <h2>Tu nombre puede cambiar la historia de Garzón</h2>
      <div>
        <div class="paso"><span class="p-n">1</span><div><b>Déjanos tus datos</b><span>Menos de un minuto, desde tu celular.</span></div></div>
        <div class="paso"><span class="p-n">2</span><div><b>Recibe la bienvenida de Diana</b><span>Te llegará un mensaje por WhatsApp.</span></div></div>
        <div class="paso"><span class="p-n">3</span><div><b>Acompáñanos cerca de ti</b><span>Actividades en tu vereda o barrio.</span></div></div>
      </div>
      <div class="red-nota">💜 <b>¿Cómo funciona la red?</b> Al registrarte entras a la red directa de la candidata. Si alguien te compartió su enlace personal, quedarás en su red. Y al terminar recibes <b>tu propio enlace</b> para invitar a familiares y amigos: ¡conviértete en Súper Promotor! Los líderes ingresan solo por invitación de la campaña.</div>
    </div>

    <div class="form-card rv">
      <form id="formRegistro" novalidate data-validar="manual">
        <h3>Registro de simpatizantes</h3>
        <p class="sub">Entras a la red oficial de la campaña.</p>

        <?php if ($refLider): ?>
        <div class="ref-chip">
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($refLider, 0, 1)) . mb_strtoupper(mb_substr(strstr($refLider, ' ') ?: ' ', 1, 1))) ?></span>
          <span>Te invita <b><?= e($refLider) ?></b> — quedarás en su equipo 💪</span>
        </div>
        <?php endif; ?>

        <div class="campo">
          <label for="f-nombre">Nombre completo</label>
          <input id="f-nombre" name="nombre" data-tipo="nombre" autocomplete="name" autocapitalize="words" maxlength="120" placeholder="Ej: María Fernanda Ortiz" required data-msg="Escribe tu nombre completo.">
        </div>
        <div class="campos-2">
          <div class="campo">
            <label for="f-doc">Documento de identidad</label>
            <input id="f-doc" name="documento" data-tipo="documento" inputmode="numeric" autocomplete="off" placeholder="Solo números, sin puntos" required data-msg="Escribe tu número de documento.">
          </div>
          <div class="campo">
            <label for="f-cel">Celular (WhatsApp)</label>
            <input id="f-cel" type="tel" name="telefono" data-tipo="celular" inputmode="tel" autocomplete="tel-national" placeholder="3XX XXX XXXX" required data-msg="Escribe tu celular de WhatsApp.">
          </div>
        </div>
        <div class="campos-2">
          <div class="campo">
            <label for="f-zona">Barrio o vereda</label>
            <select id="f-zona" name="zona_id" required data-msg="Cuéntanos tu barrio o vereda.">
              <option value="">Selecciona…</option>
              <?php foreach ($zonas as $z): ?>
              <option value="<?= (int)$z['id'] ?>"><?= e($z['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label for="f-prof">¿A qué te dedicas?</label>
            <select id="f-prof" name="profesion_id" required data-msg="Cuéntanos a qué te dedicas.">
              <option value="">Selecciona…</option>
              <?php foreach ($profesiones as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="campos-2">
          <div class="campo">
            <label for="f-cumple">Fecha de nacimiento <span style="font-weight:500;color:#9AA0AF">(te saludamos en tu día 🎂)</span></label>
            <input id="f-cumple" type="date" name="fecha_nacimiento" data-tipo="nacimiento" data-edad-min="<?= EDAD_MINIMA ?>" min="<?= $fechaMinima ?>" max="<?= $fechaMaxima ?>" required data-msg="Escribe tu fecha de nacimiento.">
          </div>
          <?php if ($pideGenero): ?>
          <div class="campo">
            <label for="f-genero">Género</label>
            <select id="f-genero" name="genero" required data-msg="Selecciona tu género.">
              <option value="">Selecciona…</option>
              <?php foreach (GENEROS as $valor => $etiqueta): ?>
              <option value="<?= $valor ?>"><?= e($etiqueta) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($pideClave): ?>
        <!-- acceso al panel personal: usuario = documento, clave = esta -->
        <div class="campo">
          <label for="f-clave">Crea una clave para tu panel <span style="font-weight:500;color:#9AA0AF">(tu usuario será tu documento)</span></label>
          <div class="clave-wrap">
            <input id="f-clave" type="password" name="clave" data-tipo="clave" minlength="<?= RED_CLAVE_MIN ?>" maxlength="72"
                   autocomplete="new-password" placeholder="Mínimo <?= RED_CLAVE_MIN ?> caracteres" required data-msg="Crea una clave de al menos <?= RED_CLAVE_MIN ?> caracteres.">
            <button type="button" class="ver-clave" aria-label="Mostrar clave">👁</button>
          </div>
        </div>
        <?php endif; ?>

        <!-- ubicación aproximada: opcional, solo con permiso explícito -->
        <label class="consent geo">
          <input type="checkbox" name="ubicacion" value="1" id="f-geo">
          <span>📍 <b>Compartir mi ubicación aproximada</b> (opcional) para invitarme a actividades cerca de mí. Se guarda redondeada a unos 100 m.</span>
        </label>
        <p class="geo-nota" id="geoNota"></p>
        <input type="hidden" name="lat" id="f-lat">
        <input type="hidden" name="lng" id="f-lng">

        <!-- código de invitación del líder + honeypot anti-bots -->
        <input type="hidden" name="ref" value="<?= e($refCodigo) ?>">
        <div class="hp" aria-hidden="true"><label>Tu web<input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>

        <label class="consent">
          <input type="checkbox" name="consentimiento" value="1" id="f-consent" required data-msg="Para registrarte necesitamos tu autorización de datos (Ley 1581).">
          <span>Autorizo el tratamiento de mis datos personales a la campaña de Diana Lucía Montes para fines de contacto e información política, conforme a la <b>Ley 1581 de 2012</b>. <a href="#politica" id="lnkPolitica">Ver política de datos</a>.</span>
        </label>

        <div class="error-msg" id="errBox" role="alert" tabindex="-1"></div>
        <button class="btn btn-rosa" style="width:100%" type="submit" id="btnEnviar">Sí, quiero sumarme</button>
        <p class="lock-note">🔒 Tus datos viajan cifrados y solo los usa la campaña.</p>
      </form>

      <div class="form-ok" id="formOk">
        <div class="ok-ico">✓</div>
        <h4>¡Bienvenida/o a la red!</h4>
        <p id="okTexto"></p>

        <!-- Súper Promotores: aparece solo si el servidor entregó el enlace personal -->
        <div class="promo-box" id="promoBox" hidden>
          <b class="promo-tit">🚀 Ahora multiplica tu voz</b>
          <span class="promo-sub">Este es <b>tu enlace personal</b>. Cada familiar o amigo que se registre con él suma a tu red.</span>
          <div class="promo-link" id="promoLink"></div>
          <div class="promo-btns">
            <a class="btn btn-wa" id="promoWa" target="_blank" rel="noopener">📲 Invitar por WhatsApp</a>
            <button type="button" class="btn btn-soft" id="promoCopiar">🔗 Copiar enlace</button>
          </div>
          <div class="promo-qr" id="promoQr"></div>
          <div class="acceso-box" id="accesoBox" hidden>
            <b>🔑 Tu acceso a tu panel</b>
            <span>Entra cuando quieras en <b id="accesoUrl"></b></span>
            <span>Usuario: <b id="accesoUsuario"></b> (tu documento) · Clave: la que acabas de crear</span>
          </div>
          <a class="btn btn-rosa promo-panel" id="promoPanel">⭐ Entrar a mi panel</a>
          <span class="promo-nota">En tu panel verás tu red, tus puntos, tus tareas y tu puesto en el ranking.</span>
        </div>
      </div>
    </div>
  </div>
</section>
</main>

<!-- ==================== FOOTER ==================== -->
<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-logo">
        <b>Diana Lucía Montes</b>
        <span>Alcaldía de Garzón 2027</span>
      </div>
      <div class="foot-col">
        <b>Campaña</b><br>
        <a href="#retos">Retos</a> · <a href="#propuestas">Propuestas</a> · <a href="#sumate">Súmate</a>
      </div>
      <div class="foot-col">
        <b>Contacto</b><br>
        Garzón, Huila · @dianamontesgarzon
      </div>
    </div>
    <div class="foot-legal">
      <span>© <?= date('Y') ?> Campaña Diana Lucía Montes · Garzón, Huila</span>
      <span>Política de tratamiento de datos · Ley 1581 de 2012</span>
    </div>
  </div>
</footer>

<script src="assets/js/validar.js?v=<?= @filemtime(__DIR__ . '/assets/js/validar.js') ?>"></script>
<script>
/* ---------- video: autoplay silenciado + botón de sonido ---------- */
const video = document.getElementById('heroVideo');
const btnSonido = document.getElementById('btnSonido');
btnSonido.addEventListener('click', () => {
  video.muted = !video.muted;
  if (!video.muted) video.play();
  btnSonido.textContent = video.muted ? '🔇' : '🔊';
  btnSonido.setAttribute('aria-label', video.muted ? 'Activar sonido' : 'Silenciar');
});
new IntersectionObserver(es => es.forEach(e => {
  if (e.isIntersecting) video.play().catch(()=>{}); else video.pause();
}), { threshold:.2 }).observe(video);

/* ---------- acordeón (solo un panel abierto a la vez) ---------- */
document.querySelectorAll('.ac-item').forEach(item => {
  const btn = item.querySelector('.ac-btn');
  const panel = item.querySelector('.ac-panel');
  btn.addEventListener('click', () => {
    const abierto = item.classList.contains('abierto');
    document.querySelectorAll('.ac-item.abierto').forEach(o => {
      o.classList.remove('abierto');
      o.querySelector('.ac-panel').style.maxHeight = null;
      o.querySelector('.ac-btn').setAttribute('aria-expanded','false');
    });
    if (!abierto) {
      item.classList.add('abierto');
      panel.style.maxHeight = panel.scrollHeight + 'px';
      btn.setAttribute('aria-expanded','true');
    }
  });
});

/* ---------- tabs ---------- */
document.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('.tab-btn').forEach(x => x.classList.remove('activo'));
  document.querySelectorAll('.tab-panel').forEach(x => x.classList.remove('activo'));
  b.classList.add('activo');
  document.getElementById(b.dataset.tab).classList.add('activo');
}));

/* ---------- modal bio ---------- */
const modalBio = document.getElementById('modalBio');
document.getElementById('btnBio').addEventListener('click', () => modalBio.classList.add('abierto'));
document.getElementById('modalBioX').addEventListener('click', () => modalBio.classList.remove('abierto'));
document.getElementById('modalBioCta').addEventListener('click', () => modalBio.classList.remove('abierto'));
modalBio.addEventListener('click', e => { if (e.target === modalBio) modalBio.classList.remove('abierto'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') modalBio.classList.remove('abierto'); });

/* ---------- reveal ---------- */
const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting){ e.target.classList.add('vis'); io.unobserve(e.target);} }), { threshold:.12 });
document.querySelectorAll('.rv').forEach(el => io.observe(el));

/* ---------- ubicación aproximada (opcional, con permiso del navegador) ---------- */
const geo = document.getElementById('f-geo');
const geoNota = document.getElementById('geoNota');
geo.addEventListener('change', () => {
  document.getElementById('f-lat').value = '';
  document.getElementById('f-lng').value = '';
  geoNota.textContent = '';
  if (!geo.checked) return;
  if (!navigator.geolocation) { geo.checked = false; geoNota.textContent = 'Tu navegador no permite compartir la ubicación.'; return; }
  geoNota.textContent = 'Obteniendo tu ubicación…';
  navigator.geolocation.getCurrentPosition(p => {
    document.getElementById('f-lat').value = p.coords.latitude.toFixed(3);
    document.getElementById('f-lng').value = p.coords.longitude.toFixed(3);
    geoNota.textContent = '✓ Ubicación aproximada lista.';
  }, () => {
    geo.checked = false;
    geoNota.textContent = 'No se compartió la ubicación. Puedes registrarte igual.';
  }, { enableHighAccuracy:false, timeout:10000, maximumAge:600000 });
});

/* ---------- Súper Promotores: enlace personal, QR y panel ---------- */
function mostrarPromotor(p) {
  const base   = location.origin + location.pathname.replace(/[^/]*$/, '');
  const enlace = base + '?ref=' + encodeURIComponent(p.codigo) + '#sumate';
  const panel  = base + 'mi/?t=' + encodeURIComponent(p.token);
  if (p.usuario) {
    document.getElementById('accesoUsuario').textContent = p.usuario;
    document.getElementById('accesoUrl').textContent = (location.host + location.pathname.replace(/[^/]*$/, '') + 'mi').replace(/^www\./, '');
    document.getElementById('accesoBox').hidden = false;
  }

  document.getElementById('promoLink').textContent = enlace;
  document.getElementById('promoWa').href = 'https://wa.me/?text=' + encodeURIComponent(
    '¡Hola! 👋 Me sumé a la campaña de Diana Lucía Montes a la Alcaldía de Garzón. Súmate tú también, toma menos de un minuto: ' + enlace);
  document.getElementById('promoPanel').href = panel;
  document.getElementById('promoCopiar').onclick = function () {
    navigator.clipboard.writeText(enlace).then(() => {
      this.textContent = '¡Copiado!';
      setTimeout(() => this.textContent = '🔗 Copiar enlace', 1600);
    });
  };
  document.getElementById('promoBox').hidden = false;

  // El QR se carga solo aquí, para no hacer más pesada la landing
  const s = document.createElement('script');
  s.src = 'assets/vendor/qrcode.js';
  s.onload = () => {
    const q = qrcode(0, 'M'); q.addData(enlace); q.make();
    document.getElementById('promoQr').innerHTML = q.createSvgTag(4, 2);
  };
  document.body.appendChild(s);
}

/* ---------- mostrar u ocultar la clave ---------- */
document.querySelectorAll('.ver-clave').forEach(b => b.addEventListener('click', () => {
  const i = b.parentNode.querySelector('input');
  i.type = i.type === 'password' ? 'text' : 'password';
  b.textContent = i.type === 'password' ? '👁' : '🙈';
  b.setAttribute('aria-label', i.type === 'password' ? 'Mostrar clave' : 'Ocultar clave');
}));

/* ---------- política de datos ---------- */
const modalPolitica = document.getElementById('modalPolitica');
document.getElementById('lnkPolitica').addEventListener('click', e => { e.preventDefault(); modalPolitica.classList.add('abierto'); });
modalPolitica.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', () => modalPolitica.classList.remove('abierto')));
modalPolitica.addEventListener('click', e => { if (e.target === modalPolitica) modalPolitica.classList.remove('abierto'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') modalPolitica.classList.remove('abierto'); });

/* ---------- envío real del formulario a registrar.php ---------- */
const formReg = document.getElementById('formRegistro');
const errBox  = document.getElementById('errBox');
const btnEnviar = document.getElementById('btnEnviar');
const TEXTO_BTN = btnEnviar.textContent;

function mostrarError(msg) {
  errBox.textContent = '⚠ ' + msg;
  errBox.style.display = 'block';
  errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

formReg.addEventListener('submit', async function (ev) {
  ev.preventDefault();
  errBox.style.display = 'none';
  if (btnEnviar.disabled) return;                 // evita doble envío
  if (!window.Validar) { mostrarError('La página no cargó completa. Recárgala e intenta de nuevo.'); return; }
  if (!Validar.formulario(this)) return;          // los errores quedan debajo de cada campo

  btnEnviar.disabled = true;
  btnEnviar.innerHTML = '<span class="giro" aria-hidden="true"></span> Enviando…';
  const control = new AbortController();
  const espera = setTimeout(() => control.abort(), 25000);
  try {
    const r = await fetch('registrar.php', { method: 'POST', body: new FormData(this), signal: control.signal,
                                             headers: { 'Accept': 'application/json' } });
    const crudo = await r.text();
    let data;
    try { data = JSON.parse(crudo); }
    catch (e) { throw new Error('respuesta'); }
    if (data.ok) {
      this.style.display = 'none';
      document.getElementById('okTexto').textContent = data.msg;
      const ok = document.getElementById('formOk');
      ok.style.display = 'block';
      if (data.promotor) mostrarPromotor(data.promotor);
      ok.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (!(data.campo && Validar.marcar(this, data.campo, data.msg))) {
      mostrarError(data.msg || 'No pudimos guardar tu registro. Intenta de nuevo.');
    }
  } catch (e) {
    mostrarError(e.name === 'AbortError'
      ? 'El servidor tardó demasiado en responder. Revisa tu conexión e intenta de nuevo.'
      : e.message === 'respuesta'
        ? 'Tuvimos un problema al guardar tu registro. Intenta de nuevo en unos minutos.'
        : 'No pudimos conectar con el servidor. Revisa tu conexión a internet e intenta de nuevo.');
  } finally {
    clearTimeout(espera);
    btnEnviar.disabled = false;
    btnEnviar.textContent = TEXTO_BTN;
  }
});
</script>
</body>
</html>
