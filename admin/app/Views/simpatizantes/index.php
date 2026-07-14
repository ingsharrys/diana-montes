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
  <a class="btn btn-gold" href="<?= url('simpatizantes/crear') ?>">＋ Nuevo registro</a>
</form>

<div class="card">
  <h3>Base de simpatizantes <span class="tag tag-grey"><?= count($lista) ?> resultados</span></h3>
  <div class="tbl-wrap">
  <table>
    <tr><th>Nombre</th><th>Documento</th><th>WhatsApp</th><th>Zona</th><th>Profesión</th><th>Nivel</th><th>Líder</th><th>Registro</th></tr>
    <?php if (!$lista): ?>
      <tr><td colspan="8" class="muted" style="text-align:center;padding:26px">Aún no hay registros con ese filtro. <a href="<?= url('simpatizantes/crear') ?>">Registra el primero</a> o comparte tu enlace de invitación. 👆</td></tr>
    <?php endif; ?>
    <?php foreach ($lista as $s): ?>
    <tr>
      <td><b><?= e($s['nombre']) ?></b></td>
      <td class="masked"><?= $esDireccion ? e($s['documento']) : enmascarar($s['documento']) ?></td>
      <td class="masked"><?= $esDireccion ? e($s['telefono']) : enmascarar($s['telefono']) ?></td>
      <td><?= e($s['zona']) ?></td>
      <td><span class="tag tag-grey"><?= e($s['profesion']) ?></span></td>
      <td><span class="tag <?= $s['nivel'] === 'votante_confirmado' ? 'tag-green' : ($s['nivel'] === 'voluntario' ? 'tag-gold' : 'tag-blue') ?>">
        <?= e(str_replace('_', ' ', $s['nivel'])) ?></span></td>
      <td><?= e($s['lider']) ?></td>
      <td class="muted"><?= fecha_co($s['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small">🔒 Documentos y teléfonos completos solo son visibles para la dirección; los demás roles los ven enmascarados.</p>
</div>

<script>
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
