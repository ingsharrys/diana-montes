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

<section class="card" style="padding:0;overflow:hidden">
  <div class="toolbar" style="padding:14px 16px 0;margin-bottom:12px">
    <span class="tag tag-blue"><?= num(count($puntos)) ?> simpatizantes con ubicación</span>
    <span class="leyenda"><i style="background:#7C3AED"></i> simpatizante</span>
    <span class="leyenda"><i style="background:#E0186C"></i> voluntario</span>
    <span class="leyenda"><i style="background:#eb6834"></i> votante confirmado</span>
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
    'nv'  => $p['nivel'],
    'z'   => $p['zona'],
    'l'   => $p['lider'],
    'inv' => (int)$p['invitados'],
], $puntos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
</script>
<?php endif; ?>
