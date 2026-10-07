<?php $err = fn($k) => isset($errores[$k]) ? '<em>' . e($errores[$k]) . '</em>' : ''; $cls = fn($k) => isset($errores[$k]) ? 'has-error' : ''; ?>
<div class="card card-form">
  <h3>Nueva tarea para la red</h3>
  <p class="muted">Ejemplos: <i>Organiza una reunión en tu casa</i>, <i>Convoca 10 vecinos al evento del sábado</i>, <i>Puerta a puerta en tu cuadra</i>, <i>Llama a 20 personas de tu lista</i>.</p>

  <form method="post" action="<?= url('tareas/guardar') ?>" novalidate data-validar>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">
      <label class="field <?= $cls('tipo') ?>">
        <span>Tipo de tarea *</span>
        <select name="tipo" id="t-tipo" required data-msg="Elige el tipo de tarea.">
          <option value="">Selecciona…</option>
          <?php foreach (TAREA_TIPOS as $k => $t): ?>
          <option value="<?= $k ?>" data-puntos="<?= $t[2] ?>" <?= ($v['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $t[1] ?> <?= e($t[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $err('tipo') ?>
      </label>
      <label class="field <?= $cls('titulo') ?>">
        <span>¿Qué hay que hacer? *</span>
        <input name="titulo" data-tipo="texto" maxlength="150" value="<?= e($v['titulo'] ?? '') ?>" placeholder="Ej: Reunión con vecinos del barrio Centro" required data-msg="Escribe la tarea.">
        <?= $err('titulo') ?>
      </label>
      <label class="field full">
        <span>Instrucciones (opcional)</span>
        <textarea name="descripcion" rows="3" maxlength="2000" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= e($v['descripcion'] ?? '') ?></textarea>
      </label>
      <label class="field <?= $cls('fecha') ?>">
        <span>Fecha y hora (obligatoria en eventos)</span>
        <input type="datetime-local" name="fecha" value="<?= e($v['fecha'] ?? '') ?>" min="<?= date('Y-m-d\TH:i') ?>">
        <?= $err('fecha') ?>
      </label>
      <label class="field <?= $cls('lugar') ?>">
        <span>Lugar (opcional)</span>
        <input name="lugar" data-tipo="texto" maxlength="200" value="<?= e($v['lugar'] ?? '') ?>" placeholder="Ej: Salón comunal, Cra 5 # 3-20">
        <?= $err('lugar') ?>
      </label>
      <label class="field">
        <span>Meta por persona (opcional)</span>
        <input name="meta" data-tipo="numero" maxlength="6" value="<?= e((string)($v['meta'] ?? '')) ?>" placeholder="Ej: 10 casas">
      </label>
      <label class="field">
        <span>Puntos al validar</span>
        <input name="puntos" id="t-puntos" data-tipo="numero" data-max="500" maxlength="3" value="<?= e((string)($v['puntos'] ?? '10')) ?>">
      </label>

      <div class="full">
        <span class="field-titulo">¿Para quién es? *</span>
        <?php $modo = $v['modo'] ?? 'segmento'; ?>
        <div class="opciones-modo">
          <label class="op-modo"><input type="radio" name="modo" value="segmento" <?= $modo === 'segmento' ? 'checked' : '' ?>>
            <b>Asignar a un grupo</b><span>Les llega a todos los que cumplan el filtro.</span></label>
          <label class="op-modo"><input type="radio" name="modo" value="abierta" <?= $modo === 'abierta' ? 'checked' : '' ?>>
            <b>Publicar abierta</b><span>La ven en su panel y se apunta quien quiera.</span></label>
          <label class="op-modo"><input type="radio" name="modo" value="personas" <?= $modo === 'personas' ? 'checked' : '' ?>>
            <b>Personas específicas</b><span>Por documento o celular.</span></label>
        </div>
        <?= isset($errores['modo']) ? '<div class="alert alert-error" style="margin-top:8px">⚠ ' . e($errores['modo']) . '</div>' : '' ?>
      </div>

      <div class="full grid g3 filtro-segmento" style="gap:12px">
        <label class="field">
          <span>Nivel mínimo</span>
          <select name="nivel_minimo">
            <?php foreach (PROMOTOR_NIVELES as $i => $n): ?>
            <option value="<?= $i ?>" <?= (int)($v['nivel_minimo'] ?? 0) === $i ? 'selected' : '' ?>><?= $n[2] ?> <?= e($n[1]) ?><?= $i ? ' o más' : ' (todos)' ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Zona</span>
          <select name="zona_id">
            <option value="">Todas</option>
            <?= zonas_opciones($zonas, $v['zona_id'] ?? null) ?>
          </select>
        </label>
        <?php if ($lideres): ?>
        <label class="field">
          <span>Red del líder</span>
          <select name="lider_id">
            <option value="">Todos los líderes</option>
            <?php foreach ($lideres as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)($v['lider_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['nombre']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <?php else: ?>
        <p class="muted small" style="align-self:end">Solo personas de tu red.</p>
        <?php endif; ?>
      </div>

      <label class="field full filtro-personas <?= $cls('personas') ?>">
        <span>Documentos o celulares (uno por línea o separados por coma)</span>
        <textarea name="personas" rows="4" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit" placeholder="1075234567&#10;3105551234"><?= e($v['personas'] ?? '') ?></textarea>
        <?= $err('personas') ?>
      </label>

      <div class="full form-actions">
        <a class="btn btn-ghost" href="<?= url('tareas') ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit">Crear tarea</button>
      </div>
    </div>
  </form>
</div>
<script>
(function () {
  const tipo = document.getElementById('t-tipo'), pts = document.getElementById('t-puntos');
  let tocado = false;
  pts.addEventListener('input', () => tocado = true);
  tipo.addEventListener('change', () => { const o = tipo.selectedOptions[0]; if (o && o.dataset.puntos && !tocado) pts.value = o.dataset.puntos; });
  const modos = document.querySelectorAll('input[name=modo]');
  function ver() {
    const m = document.querySelector('input[name=modo]:checked').value;
    document.querySelector('.filtro-segmento').style.display = m === 'personas' ? 'none' : '';
    document.querySelector('.filtro-personas').style.display = m === 'personas' ? '' : 'none';
  }
  modos.forEach(r => r.addEventListener('change', ver)); ver();
})();
</script>
