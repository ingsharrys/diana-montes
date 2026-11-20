<?php
use Models\Historia;

if (!$listo): ?>
  <p class="alert alert-error">Pulsa <b>Actualizar plataforma</b> en la Vista rápida para activar las historias ciudadanas.</p>
<?php return; endif;

$colorEstado = ['pendiente' => 'tag-gold', 'aprobada' => 'tag-green', 'rechazada' => 'tag-grey'];
$filtros = ['q' => $q, 'estado' => $estado];
$enlace  = fn(array $extra = []) => url_con('historias', array_merge($filtros, $extra));
$ocultos = function () use ($q, $estado, $listado): string {   // para volver al mismo listado tras una acción
    return '<input type="hidden" name="q" value="' . e($q) . '"><input type="hidden" name="estado" value="' . e($estado) . '">'
         . '<input type="hidden" name="pagina" value="' . (int)$listado['pagina'] . '">';
};
// Al volver de un error se conservan los datos escritos
$form = $editar ?: ($nuevo ? [] : null);
if ($form !== null && $borrador && (int)($borrador['id'] ?? 0) === (int)($form['id'] ?? 0)) {
    $form = array_merge($form, array_intersect_key($borrador, array_flip(['nombre', 'mensaje', 'zona_id', 'estado'])));
}
$total = array_sum(array_map('intval', $conteo));
?>

<div class="toolbar">
  <a class="btn <?= $estado === '' ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= e(url_con('historias', ['q' => $q])) ?>">Todas (<?= num($total) ?>)</a>
  <?php foreach (Historia::ESTADOS as $k => $txt): ?>
    <a class="btn <?= $estado === $k ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= e(url_con('historias', ['q' => $q, 'estado' => $k])) ?>">
      <?= $txt ?>s (<?= num((int)($conteo[$k] ?? 0)) ?>)</a>
  <?php endforeach; ?>
  <a class="btn btn-primary btn-mini" style="margin-left:auto" href="<?= e(url_con('historias', ['nuevo' => 1])) ?>">+ Registrar historia</a>
</div>

<form class="toolbar" method="get" action="<?= url('historias') ?>">
  <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?><input type="hidden" name="url" value="historias"><?php endif; ?>
  <input type="hidden" name="estado" value="<?= e($estado) ?>">
  <input type="search" name="q" maxlength="100" placeholder="Buscar por nombre o texto del relato…" value="<?= e($q) ?>">
  <button class="btn btn-ghost btn-mini" type="submit">Buscar</button>
</form>

