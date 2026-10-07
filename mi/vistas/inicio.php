<?php defined('PORTAL') or exit;
$nv = $yo['nivel'];
$textoWa = '¡Hola! 👋 Me sumé a la campaña de Diana Lucía Montes a la Alcaldía de Garzón. Súmate tú también, toma menos de un minuto: ' . $enlace;
$proximos = array_values(array_filter($misTareas, fn($t) => in_array($t['estado'], ['pendiente', 'aceptada'], true)));
?>
<!-- Nivel, puntos y avance -->
<section class="card">
  <div class="nivel">
    <span class="emoji"><?= $nv['actual'][2] ?></span>
    <div>
      <b><?= e($nv['actual'][1]) ?></b>
      <?php if ($nv['siguiente']): ?>
        <span>Te faltan <b style="display:inline;font-size:inherit"><?= $nv['faltan'] ?> puntos</b> para ser <?= e($nv['siguiente'][1]) ?> <?= $nv['siguiente'][2] ?></span>
      <?php else: ?>
        <span>¡Llegaste al nivel más alto! Gracias por tanto 💜</span>
      <?php endif; ?>
    </div>
    <div class="puntos"><b><?= $yo['puntos'] ?></b><span>puntos</span></div>
  </div>
  <div class="barra" role="progressbar" aria-label="Avance al siguiente nivel" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $nv['progreso'] ?>"><i style="width:<?= $nv['progreso'] ?>%"></i></div>
  <div class="barra-txt">
    <span><?= e($nv['actual'][1]) ?> · <?= $nv['actual'][0] ?></span>
    <span><?= $nv['siguiente'] ? e($nv['siguiente'][1]) . ' · ' . $nv['siguiente'][0] . ' pts' : '🏆' ?></span>
  </div>
  <div class="kpis">
    <div class="kpi"><b><?= (int)$yo['invitados'] ?></b><span>invitados por ti</span></div>
    <div class="kpi"><b><?= $tamanoRed ?></b><span>personas en tu red</span></div>
    <div class="kpi"><b><?= $porHacer ?></b><span>tareas por hacer</span></div>
    <div class="kpi"><b><?= $puesto ? '#' . $puesto : '—' ?></b><span>en el ranking</span></div>
  </div>
</section>

<?php if ($proximos): ?>
<section class="card">
  <h2>📌 Te esperan <span class="der"><a href="<?= e(portal_url('tareas')) ?>">Ver todas</a></span></h2>
  <ul class="lista">
    <?php foreach (array_slice($proximos, 0, 3) as $t): $tipo = TAREA_TIPOS[$t['tipo']] ?? TAREA_TIPOS['otra']; ?>
    <li>
      <span class="avatar" style="background:var(--rosa-soft)"><?= $tipo[1] ?></span>
      <span class="info"><b><?= e($t['titulo']) ?></b>
        <span><?= $t['rol'] === 'asistente' ? 'Invitación' : e($tipo[0]) ?><?= $t['fecha'] ? ' · ' . e(red_fecha($t['fecha'])) : '' ?></span></span>
      <span class="estado <?= ASIGNACION_ESTADOS[$t['estado']][1] ?>"><?= $t['estado'] === 'pendiente' ? 'Responder' : 'En curso' ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<!-- Enlace personal y QR -->
<section class="card">
  <h2>🔗 Tu enlace para invitar</h2>
  <div class="link" id="enlace"><?= e($enlace) ?></div>
  <div class="btns">
    <a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($textoWa) ?>">📲 Invitar por WhatsApp</a>
    <button type="button" class="btn btn-soft" id="btnCopiar">Copiar enlace</button>
  </div>
  <div class="qr-wrap">
    <div class="qr" id="qr"></div>
    <div class="small muted" style="flex:1;min-width:160px">
      <b style="color:var(--ink)">Tu QR personal.</b> Quien lo escanee llega al registro y queda en tu red. Cada persona suma <b><?= RED_PUNTOS_INVITADO ?> puntos</b>.
      <div class="btns"><button type="button" class="btn btn-rosa btn-mini" id="btnQr">⬇ Descargar QR</button></div>
    </div>
  </div>
