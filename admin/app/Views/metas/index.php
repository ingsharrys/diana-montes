<?php
/** Centro de mando › Metas y pronóstico */
$estados = [
    'cumplida'  => ['¡Meta cumplida!', 'tag-green', 'Ya se alcanzó la meta de simpatizantes.'],
    'en_camino' => ['Vamos bien', 'tag-green', 'Con el ritmo del último mes se llega a la meta antes de la fecha límite.'],
    'en_riesgo' => ['En riesgo', 'tag-gold', 'Con el ritmo actual se llega cerca, pero no alcanza: hay que subir el paso.'],
    'atrasada'  => ['Atrasados', 'tag-red', 'Con el ritmo actual no se llega a la meta: hay que multiplicar el trabajo.'],
    'sin_meta'  => ['Sin meta', 'tag-grey', 'Define la meta de simpatizantes para calcular el plan.'],
];
[$estTxt, $estTag, $estDesc] = $estados[$c['estado']];
$dec = fn(float $v) => number_format($v, $v < 10 ? 1 : 0, ',', '.');
$hoyCumple = $c['meta'] > 0 && $c['por_dia'] > 0 ? min(100, $c['hoy_n'] * 100 / $c['por_dia']) : null;
$semCumple = $c['meta'] > 0 && $c['por_semana'] > 0 ? min(100, $c['ult7'] * 100 / $c['por_semana']) : null;
?>

<!-- ============ Cuenta regresiva ============ -->
<div class="grid g4 cuenta">
  <section class="card kpi">
    <span class="lbl">Faltan para la fecha límite de la meta</span>
    <div class="kpi-num"><?= num($c['dias_hasta']) ?> <small>días</small></div>
    <span class="muted small"><?= $dec($c['semanas']) ?> semanas · <?= $dec($c['meses']) ?> meses · hasta el <?= fecha_corta($c['limite']) ?></span>
  </section>
  <section class="card kpi gold">
    <span class="lbl">Faltan para el día de elecciones</span>
    <div class="kpi-num"><?= num($diasEleccion) ?> <small>días</small></div>
    <span class="muted small"><?= fecha_corta($eleccion) ?></span>
  </section>
  <section class="card kpi violet">
    <span class="lbl">Simpatizantes registrados</span>
    <div class="kpi-num"><?= num($c['total']) ?><?php if ($c['meta']): ?> <small>/ <?= num($c['meta']) ?></small><?php endif; ?></div>
    <?php if ($c['pct'] !== null): ?><div class="progreso mini"><i style="width:<?= round($c['pct'], 1) ?>%"></i></div>
    <span class="muted small"><?= porc($c['pct']) ?> · faltan <b><?= num($c['faltan']) ?></b></span><?php endif; ?>
  </section>
  <section class="card kpi green">
    <span class="lbl">Estado</span>
    <div class="kpi-num"><span class="tag <?= $estTag ?>" style="font-size:15px;padding:6px 12px"><?= e($estTxt) ?></span></div>
    <span class="muted small"><?= e($estDesc) ?></span>
  </section>
</div>

<?php if ($c['meta'] > 0 && $c['faltan'] > 0): ?>
<!-- ============ La meta en cuotas ============ -->
<section class="card cuotas" style="margin-top:16px">
  <h3>Para llegar a <?= num($c['meta']) ?> el <?= fecha_corta($c['limite']) ?> hay que sumar</h3>
  <div class="cuotas-fila">
    <div class="cuota"><b><?= num($c['por_dia']) ?></b><span>por día</span>
      <?php if ($hoyCumple !== null): ?><div class="progreso mini"><i style="width:<?= round($hoyCumple, 1) ?>%"></i></div>
      <small>Hoy van <b><?= num($c['hoy_n']) ?></b></small><?php endif; ?></div>
    <div class="cuota"><b><?= num($c['por_semana']) ?></b><span>por semana</span>
      <?php if ($semCumple !== null): ?><div class="progreso mini"><i style="width:<?= round($semCumple, 1) ?>%"></i></div>
      <small>Últimos 7 días: <b><?= num($c['ult7']) ?></b></small><?php endif; ?></div>
    <div class="cuota"><b><?= num($c['por_mes']) ?></b><span>por mes</span>
      <small>Últimos 30 días: <b><?= num($c['ult30']) ?></b></small></div>
  </div>
