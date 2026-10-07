<?php defined('PORTAL') or exit; ?>
<section class="card">
  <h2>🧩 Asignar una tarea a tu red</h2>
  <p class="small muted" style="margin-bottom:14px">Como Embajador puedes repartir trabajo en tu red. Cada persona la ve en su panel; cuando la cumpla y el equipo la valide, sumará sus puntos.</p>
  <form method="post" action="<?= e(portal_url('asignar')) ?>" data-validar>
    <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="asignar">
    <div class="campo">
      <label for="as-tipo">Tipo de tarea</label>
      <select id="as-tipo" name="tipo" required data-msg="Elige el tipo de tarea.">
        <option value="">Selecciona…</option>
        <?php foreach (TAREA_TIPOS as $clave => $t): if ($t[3]) continue; ?>
        <option value="<?= $clave ?>"><?= $t[1] ?> <?= e($t[0]) ?> (+<?= $t[2] ?> pts)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo">
      <label for="as-t">¿Qué hay que hacer?</label>
      <input id="as-t" name="titulo" data-tipo="texto" maxlength="150" placeholder="Ej: Visitar 10 casas de tu cuadra" required data-msg="Escribe la tarea.">
    </div>
    <div class="campo">
      <label for="as-d">Instrucciones (opcional)</label>
      <textarea id="as-d" name="descripcion" maxlength="1000"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="campo"><label for="as-f">Fecha límite (opcional)</label><input id="as-f" type="date" name="fecha" min="<?= date('Y-m-d') ?>"></div>
      <div class="campo"><label for="as-m">Meta (opcional)</label><input id="as-m" name="meta" data-tipo="numero" maxlength="5" placeholder="Ej: 10"></div>
    </div>
    <div class="campo">
      <span>¿A quiénes? <button type="button" class="btn btn-soft btn-mini" id="todos" style="margin-left:6px">Marcar todos</button></span>
      <?php if (!$red && !$redCompleta): ?><p class="vacio">Aún no tienes personas en tu red.</p><?php endif; ?>
      <?php foreach ($red as $r): ?>
        <label class="check-persona"><input type="checkbox" name="personas[]" value="<?= (int)$r['id'] ?>"> <?= e($r['nombre']) ?><span>invitado directo</span></label>
      <?php endforeach; ?>
      <?php foreach ($redCompleta as $r): ?>
        <label class="check-persona"><input type="checkbox" name="personas[]" value="<?= (int)$r['id'] ?>"> <?= e(promotor_nombre_corto($r['nombre'])) ?><span><?= (int)$r['nivel_red'] ?>.º nivel</span></label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-rosa btn-block" type="submit">Asignar tarea</button>
  </form>
</section>
<script>
document.getElementById('todos').addEventListener('click', () => {
  const cajas = document.querySelectorAll('input[name="personas[]"]');
  const marcar = [...cajas].some(c => !c.checked);
  cajas.forEach(c => c.checked = marcar);
});
</script>
