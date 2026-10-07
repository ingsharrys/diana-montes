<?php defined('PORTAL') or exit;
$puedeContactar = portal_puede($yo, 'contactar');
$iniciales = fn(string $n) => mb_strtoupper(mb_substr($n, 0, 1) . mb_substr((string)strstr($n, ' '), 1, 1));
?>
<section class="card">
  <h2>🤝 Tus invitados <span class="der"><?= count($invitados) ?> directos · <?= $tamanoRed ?> en toda tu red</span></h2>
  <?php if (!$invitados): ?>
    <p class="vacio">Aún no has invitado a nadie. Comparte tu enlace desde <a href="<?= e(portal_url()) ?>">Inicio</a>: cada persona suma <?= RED_PUNTOS_INVITADO ?> puntos.</p>
  <?php else: ?>
    <ul class="lista">
      <?php foreach ($invitados as $i): $nv = promotor_nivel(red_puntos_fila($i)); ?>
      <li>
        <span class="avatar"><?= e($iniciales($i['nombre'])) ?></span>
        <span class="info">
          <b><?= e($i['nombre']) ?> <?= $nv['actual'][2] ?></b>
          <span><?= e($i['zona'] ?? '') ?> · desde <?= date('d/m/Y', strtotime($i['created_at'])) ?><?= (int)$i['invitados'] ? ' · invitó a ' . (int)$i['invitados'] : '' ?></span>
        </span>
        <?php if ($puedeContactar && $i['telefono']): ?>
          <a class="wa-ico" href="https://wa.me/57<?= e($i['telefono']) ?>?text=<?= rawurlencode('¡Hola, ' . mb_convert_case(mb_strtolower((string)strtok($i['nombre'], ' ')), MB_CASE_TITLE) . '! Te escribe ' . $yo['primer_nombre'] . ', de la red de Diana Lucía Montes. ') ?>"
             target="_blank" rel="noopener" aria-label="Escribir a <?= e($i['nombre']) ?> por WhatsApp" title="Escribir por WhatsApp">💬</a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$puedeContactar): ?>
      <div class="bloqueado" style="margin-top:10px"><b>🔒 Escribir por WhatsApp a tus invitados</b>Se desbloquea en el nivel <?= PROMOTOR_NIVELES[RED_PERMISOS['contactar'][0]][2] ?> <?= e(PROMOTOR_NIVELES[RED_PERMISOS['contactar'][0]][1]) ?>.</div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2>🌳 Toda tu red</h2>
  <?php if (!portal_puede($yo, 'red_completa')): ?>
    <div class="bloqueado"><b>🔒 Ver a los invitados de tus invitados</b>Llega al nivel <?= PROMOTOR_NIVELES[RED_PERMISOS['red_completa'][0]][2] ?> <?= e(PROMOTOR_NIVELES[RED_PERMISOS['red_completa'][0]][1]) ?> (<?= PROMOTOR_NIVELES[RED_PERMISOS['red_completa'][0]][0] ?> puntos) para ver cómo crece tu red.</div>
  <?php elseif (!$redCompleta): ?>
    <p class="vacio">Cuando tus invitados inviten a otras personas, aparecerán aquí.</p>
  <?php else: ?>
    <p class="small muted">Personas que llegaron gracias a tus invitados. Por privacidad solo ves su nombre.</p>
    <?php $capa = 0; foreach ($redCompleta as $r): if ($r['nivel_red'] !== $capa): $capa = $r['nivel_red']; ?>
      <div class="capa"><?= $capa ?>.º nivel de tu red</div>
    <?php endif; ?>
      <div class="check-persona"><?= e(promotor_nombre_corto($r['nombre'])) ?><span>invitado por <?= e(promotor_nombre_corto((string)$r['invito'])) ?></span></div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
