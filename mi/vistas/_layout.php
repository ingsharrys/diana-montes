<?php defined('PORTAL') or exit;
$aviso = $_SESSION['aviso'] ?? null; unset($_SESSION['aviso']);
$conMenu = !empty($yo) && empty($debeCrearClave) && !in_array($vista, ['login', 'no_listo'], true);
$titulos = ['inicio' => 'Mi panel', 'red' => 'Mi red', 'tareas' => 'Mis tareas', 'convocar' => 'Convocar reunión',
            'asignar' => 'Asignar tarea', 'convocatoria' => 'Lo que organizo', 'perfil' => 'Mi perfil', 'clave' => 'Crea tu clave'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#C0105A">
<title><?= e($titulos[$vista] ?? 'Mi panel') ?> · Red de Diana Lucía Montes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="portal.css?v=<?= @filemtime(__DIR__ . '/../portal.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap">
    <span class="logo">D</span>
    <span class="t">
      <small>Red de Diana Lucía Montes</small>
      <b><?= !empty($yo) ? '¡Hola, ' . e($yo['primer_nombre']) . '!' : 'Mi panel' ?></b>
    </span>
    <?php if (!empty($yo)): ?><span class="chip-nivel"><?= $yo['nivel']['actual'][2] ?> <?= e($yo['nivel']['actual'][1]) ?></span><?php endif; ?>
  </div>
</header>

<main class="wrap sube">
  <?php if ($aviso): ?><div class="aviso <?= $aviso[0] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= e($aviso[1]) ?></div><?php endif; ?>
  <?php require __DIR__ . '/' . $vista . '.php'; ?>
</main>

<?php if ($conMenu): ?>
<nav class="abajo" aria-label="Menú">
  <div class="wrap">
    <?php foreach (['inicio' => ['🏠', 'Inicio'], 'red' => ['🤝', 'Mi red'], 'tareas' => ['✅', 'Tareas'], 'perfil' => ['👤', 'Perfil']] as $dest => [$ic, $txt]):
      $activo = $vista === $dest || ($dest === 'tareas' && in_array($vista, ['convocar', 'asignar', 'convocatoria'], true)); ?>
    <a href="<?= e(portal_url($dest === 'inicio' ? '' : $dest)) ?>" class="<?= $activo ? 'activo' : '' ?>" <?= $activo ? 'aria-current="page"' : '' ?>>
      <span class="i"><?= $ic ?></span><?= $txt ?>
      <?php if ($dest === 'tareas' && !empty($porHacer)): ?><span class="badge"><?= (int)$porHacer ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
<script src="../assets/js/validar.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/validar.js') ?>"></script>
<script>
// Mostrar u ocultar la clave
document.querySelectorAll('.ver-clave').forEach(b => b.addEventListener('click', () => {
  const i = b.parentNode.querySelector('input');
  i.type = i.type === 'password' ? 'text' : 'password';
  b.textContent = i.type === 'password' ? '👁' : '🙈';
  b.setAttribute('aria-label', i.type === 'password' ? 'Mostrar clave' : 'Ocultar clave');
}));
</script>
</body>
</html>
