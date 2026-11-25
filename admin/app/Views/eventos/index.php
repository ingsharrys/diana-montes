<?php
use Models\Evento;

if (!$listo): ?>
  <p class="alert alert-error">La tabla <b>eventos</b> no existe. Ejecuta el archivo <code>sql/crear_eventos.sql</code> desde phpMyAdmin antes de usar este módulo.</p>
<?php return; endif;

$colorEstado = ['borrador' => 'tag-gold', 'publicado' => 'tag-green', 'cancelado' => 'tag-grey'];
$plural = ['borrador' => 'Borradores', 'publicado' => 'Publicados', 'cancelado' => 'Cancelados'];
$filtros = ['q' => $q, 'estado' => $estado];
$enlace = fn(array $extra = []): string => url_con('eventos', array_merge($filtros, $extra));
$ocultos = function () use ($q, $estado, $listado): string {
    // Se usa filtro_estado para que no choque con el "estado" de la acción.
    return '<input type="hidden" name="q" value="' . e($q) . '">'
         . '<input type="hidden" name="filtro_estado" value="' . e($estado) . '">'
         . '<input type="hidden" name="pagina" value="' . (int) $listado['pagina'] . '">';
};
$form = $editar ?: ($nuevo ? [] : null);
if ($form !== null && $borrador && (int) ($borrador['id'] ?? 0) === (int) ($form['id'] ?? 0)) {
    $permitidos = ['titulo', 'categoria', 'descripcion', 'fecha_inicio', 'fecha_fin', 'lugar', 'direccion', 'mapa_url', 'estado'];
    $form = array_merge($form, array_intersect_key($borrador, array_flip($permitidos)));
}
$fechaInput = static function (?string $fecha): string {
    return $fecha ? str_replace(' ', 'T', substr($fecha, 0, 16)) : '';
};
$ahora = (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
$total = array_sum(array_map('intval', $conteo));
?>

<div class="toolbar">
  <a class="btn <?= $estado === '' ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= e(url_con('eventos', ['q' => $q])) ?>">Todos (<?= num($total) ?>)</a>
  <?php foreach (Evento::ESTADOS as $clave => $texto): ?>
    <a class="btn <?= $estado === $clave ? 'btn-primary' : 'btn-ghost' ?> btn-mini" href="<?= e(url_con('eventos', ['q' => $q, 'estado' => $clave])) ?>">
      <?= e($plural[$clave]) ?> (<?= num((int) ($conteo[$clave] ?? 0)) ?>)
    </a>
  <?php endforeach; ?>
  <a class="btn btn-primary btn-mini" style="margin-left:auto" href="<?= e(url_con('eventos', ['nuevo' => 1])) ?>#formulario">+ Registrar encuentro</a>
</div>

<form class="toolbar" method="get" action="<?= e(url('eventos')) ?>">
  <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?>
    <input type="hidden" name="url" value="eventos">
  <?php endif; ?>
  <input type="hidden" name="estado" value="<?= e($estado) ?>">
  <input type="search" name="q" maxlength="100" placeholder="Buscar por título, categoría o lugar…" value="<?= e($q) ?>">
  <button class="btn btn-ghost btn-mini" type="submit">Buscar</button>
</form>

<?php if ($form !== null): $esNuevo = empty($form['id']); ?>
<section class="card card-form" style="margin-bottom:14px" id="formulario">
  <h3><?= $esNuevo ? 'Registrar un encuentro' : 'Editar el encuentro #' . (int) $form['id'] ?></h3>
  <?php if ($esNuevo): ?>
    <p class="muted small" style="margin-bottom:12px">Los nuevos encuentros quedan como <b>borrador</b>. Publícalos únicamente cuando la fecha, hora y ubicación estén confirmadas.</p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('eventos/guardar')) ?>" novalidate>
    <?= \Core\Csrf::campo() ?>
    <?= $ocultos() ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
    <div class="form-grid">
      <label class="field full"><span>Título del encuentro *</span>
        <input name="titulo" required minlength="4" maxlength="180" placeholder="Ej.: Encuentro comunitario en Garzón" value="<?= e($form['titulo'] ?? '') ?>"></label>

      <label class="field"><span>Categoría *</span>
        <input name="categoria" required minlength="3" maxlength="100" value="<?= e($form['categoria'] ?? 'Encuentro comunitario') ?>"></label>
      <label class="field"><span>Lugar *</span>
        <input name="lugar" required minlength="3" maxlength="180" placeholder="Ej.: Salón comunal" value="<?= e($form['lugar'] ?? '') ?>"></label>

      <label class="field"><span>Fecha y hora de inicio *</span>
        <input type="datetime-local" name="fecha_inicio" required value="<?= e($fechaInput($form['fecha_inicio'] ?? null)) ?>"></label>
      <label class="field"><span>Fecha y hora de finalización *</span>
        <input type="datetime-local" name="fecha_fin" required value="<?= e($fechaInput($form['fecha_fin'] ?? null)) ?>"></label>

      <label class="field full"><span>Descripción *</span>
        <textarea name="descripcion" rows="5" required minlength="10" maxlength="5000" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= e($form['descripcion'] ?? '') ?></textarea></label>

      <label class="field"><span>Dirección o indicaciones (opcional)</span>
        <input name="direccion" maxlength="255" placeholder="Ej.: Calle 5 # 6-10, Garzón" value="<?= e($form['direccion'] ?? '') ?>"></label>
      <label class="field"><span>Enlace del mapa (opcional, HTTPS)</span>
        <input type="url" name="mapa_url" maxlength="600" placeholder="https://maps.google.com/…" value="<?= e($form['mapa_url'] ?? '') ?>"></label>

      <?php if (!$esNuevo): ?>
        <label class="field"><span>Estado</span>
          <select name="estado">
            <?php foreach (Evento::ESTADOS as $clave => $texto): ?>
              <option value="<?= e($clave) ?>" <?= ($form['estado'] ?? '') === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
          </select></label>
        <p class="field small">Solo los encuentros <b>publicados</b> que aún no han terminado se muestran en la agenda pública.</p>
      <?php endif; ?>

      <div class="full form-actions">
        <button class="btn btn-primary" type="submit"><?= $esNuevo ? 'Guardar borrador' : 'Guardar cambios' ?></button>
        <a class="btn btn-ghost" href="<?= e($enlace()) ?>">Cancelar</a>
      </div>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h3>Agenda de encuentros <span class="tag tag-grey small"><?= num((int) $listado['total']) ?></span></h3>
  <p class="muted small" style="margin-bottom:10px">Los encuentros publicados aparecen en la web hasta que terminan; los anteriores permanecen en el historial administrativo.</p>
  <div class="tbl-wrap">
    <table class="cola">
      <tr><th>Encuentro</th><th>Fecha y hora</th><th>Lugar</th><th>Estado</th><th style="min-width:250px">Acciones</th></tr>
      <?php if (!$listado['registros']): ?>
        <tr><td colspan="5" class="muted" style="text-align:center;padding:26px">No hay encuentros<?= $q !== '' ? ' que coincidan con la búsqueda' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($listado['registros'] as $ev):
          $finalizado = $ev['fecha_fin'] < $ahora;
          $resumen = mb_substr((string) $ev['descripcion'], 0, 140);
          $urlMapa = (string) ($ev['mapa_url'] ?? '');
          $mapaSeguro = filter_var($urlMapa, FILTER_VALIDATE_URL) && strtolower((string) parse_url($urlMapa, PHP_URL_SCHEME)) === 'https';
      ?>
        <tr>
          <td style="max-width:350px">
            <b><?= e($ev['titulo']) ?></b> <span class="muted small">#<?= (int) $ev['id'] ?> · <?= e($ev['categoria']) ?></span>
            <p class="small" style="margin:5px 0"><?= e($resumen) ?><?= mb_strlen((string) $ev['descripcion']) > 140 ? '…' : '' ?></p>
          </td>
          <td class="small"><b><?= e(fecha_co((string) $ev['fecha_inicio'])) ?></b><br>
            <span class="muted">Hasta <?= e(fecha_co((string) $ev['fecha_fin'])) ?></span>
            <?php if ($finalizado): ?><br><span class="tag tag-grey small">Finalizado</span><?php endif; ?>
          </td>
          <td class="small"><b><?= e($ev['lugar']) ?></b>
            <?php if ($ev['direccion']): ?><br><span class="muted"><?= e($ev['direccion']) ?></span><?php endif; ?>
            <?php if ($mapaSeguro): ?><br><a href="<?= e($urlMapa) ?>" target="_blank" rel="noopener noreferrer">Ver mapa</a><?php endif; ?>
          </td>
          <td><span class="tag <?= $colorEstado[$ev['estado']] ?? 'tag-grey' ?> small"><?= e(Evento::ESTADOS[$ev['estado']] ?? $ev['estado']) ?></span></td>
          <td><div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach (['publicado' => 'Publicar', 'borrador' => 'A borrador', 'cancelado' => 'Cancelar'] as $clave => $texto):
                if ($ev['estado'] === $clave || ($clave === 'publicado' && $finalizado)) continue;
            ?>
              <form method="post" action="<?= e(url('eventos/estado')) ?>" <?= $clave === 'cancelado' ? 'onsubmit="return confirm(\'¿Cancelar este encuentro?\')"' : '' ?>>
                <?= \Core\Csrf::campo() ?><?= $ocultos() ?>
                <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                <input type="hidden" name="estado" value="<?= e($clave) ?>">
                <button type="submit" class="btn <?= $clave === 'publicado' ? 'btn-primary' : 'btn-ghost' ?> btn-mini"><?= e($texto) ?></button>
              </form>
            <?php endforeach; ?>
            <?php if ($inscripcionesListas): ?>
              <a class="btn btn-ghost btn-mini"
                 href="<?= e($enlace(['asistentes' => (int) $ev['id']])) ?>#inscripciones">
                Inscritos (<?= num((int) ($conteosInscripciones[(int) $ev['id']] ?? 0)) ?>)
              </a>
            <?php endif; ?>
            <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['editar' => (int) $ev['id']])) ?>#formulario">Editar</a>
            <form method="post" action="<?= e(url('eventos/eliminar')) ?>" onsubmit="return confirm('¿Eliminar definitivamente este encuentro?')">
              <?= \Core\Csrf::campo() ?><?= $ocultos() ?>
              <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-mini txt-peligro">Eliminar</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($listado['paginas'] > 1): ?>
    <div class="paginacion">
      <?php if ($listado['pagina'] > 1): ?>
        <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['pagina' => $listado['pagina'] - 1])) ?>">← Anterior</a>
      <?php endif; ?>
      <span class="muted small">Página <?= (int) $listado['pagina'] ?> de <?= (int) $listado['paginas'] ?></span>
      <?php if ($listado['pagina'] < $listado['paginas']): ?>
        <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['pagina' => $listado['pagina'] + 1])) ?>">Siguiente →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!$inscripcionesListas): ?>
