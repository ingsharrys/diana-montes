<?php defined('PORTAL') or exit; ?>
<section class="card">
  <h2>👤 Mis datos</h2>
  <ul class="permisos">
    <li><span class="ic">🪪</span><span>Usuario (documento)<br><b><?= e($yo['documento']) ?></b></span></li>
    <li><span class="ic">📱</span><span>Celular<br><b><?= e($yo['telefono']) ?></b></span></li>
    <li><span class="ic">📍</span><span>Barrio o vereda<br><b><?= e($yo['zona'] ?? '—') ?></b></span></li>
    <li><span class="ic">🧭</span><span>Tu líder en la campaña<br><b><?= e($yo['lider'] ?? 'Red directa de Diana') ?></b></span></li>
  </ul>
  <p class="small muted" style="margin-top:8px">¿Algún dato está mal? Pídele a tu líder que lo corrija.</p>
</section>

<section class="card">
  <h2>🔑 Cambiar mi clave</h2>
  <form method="post" action="<?= e(portal_url('perfil')) ?>" data-validar>
    <?= portal_campo_csrf() ?>
    <input type="hidden" name="accion" value="clave">
    <div class="campo">
      <label for="p-actual">Clave actual</label>
      <input id="p-actual" type="password" name="actual" autocomplete="current-password" required data-msg="Escribe tu clave actual.">
    </div>
    <div class="campo">
      <label for="p-nueva">Nueva clave (mínimo <?= RED_CLAVE_MIN ?> caracteres)</label>
      <div class="clave-wrap">
        <input id="p-nueva" type="password" name="nueva" data-tipo="clave" minlength="<?= RED_CLAVE_MIN ?>" maxlength="72" autocomplete="new-password" required data-msg="Escribe la nueva clave.">
        <button type="button" class="ver-clave" aria-label="Mostrar clave">👁</button>
      </div>
    </div>
    <div class="campo">
      <label for="p-rep">Repite la nueva clave</label>
      <input id="p-rep" type="password" name="repetir" data-tipo="repetir" data-igual="p-nueva" maxlength="72" autocomplete="new-password" required data-msg="Repite la nueva clave.">
    </div>
    <button class="btn btn-rosa btn-block" type="submit">Cambiar clave</button>
  </form>
</section>

<a class="btn btn-soft btn-block" href="<?= e(portal_url('salir')) ?>">Cerrar sesión</a>
<p class="pie">Para dejar de recibir mensajes de WhatsApp responde <b>SALIR</b> a cualquier mensaje de la campaña.</p>
