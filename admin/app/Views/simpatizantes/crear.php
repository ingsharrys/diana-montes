<?php use Core\Auth; ?>
<div class="card card-form">
  <h3>Datos del simpatizante</h3>
  <p class="muted">Los campos con * son obligatorios. La <b>profesión</b> y el <b>cumpleaños</b> alimentan los mensajes de WhatsApp que conectan.</p>

  <form method="post" action="<?= url('simpatizantes/guardar') ?>" novalidate>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">

      <label class="field <?= isset($errores['nombre']) ? 'has-error' : '' ?>">
        <span>Nombre completo *</span>
        <input name="nombre" value="<?= e($v['nombre'] ?? '') ?>" placeholder="Ej: María Fernanda Ortiz" required>
        <?php if (isset($errores['nombre'])): ?><em><?= e($errores['nombre']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['documento']) ? 'has-error' : '' ?>">
        <span>Documento de identidad *</span>
        <input name="documento" inputmode="numeric" value="<?= e($v['documento'] ?? '') ?>" placeholder="Sin puntos ni comas" required>
        <?php if (isset($errores['documento'])): ?><em><?= e($errores['documento']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['telefono']) ? 'has-error' : '' ?>">
        <span>Celular / WhatsApp *</span>
        <input name="telefono" inputmode="tel" value="<?= e($v['telefono'] ?? '') ?>" placeholder="3XX XXX XXXX" required>
        <?php if (isset($errores['telefono'])): ?><em><?= e($errores['telefono']) ?></em><?php endif; ?>
      </label>

      <label class="field">
        <span>Fecha de cumpleaños</span>
        <input type="date" name="fecha_nacimiento" value="<?= e($v['fecha_nacimiento'] ?? '') ?>">
      </label>

      <label class="field <?= isset($errores['zona_id']) ? 'has-error' : '' ?>">
        <span>Zona / barrio / vereda *</span>
        <select name="zona_id" required>
          <option value="">Selecciona…</option>
          <?php foreach ($zonas as $z): ?>
            <option value="<?= (int)$z['id'] ?>" <?= (int)($v['zona_id'] ?? 0) === (int)$z['id'] ? 'selected' : '' ?>>
              <?= e($z['nombre']) ?> (<?= e($z['tipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['zona_id'])): ?><em><?= e($errores['zona_id']) ?></em><?php endif; ?>
      </label>

      <label class="field">
        <span>Puesto de votación</span>
        <select name="puesto_id">
          <option value="">Selecciona…</option>
          <?php foreach ($puestos as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)($v['puesto_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field <?= isset($errores['profesion_id']) ? 'has-error' : '' ?>">
        <span>Profesión u ocupación * <b class="gold">(clave para mensajes)</b></span>
        <select name="profesion_id" required>
          <option value="">Selecciona…</option>
          <?php foreach ($profesiones as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)($v['profesion_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['profesion_id'])): ?><em><?= e($errores['profesion_id']) ?></em><?php endif; ?>
      </label>

      <label class="field">
        <span>Nivel de compromiso</span>
        <select name="nivel">
          <option value="simpatizante" <?= ($v['nivel'] ?? '') === 'simpatizante' ? 'selected' : '' ?>>Simpatizante</option>
          <option value="voluntario" <?= ($v['nivel'] ?? '') === 'voluntario' ? 'selected' : '' ?>>Voluntario/a</option>
          <option value="votante_confirmado" <?= ($v['nivel'] ?? '') === 'votante_confirmado' ? 'selected' : '' ?>>Votante confirmado</option>
        </select>
      </label>

      <?php if (!Auth::tieneRol('lider')): ?>
      <label class="field <?= isset($errores['lider_id']) ? 'has-error' : '' ?>">
        <span>Líder que vincula *</span>
        <select name="lider_id" required>
          <option value="">Selecciona…</option>
          <?php foreach ($lideres as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)($v['lider_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['lider_id'])): ?><em><?= e($errores['lider_id']) ?></em><?php endif; ?>
      </label>
      <?php endif; ?>

      <label class="field">
        <span>Mesa (opcional)</span>
        <input name="mesa" value="<?= e($v['mesa'] ?? '') ?>" placeholder="Ej: 12">
      </label>

      <div class="full">
        <label class="consent <?= isset($errores['consentimiento']) ? 'has-error' : '' ?>">
          <input type="checkbox" name="consentimiento" value="1">
          <span><b>Autorización de tratamiento de datos personales (Ley 1581 de 2012).</b>
          La persona autoriza el uso de sus datos para fines de contacto e información de la campaña.
          Sin esta autorización el registro no puede guardarse.</span>
        </label>
        <?php if (isset($errores['consentimiento'])): ?>
          <div class="alert alert-error" style="margin-top:8px">⚠ <?= e($errores['consentimiento']) ?></div>
        <?php endif; ?>
      </div>

      <div class="full form-actions">
        <a class="btn btn-ghost" href="<?= url('simpatizantes') ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar registro</button>
      </div>
    </div>
  </form>
</div>
