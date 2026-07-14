<div class="card card-form">
  <h3>Invitar a un miembro del equipo</h3>
  <p class="muted">Al guardar se genera su <b>contraseña temporal</b> (se muestra una sola vez) y su <b>enlace de red</b> para registrar simpatizantes.</p>

  <form method="post" action="<?= url('usuarios/guardar') ?>" novalidate>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">

      <label class="field <?= isset($errores['nombre']) ? 'has-error' : '' ?>">
        <span>Nombre completo *</span>
        <input name="nombre" value="<?= e($v['nombre'] ?? '') ?>" placeholder="Ej: Rosalba Perdomo" required>
        <?php if (isset($errores['nombre'])): ?><em><?= e($errores['nombre']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['email']) ? 'has-error' : '' ?>">
        <span>Correo electrónico *</span>
        <input type="email" name="email" value="<?= e($v['email'] ?? '') ?>" placeholder="correo@ejemplo.com" required>
        <?php if (isset($errores['email'])): ?><em><?= e($errores['email']) ?></em><?php endif; ?>
      </label>

      <label class="field">
        <span>Celular / WhatsApp</span>
        <input name="telefono" inputmode="tel" value="<?= e($v['telefono'] ?? '') ?>" placeholder="3XX XXX XXXX">
      </label>

      <label class="field">
        <span>Rol *</span>
        <select name="rol">
          <option value="lider" <?= ($v['rol'] ?? '') === 'lider' ? 'selected' : '' ?>>Líder</option>
          <option value="coordinador" <?= ($v['rol'] ?? '') === 'coordinador' ? 'selected' : '' ?>>Coordinador/a de zona</option>
          <option value="digitador" <?= ($v['rol'] ?? '') === 'digitador' ? 'selected' : '' ?>>Digitador/a (logística)</option>
        </select>
      </label>

      <label class="field <?= isset($errores['zona_id']) ? 'has-error' : '' ?>">
        <span>Zona a cargo</span>
        <select name="zona_id">
          <option value="">Sin zona específica</option>
          <?php foreach ($zonas as $z): ?>
            <option value="<?= (int)$z['id'] ?>" <?= (int)($v['zona_id'] ?? 0) === (int)$z['id'] ? 'selected' : '' ?>>
              <?= e($z['nombre']) ?> (<?= e($z['tipo']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['zona_id'])): ?><em><?= e($errores['zona_id']) ?></em><?php endif; ?>
      </label>

      <label class="field">
        <span>Coordinador/a a quien reporta</span>
        <select name="coordinador_id">
          <option value="">— (reporta a la dirección)</option>
          <?php foreach ($coordinadores as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($v['coordinador_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span>Meta de vinculados (campaña)</span>
        <input name="meta" inputmode="numeric" value="<?= e((string)($v['meta'] ?? '')) ?>" placeholder="Ej: 325">
      </label>

      <div class="full form-actions">
        <a class="btn btn-ghost" href="<?= url('usuarios') ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit">Crear invitación</button>
      </div>
    </div>
  </form>
</div>
