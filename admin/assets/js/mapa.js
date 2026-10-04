/* Mapa de la red (Leaflet + OpenStreetMap). Requiere leaflet.js y leaflet.markercluster.js.
   pintarMapa('idDelDiv', puntos, { rueda: false })
   puntos: [{n, lat, lng, nv: etiqueta del compromiso, ci: posición 0-4 en la escala, z, l, inv}] */
(function () {
  // Compromiso = escala ordenada: un solo tono, de claro (indeciso) a oscuro (testigo). Rampa validada.
  const RAMPA = ['#aa8fff', '#9363ff', '#7f22fd', '#6400cf', '#480099'];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  window.pintarMapa = function (id, puntos, opciones) {
    opciones = opciones || {};
    // Centro por defecto: Garzón, Huila
    const mapa = L.map(id, { scrollWheelZoom: opciones.rueda !== false }).setView([2.1959, -75.6278], 13);
    // OpenStreetMap bloquea (403) los mosaicos pedidos sin Referer. El admin usa
    // Referrer-Policy "same-origin", así que solo para los mosaicos se envía el
    // dominio (sin ruta ni datos de la página), como pide su política de uso.
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      referrerPolicy: 'strict-origin-when-cross-origin',
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(mapa);

    const grupo = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45 });
    puntos.forEach(p => {
      const promotor = p.inv > 0;
      const m = L.circleMarker([p.lat, p.lng], {
        radius: promotor ? 9 : 7,
        color: promotor ? '#E0186C' : '#fff', weight: promotor ? 3 : 2,
        fillColor: RAMPA[p.ci] || RAMPA[1], fillOpacity: .95
      });
      m.bindPopup('<b>' + esc(p.n) + '</b><br>' + esc(p.z) + ' · ' + esc(p.nv) +
                  '<br><span style="color:#6B6580">Red de ' + esc(p.l) + '</span>' +
                  (promotor ? '<br>⭐ Ha invitado a ' + p.inv : ''));
      grupo.addLayer(m);
    });
    mapa.addLayer(grupo);
    if (puntos.length) mapa.fitBounds(grupo.getBounds().pad(0.15), { maxZoom: 15 });
    return mapa;
  };
})();