<?php if ($form !== null): $esNueva = empty($form['id']); ?>
<section class="card card-form" style="margin-bottom:14px" id="formulario">
  <h3><?= $esNueva ? 'Registrar una historia' : 'Editar la historia #' . (int)$form['id'] ?></h3>
  <?php if ($esNueva): ?>
    <p class="muted small" style="margin-bottom:12px">Para historias contadas en persona o por teléfono. Solo se guarda si la persona autorizó el tratamiento de sus datos; se publica solo si además autorizó la publicación.</p>
  <?php endif; ?>
  <form method="post" action="<?= url('historias/guardar') ?>" novalidate>
    <?= \Core\Csrf::campo() ?>
    <input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0) ?>">
    <div class="form-grid">
      <label class="field"><span>Nombre de quien cuenta la historia</span>
        <input name="nombre" required minlength="3" maxlength="120" value="<?= e($form['nombre'] ?? '') ?>"></label>
      <label class="field"><span>Barrio o vereda</span>
        <select name="zona_id"><option value="0">Sin especificar</option><?= zonas_opciones($zonas, $form['zona_id'] ?? null) ?></select></label>
      <label class="field full"><span>Relato</span>
        <textarea name="mensaje" rows="6" required minlength="10" maxlength="5000" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= e($form['mensaje'] ?? '') ?></textarea></label>

      <?php if ($esNueva): ?>
        <label class="full small"><input type="checkbox" name="consentimiento_datos" value="1"> La persona <b>autorizó el tratamiento de sus datos</b> (Ley 1581 de 2012). Obligatorio.</label>
        <label class="full small"><input type="checkbox" name="autoriza_publicacion" value="1"> La persona <b>autorizó publicar</b> su historia con su nombre.</label>
      <?php else: ?>
        <label class="field"><span>Estado</span>
          <select name="estado">
            <?php foreach (Historia::ESTADOS as $k => $txt): ?>
              <option value="<?= $k ?>" <?= ($form['estado'] ?? '') === $k ? 'selected' : '' ?> <?= $k === 'aprobada' && empty($editar['autoriza_publicacion']) ? 'disabled' : '' ?>><?= $txt ?></option>
            <?php endforeach; ?>
          </select></label>
        <div class="field small">
          <span>Autorizaciones</span>
          Datos: <?= !empty($editar['consentimiento_datos']) ? '✅ ' . e(fecha_co((string)$editar['consentimiento_fecha'])) : '❌' ?><br>
          Publicación: <?= !empty($editar['autoriza_publicacion']) ? '✅ ' . e(fecha_co((string)$editar['autorizacion_publicacion_fecha'])) : '❌ no autorizada' ?>
          <?php if (!empty($editar['autoriza_publicacion'])): ?>
            <label style="display:block;margin-top:6px"><input type="checkbox" name="revocar_publicacion" value="1"> La persona <b>retiró</b> la autorización de publicar</label>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="full form-actions">
        <button class="btn btn-primary" type="submit"><?= $esNueva ? 'Guardar historia' : 'Guardar cambios' ?></button>
        <a class="btn btn-ghost" href="<?= e($enlace()) ?>">Cancelar</a>
      </div>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h3>Historias ciudadanas <span class="tag tag-grey small"><?= num($listado['total']) ?></span></h3>
  <p class="muted small" style="margin-bottom:10px">Solo se pueden <b>aprobar</b> (publicar) las historias con autorización de publicación. Si la persona la retira, la historia vuelve a pendiente.</p>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Historia</th><th>Barrio o vereda</th><th>Autorizaciones</th><th>Estado</th><th style="min-width:220px"></th></tr>
    <?php if (!$listado['registros']): ?>
      <tr><td colspan="5" class="muted" style="text-align:center;padding:26px">No hay historias<?= $estado ? ' ' . e(mb_strtolower(Historia::ESTADOS[$estado])) . 's' : '' ?><?= $q !== '' ? ' que coincidan con la búsqueda' : '' ?>.</td></tr>
    <?php endif; ?>
    <?php foreach ($listado['registros'] as $h):
      $largo = mb_strlen($h['mensaje']) > 180; ?>
    <tr>
      <td style="max-width:420px"><b><?= e($h['nombre']) ?></b> <span class="muted small">#<?= (int)$h['id'] ?> · <?= e(fecha_co((string)$h['created_at'])) ?></span>
        <?php if ($largo): ?>
          <details class="small"><summary><?= e(mb_substr($h['mensaje'], 0, 180)) ?>…</summary><p style="white-space:pre-line;margin:6px 0"><?= e($h['mensaje']) ?></p></details>
        <?php else: ?>
          <p class="small" style="white-space:pre-line;margin:4px 0"><?= e($h['mensaje']) ?></p>
        <?php endif; ?></td>
      <td class="small"><?= e($h['zona_nombre'] ?? '—') ?></td>
      <td class="small">Datos <?= $h['consentimiento_datos'] ? '✅' : '❌' ?><br>Publicar <?= $h['autoriza_publicacion'] ? '✅' : '❌' ?></td>
      <td><span class="tag <?= $colorEstado[$h['estado']] ?? 'tag-grey' ?> small"><?= e(Historia::ESTADOS[$h['estado']] ?? $h['estado']) ?></span></td>
      <td>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['aprobada' => 'Aprobar', 'rechazada' => 'Rechazar', 'pendiente' => 'A pendiente'] as $k => $txt):
          if ($h['estado'] === $k || ($k === 'aprobada' && (!$h['autoriza_publicacion'] || !$h['consentimiento_datos']))) continue; ?>
          <form method="post" action="<?= url('historias/estado') ?>"><?= \Core\Csrf::campo() ?><?= $ocultos() ?>
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="estado" value="<?= $k ?>">
            <button class="btn <?= $k === 'aprobada' ? 'btn-primary' : 'btn-ghost' ?> btn-mini" type="submit"><?= $txt ?></button></form>
        <?php endforeach; ?>
          <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['editar' => (int)$h['id']])) ?>#formulario">Editar</a>
          <form method="post" action="<?= url('historias/eliminar') ?>" onsubmit="return confirm('¿Eliminar definitivamente esta historia?')"><?= \Core\Csrf::campo() ?><?= $ocultos() ?>
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <button class="btn btn-ghost btn-mini txt-peligro" type="submit">Eliminar</button></form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php if ($listado['paginas'] > 1): ?>
  <div class="paginacion">
    <?php if ($listado['pagina'] > 1): ?><a class="btn btn-ghost btn-mini" href="<?= e($enlace(['pagina' => $listado['pagina'] - 1])) ?>">← Anterior</a><?php endif; ?>
    <span class="muted small">Página <?= $listado['pagina'] ?> de <?= $listado['paginas'] ?></span>
    <?php if ($listado['pagina'] < $listado['paginas']): ?><a class="btn btn-ghost btn-mini" href="<?= e($enlace(['pagina' => $listado['pagina'] + 1])) ?>">Siguiente →</a><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
