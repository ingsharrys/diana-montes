<?php
$tipo = TAREA_TIPOS[$tarea['tipo']] ?? TAREA_TIPOS['otra'];
$base = url('tareas/ver/' . (int)$tarea['id']) . (defined('USE_REWRITE') && USE_REWRITE === false ? '&' : '?');
?>
<p style="margin-bottom:10px"><a href="<?= url('tareas') ?>">← Todas las tareas</a></p>
<div class="grid g2" style="align-items:start">
  <section class="card">
    <h3><?= $tipo[1] ?> <?= e($tarea['titulo']) ?>
      <span class="tag <?= $tarea['estado'] === 'abierta' ? 'tag-green' : 'tag-grey' ?>"><?= e($tarea['estado']) ?></span></h3>
    <table>
      <tr><td class="muted" style="width:150px">Tipo</td><td><?= e($tipo[0]) ?><?= $tarea['alcance'] === 'abierta' ? ' · abierta a quien se apunte' : ($tarea['alcance'] === 'red' ? ' · convocatoria de un promotor' : '') ?></td></tr>
      <?php if ($tarea['fecha']): ?><tr><td class="muted">Fecha</td><td><?= e(red_fecha($tarea['fecha'])) ?></td></tr><?php endif; ?>
      <?php if ($tarea['lugar']): ?><tr><td class="muted">Lugar</td><td><?= e($tarea['lugar']) ?></td></tr><?php endif; ?>
      <?php if ($tarea['meta']): ?><tr><td class="muted">Meta por persona</td><td><?= num((int)$tarea['meta']) ?> <?= e($tipo[4]) ?></td></tr><?php endif; ?>
      <tr><td class="muted">Puntos</td><td><?= $tarea['alcance'] === 'red' ? (int)$tarea['puntos'] . ' quien convoca · ' . (int)$tarea['puntos_asistencia'] . ' cada asistente' : ($tipo[3] ? (int)$tarea['puntos_asistencia'] . ' por asistir' : (int)$tarea['puntos'] . ' al validar') ?></td></tr>
      <?php if ($tarea['alcance'] === 'abierta'): ?><tr><td class="muted">Visible para</td><td><?= PROMOTOR_NIVELES[(int)$tarea['nivel_minimo']][2] ?> <?= e(PROMOTOR_NIVELES[(int)$tarea['nivel_minimo']][1]) ?><?= (int)$tarea['nivel_minimo'] ? ' o más' : ' y todos los niveles' ?><?= $tarea['zona'] ? ' · ' . e($tarea['zona']) : '' ?></td></tr><?php endif; ?>
      <tr><td class="muted">Creada por</td><td><?= e((string)$tarea['creador']) ?> · <?= fecha_co($tarea['created_at']) ?></td></tr>
    </table>
    <?php if ($tarea['descripcion']): ?><p style="margin-top:10px;white-space:pre-line"><?= e($tarea['descripcion']) ?></p><?php endif; ?>
    <form method="post" action="<?= url('tareas/estado/' . (int)$tarea['id']) ?>" class="toolbar" style="margin-top:12px">
      <?= \Core\Csrf::campo() ?>
      <?php if ($tarea['estado'] === 'abierta'): ?>
        <button class="btn btn-ghost btn-mini" name="estado" value="cerrada" type="submit">Cerrar tarea</button>
        <button class="btn btn-ghost btn-mini" name="estado" value="cancelada" type="submit" onclick="return confirm('¿Cancelar la tarea? Desaparece de los paneles.')">Cancelar</button>
      <?php else: ?>
        <button class="btn btn-ghost btn-mini" name="estado" value="abierta" type="submit">Reabrir</button>
      <?php endif; ?>
    </form>
  </section>

  <section class="card">
    <h3>Avance</h3>
    <div class="grid g3" style="gap:10px">
      <?php foreach (ASIGNACION_ESTADOS as $k => [$txt]): ?>
      <a class="kpi-mini card <?= $filtro === $k ? 'activo' : '' ?>" style="text-decoration:none;color:inherit;margin:0" href="<?= e($base . 'estado=' . $k) ?>">
        <span class="muted small"><?= e($txt) ?></span><b><?= num($conteo[$k]) ?></b></a>
      <?php endforeach; ?>
    </div>
    <?php if ($conteo['hecha']): ?>
    <form method="post" action="<?= url('tareas/validartodas/' . (int)$tarea['id']) ?>" style="margin-top:12px" onsubmit="return confirm('¿Validar <?= (int)$conteo['hecha'] === 1 ? 'el reporte pendiente' : 'los ' . (int)$conteo['hecha'] . ' reportes pendientes' ?>? Cada persona sumará sus puntos.')">
      <?= \Core\Csrf::campo() ?>
      <button class="btn btn-primary" type="submit">✓ <?= (int)$conteo['hecha'] === 1 ? 'Validar el reporte por validar' : 'Validar los ' . (int)$conteo['hecha'] . ' reportes por validar' ?></button>
    </form>
    <?php endif; ?>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <h3>Personas <?= $filtro ? '· ' . e(ASIGNACION_ESTADOS[$filtro][0] ?? '') . ' <a class="small" href="' . e(url('tareas/ver/' . (int)$tarea['id'])) . '">ver todas</a>' : '' ?></h3>
  <div class="tbl-wrap">
  <table>
    <tr><th>Nombre</th><th>Zona</th><th>Papel</th><th>Estado</th><th style="text-align:right">Reportó</th><th>Comentario</th><th></th></tr>
    <?php if (!$asignaciones): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:20px"><?= $tarea['alcance'] === 'abierta' ? 'Aún nadie se ha apuntado.' : 'Nadie en este estado.' ?></td></tr><?php endif; ?>
    <?php foreach ($asignaciones as $a): $est = ASIGNACION_ESTADOS[$a['estado']]; ?>
    <tr>
      <td><b><?= e($a['nombre']) ?></b> <span class="muted small"><?= promotor_nivel((int)$a['puntos'])['actual'][2] ?> <?= (int)$a['puntos'] ?> pts</span>
        <?php if ($verTelefono && $a['telefono']): ?><br><a class="small" target="_blank" rel="noopener" href="https://wa.me/57<?= e($a['telefono']) ?>">💬 <?= e($a['telefono']) ?></a><?php endif; ?></td>
      <td class="small"><?= e($a['zona'] ?? '') ?></td>
      <td class="small"><?= $a['rol'] === 'asistente' ? 'Asistente' : 'Responsable' ?></td>
      <td><span class="tag tag-<?= ['gris' => 'grey', 'azul' => 'blue', 'oro' => 'gold', 'verde' => 'green', 'rojo' => 'red'][$est[1]] ?>"><?= e($est[0]) ?></span></td>
      <td style="text-align:right"><?= $a['resultado'] !== null ? num((int)$a['resultado']) : '—' ?></td>
      <td class="small" style="max-width:280px"><?= e((string)$a['nota']) ?></td>
      <td style="white-space:nowrap">
        <?php if (in_array($a['estado'], ['hecha', 'no_valida', 'validada'], true)): ?>
        <form method="post" action="<?= url('tareas/validar/' . (int)$a['id']) ?>" style="display:inline-flex;gap:6px">
          <?= \Core\Csrf::campo() ?>
          <?php if ($a['estado'] !== 'validada'): ?><button class="btn btn-primary btn-mini" name="valor" value="si" type="submit">✓ Validar</button><?php endif; ?>
          <?php if ($a['estado'] !== 'no_valida'): ?><button class="btn btn-ghost btn-mini" name="valor" value="no" type="submit">✗ No válida</button><?php endif; ?>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>
