/* Mapa de la red (Leaflet + Mapbox, o OpenStreetMap si no hay token).
   Requiere leaflet.js y leaflet.markercluster.js.

   pintarMapa('idDelDiv', puntos, { mapbox: 'pk.…' | null, estilo: 'claro'|'calles'|'satelite', rueda: bool, logo: url })
   puntos: [{n, lat, lng, nv: etiqueta del compromiso, ci: posición 0-4 en la escala, z, l, inv}] */
(function () {
  // Compromiso = escala ordenada: un solo tono, de claro (indeciso) a oscuro (testigo). Rampa validada.
  const RAMPA = ['#aa8fff', '#9363ff', '#7f22fd', '#6400cf', '#480099'];
  const ESTILOS = {
    calles:   ['Calles',   'mapbox/streets-v12'],
    claro:    ['Claro',    'mapbox/light-v11'],
    satelite: ['Satélite', 'mapbox/satellite-streets-v12'],
  };
  const CLAVE = 'mapa_estilo';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Los mosaicos envían solo el dominio como Referer: el admin usa Referrer-Policy
  // "same-origin", pero OpenStreetMap lo exige y los tokens de Mapbox restringidos
  // por URL lo validan. No viaja la ruta ni ningún dato de la página.
  const REFERER = 'strict-origin-when-cross-origin';

  function capaMapbox(token, estilo) {
    const hd = (window.devicePixelRatio || 1) > 1 ? '@2x' : '';
    return L.tileLayer('https://api.mapbox.com/styles/v1/' + estilo + '/tiles/512/{z}/{x}/{y}' + hd +
                       '?access_token=' + encodeURIComponent(token), {
      tileSize: 512, zoomOffset: -1, maxZoom: 20, referrerPolicy: REFERER,
      // Atribución exigida por Mapbox: © Mapbox, © OpenStreetMap e "Improve this map"
      attribution: '© <a href="https://www.mapbox.com/about/maps/" target="_blank" rel="noopener">Mapbox</a> ' +
                   '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> ' +
                   '<strong><a href="https://www.mapbox.com/map-feedback/" target="_blank" rel="noopener">Improve this map</a></strong>'
    });
  }

  function leerEstilo(porDefecto) {
    try { const e = localStorage.getItem(CLAVE); if (ESTILOS[e]) return e; } catch (e) {}
    return ESTILOS[porDefecto] ? porDefecto : 'calles';
  }

  window.pintarMapa = function (id, puntos, opciones) {
    opciones = opciones || {};
    // Centro por defecto: Garzón, Huila
    const mapa = L.map(id, { scrollWheelZoom: opciones.rueda !== false }).setView([2.1959, -75.6278], 13);

    if (opciones.mapbox) {
      const capas = {};
      Object.keys(ESTILOS).forEach(k => { capas[ESTILOS[k][0]] = capaMapbox(opciones.mapbox, ESTILOS[k][1]); });
      capas[ESTILOS[leerEstilo(opciones.estilo)][0]].addTo(mapa);
      L.control.layers(capas, null, { position: 'topright' }).addTo(mapa);
      mapa.on('baselayerchange', ev => {
        const k = Object.keys(ESTILOS).find(c => ESTILOS[c][0] === ev.name);
        try { localStorage.setItem(CLAVE, k); } catch (e) {}
      });
      // Logo de Mapbox (obligatorio, sin alterar), abajo a la izquierda
      const Logo = L.Control.extend({
        onAdd: () => {
          const a = L.DomUtil.create('a', 'mapbox-logo');
          a.href = 'https://www.mapbox.com/'; a.target = '_blank'; a.rel = 'noopener';
          a.innerHTML = '<img src="' + esc(opciones.logo) + '" alt="Mapbox" width="88" height="23">';
          return a;
        }
      });
      new Logo({ position: 'bottomleft' }).addTo(mapa);
    } else {
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, referrerPolicy: REFERER,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(mapa);
    }

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