<section class="card" id="inscripciones" style="margin-top:14px">
  <h3>Inscripciones a encuentros</h3>
  <p class="muted small">Para habilitar el registro de asistentes, ejecuta
    <code>sql/crear_evento_inscripciones.sql</code> en la base de datos.</p>
</section>
<?php elseif ($eventoAsistentes && $inscripciones): ?>
<section class="card" id="inscripciones" style="margin-top:14px">
  <div class="toolbar" style="justify-content:space-between">
    <h3>Inscritos: <?= e($eventoAsistentes['titulo']) ?>
      <span class="tag tag-grey small"><?= num((int)$inscripciones['total']) ?></span>
    </h3>
    <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['asistentes' => null, 'pagina_inscripciones' => null])) ?>">Cerrar inscritos</a>
  </div>
  <p class="muted small" style="margin-bottom:10px">"Registrado" significa que recibimos la solicitud.
    Cambiar a "Confirmado" no envía correos ni mensajes automáticamente.
    Solo el equipo autorizado debe acceder a estos datos.</p>
  <?php
    $ocultosAsistencias = $ocultos() .
        '<input type="hidden" name="evento_id" value="' . (int)$eventoAsistentes['id'] . '">' .
        '<input type="hidden" name="pagina_inscripciones" value="' . (int)$inscripciones['pagina'] . '">';
    $colorInscripcion = ['registrado' => 'tag-gold', 'confirmado' => 'tag-green', 'cancelado' => 'tag-grey'];
  ?>
  <div class="tbl-wrap">
    <table class="cola">
      <tr><th>Persona</th><th>Contacto</th><th>Registrado el</th><th>Autorización</th><th>Estado</th><th>Acciones</th></tr>
      <?php if (!$inscripciones['registros']): ?>
        <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">No hay inscritos para este encuentro.</td></tr>
      <?php endif; ?>
      <?php foreach ($inscripciones['registros'] as $ins): ?>
        <tr>
          <td><b><?= e($ins['nombre']) ?></b><br><span class="muted small">#<?= (int)$ins['id'] ?></span></td>
          <td class="small">
            <a href="mailto:<?= e($ins['correo']) ?>"><?= e($ins['correo']) ?></a><br>
            <?= e($ins['celular']) ?>
          </td>
          <td class="small"><?= e(fecha_co((string)$ins['created_at'])) ?></td>
          <td class="small"><?= !empty($ins['consentimiento_datos']) ? 'Sí · ' . e(fecha_co((string)$ins['consentimiento_fecha'])) : 'No' ?></td>
          <td><span class="tag <?= $colorInscripcion[$ins['estado']] ?? 'tag-grey' ?> small">
            <?= e(\Models\InscripcionEvento::ESTADOS[$ins['estado']] ?? $ins['estado']) ?>
          </span></td>
          <td><div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php foreach (\Models\InscripcionEvento::ESTADOS as $clave => $texto):
                if ($ins['estado'] === $clave) continue; ?>
              <form method="post" action="<?= e(url('eventos/asistenciaEstado')) ?>">
                <?= \Core\Csrf::campo() ?><?= $ocultosAsistencias ?>
                <input type="hidden" name="inscripcion_id" value="<?= (int)$ins['id'] ?>">
                <input type="hidden" name="estado_inscripcion" value="<?= e($clave) ?>">
                <button type="submit" class="btn <?= $clave === 'confirmado' ? 'btn-primary' : 'btn-ghost' ?> btn-mini">
                  <?= $clave === 'confirmado' ? 'Confirmar' : ($clave === 'cancelado' ? 'Cancelar' : 'Restablecer') ?>
                </button>
              </form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('eventos/asistenciaEliminar')) ?>"
                  onsubmit="return confirm('¿Eliminar definitivamente este registro de asistencia?')">
              <?= \Core\Csrf::campo() ?><?= $ocultosAsistencias ?>
              <input type="hidden" name="inscripcion_id" value="<?= (int)$ins['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-mini txt-peligro">Eliminar</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($inscripciones['paginas'] > 1): ?>
    <div class="paginacion">
      <?php if ($inscripciones['pagina'] > 1): ?>
        <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['asistentes' => (int)$eventoAsistentes['id'],
          'pagina_inscripciones' => $inscripciones['pagina'] - 1])) ?>#inscripciones">← Anterior</a>
      <?php endif; ?>
      <span class="muted small">Página <?= (int)$inscripciones['pagina'] ?> de <?= (int)$inscripciones['paginas'] ?></span>
      <?php if ($inscripciones['pagina'] < $inscripciones['paginas']): ?>
        <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['asistentes' => (int)$eventoAsistentes['id'],
          'pagina_inscripciones' => $inscripciones['pagina'] + 1])) ?>#inscripciones">Siguiente →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