</section>
<?php endif; ?>

<div class="grid g2" style="margin-top:16px;align-items:start">
  <!-- ============ Trayectoria ============ -->
  <section class="card">
    <h3>Trayectoria hacia la meta</h3>
    <?= grafica_trayectoria($c['serie'], $c['hoy'], $c['total'], $c['limite'], $c['meta'], $c['pronostico']) ?>
  </section>

  <!-- ============ Pronóstico ============ -->
  <section class="card">
    <h3>Pronóstico</h3>
    <?php if ($c['dias_activos'] === 0): ?>
      <p class="muted vacio">Aún no hay registros para calcular el ritmo.</p>
    <?php else: ?>
      <div class="pron-fila"><span>Ritmo del último mes</span><b><?= $dec($c['ritmo30']) ?> por día</b></div>
      <div class="pron-fila"><span>Ritmo de la última semana</span><b><?= $dec($c['ritmo7']) ?> por día</b></div>
      <?php if ($c['meta'] > 0 && $c['faltan'] > 0): ?>
      <div class="pron-fila"><span>Ritmo necesario</span><b><?= num($c['por_dia']) ?> por día</b></div>
      <?php endif; ?>
      <div class="pron-grande">
        <span>Si seguimos así, el <?= fecha_corta($c['limite']) ?> tendremos</span>
        <b><?= num($c['pronostico']) ?></b>
        <small>simpatizantes<?= $c['pron_min'] !== $c['pron_max'] ? ' (entre ' . num($c['pron_min']) . ' y ' . num($c['pron_max']) . ' según el ritmo de la semana o del mes)' : '' ?><?= $c['meta'] ? ' · ' . porc(min(999, $c['pronostico'] * 100 / max(1, $c['meta'])), 0) . ' de la meta' : '' ?></small>
      </div>
      <?php if ($c['multiplicador'] !== null && $c['multiplicador'] > 1.05): ?>
        <p class="txt-alerta">Hay que trabajar <b><?= number_format($c['multiplicador'], 1, ',', '.') ?> veces</b> más rápido que el último mes: de <?= $dec($c['ritmo']) ?> a <?= $dec($c['requerido']) ?> registros por día.</p>
      <?php elseif ($c['fecha_alcanza']): ?>
        <p class="muted small nota">Al ritmo del último mes, la meta se alcanza alrededor del <b><?= fecha_corta($c['fecha_alcanza']) ?></b>.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($c['meta_votos'] > 0): ?>
      <div class="divider"></div>
      <h3 style="margin-bottom:8px">Votos seguros</h3>
      <div class="pron-fila"><span>Hoy</span><b><?= num($c['seguros']) ?> de <?= num($c['meta_votos']) ?></b></div>
      <div class="pron-fila"><span>Hay que confirmar</span><b><?= num($c['votos_por_dia']) ?> por día</b></div>
      <?php if ($c['tasa'] > 0): ?>
      <div class="pron-fila"><span>Pronóstico (<?= porc($c['tasa'] * 100, 0) ?> de los registrados son voto seguro)</span><b><?= num($c['pron_votos']) ?></b></div>
      <p class="muted small nota">Con esa proporción, para llegar a <?= num($c['meta_votos']) ?> votos seguros se necesitan unos <b><?= num($c['registros_para_votos']) ?></b> registrados<?= $c['meta'] && $c['registros_para_votos'] > $c['meta'] ? ' (más que la meta actual de simpatizantes: conviene subirla o confirmar más votos por llamada)' : '' ?>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<!-- ============ Plan mes a mes ============ -->
