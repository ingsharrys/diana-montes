<?php use Core\Auth; ?>
<div class="card card-form">
  <h3>Datos del simpatizante</h3>
  <p class="muted">Los campos con * son obligatorios. La <b>profesión</b> y el <b>cumpleaños</b> alimentan los mensajes de WhatsApp que conectan.</p>

  <form method="post" action="<?= url('simpatizantes/guardar') ?>" novalidate data-validar>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">

      <label class="field <?= isset($errores['nombre']) ? 'has-error' : '' ?>">
        <span>Nombre completo *</span>
        <input name="nombre" data-tipo="nombre" maxlength="120" autocapitalize="words" value="<?= e($v['nombre'] ?? '') ?>" placeholder="Ej: María Fernanda Ortiz" required data-msg="Escribe el nombre completo.">
        <?php if (isset($errores['nombre'])): ?><em><?= e($errores['nombre']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['documento']) ? 'has-error' : '' ?>">
        <span>Documento de identidad *</span>
        <input name="documento" data-tipo="documento" inputmode="numeric" value="<?= e($v['documento'] ?? '') ?>" placeholder="Solo números, sin puntos" required data-msg="Escribe el número de documento.">
        <?php if (isset($errores['documento'])): ?><em><?= e($errores['documento']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['telefono']) ? 'has-error' : '' ?>">
        <span>Celular / WhatsApp *</span>
        <input type="tel" name="telefono" data-tipo="celular" inputmode="tel" value="<?= e($v['telefono'] ?? '') ?>" placeholder="3XX XXX XXXX" required data-msg="Escribe el celular.">
        <?php if (isset($errores['telefono'])): ?><em><?= e($errores['telefono']) ?></em><?php endif; ?>
      </label>

      <label class="field <?= isset($errores['fecha_nacimiento']) ? 'has-error' : '' ?>">
        <span>Fecha de nacimiento *</span>
        <input type="date" name="fecha_nacimiento" data-tipo="nacimiento" data-edad-min="<?= EDAD_MINIMA ?>" min="<?= date('Y-m-d', strtotime('-110 years')) ?>" value="<?= e($v['fecha_nacimiento'] ?? '') ?>"
               max="<?= date('Y-m-d', strtotime('-' . EDAD_MINIMA . ' years')) ?>" required data-msg="Escribe la fecha de nacimiento.">
        <?php if (isset($errores['fecha_nacimiento'])): ?><em><?= e($errores['fecha_nacimiento']) ?></em><?php endif; ?>
      </label>

      <?php if ($pideGenero): ?>
      <label class="field <?= isset($errores['genero']) ? 'has-error' : '' ?>">
        <span>Género *</span>
        <select name="genero" required data-msg="Selecciona el género.">
          <option value="">Selecciona…</option>
          <?php foreach (GENEROS as $valor => $etiqueta): ?>
            <option value="<?= $valor ?>" <?= ($v['genero'] ?? '') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['genero'])): ?><em><?= e($errores['genero']) ?></em><?php endif; ?>
      </label>
      <?php endif; ?>

      <label class="field <?= isset($errores['zona_id']) ? 'has-error' : '' ?>">
        <span>Zona / barrio / vereda *</span>
        <select name="zona_id" required data-buscar="Buscar barrio o vereda…" data-msg="Selecciona la zona.">
          <option value="">Selecciona…</option>
          <?= zonas_opciones($zonas, $v['zona_id'] ?? null) ?>
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
        <select name="profesion_id" required data-buscar="Buscar profesión u oficio…" data-msg="Selecciona la profesión: es la clave de los mensajes.">
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
          <?php foreach ($compromisos as $valor => $etiqueta): ?>
            <option value="<?= $valor ?>" <?= ($v['nivel'] ?? 'simpatizante') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <?php if (!Auth::tieneRol('lider')): ?>
      <label class="field <?= isset($errores['lider_id']) ? 'has-error' : '' ?>">
        <span>Líder que vincula *</span>
        <select name="lider_id" required data-msg="Selecciona el líder que vincula.">
          <option value="">Selecciona…</option>
          <?php foreach ($lideres as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)($v['lider_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errores['lider_id'])): ?><em><?= e($errores['lider_id']) ?></em><?php endif; ?>
      </label>
      <?php endif; ?>

      <label class="field <?= isset($errores['mesa']) ? 'has-error' : '' ?>">
        <span>Mesa (opcional)</span>
        <input name="mesa" data-tipo="numero" data-min="1" data-max="9999" maxlength="4" value="<?= e($v['mesa'] ?? '') ?>" placeholder="Ej: 12">
        <?php if (isset($errores['mesa'])): ?><em><?= e($errores['mesa']) ?></em><?php endif; ?>
      </label>

      <div class="full">
        <label class="consent <?= isset($errores['consentimiento']) ? 'has-error' : '' ?>">
          <input type="checkbox" name="consentimiento" value="1" required data-msg="Sin la autorización de datos no se puede guardar el registro." <?= !empty($_POST['consentimiento']) ? 'checked' : '' ?>>
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
