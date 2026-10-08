/* Red de contactos: grafo "quién trajo a quién" dibujado en canvas con d3-force.
   Requiere /assets/vendor/d3-force.min.js.

   pintarRed(elemento, datos, { paleta, interactivo, etiquetas, colorear, alElegir })
     datos.nodos:   [{n: nombre, t: 'raiz'|'equipo'|'simp', g: color (-1 = gris), v: invitados, …detalle opcional}]
     datos.enlaces: [[hijo, padre]] por índice
     colorear(n):   color propio de cada nodo (si no, el color del equipo)
     alElegir(n):   se llama al tocar una persona (o null al tocar el fondo)

   Devuelve { acercar, alejar, centrar, enfocar(i), elegir(i), repintar(), nodos }.
   Cada nodo trae calculado: padre, hijos, capa (1 = registrado directo por el
   equipo, 2 = invitado de alguien de la capa 1…) y total (toda su red debajo).

   Las posiciones se calculan de una vez (sin animación): carga rápida y sin
   movimiento brusco en pantalla. */
(function () {
  const GRIS = '#B9B3CC', RAIZ = '#1C1630', SUPERFICIE = '#FFFFFF';

  window.pintarRed = function (el, datos, opciones) {
    opciones = Object.assign({ paleta: [], interactivo: true, etiquetas: true, colorear: null, alElegir: null }, opciones || {});
    const tip = document.getElementById('tip');
    const canvas = document.createElement('canvas');
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'Grafo de la red de contactos: ' + datos.nodos.length + ' personas');
    el.appendChild(canvas);
    const ctx = canvas.getContext('2d');

    const nodos = datos.nodos.map((n, i) => Object.assign({ i, hijos: [], padre: null, capa: 0, total: 0 }, n));
    const enlaces = datos.enlaces.map(([a, b]) => ({ source: a, target: b }));

    // Estructura del árbol: padre, hijos, capa y tamaño de la red de cada uno
    datos.enlaces.forEach(([h, p]) => { nodos[h].padre = nodos[p]; nodos[p].hijos.push(nodos[h]); });
    const capa = n => n._capa ?? (n._capa = n.t !== 'simp' ? 0 : (n.padre && n.padre.t === 'simp' ? capa(n.padre) + 1 : 1));
    const total = n => n._total ?? (n._total = n.hijos.reduce((s, h) => s + (h.t === 'simp' ? 1 : 0) + total(h), 0));
    nodos.forEach(n => { n.capa = capa(n); n.total = total(n); });

    const radio = n => n.t === 'raiz' ? 11 : n.t === 'equipo' ? 7.5 : 3.4 + Math.min(5, Math.sqrt(n.total) * 1.4);
    const colorBase = n => n.t === 'raiz' ? RAIZ : (n.g >= 0 ? (opciones.paleta[n.g] || GRIS) : GRIS);
    const color = n => (opciones.colorear && n.t !== 'raiz' ? opciones.colorear(n) : null) || colorBase(n);

    d3.forceSimulation(nodos)
      .force('enlace', d3.forceLink(enlaces)
        .distance(l => l.target.t === 'raiz' ? 90 : l.target.t === 'equipo' ? 30 : 14)
        .strength(l => l.target.t === 'raiz' ? 0.5 : 0.9))
      .force('carga', d3.forceManyBody().strength(n => n.t === 'simp' ? -12 : -260).distanceMax(420))
      .force('choque', d3.forceCollide(n => radio(n) + 1.5))
      .force('x', d3.forceX().strength(0.05))
      .force('y', d3.forceY().strength(0.05))
      .stop()
      .tick(320);

    let ancho = 0, alto = 0, dpr = 1, vista = { k: 1, x: 0, y: 0 }, resaltado = null, elegido = null, enRama = null;

    /** Rama del elegido: él, todos los que trajo (hacia abajo) y su camino hasta la raíz. */
    function marcarRama(n) {
      if (!n) { enRama = null; return; }
      enRama = new Set();
      const bajar = x => { enRama.add(x); x.hijos.forEach(bajar); };
      bajar(n);
      for (let p = n.padre; p; p = p.padre) enRama.add(p);
    }

    function encuadrar() {
      if (!nodos.length) return;
      let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
      nodos.forEach(n => { x0 = Math.min(x0, n.x); y0 = Math.min(y0, n.y); x1 = Math.max(x1, n.x); y1 = Math.max(y1, n.y); });
      const k = Math.min(ancho / (x1 - x0 + 60), alto / (y1 - y0 + 60), 2.5);
      vista = { k, x: ancho / 2 - (x0 + x1) / 2 * k, y: alto / 2 - (y0 + y1) / 2 * k };
    }

    function dibujar() {
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, ancho, alto);
      ctx.translate(vista.x, vista.y);
      ctx.scale(vista.k, vista.k);
      const tenue = n => enRama && !enRama.has(n);

      // Enlaces: finos y discretos; los de la rama elegida, marcados
      ctx.lineWidth = 1 / vista.k;
      ctx.strokeStyle = enRama ? 'rgba(107,101,128,.10)' : 'rgba(107,101,128,.30)';
      ctx.beginPath();
      enlaces.forEach(l => { if (enRama && enRama.has(l.source) && enRama.has(l.target)) return; ctx.moveTo(l.source.x, l.source.y); ctx.lineTo(l.target.x, l.target.y); });
      ctx.stroke();
      if (enRama) {
        ctx.lineWidth = 2 / vista.k;
        ctx.strokeStyle = 'rgba(28,22,48,.55)';
        ctx.beginPath();
        enlaces.forEach(l => { if (enRama.has(l.source) && enRama.has(l.target)) { ctx.moveTo(l.source.x, l.source.y); ctx.lineTo(l.target.x, l.target.y); } });
        ctx.stroke();
      }

      // Nodos con un aro del color de la superficie para que se distingan al solaparse
      ['simp', 'equipo', 'raiz'].forEach(tipo => nodos.forEach(n => {
        if (n.t !== tipo) return;
        ctx.globalAlpha = tenue(n) ? 0.16 : 1;
        ctx.beginPath();
        ctx.arc(n.x, n.y, radio(n), 0, 2 * Math.PI);
        ctx.fillStyle = color(n);
        ctx.fill();
        const marcado = n === resaltado || n === elegido;
        ctx.lineWidth = (marcado ? 3 : 1.5) / vista.k;
        ctx.strokeStyle = marcado ? RAIZ : SUPERFICIE;
        ctx.stroke();
      }));
      ctx.globalAlpha = 1;

      // Nombres: raíz y equipo siempre; promotores (trajeron gente) también; todos al acercar
      if (opciones.etiquetas) {
        ctx.textAlign = 'center';
        const todos = vista.k >= 2.6;
        nodos.forEach(n => {
          const promotor = n.t === 'simp' && n.total > 0;
          if (n.t === 'simp' && !promotor && !todos && n !== elegido) return;
          if (tenue(n)) return;
          const texto = n.t === 'raiz' ? n.n : n.t === 'equipo' ? n.n.split(' ')[0] : n.n;
          ctx.font = (n.t === 'simp' ? '500 ' : '700 ') + ((n.t === 'simp' ? 9.5 : 11) / vista.k) + 'px Inter, system-ui, sans-serif';
          const y = n.y - radio(n) - 4 / vista.k;
          ctx.lineWidth = 3 / vista.k; ctx.strokeStyle = 'rgba(255,255,255,.92)'; ctx.strokeText(texto, n.x, y);
          ctx.fillStyle = RAIZ; ctx.fillText(texto, n.x, y);
        });
      }
    }

    function redimensionar() {
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) return;
      const primera = !ancho;
      ancho = r.width; alto = r.height; dpr = window.devicePixelRatio || 1;
      canvas.width = ancho * dpr; canvas.height = alto * dpr;
      canvas.style.width = ancho + 'px'; canvas.style.height = alto + 'px';
      if (primera || !opciones.interactivo) encuadrar();
      dibujar();
    }

    // Nodo más cercano al puntero (área de acierto generosa)
    function nodoEn(px, py) {
      const x = (px - vista.x) / vista.k, y = (py - vista.y) / vista.k;
      let mejor = null, dist = (12 / vista.k) ** 2;
      nodos.forEach(n => { const d = (n.x - x) ** 2 + (n.y - y) ** 2; if (d < Math.max(dist, radio(n) ** 2)) { dist = d; mejor = n; } });
      return mejor;
    }

    function textoNodo(n) {
      if (n.t === 'raiz') return n.n + ' · centro de la red';
      if (n.t === 'equipo') return n.n + ' · equipo de campaña · ' + n.total + ' en su red';
      return n.n + ' · capa ' + n.capa + (n.total > 0 ? ' · su red: ' + n.total : '');
    }

    let arrastre = null;
    canvas.addEventListener('pointermove', e => {
      const r = canvas.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
      if (arrastre) {
        if (Math.abs(e.clientX - arrastre.x) + Math.abs(e.clientY - arrastre.y) > 4) arrastre.movio = true;
        vista.x = arrastre.vx + (e.clientX - arrastre.x); vista.y = arrastre.vy + (e.clientY - arrastre.y);
        dibujar(); return;
      }
      const n = nodoEn(px, py);
      if (n !== resaltado) { resaltado = n; dibujar(); }
      canvas.style.cursor = n ? 'pointer' : (opciones.interactivo ? 'grab' : 'default');
      if (n && tip) {
        tip.textContent = textoNodo(n); tip.hidden = false;
        tip.style.left = Math.min(e.clientX + 12, innerWidth - tip.offsetWidth - 4) + 'px';
        tip.style.top = (e.clientY - tip.offsetHeight - 10) + 'px';
      } else if (tip) tip.hidden = true;
    });
    canvas.addEventListener('pointerleave', () => { resaltado = null; if (tip) tip.hidden = true; dibujar(); });

    function elegir(n) {
      elegido = n; marcarRama(n); dibujar();
      if (opciones.alElegir) opciones.alElegir(n);
    }

    if (opciones.interactivo) {
      canvas.addEventListener('pointerdown', e => {
        arrastre = { x: e.clientX, y: e.clientY, vx: vista.x, vy: vista.y, movio: false };
        canvas.setPointerCapture(e.pointerId); canvas.style.cursor = 'grabbing';
      });
      canvas.addEventListener('pointerup', e => {
        const clic = arrastre && !arrastre.movio;
        arrastre = null; canvas.style.cursor = 'grab';
        if (clic && opciones.alElegir) {
          const r = canvas.getBoundingClientRect();
          elegir(nodoEn(e.clientX - r.left, e.clientY - r.top));
        }
      });
      canvas.addEventListener('wheel', e => {
        e.preventDefault();
        const r = canvas.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
        zoom(Math.exp(-e.deltaY * 0.0015), px, py);
      }, { passive: false });
    }

    function zoom(f, px, py) {
      const k = Math.max(0.2, Math.min(8, vista.k * f));
      px = px ?? ancho / 2; py = py ?? alto / 2;
      vista.x = px - (px - vista.x) * (k / vista.k);
      vista.y = py - (py - vista.y) * (k / vista.k);
      vista.k = k; dibujar();
    }

    /** Centra la vista en una persona, la elige y marca su rama. */
    function enfocar(i) {
      const n = nodos[i]; if (!n) return;
      const k = Math.max(vista.k, 2.4);
      vista = { k, x: ancho / 2 - n.x * k, y: alto / 2 - n.y * k };
      elegir(n);
    }

    new ResizeObserver(redimensionar).observe(el);
    redimensionar();

    return {
      nodos,
      acercar: () => zoom(1.4),
      alejar: () => zoom(1 / 1.4),
      centrar: () => { encuadrar(); dibujar(); },
      enfocar,
      elegir: i => elegir(i === null ? null : nodos[i]),
      repintar: dibujar,
    };
  };
})();
