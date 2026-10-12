<?php
/** Organización › Testigos electorales: una persona por mesa, de propuesto a acreditado */
use Models\Organizacion as O;
require __DIR__ . '/_tabs.php';
if (!$disponible) return;
$x = $editar ?? ($prefill ? ['nombre' => $prefill['nombre'], 'documento' => $prefill['documento'], 'telefono' => $prefill['telefono'], 'puesto_id' => $prefill['puesto_id']] : []);
$mesasTot = array_sum(array_map(fn($p) => (int)$p['mesas'], $cobertura));
$cubiertas = array_sum(array_map(fn($p) => (int)$p['cubiertas'], $cobertura));
$estTag = ['propuesto' => 'tag-grey', 'confirmado' => 'tag-blue', 'capacitado' => 'tag-gold', 'acreditado' => 'tag-green'];
?>
<div class="grid g2" style="margin-top:14px;align-items:start">
  <section class="card">
    <h3>Cobertura por puesto
      <?php if ($mesasTot): ?><span class="tag tag-blue"><?= num($cubiertas) ?> de <?= num($mesasTot) ?> mesas · <?= porc($cubiertas * 100 / $mesasTot, 0) ?></span><?php endif; ?></h3>
    <?php if (!$cobertura): ?><p class="muted vacio">No hay puestos de votación. Créalos en Catálogos.</p><?php else: ?>
    <div class="tbl-wrap"><table class="cola">
      <tr><th>Puesto</th><th style="text-align:right">Mesas</th><th style="min-width:130px">Con testigo</th><th style="text-align:right">Testigos</th><th style="text-align:right">Acreditados</th></tr>
      <?php foreach ($cobertura as $p): $pc = $p['mesas'] ? min(100, (int)$p['cubiertas'] * 100 / (int)$p['mesas']) : null; ?>
      <tr class="<?= $puesto === (int)$p['id'] ? 'mes-actual' : '' ?>">
        <td><a href="<?= url_con('organizacion/testigos', ['puesto' => $p['id']]) ?>"><b><?= e($p['nombre']) ?></b></a><br><span class="muted small"><?= e($p['zona'] ?? '') ?></span></td>
        <td style="text-align:right"><?= $p['mesas'] ? num((int)$p['mesas']) : '<span class="muted">—</span>' ?></td>
        <td><?php if ($pc !== null): ?><div class="progreso mini"><i style="width:<?= round($pc, 1) ?>%"></i></div><span class="muted small"><?= num((int)$p['cubiertas']) ?> · faltan <b><?= num(max(0, (int)$p['mesas'] - (int)$p['cubiertas'])) ?></b></span>
          <?php else: ?><span class="muted small">carga las mesas en Territorio</span><?php endif; ?></td>
        <td style="text-align:right"><?= num((int)$p['testigos']) ?></td>
        <td style="text-align:right"><?= num((int)$p['acreditados']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>

  <div>
  <section class="card card-form" id="form">
    <h3><?= $editar ? 'Editar testigo' : 'Asignar testigo' ?></h3>
    <form method="post" action="<?= url('organizacion/guardartestigo/' . (int)($editar['id'] ?? 0)) ?>" novalidate>
      <?= \Core\Csrf::campo() ?>
      <div class="form-grid">
        <label class="field full"><span>Nombre completo *</span><input name="nombre" maxlength="120" value="<?= e($x['nombre'] ?? '') ?>" required></label>
        <label class="field"><span>Documento * (para la credencial)</span><input name="documento" data-tipo="numero" maxlength="12" value="<?= e($x['documento'] ?? '') ?>" required></label>
        <label class="field"><span>Celular</span><input name="telefono" data-tipo="numero" maxlength="10" value="<?= e($x['telefono'] ?? '') ?>" placeholder="3XX XXX XXXX"></label>
        <label class="field"><span>Puesto de votación *</span>
          <select name="puesto_id" required><option value="">Selecciona…</option>
            <?php foreach ($cobertura as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($x['puesto_id'] ?? $puesto) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?><?= $p['mesas'] ? ' (' . (int)$p['mesas'] . ' mesas)' : '' ?></option><?php endforeach; ?>
          </select></label>
        <label class="field"><span>Mesa (vacío = testigo del puesto)</span><input name="mesa" data-tipo="numero" maxlength="3" value="<?= e((string)($x['mesa'] ?? '')) ?>"></label>
        <label class="field"><span>Estado</span>
          <select name="estado"><?php foreach (O::TESTIGO_ESTADOS as $k => $t): ?><option value="<?= $k ?>" <?= ($x['estado'] ?? 'propuesto') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Notas</span><input name="notas" maxlength="255" value="<?= e($x['notas'] ?? '') ?>" placeholder="Ej: capacitación sáb 3 p. m."></label>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-primary" type="submit"><?= $editar ? 'Guardar cambios' : 'Asignar' ?></button>
        <?php if ($editar || $prefill): ?><a class="btn btn-ghost" href="<?= url('organizacion/testigos') ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <?php if ($editar): ?>
      <form method="post" action="<?= url('organizacion/borrar/testigo/' . (int)$editar['id']) ?>" style="margin-top:10px" onsubmit="return confirm('¿Quitar este testigo?')">
        <?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini txt-peligro" type="submit">Quitar testigo</button></form>
    <?php endif; ?>
    <p class="muted small nota">Pasos: <b>propuesto</b> → <b>confirmado</b> (aceptó) → <b>capacitado</b> → <b>acreditado</b> (credencial de la Registraduría, la tramita la campaña).</p>
  </section>

  <?php if ($sugeridos): ?>
  <section class="card" style="margin-top:16px">
    <h3>Simpatizantes que quieren ser testigos <span class="tag tag-gold"><?= num($nSugeridos) ?></span></h3>
    <p class="muted small" style="margin-bottom:8px">Marcados con el compromiso "Testigo electoral" y aún sin mesa.</p>
    <?php foreach ($sugeridos as $s): ?>
      <div class="pron-fila"><span><b style="color:var(--ink)"><?= e($s['nombre']) ?></b><br><?= e($s['puesto'] ?? 'sin puesto') ?></span>
        <a class="btn btn-ghost btn-mini" href="<?= url_con('organizacion/testigos', ['sugerido' => $s['id']]) ?>#form">Asignar mesa</a></div>
    <?php endforeach; ?>
    <?php if ($nSugeridos > count($sugeridos)): ?><p class="muted small nota">Se muestran <?= count($sugeridos) ?> de <?= num($nSugeridos) ?>. A medida que los asignas aparecen los siguientes.</p><?php endif; ?>
  </section>
  <?php endif; ?>
  </div>
</div>

<section class="card" style="margin-top:16px">
  <h3>Testigos <?= $puesto ? 'del puesto' : '' ?> <span class="tag tag-grey"><?= num(count($lista)) ?></span>
    <?php if ($puesto): ?><a class="btn btn-ghost btn-mini" href="<?= url('organizacion/testigos') ?>">Ver todos</a><?php endif; ?></h3>
  <?php if (!$lista): ?><p class="muted vacio">Aún no hay testigos asignados<?= $puesto ? ' en este puesto' : '' ?>.</p><?php else: ?>
  <div class="tbl-wrap"><table class="cola">
    <tr><th>Puesto · mesa</th><th>Testigo</th><th>Celular</th><th>Estado</th><th></th></tr>
    <?php foreach ($lista as $t): ?>
    <tr><td><b><?= e($t['puesto']) ?></b><br><span class="muted small"><?= $t['mesa'] ? 'Mesa ' . (int)$t['mesa'] : 'Testigo del puesto' ?></span></td>
      <td><?= e($t['nombre']) ?><br><span class="muted small">CC <?= e($t['documento'] ?? '') ?></span></td>
      <td><?= $t['telefono'] ? '<a class="tel" href="https://wa.me/57' . e($t['telefono']) . '" target="_blank" rel="noopener">' . e($t['telefono']) . '</a>' : '—' ?></td>
      <td><span class="tag <?= $estTag[$t['estado']] ?>"><?= e(O::TESTIGO_ESTADOS[$t['estado']]) ?></span></td>
      <td><a class="btn btn-ghost btn-mini" href="<?= url_con('organizacion/testigos', ['editar' => $t['id'], 'puesto' => $puesto]) ?>#form">Editar</a></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
