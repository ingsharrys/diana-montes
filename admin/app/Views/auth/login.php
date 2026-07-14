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

    <?php if (\Core\Google::configurado()): ?>
      <a class="btn-google btn-block" href="<?= url('auth/google') ?>">
        <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        <span>Continuar con Google</span>
      </a>
      <p class="google-hint">Líderes y digitadores ingresan con su cuenta de Google.</p>
      <div class="divider"><span>o con contraseña</span></div>
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
