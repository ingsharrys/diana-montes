<?php
use Models\Video;

if (!$listo): ?>
  <p class="alert alert-error">La tabla <code>videos_editoriales</code> todavía no existe. Ejecuta <b>sql/crear_videos_editoriales.sql</b> en la base de datos.</p>
<?php return; endif;

$colorEstado = ['borrador' => 'tag-gold', 'publicado' => 'tag-green'];
$filtros = ['q' => $q, 'estado' => $estado, 'tema' => $tema];
$enlace = fn(array $extra = []) => url_con('videos', array_merge($filtros, $extra));
$ocultos = function () use ($q, $estado, $tema, $listado): string {
    return '<input type="hidden" name="q" value="' . e($q) . '">'
         . '<input type="hidden" name="filtro_estado" value="' . e($estado) . '">'
         . '<input type="hidden" name="filtro_tema" value="' . e($tema) . '">'
         . '<input type="hidden" name="pagina" value="' . (int) $listado['pagina'] . '">';
};

$form = $editar ?: ($nuevo ? [] : null);
if ($form !== null && $borrador && (int) ($borrador['id'] ?? 0) === (int) ($form['id'] ?? 0)) {
    $form = array_merge($form, array_intersect_key($borrador,
        array_flip(['titulo', 'tema', 'plataforma', 'fuente', 'formato', 'orden', 'estado'])));
}
$total = array_sum(array_map('intval', $conteo));

