<?php require __DIR__ . '/_tabs.php'; ?>

<?php if ($disponible):
  $periodos = [7 => '7 días', 30 => '30 días', 90 => '90 días', 0 => 'Todo'];
  $sep = (defined('USE_REWRITE') && USE_REWRITE === false) ? '&' : '?'; ?>

<div class="toolbar" style="margin-top:14px">
  <?php foreach ($periodos as $d => $etq): ?>
    <a class="btn btn-mini <?= $dias === $d ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('whatsapp') . $sep . 'd=' . $d) ?>"><?= $etq ?></a>
  <?php endforeach; ?>
  <span class="spacer"></span>
  <?php if ($esDireccion): ?>
  <form method="post" action="<?= url('whatsapp/procesar') ?>">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-gold btn-mini" type="submit" title="Encola los saludos de hoy y envía la cola sin esperar al proceso automático">▶ Enviar ahora</button>
  </form>
  <?php endif; ?>
</div>

<!-- Embudo del mensaje: enviado → entregado → leído -->
<div class="grid g4 wa-kpis">
  <div class="card kpi"><div class="lbl">Enviados</div><div class="kpi-num"><?= num($m['enviados']) ?></div>
    <span class="muted small"><?= num($m['pendientes']) ?> en cola</span></div>
  <div class="card kpi violet"><div class="lbl">Entregados</div><div class="kpi-num"><?= num($m['entregados']) ?></div>
    <span class="muted small"><?= $m['tasa_entrega'] !== null ? porc($m['tasa_entrega']) . ' de los enviados' : '—' ?></span></div>
  <div class="card kpi green"><div class="lbl">Leídos</div><div class="kpi-num"><?= num($m['leidos']) ?></div>
    <span class="muted small"><?= $m['tasa_lectura'] !== null ? porc($m['tasa_lectura']) . ' de los enviados' : '—' ?></span></div>
  <div class="card kpi" style="border-top-color:var(--bad)"><div class="lbl">Con error</div><div class="kpi-num"><?= num($m['errores']) ?></div>
    <span class="muted small"><?= $m['tasa_error'] !== null ? porc($m['tasa_error']) . ' de los intentos' : '—' ?></span></div>
</div>

<div class="grid g2" style="margin-top:16px">
  <section class="card">
    <h3>Recorrido de los mensajes <span class="tag tag-grey"><?= $dias ? 'últimos ' . $dias . ' días' : 'todo el tiempo' ?></span></h3>
    <div class="embudo"><?= grafica_barras([
        ['Enviados',   $m['enviados'],   RAMPA_NIVEL[1], 'Enviados: ' . num($m['enviados'])],
        ['Entregados', $m['entregados'], RAMPA_NIVEL[2], 'Entregados: ' . num($m['entregados'])],
        ['Leídos',     $m['leidos'],     RAMPA_NIVEL[4], 'Leídos: ' . num($m['leidos'])],
    ]) ?></div>
    <div class="calidad-fila sin-barra" style="margin-top:10px"><span>💬 Respuestas recibidas</span><b><?= num($m['respuestas']) ?></b></div>
    <div class="calidad-fila sin-barra"><span>🚫 Dados de baja (SALIR)</span><b><?= num($m['bajas']) ?></b></div>
    <div class="calidad-fila sin-barra"><span>⏭ Omitidos (baja o vencidos)</span><b><?= num($m['omitidos']) ?></b></div>
    <p class="muted small nota">"Leído" depende de que la persona tenga activadas las confirmaciones de lectura: el número real suele ser mayor.</p>
  </section>

  <section class="card">
    <h3>Enviados por día <span class="tag tag-grey">últimos 14 días</span></h3>
    <?= grafica_columnas($porDia, '#7C3AED', '#C4B2FF', 'mensajes enviados') ?>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <h3>Por ocasión</h3>
  <?php if (!$ocasiones): ?>
    <p class="muted vacio">Todavía no hay mensajes. Activa ocasiones en la pestaña <a href="<?= url('whatsapp/ocasiones') ?>">Ocasiones</a>.</p>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <tr><th>Ocasión</th><th style="text-align:right">En cola</th><th style="text-align:right">Enviados</th><th style="text-align:right">Entregados</th>
        <th style="text-align:right">Leídos</th><th style="text-align:right">Con error</th><th style="min-width:140px">Lectura</th></tr>
    <?php foreach ($ocasiones as $o): $lect = $o['enviados'] ? $o['leidos'] * 100 / $o['enviados'] : null; ?>
    <tr>
      <td><b><?= e($o['nombre']) ?></b></td>
      <td style="text-align:right"><?= num((int)$o['pendientes']) ?></td>
      <td style="text-align:right"><?= num((int)$o['enviados']) ?></td>
      <td style="text-align:right"><?= num((int)$o['entregados']) ?></td>
      <td style="text-align:right"><b><?= num((int)$o['leidos']) ?></b></td>
      <td style="text-align:right"><?= (int)$o['errores'] ? '<b style="color:var(--bad)">' . num((int)$o['errores']) . '</b>' : '0' ?></td>
      <td><?php if ($lect !== null): ?><div class="progreso mini"><i style="width:<?= round($lect, 1) ?>%"></i></div><span class="muted small"><?= porc($lect, 0) ?></span><?php else: ?>—<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<div class="grid g2" style="margin-top:16px">
  <section class="card">
    <h3>Últimos errores</h3>
    <?php if (!$errores): ?><p class="muted vacio">Sin errores. 👍</p><?php else: ?>
    <table>
      <tr><th>Fecha</th><th>Para</th><th>Error</th></tr>
      <?php foreach ($errores as $er): ?>
      <tr>
        <td class="muted small"><?= fecha_co($er['error_at']) ?></td>
        <td><b><?= e(promotor_nombre_corto($er['nombre'])) ?></b><br><span class="muted small"><?= e($er['ocasion']) ?></span></td>
        <td><span class="tag tag-gold"><?= e($er['error_codigo']) ?></span> <span class="small"><?= e($er['error_detalle']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Últimas respuestas recibidas</h3>
    <?php if (!$respuestas): ?><p class="muted vacio">Aún nadie ha respondido.</p><?php else: ?>
    <table>
      <tr><th>Fecha</th><th>De</th><th>Mensaje</th></tr>
      <?php foreach ($respuestas as $r): ?>
      <tr>
        <td class="muted small"><?= fecha_co($r['recibido_at']) ?></td>
        <td><b><?= e($r['nombre'] ? promotor_nombre_corto($r['nombre']) : enmascarar($r['telefono'])) ?></b></td>
        <td class="small"><?= $r['texto'] !== null ? e(mb_strimwidth($r['texto'], 0, 140, '…')) : '<span class="muted">(' . e($r['tipo']) . ')</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
