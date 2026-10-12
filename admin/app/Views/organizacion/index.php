<?php
/** Organización › Resumen */
require __DIR__ . '/_tabs.php';
if (!$disponible) return;
$t = $r['testigos']; $p = $r['publicidad']; $g = $r['gastos'];
$cobertura = $t['mesas'] ? min(100, $t['cubiertas'] * 100 / $t['mesas']) : null;
$gastado = $g['colaboradores'] + $g['publicidad'] + $g['otros'];
?>
<div class="grid g4" style="margin-top:14px">
  <a class="card kpi org-kpi" href="<?= url('organizacion/colaboradores') ?>">
    <span class="lbl">Colaboradores activos</span>
    <div class="kpi-num"><?= num($r['colaboradores']['total']) ?></div>
    <?php $np = $r['colaboradores']['pagos']; $nv = $r['colaboradores']['voluntarios']; ?>
    <span class="muted small"><b><?= num($np) ?></b> <?= $np === 1 ? 'pago' : 'pagos' ?> · <b><?= num($nv) ?></b> <?= $nv === 1 ? 'voluntario' : 'voluntarios' ?></span>
    <?php if ($verDinero && $r['nomina_mensual']): ?><span class="muted small" style="display:block">Nómina: <b><?= pesos($r['nomina_mensual']) ?></b> al mes</span><?php endif; ?>
  </a>
  <a class="card kpi gold org-kpi" href="<?= url('organizacion/testigos') ?>">
    <span class="lbl">Mesas con testigo</span>
    <div class="kpi-num"><?= num($t['cubiertas']) ?><?php if ($t['mesas']): ?> <small>/ <?= num($t['mesas']) ?></small><?php endif; ?></div>
    <?php if ($cobertura !== null): ?><div class="progreso mini"><i style="width:<?= round($cobertura, 1) ?>%"></i></div>
    <span class="muted small"><?= porc($cobertura, 0) ?> cubiertas · faltan <b><?= num(max(0, $t['mesas'] - $t['cubiertas'])) ?></b></span>
    <?php else: ?><span class="muted small">Carga las mesas de cada puesto en Territorio.</span><?php endif; ?>
  </a>
  <a class="card kpi violet org-kpi" href="<?= url('organizacion/publicidad') ?>">
    <span class="lbl">Publicidad en bodega</span>
    <div class="kpi-num"><?= num($p['bodega']) ?> <small>piezas</small></div>
    <span class="muted small"><?= num($p['entregado']) ?> entregadas<?= $p['pedido'] ? ' · ' . num($p['pedido']) . ' por llegar' : '' ?></span>
  </a>
  <?php if ($verDinero): ?>
  <a class="card kpi green org-kpi" href="<?= url('organizacion/gastos') ?>">
    <span class="lbl">Gastado</span>
    <div class="kpi-num"><?= pesos($gastado) ?></div>
    <?php if ($tope): $pt = $gastado * 100 / $tope; ?><div class="progreso mini <?= $pt > 90 ? 'alerta' : '' ?>"><i style="width:<?= min(100, round($pt, 1)) ?>%"></i></div>
    <span class="muted small"><?= porc($pt, 0) ?> del tope de <?= pesos($tope) ?></span>
    <?php else: ?><span class="muted small">Define el tope de gastos en la pestaña Gastos.</span><?php endif; ?>
  </a>
  <?php else: ?>
  <section class="card kpi green">
    <span class="lbl">Testigos confirmados</span>
    <div class="kpi-num"><?= num($t['confirmados']) ?> <small>/ <?= num($t['testigos']) ?></small></div>
    <span class="muted small"><?= num($t['capacitados']) ?> capacitados · <?= num($t['acreditados']) ?> acreditados</span>
  </section>
  <?php endif; ?>
</div>

<div class="grid g2" style="margin-top:16px">
  <section class="card embudo">
    <h3>Testigos electorales: de propuesto a acreditado</h3>
    <?php $paso = fn($n) => $t['testigos'] ? $n * 100 / max(1, $t['testigos']) : 0; ?>
    <?= grafica_barras([
        ['Asignados', $t['testigos'], '#7C3AED', num($t['testigos']) . ' testigos'],
        ['Confirmados', $t['confirmados'], '#9363ff', num($t['confirmados']) . ' confirmados'],
        ['Capacitados', $t['capacitados'], '#E0186C', num($t['capacitados']) . ' capacitados'],
        ['Acreditados', $t['acreditados'], '#1baf7a', num($t['acreditados']) . ' acreditados'],
    ]) ?>
    <?php if ($t['sin_mesas']): ?><p class="muted small nota">⚠ <?= num($t['sin_mesas']) ?> puesto<?= $t['sin_mesas'] === 1 ? '' : 's' ?> sin número de mesas: cárgalo en <a href="<?= url('territorio') ?>">Territorio</a> para medir la cobertura.</p><?php endif; ?>
  </section>
  <?php if ($verDinero): ?>
  <section class="card">
    <h3>¿En qué se va la plata?</h3>
    <?php
    $partes = [['Colaboradores (pagos)', $g['colaboradores'], '#7C3AED'], ['Publicidad impresa', $g['publicidad'], '#E0186C']];
    $i = 2; foreach ($g['por_categoria'] as $cat => $v) $partes[] = [\Models\Organizacion::GASTOS[$cat] ?? $cat, (int)$v, PALETA[$i++ % count(PALETA)]];
    echo grafica_proporcion($partes, 'pesos');
    ?>
  </section>
  <?php else: ?>
  <section class="card embudo">
    <h3>Publicidad impresa</h3>
    <?= grafica_barras([
        ['Recibida', $p['recibido'], '#7C3AED', num($p['recibido']) . ' piezas'],
        ['Entregada a zonas', $p['entregado'], '#E0186C', num($p['entregado']) . ' piezas'],
        ['En bodega', $p['bodega'], '#1baf7a', num($p['bodega']) . ' piezas'],
    ]) ?>
  </section>
  <?php endif; ?>
</div>
