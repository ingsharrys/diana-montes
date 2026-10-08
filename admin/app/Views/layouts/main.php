<?php
use Core\Auth;
$u    = Auth::usuario();
$ruta = ruta_actual();
$seccion = explode('/', $ruta)[0];

// Menú lateral: [ruta, ícono, etiqueta, roles (null = todos)]
$menu = [
    ['dashboard',           'inicio',    'Inicio',        null],
    ['simpatizantes',       'personas',  'Simpatizantes', null],
    ['simpatizantes/crear', 'registrar', 'Registrar',     null],
    ['simpatizantes/verificar', 'verificar', 'Verificar', null],
    ['tareas',              'tareas',    'Tareas',        ['direccion', 'coordinador', 'lider']],
    ['red',                 'red',       'Red',           null],
    ['mapa',                'mapa',      'Mapa',          null],
    ['whatsapp',            'whatsapp',  'WhatsApp',      ['direccion', 'coordinador']],
    ['usuarios',            'equipo',    'Equipo',        ['direccion']],
    ['catalogos',           'catalogos', 'Catálogos',     ['direccion']],
    ['datos',               'candado',   'Datos personales', ['direccion']],
];
// Pestañas del centro de mando (las tres vistas de inteligencia de la red)
$pestanas = ['dashboard' => 'Vista rápida', 'territorio' => 'Territorio', 'red' => 'Red de contactos', 'mapa' => 'Mapa'];
$enMando  = isset($pestanas[$seccion]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titulo ?? '') ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>
<div class="app-shell">
  <aside class="rail" aria-label="Menú principal">
    <a class="rail-logo" href="<?= url('dashboard') ?>" title="Campaña Diana Montes"><span class="brand-mark">DM</span></a>
    <nav>
      <?php foreach ($menu as [$destino, $ico, $etiqueta, $roles]):
        if ($roles && !Auth::tieneRol(...$roles)) continue;
        // "Simpatizantes" no se marca cuando se está en una de sus subpáginas del menú (Registrar, Verificar)
        $activo = $ruta === $destino || ($seccion === $destino && !in_array($ruta, ['simpatizantes/crear', 'simpatizantes/verificar'], true)); ?>
        <a class="rail-item <?= $activo ? 'activo' : '' ?>" href="<?= url($destino) ?>" <?= $activo ? 'aria-current="page"' : '' ?>>
          <?= icono($ico) ?><span><?= e($etiqueta) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <a class="rail-item rail-salir" href="<?= url('auth/logout') ?>"><?= icono('salir') ?><span>Salir</span></a>
  </aside>

  <main class="main">
    <header class="banda">
      <div class="banda-top">
        <div class="miga">
          <?php if ($enMando): ?>Centro de mando <span aria-hidden="true">›</span> <?= e($pestanas[$seccion]) ?>
          <?php else: ?><?= e($titulo ?? '') ?><?php endif; ?>
        </div>
        <div class="user-pill">
          <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'] ?? '?', 0, 2))) ?></span>
          <span class="who"><?= e($u['nombre'] ?? '') ?></span>
          <span class="role-badge"><?= e(mb_strtoupper($u['rol'] ?? '')) ?></span>
        </div>
      </div>
      <?php if ($enMando): ?>
      <nav class="tabs" aria-label="Vistas del centro de mando">
        <?php foreach ($pestanas as $destino => $etiqueta): ?>
          <a class="tab <?= $seccion === $destino ? 'activo' : '' ?>" href="<?= url($destino) ?>"><?= e($etiqueta) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </header>

    <div class="contenido">
      <?php if ($msg = \Core\Session::flash('ok')): ?>
        <div class="alert alert-ok">✔ <?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($msg = \Core\Session::flash('error')): ?>
        <div class="alert alert-error">⚠ <?= e($msg) ?></div>
      <?php endif; ?>

      <?= $contenido ?>
    </div>
  </main>
</div>

<!-- Globo de ayuda para las gráficas: cualquier elemento con data-tip lo muestra al pasar o enfocar -->
<div class="tip" id="tip" role="tooltip" hidden></div>
<script src="<?= asset('../assets/js/validar.js') ?>"></script>
<script>
(function () {
  const tip = document.getElementById('tip');
  function mostrar(el) {
    tip.textContent = el.dataset.tip; tip.hidden = false;
    const r = el.getBoundingClientRect(), t = tip.getBoundingClientRect();
    let x = r.left + r.width / 2 - t.width / 2, y = r.top - t.height - 8;
    if (y < 4) y = r.bottom + 8;
    tip.style.left = Math.max(4, Math.min(x, innerWidth - t.width - 4)) + 'px';
    tip.style.top = y + 'px';
  }
  const ocultar = () => { tip.hidden = true; };
  ['mouseover', 'focusin'].forEach(ev => document.addEventListener(ev, e => {
    const el = e.target.closest && e.target.closest('[data-tip]'); if (el) mostrar(el);
  }));
  ['mouseout', 'focusout'].forEach(ev => document.addEventListener(ev, e => {
    if (e.target.closest && e.target.closest('[data-tip]')) ocultar();
  }));
  addEventListener('scroll', ocultar, { passive: true });
})();
</script>
</body>
</html>
