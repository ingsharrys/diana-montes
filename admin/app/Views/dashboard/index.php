<div class="grid g3">
  <div class="card kpi">
    <div class="lbl">Simpatizantes registrados</div>
    <div class="kpi-num"><?= number_format($totalSimp, 0, ',', '.') ?></div>
  </div>
  <div class="card kpi gold">
    <div class="lbl">Nuevos esta semana</div>
    <div class="kpi-num">+<?= number_format($nuevosSemana, 0, ',', '.') ?></div>
  </div>
  <div class="card kpi green">
    <div class="lbl">Acciones rápidas</div>
    <a class="btn btn-gold" style="margin-top:8px;display:inline-block" href="<?= url('simpatizantes/crear') ?>">＋ Registrar simpatizante</a>
  </div>
</div>

<div class="grid g2" style="margin-top:16px">
  <div class="card">
    <h3>Registros por zona</h3>
    <table>
      <tr><th>Zona</th><th>Tipo</th><th style="text-align:right">Total</th></tr>
      <?php foreach ($porZona as $z): ?>
      <tr>
        <td><?= e($z['nombre']) ?></td>
        <td><span class="tag <?= $z['tipo'] === 'rural' ? 'tag-gold' : 'tag-blue' ?>"><?= e($z['tipo']) ?></span></td>
        <td style="text-align:right"><b><?= number_format((int)$z['total'], 0, ',', '.') ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($auditoria): ?>
  <div class="card">
    <h3>Auditoría reciente <span class="tag tag-blue">solo dirección</span></h3>
    <table>
      <tr><th>Fecha</th><th>Usuario</th><th>Acción</th></tr>
      <?php foreach ($auditoria as $a): ?>
      <tr>
        <td class="muted"><?= fecha_co($a['created_at']) ?></td>
        <td><?= e($a['usuario'] ?? 'Sistema') ?></td>
        <td><?= e($a['accion']) ?> <span class="muted"><?= e($a['detalle']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
