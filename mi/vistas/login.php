<?php defined('PORTAL') or exit; ?>
<div class="acceso">
  <section class="card">
    <div class="marca">
      <span class="logo">D</span>
      <h1>Entra a tu panel</h1>
      <p class="muted small">Tu red, tus tareas y tus puntos en la campaña de Diana.</p>
    </div>
    <?php if (!empty($error)): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(portal_url('login')) ?>" data-validar>
      <?= portal_campo_csrf() ?>
      <div class="campo">
        <label for="l-doc">Usuario: tu número de documento</label>
        <input id="l-doc" name="documento" data-tipo="documento" autocomplete="username" placeholder="Solo números" required
               value="<?= e(normalizar_documento((string)($_POST['documento'] ?? ''))) ?>" data-msg="Escribe tu número de documento.">
      </div>
      <div class="campo">
        <label for="l-clave">Clave</label>
        <div class="clave-wrap">
          <input id="l-clave" type="password" name="clave" autocomplete="current-password" required data-msg="Escribe tu clave.">
          <button type="button" class="ver-clave" aria-label="Mostrar clave">👁</button>
        </div>
      </div>
      <button class="btn btn-rosa btn-block" type="submit">Entrar</button>
    </form>
    <p class="pie">¿Primera vez o no recuerdas tu clave?<br>Abre el <b>enlace de tu panel</b> que recibiste al registrarte, o pídele a tu líder una clave temporal.</p>
  </section>
  <p class="pie">¿Aún no estás en la red? <a href="../#sumate">Regístrate aquí</a></p>
  <p class="pie"><a href="../privacidad/">Privacidad</a> · <a href="../terminos/">Condiciones</a> · <a href="../eliminar-datos/">Eliminar mis datos</a></p>
</div>
