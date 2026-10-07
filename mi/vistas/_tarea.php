<?php defined('PORTAL') or exit;
/** Tarjeta de una asignación ($t) con sus acciones. */
$tipo = TAREA_TIPOS[$t['tipo']] ?? TAREA_TIPOS['otra'];
$est  = ASIGNACION_ESTADOS[$t['estado']];
$esAsistente = $t['rol'] === 'asistente';
$pts  = $esAsistente ? (int)$t['puntos_asistencia'] : (int)$t['puntos'];
$abierta = $t['tarea_estado'] === 'abierta';
?>
<article class="tarea">
  <div class="cab">
    <span class="emo"><?= $tipo[1] ?></span>
    <div style="flex:1;min-width:0">
      <b><?= e($t['titulo']) ?></b>
      <div class="meta">
        <?= $esAsistente ? 'Invitación' : e($tipo[0]) ?>
        <?php if ($t['fecha']): ?> · 📅 <?= e(red_fecha($t['fecha'])) ?><?php endif; ?>
        <?php if ($t['lugar']): ?> · 📍 <?= e($t['lugar']) ?><?php endif; ?>
        <?php if ($t['meta'] && !$esAsistente): ?> · 🎯 meta <?= (int)$t['meta'] ?><?php endif; ?>
      </div>
      <?php if ($t['asignada_por']): ?><div class="meta">De: <?= e($t['asignada_por']) ?></div><?php endif; ?>
    </div>
    <div style="text-align:right;display:flex;flex-direction:column;gap:4px;align-items:flex-end">
      <span class="estado <?= $est[1] ?>"><?= $esAsistente && $t['estado'] === 'aceptada' ? 'Asistirás' : ($esAsistente && $t['estado'] === 'hecha' ? 'Asististe' : $est[0]) ?></span>
      <?php if ($pts): ?><span class="pts">+<?= $pts ?> pts</span><?php endif; ?>
    </div>
  </div>
  <?php if ($t['descripcion']): ?><p class="desc"><?= e($t['descripcion']) ?></p><?php endif; ?>

  <?php if ($abierta && in_array($t['estado'], ['pendiente', 'rechazada'], true)): ?>
    <form method="post" class="acciones" action="<?= e(portal_url('tareas')) ?>">
      <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="responder"><input type="hidden" name="asig" value="<?= (int)$t['asig_id'] ?>">
      <input type="hidden" name="volver" value="<?= e($volverA ?? 'tareas') ?>">
      <button class="btn btn-rosa btn-mini" name="respuesta" value="aceptar"><?= $esAsistente ? '✓ Asistiré' : '✓ La haré' ?></button>
      <?php if ($t['estado'] === 'pendiente'): ?><button class="btn btn-soft btn-mini" name="respuesta" value="rechazar">No puedo</button><?php endif; ?>
    </form>
  <?php elseif ($abierta && !$esAsistente && in_array($t['estado'], ['aceptada', 'hecha'], true)): ?>
    <details <?= $t['estado'] === 'aceptada' ? 'open' : '' ?>>
      <summary class="small" style="margin-top:10px;cursor:pointer;font-weight:700;color:var(--rosa-osc)"><?= $t['estado'] === 'hecha' ? 'Corregir mi reporte' : '¿Ya la hiciste? Cuéntanos cómo te fue' ?></summary>
      <form method="post" class="reporte" action="<?= e(portal_url('tareas')) ?>" data-validar>
        <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="reportar"><input type="hidden" name="asig" value="<?= (int)$t['asig_id'] ?>">
        <div class="campo">
          <label for="r<?= (int)$t['asig_id'] ?>">¿Cuántas <?= e($tipo[4]) ?>?</label>
          <input id="r<?= (int)$t['asig_id'] ?>" name="resultado" data-tipo="numero" maxlength="6" value="<?= e((string)($t['resultado'] ?? '')) ?>" required data-msg="Escribe un número (puede ser 0).">
        </div>
        <div class="campo">
          <label for="n<?= (int)$t['asig_id'] ?>">Comentario (opcional)</label>
          <textarea id="n<?= (int)$t['asig_id'] ?>" name="nota" maxlength="500" placeholder="Ej: visité la cuadra de la iglesia, 3 personas piden reunión con Diana"><?= e((string)($t['nota'] ?? '')) ?></textarea>
        </div>
        <button class="btn btn-rosa btn-block" type="submit">Enviar reporte</button>
      </form>
    </details>
    <?php if ($t['estado'] === 'aceptada'): ?>
    <form method="post" action="<?= e(portal_url('tareas')) ?>" style="margin-top:8px">
      <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="responder"><input type="hidden" name="asig" value="<?= (int)$t['asig_id'] ?>">
      <button class="btn btn-soft btn-mini" name="respuesta" value="rechazar">Ya no puedo hacerla</button>
    </form>
    <?php endif; ?>
  <?php elseif ($abierta && $esAsistente && $t['estado'] === 'aceptada'): ?>
    <form method="post" class="acciones" action="<?= e(portal_url('tareas')) ?>">
      <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="responder"><input type="hidden" name="asig" value="<?= (int)$t['asig_id'] ?>">
      <button class="btn btn-soft btn-mini" name="respuesta" value="rechazar">Ya no podré ir</button>
    </form>
  <?php endif; ?>
  <?php if ($t['estado'] === 'hecha' && !$esAsistente): ?><p class="small muted" style="margin-top:8px">Reportaste <b><?= (int)$t['resultado'] ?></b> <?= e($tipo[4]) ?>. El equipo lo validará pronto.</p><?php endif; ?>
</article>
