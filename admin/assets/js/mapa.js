/* Mapa de la red (Leaflet + OpenStreetMap). Requiere leaflet.js y leaflet.markercluster.js.
   pintarMapa('idDelDiv', puntos, { rueda: false })  — puntos: [{n, lat, lng, nv, z, l, inv}] */
(function () {
  // Primeras 3 posiciones de la paleta validada (todas distinguibles entre sí, también con daltonismo)
  const COLORES = { simpatizante: '#7C3AED', voluntario: '#E0186C', votante_confirmado: '#eb6834' };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  window.pintarMapa = function (id, puntos, opciones) {
    opciones = opciones || {};
    // Centro por defecto: Garzón, Huila
    const mapa = L.map(id, { scrollWheelZoom: opciones.rueda !== false }).setView([2.1959, -75.6278], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '&copy; OpenStreetMap'
    }).addTo(mapa);

    const grupo = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 });
    puntos.forEach(p => {
      const promotor = p.inv > 0;
      const m = L.circleMarker([p.lat, p.lng], {
        radius: promotor ? 9 : 7,
        color: promotor ? '#1C1630' : '#fff', weight: promotor ? 3 : 2,
        fillColor: COLORES[p.nv] || COLORES.simpatizante, fillOpacity: .92
      });
      m.bindPopup('<b>' + esc(p.n) + '</b><br>' + esc(p.z) + ' · ' + esc(String(p.nv).replace('_', ' ')) +
                  '<br><span style="color:#6B6580">Red de ' + esc(p.l) + '</span>' +
                  (promotor ? '<br>⭐ Ha invitado a ' + p.inv : ''));
      grupo.addLayer(m);
    });
    mapa.addLayer(grupo);
    if (puntos.length) mapa.fitBounds(grupo.getBounds().pad(0.15), { maxZoom: 15 });
    return mapa;
  };
})();
