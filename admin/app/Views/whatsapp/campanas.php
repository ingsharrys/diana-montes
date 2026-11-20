<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$disponible) return; ?>
<?php if (!$campanasListas): ?>
  <div class="activar-red" style="margin-top:14px"><span class="ar-ico">📣</span><span class="ar-txt"><b>Los mensajes masivos aún no están activos</b>
    La dirección debe pulsar "Actualizar plataforma" en la Vista rápida.</span></div>
<?php return; endif; ?>

<?php
$estadoTag = function (array $c): string {
    if ($c['estado'] === 'cancelada')  return '<span class="tag tag-grey small">Cancelado</span>';
    if ($c['estado'] === 'programada') return '<span class="tag tag-blue small">Programado</span><br><span class="muted small">' . e(date('d/m/Y g:i a', strtotime((string)$c['programada_para']))) . '</span>';
    return (int)$c['pendientes'] > 0 ? '<span class="tag tag-gold small">Enviando</span>' : '<span class="tag tag-green small">Terminado</span>';
};
$pct = fn($a, $b) => $b ? round($a * 100 / $b) : 0;
$diasCola = $cupo['tope'] ? (int)ceil($cupo['pendientes'] / $cupo['tope']) : 0;
?>

<div class="grid g4" style="margin-top:14px">
  <section class="card kpi"><span class="lbl">Tope diario de envíos</span><div class="kpi-num"><?= num($cupo['tope']) ?></div>
    <span class="muted small">Configurable con WA_LIMITE_DIARIO</span></section>
  <section class="card kpi green"><span class="lbl">Enviados hoy</span><div class="kpi-num"><?= num($cupo['enviados_hoy']) ?></div>
    <span class="muted small">Quedan <?= num($cupo['disponible']) ?> por hoy</span></section>
  <section class="card kpi gold"><span class="lbl">En cola</span><div class="kpi-num"><?= num($cupo['pendientes']) ?></div>
    <span class="muted small"><?= $cupo['pendientes'] ? ($diasCola > 1 ? "Unos $diasCola días al ritmo actual" : 'Salen hoy') : 'Nada pendiente' ?></span></section>
  <section class="card" style="display:flex;flex-direction:column;justify-content:center;gap:8px">
    <?php if ($esDireccion): ?>
      <a class="btn btn-primary" href="<?= url('whatsapp/nuevacampana') ?>">📣 Nuevo envío masivo</a>
    <?php endif; ?>
    <span class="muted small">A todos, o por zona, líder, puesto, compromiso, edad, género, profesión o red.</span>
  </section>
</div>

<p class="aviso-legal" style="margin-top:14px">⚖️ <b>Solo reciben quienes aceptaron el tratamiento de datos y no se han dado de baja</b> (Ley 1581 de 2012). WhatsApp exige
  una <b>plantilla aprobada por Meta</b> para escribirle primero a alguien; la del texto libre es <code>mensaje_campana</code> (pestaña Plantillas).
  Los mensajes que piden el voto son propaganda electoral: solo en los 3 meses antes de la votación (Ley 1475 de 2011, art. 35).
  Envía solo lo que la gente espera recibir: si muchos bloquean o reportan el número, Meta baja su calidad y limita los envíos.</p>

<section class="card" style="margin-top:16px">
  <h3>Envíos <span class="tag tag-grey small"><?= num(count($campanas)) ?></span></h3>
  <?php if (!$campanas): ?>
    <p class="muted vacio">Aún no hay envíos masivos. <?= $esDireccion ? 'Crea el primero con el botón de arriba.' : '' ?></p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Envío</th><th>Estado</th><th style="text-align:right">Personas</th><th style="min-width:150px">Avance</th>
        <th style="text-align:right">Entregados</th><th style="text-align:right">Leídos</th><th style="text-align:right">Errores</th><th style="text-align:right">Respuestas</th><?php if ($esDireccion): ?><th></th><?php endif; ?></tr>
    <?php foreach ($campanas as $c):
      $total = (int)$c['total']; $env = (int)$c['enviados']; $cuenta = $total ?: (int)$c['audiencia']; ?>
    <tr>
      <td><b><?= e($c['nombre']) ?></b><br><span class="small"><?= e((string)$c['resumen']) ?></span>
        <br><span class="muted small">Plantilla <?= e((string)$c['plantilla']) ?> · <?= e(date('d/m/Y', strtotime($c['created_at']))) ?><?= $c['autor'] ? ' · ' . e($c['autor']) : '' ?></span>
        <?php if ($c['texto']): ?><details class="small"><summary>Ver el mensaje</summary><p style="white-space:pre-line;margin:6px 0"><?= e($c['texto']) ?></p></details><?php endif; ?></td>
      <td><?= $estadoTag($c) ?></td>
      <td style="text-align:right"><b><?= num($cuenta) ?></b></td>
      <td><?php if ($total): ?><div class="progreso mini"><i style="width:<?= $pct($env, $total) ?>%"></i></div>
          <span class="muted small"><?= num($env) ?> enviados<?= (int)$c['pendientes'] ? ' · ' . num((int)$c['pendientes']) . ' en cola' : '' ?><?= (int)$c['omitidos'] ? ' · ' . num((int)$c['omitidos']) . ' omitidos' : '' ?></span>
          <?php else: ?><span class="muted small">—</span><?php endif; ?></td>
      <td style="text-align:right"><?= $env ? num((int)$c['entregados']) . '<br><span class="muted small">' . $pct((int)$c['entregados'], $env) . ' %</span>' : '—' ?></td>
      <td style="text-align:right"><?= $env ? num((int)$c['leidos']) . '<br><span class="muted small">' . $pct((int)$c['leidos'], $env) . ' %</span>' : '—' ?></td>
      <td style="text-align:right"><?= (int)$c['errores'] ? '<span style="color:var(--bad)">' . num((int)$c['errores']) . '</span>' : '0' ?></td>
      <td style="text-align:right"><?= num((int)($respuestas[$c['id']] ?? 0)) ?></td>
      <?php if ($esDireccion): ?><td>
        <?php if ($c['estado'] === 'programada' || ($c['estado'] === 'enviando' && (int)$c['pendientes'] > 0)): ?>
        <form method="post" action="<?= url('whatsapp/cancelarcampana/' . (int)$c['id']) ?>" onsubmit="return confirm('¿Cancelar este envío? Lo que aún no ha salido ya no se enviará.')">
          <?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini txt-peligro" type="submit">Cancelar</button></form>
        <?php endif; ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small nota">Respuestas: mensajes que escribieron quienes recibieron el envío, en los 3 días siguientes. Se leen en el Resumen de WhatsApp.</p>
  <?php endif; ?>
</section>
