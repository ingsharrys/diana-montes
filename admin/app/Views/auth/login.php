<div class="login-wrap">
  <div class="login-hero">
    <div class="eyebrow">Campaña · Alcaldía de Garzón 2027</div>
    <h1>Una campaña organizada vale por mil volantes.</h1>
    <div class="hero-chips">
      <span>🔒 Cifrado</span><span>⚖️ Ley 1581</span><span>👁 Auditoría</span>
    </div>
  </div>
  <div class="login-card">
    <div class="brand"><span class="brand-mark">G</span> Plataforma Electoral</div>
    <h2>Iniciar sesión</h2>
    <p class="sub">Acceso exclusivo para el equipo autorizado.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('auth/login') ?>" autocomplete="off">
      <?= \Core\Csrf::campo() ?>
      <label class="field"><span>Correo electrónico</span>
        <input type="email" name="email" required autofocus>
      </label>
      <label class="field"><span>Contraseña</span>
        <input type="password" name="password" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
    </form>
    <p class="login-foot">Tras <?= MAX_LOGIN_ATTEMPTS ?> intentos fallidos la cuenta se bloquea <?= LOCK_MINUTES ?> minutos.</p>
  </div>
</div>
