<?php defined('PORTAL') or exit;
$porResponder = array_filter($misTareas, fn($t) => $t['estado'] === 'pendiente' && $t['tarea_estado'] === 'abierta');
$enCurso      = array_filter($misTareas, fn($t) => $t['estado'] === 'aceptada' && $t['tarea_estado'] === 'abierta');
$historial    = array_filter($misTareas, fn($t) => !in_array($t['estado'], ['pendiente', 'aceptada'], true) || $t['tarea_estado'] !== 'abierta');
$volverA = 'tareas';
?>
<?php if (portal_puede($yo, 'convocar') || portal_puede($yo, 'asignar')): ?>
<div class="btns" style="margin:0 0 14px">
  <?php if (portal_puede($yo, 'convocar')): ?><a class="btn btn-violeta" href="<?= e(portal_url('convocar')) ?>">📣 Convocar reunión</a><?php endif; ?>
  <?php if (portal_puede($yo, 'asignar')): ?><a class="btn btn-soft" href="<?= e(portal_url('asignar')) ?>">🧩 Asignar tarea a mi red</a><?php endif; ?>
</div>
<?php endif; ?>

<section class="card">
  <h2>📬 Por responder <span class="der"><?= count($porResponder) ?></span></h2>
  <?php if (!$porResponder): ?><p class="vacio">No tienes tareas nuevas. 🙌</p><?php endif; ?>
  <?php foreach ($porResponder as $t) require __DIR__ . '/_tarea.php'; ?>
</section>

<section class="card">
  <h2>🏃 En curso <span class="der"><?= count($enCurso) ?></span></h2>
  <?php if (!$enCurso): ?><p class="vacio">Cuando aceptes una tarea o confirmes un evento aparecerá aquí.</p><?php endif; ?>
  <?php foreach ($enCurso as $t) require __DIR__ . '/_tarea.php'; ?>
</section>

<?php if ($abiertas): ?>
<section class="card">
  <h2>🙋 Tareas abiertas <span class="der">apúntate</span></h2>
  <?php foreach ($abiertas as $a): $tipo = TAREA_TIPOS[$a['tipo']] ?? TAREA_TIPOS['otra']; $esEvento = $tipo[3]; ?>
  <article class="tarea">
    <div class="cab">
      <span class="emo"><?= $tipo[1] ?></span>
      <div style="flex:1;min-width:0">
        <b><?= e($a['titulo']) ?></b>
        <div class="meta"><?= e($tipo[0]) ?><?php if ($a['fecha']): ?> · 📅 <?= e(red_fecha($a['fecha'])) ?><?php endif; ?><?php if ($a['lugar']): ?> · 📍 <?= e($a['lugar']) ?><?php endif; ?></div>
      </div>
      <?php $pts = $esEvento ? (int)$a['puntos_asistencia'] : (int)$a['puntos']; if ($pts): ?><span class="pts">+<?= $pts ?> pts</span><?php endif; ?>
    </div>
    <?php if ($a['descripcion']): ?><p class="desc"><?= e($a['descripcion']) ?></p><?php endif; ?>
    <form method="post" class="acciones" action="<?= e(portal_url('tareas')) ?>">
      <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="apuntarme"><input type="hidden" name="tarea" value="<?= (int)$a['id'] ?>">
      <button class="btn btn-rosa btn-mini" type="submit"><?= $esEvento ? '🙋 Asistiré' : '🙋 Me apunto' ?></button>
    </form>
  </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($organizo): ?>
<section class="card">
  <h2>🗂️ Lo que organizo</h2>
  <ul class="lista">
    <?php foreach ($organizo as $o): $tipo = TAREA_TIPOS[$o['tipo']] ?? TAREA_TIPOS['otra']; $esConv = $o['alcance'] === 'red'; ?>
    <li>
      <span class="avatar" style="background:var(--rosa-soft)"><?= $tipo[1] ?></span>
      <span class="info"><b><?= e($o['titulo']) ?></b>
        <span><?= $o['fecha'] ? e(red_fecha($o['fecha'])) . ' · ' : '' ?>
          <?= $esConv ? (int)$o['confirmados'] . ' confirmados de ' . (int)$o['invitados'] . ' invitados'
                      : (int)$o['cumplidas'] . ' de ' . (int)$o['responsables'] . ' la cumplieron' ?>
          <?= $o['estado'] !== 'abierta' ? ' · cerrada' : '' ?></span></span>
      <a class="btn btn-soft btn-mini" href="<?= e(portal_url('convocatoria', ['id' => $o['id']])) ?>">Ver</a>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!portal_puede($yo, 'convocar')): ?>
<div class="bloqueado" style="margin-bottom:14px"><b>🔒 Convocar reuniones con tu red</b>Se desbloquea en el nivel <?= PROMOTOR_NIVELES[RED_PERMISOS['convocar'][0]][2] ?> <?= e(PROMOTOR_NIVELES[RED_PERMISOS['convocar'][0]][1]) ?> (<?= PROMOTOR_NIVELES[RED_PERMISOS['convocar'][0]][0] ?> puntos).</div>
<?php endif; ?>

<?php if ($historial): ?>
<section class="card">
  <h2>🗃️ Historial</h2>
  <?php foreach ($historial as $t) require __DIR__ . '/_tarea.php'; ?>
</section>
<?php endif; ?>
