<?php if (!$activa): ?>
<div class="card">
  <h3>🗺 Mapa de la red</h3>
  <p class="muted">El mapa se habilita cuando la dirección activa la <b>red de Súper Promotores</b> desde el Panel.</p>
</div>
<?php else: ?>
<?php /* Librerías servidas desde el propio sitio: /assets/vendor (ver LICENCIAS.md) */ ?>
<link rel="stylesheet" href="<?= asset('../assets/vendor/leaflet/leaflet.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.Default.css') ?>">

<div class="toolbar">
  <span class="tag tag-blue"><?= count($puntos) ?> simpatizantes con ubicación</span>
  <span class="leyenda"><i style="background:#2E5496"></i> simpatizante</span>
  <span class="leyenda"><i style="background:#E8A521"></i> voluntario</span>
  <span class="leyenda"><i style="background:#2E9E5B"></i> votante confirmado</span>
  <span class="leyenda"><i class="aro"></i> promotor (ya invitó)</span>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div id="mapa" class="mapa"></div>
</div>
<p class="muted small" style="margin-top:8px">📍 Solo aparecen quienes autorizaron compartir su ubicación al registrarse. Se guarda redondeada (~100 m): es una zona, no la dirección exacta de su casa.</p>

<script src="<?= asset('../assets/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('../assets/vendor/markercluster/leaflet.markercluster.js') ?>"></script>
<script>
(function () {
  const puntos = <?= json_encode(array_map(fn($p) => [
      'n'   => promotor_nombre_corto($p['nombre']),
      'lat' => (float)$p['lat'],
      'lng' => (float)$p['lng'],
      'nv'  => $p['nivel'],
      'z'   => $p['zona'],
      'l'   => $p['lider'],
      'inv' => (int)$p['invitados'],
  ], $puntos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  // Centro por defecto: Garzón, Huila
  const mapa = L.map('mapa').setView([2.1959, -75.6278], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; OpenStreetMap'
  }).addTo(mapa);

  const colores = { simpatizante: '#2E5496', voluntario: '#E8A521', votante_confirmado: '#2E9E5B' };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const grupo = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 });
  puntos.forEach(p => {
    const m = L.circleMarker([p.lat, p.lng], {
      radius: p.inv > 0 ? 9 : 7,
      color: p.inv > 0 ? '#E0186C' : '#fff', weight: p.inv > 0 ? 3 : 2,
      fillColor: colores[p.nv] || '#2E5496', fillOpacity: .9
    });
    m.bindPopup('<b>' + esc(p.n) + '</b><br>' + esc(p.z) + ' · ' + esc(String(p.nv).replace('_', ' ')) +
                '<br><span style="color:#6A7385">Red de ' + esc(p.l) + '</span>' +
                (p.inv > 0 ? '<br>⭐ Ha invitado a ' + p.inv : ''));
    grupo.addLayer(m);
  });
  mapa.addLayer(grupo);
  if (puntos.length) mapa.fitBounds(grupo.getBounds().pad(0.15), { maxZoom: 15 });
})();
</script>
<?php endif; ?>
