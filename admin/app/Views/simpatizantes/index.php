<style>
/* ===== caja de invitación (estilos autocontenidos) ===== */
.invita-box{display:flex;align-items:center;gap:12px;background:linear-gradient(100deg,#E9F8EF, #F2FBF5);border:1.5px solid #BCE6CB;border-radius:14px;padding:12px 16px;margin-bottom:14px;flex-wrap:wrap}
.invita-box .ib-ico{width:40px;height:40px;border-radius:12px;background:#1FAF5A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:19px;flex:none}
.invita-box .ib-txt{flex:1;min-width:200px}
.invita-box .ib-txt b{display:block;font-size:13.5px;color:#14264A}
.invita-box .ib-txt span{font-size:12px;color:#4E7A5F;word-break:break-all}
.invita-box .ib-btns{display:flex;gap:8px;flex-wrap:wrap}
.btn-wa{background:#1FAF5A;color:#fff;border:none;border-radius:10px;padding:10px 16px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}
.btn-wa:hover{background:#128C46}
.btn-copiar{background:#fff;color:#14264A;border:1.5px solid #BCE6CB;border-radius:10px;padding:10px 16px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-copiar:hover{border-color:#1FAF5A;color:#128C46}
.qr-box{display:flex;gap:14px;align-items:center;width:100%;padding-top:10px;border-top:1px dashed #BCE6CB;flex-wrap:wrap}
.qr-box[hidden]{display:none}
.qr-img{width:140px;height:140px;background:#fff;border-radius:10px;padding:6px;flex:none}
.qr-img svg{width:100%;height:100%;display:block}
.qr-box .qr-txt{flex:1;min-width:200px;font-size:12.5px;color:#4E7A5F;display:flex;flex-direction:column;gap:6px;align-items:flex-start}
.qr-box .qr-txt b{color:#14264A;font-size:13.5px}
</style>

<!-- ============ INVITACIÓN: WhatsApp o copiar enlace ============ -->
<div class="invita-box">
  <span class="ib-ico">🤝</span>
  <span class="ib-txt">
    <b><?= $esEnlacePropio ? 'Tu enlace de invitación' : 'Enlace de invitación de la campaña (red directa de Diana)' ?></b>
    <span id="miEnlace"><?= e($miEnlace) ?></span>
  </span>
  <span class="ib-btns">
    <a class="btn-wa" target="_blank" rel="noopener"
       href="https://wa.me/?text=<?= rawurlencode("¡Hola! 👋 Te invito a sumarte a la campaña de Diana Lucía Montes a la Alcaldía de Garzón. Regístrate aquí, toma menos de un minuto: " . $miEnlace) ?>">
       📲 Invitar por WhatsApp</a>
    <button type="button" class="btn-copiar" onclick="copiarEnlace(this)">🔗 Copiar enlace</button>
    <button type="button" class="btn-copiar" onclick="verQr()">▦ Ver QR</button>
  </span>
  <span class="qr-box" id="qrBox" hidden>
    <span class="qr-img" id="qrImg"></span>
    <span class="qr-txt">
      <b>Tu QR de invitación</b>
      Imprímelo en volantes, afiches o en tu negocio: quien lo escanee llega al registro y queda en tu red.
      <button type="button" class="btn-wa" onclick="descargarQr()">⬇ Descargar QR</button>
    </span>
  </span>
</div>

<form class="toolbar" method="get" action="<?= url('simpatizantes') ?>">
  <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?>
  <input type="hidden" name="url" value="simpatizantes">
  <?php endif; ?>
  <input type="search" name="q" placeholder="Buscar nombre o documento…" value="<?= e($busqueda) ?>">
  <select name="profesion" onchange="this.form.submit()">
    <option value="0">Todas las profesiones</option>
    <?php foreach ($profesiones as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= $profesionSel === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost" type="submit">Buscar</button>
  <span class="spacer"></span>
  <?php if (\Core\Auth::tieneRol('direccion')): ?>
  <a class="btn btn-ghost" href="<?= url('simpatizantes/duplicados') ?>">⧉ Duplicados</a>
  <?php endif; ?>
  <a class="btn btn-ghost" href="<?= url('simpatizantes/verificar') ?>">✓ Completar y verificar</a>
  <a class="btn btn-gold" href="<?= url('simpatizantes/crear') ?>">＋ Nuevo registro</a>
</form>

<?php if ($ct = \Core\Session::get('clave_temporal')): unset($_SESSION['clave_temporal']);
  $msgClave = '¡Hola ' . strtok($ct['nombre'], ' ') . '! 👋 Este es tu acceso al panel de la red de Diana Lucía Montes: '
            . rtrim(LANDING_URL, '/') . '/mi/ · Usuario: ' . $ct['documento'] . ' · Clave temporal: ' . $ct['clave']
            . ' (al entrar te pedirá crear una nueva).'; ?>
<div class="alert alert-ok clave-temp" style="font-weight:500">
  <span>🔑 Clave temporal de <b><?= e($ct['nombre']) ?></b>: <code><?= e($ct['clave']) ?></code><br>
    <span class="small">Usuario: su documento. Al entrar a <?= e(rtrim(LANDING_URL, '/')) ?>/mi le pedirá crear una clave propia. Esta clave no se vuelve a mostrar.</span></span>
  <a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= $esDireccion ? '57' . e($ct['telefono']) : '' ?>?text=<?= rawurlencode($msgClave) ?>">📲 Enviársela por WhatsApp</a>
</div>
<?php endif; ?>

<div class="card">
  <h3>Base de simpatizantes <span class="tag tag-grey"><?= count($lista) ?> resultados</span></h3>
  <div class="tbl-wrap">
  <table>
    <tr><th>Nombre</th><th>Documento</th><th>WhatsApp</th><th>Zona</th><th>Profesión</th><th>Compromiso</th>
        <?php if ($redActiva): ?><th style="text-align:center" title="Invitados que se registraron con su enlace personal · puntos y nivel en la red">Invitados · puntos</th><?php endif; ?>
        <th>Líder</th><th>Registro</th>
        <?php if ($redActiva): ?><th>Panel</th><?php endif; ?></tr>
    <?php if (!$lista): ?>
      <tr><td colspan="<?= $redActiva ? 10 : 8 ?>" class="muted" style="text-align:center;padding:26px">Aún no hay registros con ese filtro. <a href="<?= url('simpatizantes/crear') ?>">Registra el primero</a> o comparte tu enlace de invitación. 👆</td></tr>
    <?php endif; ?>
    <?php foreach ($lista as $s): ?>
    <tr>
      <td><b><?= e($s['nombre']) ?></b></td>
      <td class="masked"><?= $esDireccion ? e($s['documento']) : enmascarar($s['documento']) ?></td>
      <td class="masked"><?= $esDireccion ? e($s['telefono']) : enmascarar($s['telefono']) ?></td>
      <td><?= e($s['zona']) ?></td>
      <td><span class="tag tag-grey"><?= e($s['profesion']) ?></span></td>
      <td><span class="tag <?= in_array($s['nivel'], COMPROMISOS_SEGUROS, true) ? 'tag-green' : ($s['nivel'] === 'indeciso' ? 'tag-grey' : 'tag-blue') ?>">
        <?= e(compromiso_etiqueta($s['nivel'])) ?></span></td>
      <?php if ($redActiva): ?>
      <?php $pts = red_puntos_fila($s); ?>
      <td style="text-align:center;white-space:nowrap"><b><?= (int)$s['invitados'] ?></b> · <?= $pts ?> pts <span title="<?= e(promotor_nivel($pts)['actual'][1]) ?>"><?= promotor_nivel($pts)['actual'][2] ?></span></td>
      <?php endif; ?>
      <td><?= e($s['lider']) ?></td>
      <td class="muted"><?= fecha_co($s['created_at']) ?></td>
      <?php if ($redActiva): ?>
      <td>
        <?php if (!empty($s['token_panel'])):
          $msgPanel = '¡Hola ' . strtok($s['nombre'], ' ') . '! 👋 Gracias por sumarte a la campaña de Diana. '
                    . 'Este es tu panel: ahí tienes tu enlace y tu QR para invitar, tus tareas, tus puntos '
                    . 'y tu puesto en el ranking: ' . rtrim(LANDING_URL, '/') . '/mi/?t=' . $s['token_panel'];
          // Con teléfono completo solo quien puede verlo (dirección/coordinación); el resto elige el contacto en WhatsApp
          $waPanel = 'https://wa.me/' . ($esDireccion ? '57' . $s['telefono'] : '') . '?text=' . rawurlencode($msgPanel); ?>
          <a class="btn btn-ghost btn-mini" target="_blank" rel="noopener" href="<?= e($waPanel) ?>" title="Enviarle el enlace de su panel por WhatsApp">📲 Enviar</a>
        <?php endif; ?>
        <?php if (array_key_exists('tiene_clave', $s) && \Core\Auth::tieneRol('direccion', 'coordinador', 'lider')): ?>
          <form method="post" action="<?= url('simpatizantes/clave/' . (int)$s['id']) ?>" style="display:inline"
                onsubmit="return confirm('¿Generar una clave temporal para <?= e(strtok($s['nombre'], ' ')) ?>? Su clave actual dejará de servir.')">
            <?= \Core\Csrf::campo() ?>
            <button class="btn btn-ghost btn-mini" type="submit" title="<?= $s['tiene_clave'] ? 'Ya tiene clave' . ($s['ultimo_acceso'] ? ' · último acceso ' . fecha_co($s['ultimo_acceso']) : '') . '. Genera una temporal si la olvidó.' : 'Aún no tiene clave: genérale una temporal' ?>">🔑 <?= $s['tiene_clave'] ? 'Clave' : 'Dar clave' ?></button>
          </form>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small">🔒 Documentos y teléfonos completos solo son visibles para la dirección; los demás roles los ven enmascarados.</p>
</div>

<script src="<?= asset('../assets/vendor/qrcode.js') ?>"></script>
<script>
/* QR del enlace de invitación (librería servida desde /assets/vendor) */
let qrActual = null;
function verQr(){
  const box = document.getElementById('qrBox');
  if (!qrActual && window.qrcode) {
    qrActual = qrcode(0, 'M');
    qrActual.addData(document.getElementById('miEnlace').textContent.trim());
    qrActual.make();
    document.getElementById('qrImg').innerHTML = qrActual.createSvgTag(4, 2);
  }
  box.hidden = !box.hidden;
}
function descargarQr(){
  if (!qrActual) return;
  const a = document.createElement('a');
  a.href = qrActual.createDataURL(10, 4);
  a.download = 'qr-invitacion-campana.gif';
  a.click();
}

function copiarEnlace(btn){
  const enlace = document.getElementById('miEnlace').textContent.trim();
  const listo = () => { const t = btn.innerHTML; btn.innerHTML = '✅ ¡Copiado!'; setTimeout(() => btn.innerHTML = t, 1800); };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(enlace).then(listo);
  } else {
    // Respaldo para conexiones sin HTTPS o navegadores viejos
    const ta = document.createElement('textarea');
    ta.value = enlace; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); ta.remove(); listo();
  }
}
</script>
