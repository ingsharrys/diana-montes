<?php if (!$activa): ?>
<div class="card">
  <h3>Mapa de la red</h3>
  <p class="muted">El mapa se habilita cuando la dirección actualiza la plataforma desde la Vista rápida.</p>
</div>
<?php else: ?>
<?php /* Librerías servidas desde el propio sitio: /assets/vendor (ver LICENCIAS.md) */ ?>
<link rel="stylesheet" href="<?= asset('../assets/vendor/leaflet/leaflet.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.Default.css') ?>">

<?php if (($problema = mapbox_problema()) && \Core\Auth::tieneRol('direccion')): ?>
  <div class="alert alert-error"><?= e($problema) ?> Mientras tanto se usa OpenStreetMap.</div>
<?php endif; ?>

<section class="card" style="padding:0;overflow:hidden">
  <div class="toolbar" style="padding:14px 16px 0;margin-bottom:12px">
    <span class="tag tag-blue"><?= num(count($puntos)) ?> simpatizantes con ubicación</span>
    <?php foreach (array_values(COMPROMISOS) as $i => $etiqueta): ?>
      <span class="leyenda"><i style="background:<?= RAMPA_NIVEL[$i] ?>"></i> <?= e(mb_strtolower($etiqueta)) ?></span>
    <?php endforeach; ?>
    <span class="leyenda"><i class="aro"></i> promotor (ya invitó)</span>
  </div>
  <div id="mapa" class="mapa"></div>
</section>
<p class="muted small" style="margin-top:8px">📍 Solo aparecen quienes autorizaron compartir su ubicación al registrarse. Se guarda redondeada (~100 m): es una zona, no la dirección exacta de su casa.</p>

<script src="<?= asset('../assets/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('../assets/vendor/markercluster/leaflet.markercluster.js') ?>"></script>
<script src="<?= asset('assets/js/mapa.js') ?>"></script>
<script>
pintarMapa('mapa', <?= json_encode(array_map(fn($p) => [
    'n'   => promotor_nombre_corto($p['nombre']),
    'lat' => (float)$p['lat'],
    'lng' => (float)$p['lng'],
    'nv'  => compromiso_etiqueta($p['nivel']),
    'ci'  => compromiso_indice($p['nivel']),
    'z'   => $p['zona'],
    'l'   => $p['lider'],
    'inv' => (int)$p['invitados'],
], $puntos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= mapa_opciones('calles') ?>);
</script>
<?php endif; ?>
