<?php
$sep = defined('USE_REWRITE') && USE_REWRITE === false ? '&' : '?';
$sitio = rtrim(LANDING_URL, '/');
$coincide = [
    'total'     => ['Documento y celular coinciden', 'tag-green'],
    'documento' => ['Solo coincide el documento',    'tag-gold'],
    'telefono'  => ['Solo coincide el celular',      'tag-gold'],
    'ninguna'   => ['No está en la base',            'tag-grey'],
];
$colorEstado = ['pendiente' => 'tag-gold', 'atendida' => 'tag-green', 'cerrada' => 'tag-grey'];
?>
<section class="card" style="margin-bottom:14px">
  <h3>Páginas legales publicadas</h3>
  <p class="muted small" style="margin-bottom:10px">Copia estas direcciones en Meta (<b>App › Configuración › Básica</b>) y en la pantalla de consentimiento de Google.</p>
  <div class="tbl-wrap">
  <table>
    <tr><th>Campo en Meta</th><th>URL</th></tr>
    <tr><td>URL de la Política de privacidad</td><td><a href="<?= e($sitio) ?>/privacidad/" target="_blank" rel="noopener"><b><?= e($sitio) ?>/privacidad/</b></a></td></tr>
    <tr><td>URL de las Condiciones del servicio</td><td><a href="<?= e($sitio) ?>/terminos/" target="_blank" rel="noopener"><b><?= e($sitio) ?>/terminos/</b></a></td></tr>
    <tr><td>Eliminación de datos de usuario › URL de las instrucciones</td><td><a href="<?= e($sitio) ?>/eliminar-datos/" target="_blank" rel="noopener"><b><?= e($sitio) ?>/eliminar-datos/</b></a></td></tr>
  </table>
  </div>
</section>

<?php if (!$listo): ?>
  <p class="alert alert-error">Pulsa <b>Actualizar plataforma</b> en Inicio para activar las solicitudes de datos personales.</p>
<?php else: ?>
<div class="toolbar">
  <?php foreach (['pendiente' => 'Pendientes', 'atendida' => 'Atendidas', 'cerrada' => 'Cerradas', 'todas' => 'Todas'] as $k => $txt): ?>
    <a class="btn <?= $estado === $k ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= url('datos') . $sep ?>estado=<?= $k ?>">
      <?= $txt ?><?= $k !== 'todas' && !empty($conteo[$k]) ? ' (' . (int)$conteo[$k] . ')' : '' ?></a>
  <?php endforeach; ?>
</div>

