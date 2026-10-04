<?php
/** Centro de mando › Territorio */
$avanceTotal = $metaVotos > 0 ? min(100, $totales['seguros'] * 100 / $metaVotos) : null;
$urlSinPuesto = url('simpatizantes/verificar') . ((defined('USE_REWRITE') && USE_REWRITE === false) ? '&' : '?') . 'estado=sin_puesto';
?>
<?php if (!$conPotencial): ?>
  <div class="activar-red"><span class="ar-ico">🗳</span><span class="ar-txt"><b>Falta actualizar la plataforma</b>
    La dirección debe pulsar "Actualizar plataforma" en la Vista rápida para registrar mesas y potencial electoral por puesto.</span></div>
<?php endif; ?>

<div class="grid g3">
  <section class="card">
    <h3><?= $soloLider ? 'Votos seguros de tu red' : 'Votos seguros vs. meta para ganar' ?></h3>
    <p class="hero-num" style="margin:6px 0 2px;text-align:left"><?= num($totales['seguros']) ?>
      <?php if ($metaVotos): ?><span class="muted" style="font-size:18px">/ <?= num($metaVotos) ?></span><?php endif; ?></p>
    <?php if ($avanceTotal !== null): ?>
      <div class="progreso"><i style="width:<?= round($avanceTotal, 1) ?>%"></i></div>
      <p class="muted small"><?= porc($avanceTotal) ?> de los votos necesarios · faltan <b><?= num(max(0, $metaVotos - $totales['seguros'])) ?></b></p>
    <?php elseif (!$soloLider): ?>
      <p class="muted small">Define abajo cuántos votos se necesitan para ganar.</p>
    <?php endif; ?>
    <p class="muted small nota">Voto seguro = compromiso "Voto seguro", "Voluntario" o "Testigo", confirmado por el equipo.</p>
  </section>

  <section class="card">
    <h3>Base registrada</h3>
    <p class="hero-num" style="margin:6px 0 2px;text-align:left"><?= num($totales['registrados']) ?></p>
    <p class="muted small">simpatizantes<?= $potencialTotal ? ' · ' . porc($totales['registrados'] * 100 / $potencialTotal) . ' de los ' . num($potencialTotal) . ' habilitados registrados en los puestos' : '' ?></p>
    <?php if ($totales['sin_puesto']): ?>
      <p class="nota"><a href="<?= e($urlSinPuesto) ?>">⚠ <?= num($totales['sin_puesto']) ?> sin puesto de votación → completar</a></p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Meta de votos para ganar</h3>
    <?php if ($puedeEditar): ?>
      <form method="post" action="<?= url('territorio/meta') ?>" class="editar-meta" style="margin-top:0">
        <?= \Core\Csrf::campo() ?>
        <input name="meta_votos" inputmode="numeric" placeholder="Ej: 15000" value="<?= $metaVotos ?: '' ?>" aria-label="Meta de votos" required>
        <button class="btn btn-primary btn-mini" type="submit">Guardar</button>
      </form>
      <p class="muted small nota">Referencia 2023 en Garzón: 33.871 votos válidos. Con 3 o 4 candidatos se suele ganar con el 35–45 % (unos 12.000 a 15.000 votos).
        Se reparte entre los puestos según cuántos habilitados tiene cada uno.</p>
    <?php elseif ($metaVotos): ?>
      <p class="hero-num" style="margin:6px 0 2px;text-align:left"><?= num($metaVotos) ?></p>
      <p class="muted small">definida por la dirección</p>
    <?php else: ?>
      <p class="muted small"><?= $soloLider ? 'En esta vista ves los votos de tu red por puesto.' : 'La dirección aún no la define.' ?></p>
    <?php endif; ?>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <h3>Puestos de votación <span class="tag tag-grey"><?= $metaVotos ? 'ordenados por lo que falta' : 'orden alfabético' ?></span></h3>
  <?php if ($sinPotencial && $puedeEditar): ?>
    <p class="alert alert-error" style="font-weight:500">Falta el número de habilitados de <?= $sinPotencial ?> puesto<?= $sinPotencial === 1 ? '' : 's' ?>.
      Cárgalo con el dato de la Registraduría (potencial electoral por puesto) para repartir bien la meta.</p>
  <?php endif; ?>
  <?php if (!$puestos): ?>
    <p class="muted vacio">No hay puestos de votación. Créalos en Catálogos.</p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola terr">
    <tr>
      <th>Puesto</th><th>Mesas</th><th>Habilitados</th><th style="text-align:right">Registrados</th><th style="text-align:right">Votos seguros</th>
      <?php if ($metaVotos): ?><th style="text-align:right">Meta</th><th style="min-width:130px">Avance</th><th style="text-align:right">Faltan</th><?php endif; ?>
      <?php if ($puedeEditar): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($puestos as $p): $fid = 'fp' . (int)$p['id']; ?>
    <tr>
      <td><b><?= e($p['nombre']) ?></b><br><span class="muted small"><?= e($p['zona'] ?? '—') ?></span>
        <?php if ($puedeEditar): ?><form id="<?= $fid ?>" method="post" action="<?= url('territorio/puesto/' . (int)$p['id']) ?>"><?= \Core\Csrf::campo() ?></form><?php endif; ?></td>
      <?php if ($puedeEditar): ?>
        <td><input class="in-mesa" name="mesas" form="<?= $fid ?>" inputmode="numeric" value="<?= e((string)$p['mesas']) ?>" placeholder="—" aria-label="Mesas de <?= e($p['nombre']) ?>"></td>
        <td><input class="in-mesa" style="width:86px" name="potencial" form="<?= $fid ?>" inputmode="numeric" value="<?= e((string)$p['potencial']) ?>" placeholder="—" aria-label="Habilitados de <?= e($p['nombre']) ?>"></td>
      <?php else: ?>
        <td><?= $p['mesas'] ? num((int)$p['mesas']) : '—' ?></td>
        <td><?= $p['potencial'] ? num((int)$p['potencial']) : '—' ?></td>
      <?php endif; ?>
      <td style="text-align:right"><b><?= num((int)$p['registrados']) ?></b><?php if ($p['cobertura'] !== null): ?><br><span class="muted small"><?= porc($p['cobertura']) ?></span><?php endif; ?></td>
      <td style="text-align:right"><b><?= num((int)$p['seguros']) ?></b></td>
      <?php if ($metaVotos): ?>
        <td style="text-align:right"><?= $p['meta'] !== null ? num($p['meta']) : '—' ?></td>
        <td><?php if ($p['avance'] !== null): ?>
          <div class="progreso mini"><i style="width:<?= round($p['avance'], 1) ?>%"></i></div>
          <span class="muted small"><?= porc($p['avance'], 0) ?></span><?php else: ?><span class="muted small">sin potencial</span><?php endif; ?></td>
        <td style="text-align:right"><?= $p['brecha'] !== null ? '<b>' . num($p['brecha']) . '</b>' : '—' ?></td>
      <?php endif; ?>
      <?php if ($puedeEditar): ?><td><button class="btn btn-ghost btn-mini" type="submit" form="<?= $fid ?>">Guardar</button></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small nota">La meta de cada puesto = meta de votos × (habilitados del puesto ÷ habilitados de todos los puestos). "Faltan" indica dónde enfocar el trabajo territorial.</p>
  <?php endif; ?>
</section>