<section class="card" style="margin-top:16px">
  <h3>Plan mes a mes <span class="tag tag-grey">meta repartida en partes iguales por día</span></h3>
  <div class="tbl-wrap">
  <table class="cola plan">
    <tr><th>Mes</th><th style="text-align:right">Registrados</th><th style="text-align:right">Meta del mes</th><th style="min-width:140px">Cumplimiento</th><th style="text-align:right">Acumulado</th></tr>
    <?php foreach ($c['meses_plan'] as $m):
      $d = new DateTimeImmutable($m['mes'] . '-01');
      $cum = $m['meta'] ? min(100, $m['real'] * 100 / $m['meta']) : null; ?>
    <tr class="<?= $m['actual'] ? 'mes-actual' : ($m['pasado'] ? 'mes-pasado' : '') ?>">
      <td><b><?= ucfirst(mes_corto($d)) ?> <?= $d->format('Y') ?></b><?= $m['actual'] ? ' <span class="tag tag-blue">este mes</span>' : '' ?></td>
      <td style="text-align:right"><?= $m['pasado'] || $m['actual'] ? num($m['real']) : '—' ?></td>
      <td style="text-align:right"><?= $m['meta'] !== null ? '<b>' . num($m['meta']) . '</b>' : '—' ?></td>
      <td><?php if ($cum !== null && ($m['actual'])): ?><div class="progreso mini"><i style="width:<?= round($cum, 1) ?>%"></i></div><span class="muted small"><?= porc($cum, 0) ?></span>
          <?php elseif ($m['pasado']): ?><span class="muted small">cerrado</span><?php else: ?><span class="muted small">por venir</span><?php endif; ?></td>
      <td style="text-align:right"><?= $m['acumulado'] !== null ? num($m['acumulado']) : ($m['esperado'] !== null ? '<span class="muted">debería ir en</span> <b>' . num($m['esperado']) . '</b>' : '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small nota">La meta de cada mes se recalcula todos los días con lo que falta: si un día se suma menos, la cuota de los días siguientes sube.</p>
</section>

<?php if ($equipo): ?>
<!-- ============ Meta diaria por persona del equipo ============ -->
<section class="card" style="margin-top:16px">
  <h3>Cuota de cada persona del equipo <span class="tag tag-grey">según su meta asignada</span></h3>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Persona</th><th style="text-align:right">Vinculados</th><th style="text-align:right">Meta</th><th style="min-width:120px">Avance</th><th style="text-align:right">Por día</th><th style="text-align:right">Por semana</th></tr>
    <?php foreach ($equipo as $m): $pm = min(100, (int)$m['vinculados'] * 100 / max(1, (int)$m['meta'])); ?>
    <tr>
      <td><b><?= e($m['nombre']) ?></b><br><span class="muted small"><?= e(ucfirst($m['rol'])) ?></span></td>
      <td style="text-align:right"><?= num((int)$m['vinculados']) ?></td>
      <td style="text-align:right"><?= num((int)$m['meta']) ?></td>
      <td><div class="progreso mini"><i style="width:<?= round($pm, 1) ?>%"></i></div><span class="muted small"><?= porc($pm, 0) ?></span></td>
      <td style="text-align:right"><b><?= $m['faltan'] ? num($m['por_dia']) : '✓' ?></b></td>
      <td style="text-align:right"><?= $m['faltan'] ? num($m['por_semana']) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<!-- ============ Ajustes ============ -->
<section class="card card-form" style="margin-top:16px">
  <h3>Ajustar la meta y las fechas</h3>
  <form method="post" action="<?= url('metas/ajustes') ?>" class="form-grid tres">
    <?= \Core\Csrf::campo() ?>
    <label class="field"><span>Meta de simpatizantes</span>
      <input name="meta" data-tipo="numero" maxlength="7" value="<?= $c['meta'] ?: '' ?>" placeholder="Ej: 20000" required></label>
    <label class="field"><span>Fecha para cumplir la meta</span>
      <input type="date" name="fecha_meta" value="<?= e($c['limite']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></label>
    <label class="field"><span>Día de elecciones</span>
      <input type="date" name="fecha_eleccion" value="<?= e($eleccion) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></label>
    <div class="full"><button class="btn btn-primary" type="submit">Guardar</button>
      <span class="muted small" style="margin-left:8px">Consejo: pon la fecha de la meta unas semanas antes de las elecciones, para tener tiempo de confirmar los votos.</span></div>
  </form>
</section>
<?php endif; ?>