<section class="card" style="margin-bottom:14px">
  <h3>Solicitudes de los titulares</h3>
  <p class="muted small" style="margin-bottom:10px;line-height:1.6">
    Llegan desde <b>/eliminar-datos/</b> y por WhatsApp cuando alguien escribe <b>ELIMINAR MIS DATOS</b>; en ese caso ya se dio de baja de los mensajes.
    La ley da <b>15 días hábiles</b> para responder (10 para consultas). Antes de borrar, revisa que el documento y el celular coincidan con el registro.
    Al eliminar se borran el registro, sus mensajes, tareas y sesiones de la app; sus invitados pasan a quien lo invitó, y la solicitud queda con los datos ocultos como prueba.
  </p>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Radicado</th><th>Solicitud</th><th>Titular</th><th>Registro en la base</th><th>Estado</th><th style="min-width:250px"></th></tr>
    <?php if (!$lista): ?>
      <tr><td colspan="6" class="muted" style="text-align:center;padding:26px">No hay solicitudes <?= $estado === 'todas' ? '' : e($estado . 's') ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($lista as $s): [$coTxt, $coTag] = $coincide[$s['coincide']]; ?>
    <tr>
      <td><b><?= e($s['radicado']) ?></b><br><span class="muted small"><?= date('d/m/Y h:i a', strtotime($s['created_at'])) ?><br><?= e(SOLICITUD_CANALES[$s['canal']] ?? $s['canal']) ?></span></td>
      <td><b><?= e(SOLICITUD_TIPOS[$s['tipo']] ?? $s['tipo']) ?></b>
        <?php if ($s['detalle']): ?><br><span class="small"><?= e($s['detalle']) ?></span><?php endif; ?>
        <?php if ($s['correo']): ?><br><span class="muted small">Responder a <?= e($s['correo']) ?></span><?php endif; ?></td>
      <td><?= e($s['nombre']) ?><br><span class="muted small">Doc. <?= e($s['documento'] ?? '—') ?> · Cel. <?= e($s['telefono'] ?? '—') ?></span></td>
      <td>
        <?php if ($s['estado'] === 'pendiente'): ?>
          <span class="tag <?= $coTag ?>"><?= $coTxt ?></span>
          <?php if ($s['registro_nombre']): ?><br><span class="small"><b><?= e($s['registro_nombre']) ?></b> · <?= e($s['registro_documento']) ?> · <?= e($s['registro_telefono']) ?></span><?php endif; ?>
        <?php else: ?><span class="muted small">—</span><?php endif; ?>
      </td>
      <td><span class="tag <?= $colorEstado[$s['estado']] ?>"><?= e(SOLICITUD_ESTADOS[$s['estado']][0]) ?></span>
        <?php if ($s['atendida_at']): ?><br><span class="muted small"><?= date('d/m/Y', strtotime($s['atendida_at'])) ?> · <?= e($s['atendida_por_nombre'] ?? '') ?></span><?php endif; ?>
        <?php if ($s['respuesta']): ?><br><span class="small"><?= e($s['respuesta']) ?></span><?php endif; ?></td>
      <td>
        <?php if ($s['estado'] === 'pendiente'): ?>
          <?php if ($s['simpatizante_id'] && in_array($s['tipo'], ['eliminar', 'revocar'], true)): ?>
          <form method="post" action="<?= url('datos/eliminar/' . (int)$s['id']) ?>" style="margin-bottom:8px"
                onsubmit="return confirm('¿Eliminar todos los datos de <?= e(addslashes((string)$s['registro_nombre'])) ?>? No se puede deshacer.')">
            <?= \Core\Csrf::campo() ?>
            <button class="btn btn-primary btn-mini" type="submit">Eliminar sus datos</button>
          </form>
          <?php endif; ?>
          <form method="post" action="<?= url('datos/cerrar/' . (int)$s['id']) ?>" style="display:grid;gap:6px">
            <?= \Core\Csrf::campo() ?>
            <input name="respuesta" maxlength="500" placeholder="Respuesta para el titular (opcional)" style="font-size:13px;padding:6px 8px">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn btn-ghost btn-mini" name="estado" value="atendida" type="submit">Marcar atendida</button>
              <button class="btn btn-ghost btn-mini" name="estado" value="cerrada" type="submit" title="Por ejemplo, si no se encontraron sus datos">Cerrar sin datos</button>
            </div>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>
<?php endif; ?>

<section class="card card-form">
  <h3>Datos del responsable</h3>
  <p class="muted" style="margin-bottom:14px">Se muestran en las páginas legales. El correo y el WhatsApp son los canales para que las personas ejerzan sus derechos: si los dejas vacíos, solo se muestra el formulario.</p>
  <form method="post" action="<?= url('datos/responsable') ?>" novalidate>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">
      <?php foreach (LEGAL_CAMPOS as $k => [$etq, $defecto]): ?>
      <label class="field">
        <span><?= e($etq) ?></span>
        <input name="<?= $k ?>" maxlength="200" <?= $k === 'legal_email' ? 'type="email"' : '' ?> value="<?= e($legal[$k]) ?>" placeholder="<?= e($defecto ?: ($k === 'legal_email' ? 'datos@dianamontes.com' : ($k === 'legal_telefono' ? '300 000 0000' : ''))) ?>">
      </label>
      <?php endforeach; ?>
      <div class="full form-actions"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </div>
  </form>
</section>
