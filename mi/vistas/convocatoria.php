<?php defined('PORTAL') or exit;
$tipo = TAREA_TIPOS[$tarea['tipo']] ?? TAREA_TIPOS['otra'];
$esConv = $tarea['alcance'] === 'red';
$abierta = $tarea['estado'] === 'abierta';
?>
<p style="margin-bottom:10px"><a href="<?= e(portal_url('tareas')) ?>">← Volver a mis tareas</a></p>
<section class="card">
  <h2><?= $tipo[1] ?> <?= e($tarea['titulo']) ?></h2>
  <p class="small muted">
    <?= $tarea['fecha'] ? '📅 ' . e(red_fecha($tarea['fecha'])) : '' ?>
    <?= $tarea['lugar'] ? ' · 📍 ' . e($tarea['lugar']) : '' ?>
    <?= $tarea['meta'] ? ' · 🎯 meta ' . (int)$tarea['meta'] : '' ?>
    <?= !$abierta ? ' · <b>' . ($tarea['estado'] === 'cancelada' ? 'cancelada' : 'cerrada') . '</b>' : '' ?>
  </p>
  <?php if ($tarea['descripcion']): ?><p style="margin-top:8px;white-space:pre-line"><?= e($tarea['descripcion']) ?></p><?php endif; ?>
</section>

<section class="card">
  <?php if ($esConv): ?>
    <h2>🙋 Invitados <span class="der"><?= count(array_filter($participantes, fn($x) => $x['estado'] === 'aceptada')) ?> confirmados</span></h2>
    <?php if ($abierta): ?><p class="small muted" style="margin-bottom:8px">Después de la reunión marca quiénes asistieron y guarda. El equipo validará la asistencia para sumar los puntos.</p><?php endif; ?>
    <form method="post" action="<?= e(portal_url('convocatoria', ['id' => $tarea['id']])) ?>">
      <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="asistencia"><input type="hidden" name="tarea" value="<?= (int)$tarea['id'] ?>">
      <?php foreach ($participantes as $pa): $est = ASIGNACION_ESTADOS[$pa['estado']]; ?>
        <label class="check-persona">
          <input type="checkbox" name="asistio[]" value="<?= (int)$pa['id'] ?>" <?= in_array($pa['estado'], ['hecha', 'validada'], true) ? 'checked' : '' ?> <?= $abierta && $pa['estado'] !== 'validada' ? '' : 'disabled' ?>>
          <?= e($pa['nombre']) ?>
          <span class="estado <?= $est[1] ?>"><?= ['pendiente' => 'Sin responder', 'aceptada' => 'Asistirá', 'rechazada' => 'No puede', 'hecha' => 'Asistió', 'validada' => 'Asistió ✓', 'no_valida' => 'No validada'][$pa['estado']] ?></span>
        </label>
      <?php endforeach; ?>
      <?php if ($abierta && $participantes): ?><button class="btn btn-rosa btn-block" type="submit" style="margin-top:12px">Guardar asistencia</button><?php endif; ?>
    </form>
  <?php else: ?>
    <h2>👥 Personas asignadas</h2>
    <ul class="lista">
      <?php foreach ($participantes as $pa): $est = ASIGNACION_ESTADOS[$pa['estado']]; ?>
      <li>
        <span class="info"><b><?= e($pa['nombre']) ?></b>
          <span><?= $pa['resultado'] !== null ? 'Reportó ' . (int)$pa['resultado'] . ' ' . e($tipo[4]) : 'Sin reporte' ?><?= $pa['nota'] ? ' · “' . e($pa['nota']) . '”' : '' ?></span></span>
        <span class="estado <?= $est[1] ?>"><?= $est[0] ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if ($abierta && $esConv): ?>
<form method="post" action="<?= e(portal_url('tareas')) ?>" onsubmit="return confirm('¿Cancelar esta convocatoria? Tus invitados dejarán de verla.')">
  <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="cancelar_convocatoria"><input type="hidden" name="tarea" value="<?= (int)$tarea['id'] ?>">
  <button class="btn btn-soft btn-block" type="submit">Cancelar convocatoria</button>
</form>
<?php endif; ?>
