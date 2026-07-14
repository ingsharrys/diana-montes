<div class="toolbar">
  <span class="muted">Los líderes entran solo por invitación: crea aquí su acceso y comparte su enlace de red.</span>
  <span class="spacer"></span>
  <a class="btn btn-gold" href="<?= url('usuarios/crear') ?>">＋ Invitar al equipo</a>
</div>

<div class="card">
  <h3>Equipo de campaña <span class="tag tag-grey"><?= count($lista) ?> usuarios</span></h3>
  <div class="tbl-wrap">
  <table>
    <tr><th>Nombre</th><th>Rol</th><th>Zona</th><th>Red / enlace de invitación</th><th>Vinculados</th><th>Meta</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($lista as $u): ?>
    <tr>
      <td><b><?= e($u['nombre']) ?></b><br><span class="muted small"><?= e($u['email']) ?></span></td>
      <td><span class="tag <?= $u['rol']==='direccion'?'tag-gold':($u['rol']==='coordinador'?'tag-blue':'tag-grey') ?>"><?= e($u['rol']) ?></span></td>
      <td><?= e($u['zona'] ?? '—') ?></td>
      <td>
        <?php if ($u['codigo_ref']): ?>
          <code class="ref-code"><?= e($u['codigo_ref']) ?></code>
          <button type="button" class="reveal" onclick="copiarRef(this,'<?= e(LANDING_URL . '/?ref=' . $u['codigo_ref'] . '#sumate') ?>')">copiar enlace</button>
        <?php else: ?><span class="muted">—</span><?php endif; ?>
      </td>
      <td style="text-align:center"><b><?= (int)$u['vinculados'] ?></b></td>
      <td style="text-align:center"><?= $u['meta'] ? (int)$u['meta'] : '—' ?></td>
      <td><span class="tag <?= $u['activo'] ? 'tag-green' : 'tag-grey' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
      <td>
        <form method="post" action="<?= url('usuarios/estado/' . (int)$u['id']) ?>" style="display:inline">
          <?= \Core\Csrf::campo() ?>
          <button class="btn btn-ghost btn-mini" type="submit"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
        </form>
        <form method="post" action="<?= url('usuarios/resetclave/' . (int)$u['id']) ?>" style="display:inline"
              onsubmit="return confirm('¿Generar una nueva contraseña temporal para <?= e($u['nombre']) ?>?')">
          <?= \Core\Csrf::campo() ?>
          <button class="btn btn-ghost btn-mini" type="submit">Nueva clave</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small">🔒 Las contraseñas temporales se muestran una sola vez al crearlas. El enlace de invitación registra simpatizantes directamente en la red de cada líder.</p>
</div>

<script>
function copiarRef(btn, url){
  navigator.clipboard.writeText(url).then(()=>{ btn.textContent='¡copiado!'; setTimeout(()=>btn.textContent='copiar enlace',1600); });
}
</script>