</section>

<!-- Lo que puede hacer en su nivel -->
<section class="card">
  <h2>🔓 Lo que puedes hacer</h2>
  <ul class="permisos">
    <?php foreach (RED_PERMISOS as [$min, $texto]): $ok = $yo['nivel_i'] >= $min; ?>
    <li class="<?= $ok ? '' : 'bloq' ?>">
      <span class="ic"><?= $ok ? '✓' : '🔒' ?></span>
      <span><?= e($texto) ?></span>
      <?php if (!$ok): ?><span class="req"><?= PROMOTOR_NIVELES[$min][2] ?> <?= e(PROMOTOR_NIVELES[$min][1]) ?></span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>

<!-- Cómo ganar puntos -->
<section class="card">
  <h2>⭐ Cómo sumar puntos</h2>
  <ul class="permisos">
    <li><span class="ic">+<?= RED_PUNTOS_INVITADO ?></span><span>Por cada persona que se registra con tu enlace o tu QR.</span></li>
    <li><span class="ic">+<?= RED_PUNTOS_SEGURO ?></span><span>Extra cuando el equipo confirma que tu invitado votará por Diana.</span></li>
    <li><span class="ic">+</span><span>Por cada tarea que cumples y el equipo valida (reuniones, puerta a puerta, llamadas…).</span></li>
  </ul>
  <p class="small muted" style="margin-top:8px">Niveles:
    <?php foreach (PROMOTOR_NIVELES as $i => $n): ?><?= $i ? ' · ' : '' ?><?= $n[2] ?> <?= e($n[1]) ?> <?= $n[0] ?><?php endforeach; ?> puntos.</p>
</section>

<!-- Ranking -->
<section class="card">
  <h2>🏆 Ranking de la red</h2>
  <?php if (!$ranking): ?>
    <p class="vacio">Aún nadie tiene puntos. ¡Sé el primero en aparecer aquí!</p>
  <?php else: ?>
    <table class="rank">
      <?php $enTop = false; foreach ($ranking as $i => $r): $esYo = (int)$r['id'] === $id; $enTop = $enTop || $esYo; ?>
      <tr class="<?= $esYo ? 'yo' : '' ?>">
        <td class="pos"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></td>
        <td><?= e(promotor_nombre_corto($r['nombre'])) ?><?= $esYo ? ' (tú)' : '' ?></td>
        <td class="num"><?= (int)$r['puntos'] ?> pts</td>
      </tr>
      <?php endforeach; ?>
      <?php if ($puesto && !$enTop): ?>
      <tr><td colspan="3" style="text-align:center;color:var(--gris);border-bottom:none">···</td></tr>
      <tr class="yo"><td class="pos"><?= $puesto ?></td><td><?= e(promotor_nombre_corto($yo['nombre'])) ?> (tú)</td><td class="num"><?= $yo['puntos'] ?> pts</td></tr>
      <?php endif; ?>
    </table>
  <?php endif; ?>
</section>

<script src="../assets/vendor/qrcode.js"></script>
<script>
(function () {
  const enlace = <?= json_encode($enlace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  document.getElementById('btnCopiar').addEventListener('click', function () {
    const listo = () => { this.textContent = '¡Copiado!'; setTimeout(() => this.textContent = 'Copiar enlace', 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(enlace).then(listo); else listo();
  });
  if (!window.qrcode) { document.querySelector('.qr-wrap').style.display = 'none'; return; }
  const q = qrcode(0, 'M'); q.addData(enlace); q.make();
  document.getElementById('qr').innerHTML = q.createSvgTag(4, 2);
  document.getElementById('btnQr').addEventListener('click', () => {
    const a = document.createElement('a'); a.href = q.createDataURL(10, 4); a.download = 'mi-qr-diana-montes.gif'; a.click();
  });
})();
</script>
