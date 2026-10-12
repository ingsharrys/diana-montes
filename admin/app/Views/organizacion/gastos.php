<?php
/** Organización › Gastos de la campaña frente al tope (solo dirección) */
use Models\Organizacion as O;
require __DIR__ . '/_tabs.php';
if (!$disponible) return;
$x = $editar ?? [];
$g = $r['gastos'];
$gastado = $g['colaboradores'] + $g['publicidad'] + $g['otros'];
$pt = $tope ? $gastado * 100 / $tope : null;
?>
<div class="grid g4" style="margin-top:14px">
  <section class="card kpi green"><span class="lbl">Total gastado</span><div class="kpi-num"><?= pesos($gastado) ?></div>
    <?php if ($pt !== null): ?><div class="progreso mini <?= $pt > 90 ? 'alerta' : '' ?>"><i style="width:<?= min(100, round($pt, 1)) ?>%"></i></div>
    <span class="muted small"><?= porc($pt, 0) ?> del tope · quedan <b><?= pesos(max(0, $tope - $gastado)) ?></b></span><?php endif; ?></section>
  <section class="card kpi"><span class="lbl">Pagos a colaboradores</span><div class="kpi-num"><?= pesos($g['colaboradores']) ?></div>
    <span class="muted small">Nómina activa: <?= pesos($r['nomina_mensual']) ?> al mes</span></section>
  <section class="card kpi gold"><span class="lbl">Publicidad impresa</span><div class="kpi-num"><?= pesos($g['publicidad']) ?></div>
    <span class="muted small">pedidos y recibidos</span></section>
  <section class="card kpi violet"><span class="lbl">Otros gastos</span><div class="kpi-num"><?= pesos($g['otros']) ?></div>
    <span class="muted small"><?= num(count($lista)) ?> registros</span></section>
</div>
<?php if ($pt !== null && $pt > 90): ?>
  <p class="alert alert-error" style="margin-top:12px">⚠ La campaña va en el <?= porc($pt, 0) ?> del tope de gastos. Superarlo es causal de sanción: revisa con el contador antes de nuevos compromisos.</p>
<?php endif; ?>

<div class="grid g2" style="margin-top:16px;align-items:start">
  <section class="card">
    <h3>Gastos registrados</h3>
    <?php if (!$lista): ?><p class="muted vacio">Registra transporte, alimentación, sede, eventos y demás gastos. Los pagos a colaboradores y la publicidad impresa se suman solos.</p><?php else: ?>
    <div class="tbl-wrap"><table class="cola">
      <tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th style="text-align:right">Valor</th><th></th></tr>
      <?php foreach ($lista as $gs): ?>
      <tr><td><?= e(date('d/m/Y', strtotime($gs['fecha']))) ?></td>
        <td><b><?= e($gs['concepto']) ?></b><?= $gs['proveedor'] ? '<br><span class="muted small">' . e($gs['proveedor']) . '</span>' : '' ?></td>
        <td class="small"><?= e(O::GASTOS[$gs['categoria']] ?? $gs['categoria']) ?></td>
        <td style="text-align:right"><b><?= pesos((float)$gs['valor']) ?></b></td>
        <td style="white-space:nowrap"><a class="btn btn-ghost btn-mini" href="<?= url_con('organizacion/gastos', ['editar' => $gs['id']]) ?>#form">Editar</a>
          <form method="post" action="<?= url('organizacion/borrar/gasto/' . (int)$gs['id']) ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este gasto?')"><?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini" type="submit" aria-label="Eliminar gasto">✕</button></form></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>

  <div>
  <section class="card card-form" id="form">
    <h3><?= $x ? 'Editar gasto' : 'Registrar gasto' ?></h3>
    <form method="post" action="<?= url('organizacion/guardargasto/' . (int)($x['id'] ?? 0)) ?>" novalidate>
      <?= \Core\Csrf::campo() ?>
      <div class="form-grid">
        <label class="field"><span>Fecha *</span><input type="date" name="fecha" value="<?= e($x['fecha'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required></label>
        <label class="field"><span>Categoría *</span>
          <select name="categoria" required><option value="">Selecciona…</option>
            <?php foreach (O::GASTOS as $k => $t): ?><option value="<?= $k ?>" <?= ($x['categoria'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
        <label class="field full"><span>Concepto *</span><input name="concepto" maxlength="150" value="<?= e($x['concepto'] ?? '') ?>" placeholder="Ej: Refrigerios reunión vereda El Recreo" required></label>
        <label class="field"><span>Valor *</span><input name="valor" data-tipo="numero" maxlength="11" value="<?= !empty($x['valor']) ? (int)$x['valor'] : '' ?>" required></label>
        <label class="field"><span>Proveedor</span><input name="proveedor" maxlength="120" value="<?= e($x['proveedor'] ?? '') ?>"></label>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-primary" type="submit"><?= $x ? 'Guardar cambios' : 'Registrar' ?></button>
        <?php if ($x): ?><a class="btn btn-ghost" href="<?= url('organizacion/gastos') ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="card" style="margin-top:16px">
    <h3>Tope de gastos</h3>
    <form method="post" action="<?= url('organizacion/tope') ?>" class="editar-meta" style="margin-top:0">
      <?= \Core\Csrf::campo() ?>
      <input name="tope" data-tipo="numero" maxlength="12" value="<?= $tope ?: '' ?>" placeholder="Ej: 350000000" aria-label="Tope de gastos">
      <button class="btn btn-primary btn-mini" type="submit">Guardar</button>
    </form>
    <p class="muted small nota">El Consejo Nacional Electoral fija el tope para cada municipio. Todos los ingresos y gastos se reportan en Cuentas Claras con sus soportes.</p>
  </section>
  </div>
</div>
