<?php defined('PORTAL') or exit; ?>
<section class="card">
  <h2>🔑 Crea tu clave</h2>
  <p class="muted small" style="margin-bottom:14px">
    <?= $yo['clave_hash'] ? 'Entraste con una clave temporal. Crea una nueva que solo tú conozcas.' : 'Así podrás entrar a tu panel desde cualquier celular.' ?>
    Tu <b>usuario</b> es tu número de documento: <b><?= e($yo['documento']) ?></b>.
  </p>
  <form method="post" action="<?= e(portal_url('clave')) ?>" data-validar>
    <?= portal_campo_csrf() ?>
    <input type="hidden" name="accion" value="clave">
    <div class="campo">
      <label for="c-nueva">Nueva clave (mínimo <?= RED_CLAVE_MIN ?> caracteres)</label>
      <div class="clave-wrap">
        <input id="c-nueva" type="password" name="nueva" data-tipo="clave" minlength="<?= RED_CLAVE_MIN ?>" maxlength="72" autocomplete="new-password" required data-msg="Escribe tu nueva clave.">
        <button type="button" class="ver-clave" aria-label="Mostrar clave">👁</button>
      </div>
    </div>
    <div class="campo">
      <label for="c-rep">Repite la clave</label>
      <input id="c-rep" type="password" name="repetir" data-tipo="repetir" data-igual="c-nueva" maxlength="72" autocomplete="new-password" required data-msg="Repite la clave.">
    </div>
    <button class="btn btn-rosa btn-block" type="submit">Guardar mi clave</button>
  </form>
</section>
