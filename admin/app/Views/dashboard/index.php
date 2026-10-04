<?php if ($puedeActivarRed): ?>
<div class="activar-red">
  <span class="ar-ico">🚀</span>
  <span class="ar-txt">
    <b>Activa la red de Súper Promotores y el mapa</b>
    Cada simpatizante recibirá su enlace personal, su QR y su panel con ranking para invitar a familiares y amigos,
    y podrás ver en un mapa a quienes compartan su ubicación. Se hace una sola vez y no borra ningún dato.
  </span>
  <form method="post" action="<?= url('dashboard/activarred') ?>">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-gold" type="submit">Activar ahora</button>
  </form>
</div>
<?php endif; ?>

<div class="grid <?= $redActiva ? 'g4' : 'g3' ?>">
  <div class="card kpi">
    <div class="lbl">Simpatizantes registrados</div>
    <div class="kpi-num"><?= number_format($totalSimp, 0, ',', '.') ?></div>
  </div>
  <div class="card kpi gold">
    <div class="lbl">Nuevos esta semana</div>
    <div class="kpi-num">+<?= number_format($nuevosSemana, 0, ',', '.') ?></div>
  </div>
  <?php if ($redActiva): ?>
  <div class="card kpi violet">
    <div class="lbl">Promotores activos <span class="muted">(ya invitaron)</span></div>
    <div class="kpi-num"><?= number_format($promotores, 0, ',', '.') ?></div>
  </div>
  <div class="card kpi green">
    <div class="lbl">Con ubicación en el mapa</div>
    <div class="kpi-num"><?= number_format($conUbicacion, 0, ',', '.') ?></div>
    <a class="small" href="<?= url('mapa') ?>">Ver mapa →</a>
  </div>
  <?php else: ?>
  <div class="card kpi green">
    <div class="lbl">Acciones rápidas</div>
    <a class="btn btn-gold" style="margin-top:8px;display:inline-block" href="<?= url('simpatizantes/crear') ?>">＋ Registrar simpatizante</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($avance): ?>
<div class="card mi-avance" style="margin-top:16px">
  <h3>⭐ Mi avance <span class="tag tag-blue">puesto #<?= $avance['puesto'] ?> de <?= $avance['equipo'] ?> en el equipo</span></h3>
  <?php if ($avance['progreso'] !== null): ?>
    <div class="progreso"><i style="width:<?= $avance['progreso'] ?>%"></i></div>
    <div class="progreso-txt">
      <span><b><?= $avance['vinculados'] ?></b> de <?= $avance['meta'] ?> simpatizantes en tu red</span>
      <span><?= $avance['progreso'] ?>%<?= $avance['progreso'] >= 100 ? ' 🎉 ¡Meta cumplida!' : '' ?></span>
    </div>
  <?php else: ?>
    <p><b><?= $avance['vinculados'] ?></b> simpatizantes en tu red. <span class="muted">La dirección aún no te asignó una meta.</span></p>
  <?php endif; ?>
  <p class="muted small" style="margin-top:8px">Comparte tu enlace o tu QR desde <a href="<?= url('simpatizantes') ?>">Simpatizantes</a> para crecer más rápido.</p>
</div>
<?php endif; ?>

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

  <?php if ($redActiva): ?>
  <div class="card">
    <h3>🏆 Top Súper Promotores <span class="tag tag-grey">ciudadanos que más invitan</span></h3>
    <?php if (!$topPromotores): ?>
      <p class="muted">Todavía nadie ha invitado a alguien con su enlace personal. Anima a los simpatizantes a abrir su panel y compartir su QR.</p>
    <?php else: ?>
    <table>
      <tr><th>#</th><th>Promotor</th><th>Zona</th><th style="text-align:right">Invitados</th></tr>
      <?php foreach ($topPromotores as $i => $p): $nv = promotor_nivel((int)$p['invitados']); ?>
      <tr>
        <td class="muted"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></td>
        <td><b><?= e($p['nombre']) ?></b><br><span class="muted small"><?= $nv['actual'][2] . ' ' . e($nv['actual'][1]) ?> · red de <?= e($p['lider']) ?></span></td>
        <td><?= e($p['zona'] ?? '—') ?></td>
        <td style="text-align:right"><b><?= (int)$p['invitados'] ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($rankingEquipo || $auditoria): ?>
<div class="grid g2" style="margin-top:16px">
  <?php if ($rankingEquipo): ?>
  <div class="card">
    <h3>👥 Ranking del equipo <span class="tag tag-grey">simpatizantes vinculados vs. meta</span></h3>
    <table>
      <tr><th>Miembro</th><th>Avance</th><th style="text-align:right">Red</th></tr>
      <?php foreach ($rankingEquipo as $m): $pct = $m['meta'] ? min(100, (int)round($m['vinculados'] * 100 / $m['meta'])) : null; ?>
      <tr>
        <td><b><?= e($m['nombre']) ?></b> <span class="muted small"><?= e($m['rol']) ?></span></td>
        <td style="min-width:120px">
          <?php if ($pct !== null): ?>
            <div class="progreso mini"><i style="width:<?= $pct ?>%"></i></div>
            <span class="muted small"><?= $pct ?>% de <?= (int)$m['meta'] ?></span>
          <?php else: ?><span class="muted small">sin meta</span><?php endif; ?>
        </td>
        <td style="text-align:right"><b><?= (int)$m['vinculados'] ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

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
<?php endif; ?>
