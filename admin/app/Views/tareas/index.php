<div class="grid g4 kpi-fila" style="margin-bottom:14px">
  <div class="card kpi-mini"><span class="muted small">Tareas abiertas</span><b><?= num($resumen['abiertas'] ?? 0) ?></b></div>
  <div class="card kpi-mini"><span class="muted small">Personas comprometidas</span><b><?= num($resumen['personas'] ?? 0) ?></b></div>
  <div class="card kpi-mini"><span class="muted small">Tareas aceptadas o hechas</span><b><?= num($resumen['comprometidas'] ?? 0) ?></b></div>
  <div class="card kpi-mini <?= ($resumen['por_validar'] ?? 0) ? 'kpi-alerta' : '' ?>"><span class="muted small">Reportes por validar</span><b><?= num($resumen['por_validar'] ?? 0) ?></b></div>
</div>

<div class="toolbar">
  <?php foreach (['abierta' => 'Abiertas', 'cerrada' => 'Cerradas', 'todas' => 'Todas'] as $k => $txt): ?>
    <a class="btn <?= $estado === $k ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= url('tareas') . (defined('USE_REWRITE') && USE_REWRITE === false ? '&' : '?') ?>estado=<?= $k ?>"><?= $txt ?></a>
  <?php endforeach; ?>
  <span class="spacer"></span>
  <a class="btn btn-gold" href="<?= url('tareas/crear') ?>">＋ Nueva tarea</a>
</div>

<section class="card">
  <h3>Tareas y convocatorias</h3>
  <p class="muted small" style="margin-bottom:10px">Las personas las ven en su panel (<b><?= e(rtrim(LANDING_URL, '/')) ?>/mi</b>), responden si pueden y reportan lo que lograron. Al validar su reporte suman los puntos que los hacen subir de nivel.</p>
  <div class="tbl-wrap">
  <table>
    <tr><th>Tarea</th><th>Fecha</th><th>Creada por</th><th style="text-align:right">Asignadas</th><th style="text-align:right">Aceptadas</th><th style="text-align:right">Por validar</th><th style="text-align:right">Validadas</th><th style="text-align:right">Resultado</th><th></th></tr>
    <?php if (!$tareas): ?>
      <tr><td colspan="9" class="muted" style="text-align:center;padding:26px">No hay tareas aquí. <a href="<?= url('tareas/crear') ?>">Crea la primera</a>: una reunión, un puerta a puerta o una jornada de llamadas.</td></tr>
    <?php endif; ?>
    <?php foreach ($tareas as $t): $tipo = TAREA_TIPOS[$t['tipo']] ?? TAREA_TIPOS['otra']; ?>
    <tr>
      <td><b><?= $tipo[1] ?> <?= e($t['titulo']) ?></b><br>
        <span class="muted small"><?= e($tipo[0]) ?>
          <?= $t['alcance'] === 'abierta' ? ' · <span class="tag tag-blue">abierta</span>' : '' ?>
          <?= $t['alcance'] === 'red' ? ' · <span class="tag tag-gold">convocatoria de la red</span>' : '' ?>
          <?= $t['estado'] !== 'abierta' ? ' · <span class="tag tag-grey">' . e($t['estado']) . '</span>' : '' ?></span></td>
      <td class="small"><?= $t['fecha'] ? e(red_fecha($t['fecha'])) : '—' ?></td>
      <td class="small"><?= e((string)$t['creador']) ?><?= $t['creada_en_red'] ? ' <span class="muted">(promotor)</span>' : '' ?></td>
      <td style="text-align:right"><?= num((int)$t['total']) ?></td>
      <td style="text-align:right"><?= num((int)$t['aceptadas']) ?></td>
      <td style="text-align:right"><?= (int)$t['por_validar'] ? '<b class="txt-alerta">' . num((int)$t['por_validar']) . '</b>' : '0' ?></td>
      <td style="text-align:right"><?= num((int)$t['validadas']) ?></td>
      <td style="text-align:right"><?= num((int)$t['resultado']) ?><?= $t['meta'] ? ' / ' . num((int)$t['meta']) : '' ?></td>
      <td><a class="btn btn-ghost btn-mini" href="<?= url('tareas/ver/' . (int)$t['id']) ?>">Ver</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <h3>Escalera de la red: niveles y permisos en el panel del simpatizante</h3>
  <p class="muted small" style="margin-bottom:10px">Cada simpatizante entra a su panel en <b><?= e(rtrim(LANDING_URL, '/')) ?>/mi</b> con su documento y su clave.
    Sube de nivel con puntos: <b>+<?= RED_PUNTOS_INVITADO ?></b> por invitado, <b>+<?= RED_PUNTOS_SEGURO ?></b> si su invitado se confirma como voto seguro y los puntos de cada tarea validada.</p>
  <div class="tbl-wrap">
  <table>
    <tr><th>Permiso</th><?php foreach (PROMOTOR_NIVELES as $n): ?><th style="text-align:center"><?= $n[2] ?> <?= e($n[1]) ?><br><span class="muted small"><?= $n[0] ?>+ pts</span></th><?php endforeach; ?></tr>
    <?php foreach (RED_PERMISOS as [$min, $texto]): ?>
    <tr><td><?= e($texto) ?></td><?php foreach (PROMOTOR_NIVELES as $i => $n): ?><td style="text-align:center"><?= $i >= $min ? '✅' : '<span class="muted">—</span>' ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>
