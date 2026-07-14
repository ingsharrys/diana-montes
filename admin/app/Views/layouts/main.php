<?php use Core\Auth; $u = Auth::usuario(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titulo ?? '') ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="brand-mark">G</span> Campaña Garzón</div>
    <nav>
      <a class="nav-item" href="<?= url('dashboard') ?>">📊 Panel</a>
      <a class="nav-item" href="<?= url('simpatizantes') ?>">🗂 Simpatizantes</a>
      <a class="nav-item" href="<?= url('simpatizantes/crear') ?>">＋ Nuevo registro</a>
      <?php if (Auth::tieneRol('direccion')): ?>
      <a class="nav-item" href="<?= url('usuarios') ?>">👥 Equipo</a>
      <a class="nav-item" href="<?= url('catalogos') ?>">🏘 Catálogos</a>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <span class="dot-ok"></span>Sesión segura<br>
      <a class="link-salir" href="<?= url('auth/logout') ?>">Cerrar sesión</a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1><?= e($titulo ?? '') ?></h1>
      <div class="user-pill">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'] ?? '?', 0, 2))) ?></span>
        <span class="who"><?= e($u['nombre'] ?? '') ?></span>
        <span class="role-badge"><?= e(mb_strtoupper($u['rol'] ?? '')) ?></span>
      </div>
    </div>

    <?php if ($msg = \Core\Session::flash('ok')): ?>
      <div class="alert alert-ok">✔ <?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = \Core\Session::flash('error')): ?>
      <div class="alert alert-error">⚠ <?= e($msg) ?></div>
    <?php endif; ?>

    <?= $contenido ?>
  </main>
</div>
</body>
</html>