// La URL del archivo depende de la raíz pública, no de /admin/videos.
$raizPublica = rtrim(dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'))), '/') . '/';
$posterUrl = static function (?string $path) use ($raizPublica): ?string {
    if ($path === null || !preg_match('~\A\./assets/videos/poster/[a-zA-Z0-9_.-]+\z~', $path)) {
        return null;
    }
    return $raizPublica . substr($path, 2);
};
?>

<div class="toolbar">
  <a class="btn <?= $estado === '' ? 'btn-primary' : 'btn-ghost' ?> btn-mini"
     href="<?= e(url_con('videos', ['q' => $q, 'tema' => $tema])) ?>">Todos (<?= num($total) ?>)</a>
  <?php foreach (Video::ESTADOS as $k => $etiqueta): ?>
    <a class="btn <?= $estado === $k ? 'btn-primary' : 'btn-ghost' ?> btn-mini"
       href="<?= e(url_con('videos', ['q' => $q, 'tema' => $tema, 'estado' => $k])) ?>">
      <?= $k === 'borrador' ? 'Borradores' : 'Publicados' ?> (<?= num((int) ($conteo[$k] ?? 0)) ?>)
    </a>
  <?php endforeach; ?>
  <a class="btn btn-primary btn-mini" style="margin-left:auto"
     href="<?= e(url_con('videos', ['nuevo' => 1])) ?>#formulario">+ Registrar video</a>
</div>

<form class="toolbar" method="get" action="<?= e(url('videos')) ?>">
  <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?>
    <input type="hidden" name="url" value="videos">
  <?php endif; ?>
  <input type="hidden" name="estado" value="<?= e($estado) ?>">
  <input type="search" name="q" maxlength="100" placeholder="Buscar por título o enlace..." value="<?= e($q) ?>">
  <select name="tema" aria-label="Filtrar por tema">
    <option value="">Todos los temas</option>
    <?php foreach (Video::TEMAS as $valor => $etiqueta): ?>
      <option value="<?= e($valor) ?>" <?= $tema === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-mini" type="submit">Buscar</button>
</form>

<?php if ($form !== null): $esNuevo = empty($form['id']); ?>
<section class="card card-form" style="margin-bottom:14px" id="formulario">
  <h3><?= $esNuevo ? 'Registrar video' : 'Editar video #' . (int) $form['id'] ?></h3>
  <p class="muted small" style="margin-bottom:12px">
    <?= $esNuevo ? 'El video se guarda como borrador y aparecerá en la web cuando lo publiques.' : 'Puedes cambiar la fuente, la portada, la posición y el estado del video.' ?>
  </p>

  <form method="post" action="<?= e(url('videos/guardar')) ?>" enctype="multipart/form-data" novalidate>
    <?= \Core\Csrf::campo() ?>
    <?= $ocultos() ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">

    <div class="form-grid">
      <label class="field full"><span>Título del video *</span>
        <input name="titulo" required minlength="4" maxlength="180"
               placeholder="Ej.: Nuestro amigo Alvarín tiene una hermosa historia"
               value="<?= e($form['titulo'] ?? '') ?>"></label>

      <label class="field"><span>Tema *</span>
        <select name="tema" required>
          <option value="">Selecciona un tema</option>
          <?php foreach (Video::TEMAS as $valor => $etiqueta): ?>
            <option value="<?= e($valor) ?>" <?= ($form['tema'] ?? '') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select></label>

      <label class="field"><span>Plataforma *</span>
        <select name="plataforma" id="video-plataforma" required>
          <option value="">Selecciona una plataforma</option>
          <?php foreach (Video::PLATAFORMAS as $valor => $etiqueta): ?>
            <option value="<?= e($valor) ?>" <?= ($form['plataforma'] ?? '') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select></label>

      <!-- Se muestra únicamente para Facebook, YouTube y Vimeo. -->
      <label class="field full" id="video-fuente-grupo"><span>Enlace o identificador del video *</span>
        <input name="fuente" id="video-fuente" maxlength="700" 
               value="<?= e(($form['plataforma'] ?? '') === 'mp4' ? '' : ($form['fuente'] ?? '')) ?>"
               placeholder="https://web.facebook.com/reel/… o ID de YouTube">
        <small class="muted">Facebook: URL HTTPS · YouTube: URL o ID de 11 caracteres · Vimeo: URL o ID público.</small>
      </label>

      <!-- Se muestra solamente cuando se selecciona «Archivo MP4». -->
      <div class="field full" id="video-mp4-grupo" hidden>
        <label for="video-archivo"><span>Subir archivo MP4 (máximo 50 MB) *</span></label>
        <input type="file" id="video-archivo" name="video_archivo"
               accept=".mp4,video/mp4" aria-describedby="video-archivo-ayuda video-archivo-error">
        <small class="muted" id="video-archivo-ayuda">
          El archivo se guardará automáticamente en <code>./assets/videos/</code>.
          <?= (!$esNuevo && ($form['plataforma'] ?? '') === 'mp4')
              ? 'Si no seleccionas otro archivo, conservarás el MP4 actual.'
              : 'Selecciona un archivo para registrar el video.' ?>
        </small>
        <small id="video-archivo-error" class="txt-peligro" role="alert" hidden></small>
        <?php if (!$esNuevo && ($form['plataforma'] ?? '') === 'mp4'): ?>
          <small class="muted">Video actual: <code><?= e($form['fuente'] ?? '') ?></code></small>
        <?php endif; ?>
      </div>

      <label class="field"><span>Orientación *</span>
        <select name="formato" required>
          <?php foreach (Video::FORMATOS as $valor => $etiqueta): ?>
            <option value="<?= e($valor) ?>" <?= ($form['formato'] ?? 'horizontal') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select></label>

      <label class="field"><span>Orden de visualización *</span>
        <input type="number" name="orden" min="0" max="999999" step="1" required
               value="<?= e((string) ($form['orden'] ?? '0')) ?>">
        <small class="muted">Los números menores se muestran primero (ej.: 1, 2, 3).</small>
      </label>

      <label class="field full"><span>Portada (opcional, JPG/PNG/WEBP, máximo 4 MB)</span>
        <input type="file" name="poster_archivo" accept="image/jpeg,image/png,image/webp">
        <small class="muted">Si no seleccionas una imagen, se conserva la actual o se utiliza una portada pendiente.</small>
      </label>

      <?php if (!$esNuevo && $urlPoster = $posterUrl($form['poster'] ?? null)): ?>
        <div class="field full">
          <span class="muted small">Portada actual</span>
          <img src="<?= e($urlPoster) ?>" alt="Portada actual del video"
               style="width:180px;height:110px;object-fit:cover;border-radius:10px;max-width:100%">
        </div>
      <?php endif; ?>

      <?php if (!$esNuevo): ?>
        <label class="field"><span>Estado</span>
          <select name="estado">
            <?php foreach (Video::ESTADOS as $valor => $etiqueta): ?>
              <option value="<?= e($valor) ?>" <?= ($form['estado'] ?? '') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
            <?php endforeach; ?>
          </select></label>
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
  <h3>Videos editoriales <span class="tag tag-grey small"><?= num((int) $listado['total']) ?></span></h3>
  <p class="muted small" style="margin-bottom:10px">Solo los videos <b>publicados</b> aparecen en «¿Por qué hago las cosas?»; la orientación controla el modal vertical u horizontal.</p>
  <div class="tbl-wrap">
    <table class="cola">
      <tr><th>Video</th><th>Tema y plataforma</th><th>Orden</th><th>Estado</th><th style="min-width:220px">Acciones</th></tr>
      <?php if (!$listado['registros']): ?>
        <tr><td colspan="5" class="muted" style="text-align:center;padding:26px">No hay videos<?= $q !== '' ? ' que coincidan con la búsqueda' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($listado['registros'] as $video):
        $miniatura = $posterUrl($video['poster'] ?? null);
      ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if ($miniatura): ?>
                <img src="<?= e($miniatura) ?>" alt="" width="90" height="54"
                     style="object-fit:cover;border-radius:7px;flex-shrink:0">
              <?php endif; ?>
              <div><b><?= e($video['titulo']) ?></b><br>
                <span class="muted small">#<?= (int) $video['id'] ?> · <?= e(fecha_co((string) $video['created_at'])) ?></span>
              </div>
            </div>
          </td>
          <td class="small">
            <?= e(Video::TEMAS[$video['tema']] ?? $video['tema']) ?><br>
            <span class="muted"><?= e(Video::PLATAFORMAS[$video['plataforma']] ?? $video['plataforma']) ?> · <?= e(Video::FORMATOS[$video['formato']] ?? $video['formato']) ?></span>
          </td>
          <td><?= (int) $video['orden'] ?></td>
          <td><span class="tag <?= $colorEstado[$video['estado']] ?? 'tag-grey' ?> small"><?= e(Video::ESTADOS[$video['estado']] ?? $video['estado']) ?></span></td>
          <td><div style="display:flex;flex-wrap:wrap;gap:6px">
            <form method="post" action="<?= e(url('videos/estado')) ?>">
              <?= \Core\Csrf::campo() ?><?= $ocultos() ?>
              <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
              <input type="hidden" name="estado" value="<?= $video['estado'] === 'publicado' ? 'borrador' : 'publicado' ?>">
              <button class="btn <?= $video['estado'] === 'publicado' ? 'btn-ghost' : 'btn-primary' ?> btn-mini" type="submit">
                <?= $video['estado'] === 'publicado' ? 'Ocultar' : 'Publicar' ?>
              </button>
            </form>
            <a class="btn btn-ghost btn-mini" href="<?= e($enlace(['editar' => (int) $video['id']])) ?>#formulario">Editar</a>
            <form method="post" action="<?= e(url('videos/eliminar')) ?>"
                  onsubmit="return confirm('¿Eliminar definitivamente este video del catálogo? La portada cargada permanecerá en el servidor.')">
              <?= \Core\Csrf::campo() ?><?= $ocultos() ?>
              <input type="hidden" name="id" value="<?= (int) $video['id'] ?>">
              <button class="btn btn-ghost btn-mini txt-peligro" type="submit">Eliminar</button>
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

<!-- Mostrar únicamente el campo de subida cuando la plataforma sea MP4. -->
<style>
  #formulario #video-mp4-grupo[hidden],
  #formulario #video-fuente-grupo[hidden],
  #formulario #video-archivo-error[hidden] { display: none !important; }
</style>
<script>
(function () {
    const select = document.getElementById('video-plataforma');
    const grupoEnlace = document.getElementById('video-fuente-grupo');
    const enlace = document.getElementById('video-fuente');
    const grupoMp4 = document.getElementById('video-mp4-grupo');
    const archivo = document.getElementById('video-archivo');
    const error = document.getElementById('video-archivo-error');
    if (!select || !grupoEnlace || !enlace || !grupoMp4 || !archivo || !error) return;

    // Si estamos editando un MP4, se permite conservarlo sin volver a subirlo.
    const mp4Guardado = <?= ($form !== null && !empty($form['id']) && ($form['plataforma'] ?? '') === 'mp4') ? 'true' : 'false' ?>;
    const maximo = 50 * 1024 * 1024;

    function actualizarCampos() {
        const esMp4 = select.value === 'mp4';
        const esEnlace = ['facebook', 'youtube', 'vimeo'].includes(select.value);
        grupoMp4.hidden = !esMp4;
        grupoEnlace.hidden = !esEnlace;
        archivo.disabled = !esMp4;
        archivo.required = esMp4 && !mp4Guardado;
        enlace.disabled = !esEnlace;
        enlace.required = esEnlace;
        if (!esMp4) {
            archivo.value = '';
            error.hidden = true;
            error.textContent = '';
        }
    }

    archivo.addEventListener('change', function () {
        error.hidden = true;
        error.textContent = '';
        const video = archivo.files && archivo.files[0];
        if (!video) return;
        if (video.size === 0 || video.size > maximo || !/\.mp4$/i.test(video.name)) {
            archivo.value = '';
            error.textContent = 'Solo se admiten archivos MP4 de máximo 50 MB.';
            error.hidden = false;
        }
    });

    select.addEventListener('change', actualizarCampos);
    actualizarCampos();
})();
</script>
