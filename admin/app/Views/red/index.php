<?php
$sep = defined('USE_REWRITE') && USE_REWRITE === false ? '&' : '?';
$niveles = array_map(fn($n) => ['min' => $n[0], 'nombre' => $n[1], 'emoji' => $n[2]], PROMOTOR_NIVELES);
?>
<section class="card red-guia">
  <h3>Cómo leer la red</h3>
  <div class="red-guia-items">
    <div><span class="g-ico raiz"></span><p><b>El centro</b> es <?= $esLider ? 'tu red como líder' : 'la campaña' ?>. Alrededor está el equipo (líderes y coordinadores) con su nombre.</p></div>
    <div><span class="g-ico punto"></span><p><b>Cada punto es una persona.</b> La línea la une con <b>quien la trajo</b>: el líder que la registró o el simpatizante que la invitó con su enlace.</p></div>
    <div><span class="g-ico grande"></span><p><b>Los puntos grandes con nombre</b> son <b>promotores</b>: personas que ya invitaron gente. Entre más grande, más grande es su red.</p></div>
    <div><span class="g-ico capas">1·2·3</span><p><b>Capas:</b> la capa 1 la registró el equipo; la capa 2 son invitados de la capa 1, y así. Más capas = la red crece sola.</p></div>
  </div>
  <div class="red-cifras" id="rCifras"></div>
</section>

<?php if (count($grafo['nodos']) <= 1): ?>
  <section class="card"><p class="muted vacio">Aún no hay simpatizantes para dibujar la red. Comparte tu enlace de invitación desde Simpatizantes.</p></section>
<?php else: ?>
<div class="red-layout">
  <section class="card red-principal">
    <div class="red-barra">
      <div class="red-buscar">
        <input type="search" id="rBuscar" placeholder="Buscar a una persona…" autocomplete="off" aria-label="Buscar a una persona en la red">
        <ul id="rResultados" class="red-resultados" hidden></ul>
      </div>
      <div class="red-modos" role="group" aria-label="Colorear por">
        <span class="muted small">Colorear por</span>
        <?php foreach (['equipo' => 'Equipo', 'compromiso' => 'Compromiso', 'nivel' => 'Nivel', 'capa' => 'Capa'] as $k => $t): ?>
          <button type="button" class="btn btn-mini <?= $k === 'equipo' ? 'btn-primary' : 'btn-ghost' ?>" data-modo="<?= $k ?>"><?= $t ?></button>
        <?php endforeach; ?>
      </div>
      <span class="red-controles">
        <button type="button" class="btn btn-ghost btn-mini" id="rAcercar" aria-label="Acercar">＋</button>
        <button type="button" class="btn btn-ghost btn-mini" id="rAlejar" aria-label="Alejar">－</button>
        <button type="button" class="btn btn-ghost btn-mini" id="rCentrar">Ver todo</button>
      </span>
    </div>
    <div class="red-lienzo red-grande" id="redGrande"></div>
    <ul class="red-ley" id="rLeyenda" aria-label="Leyenda de colores"></ul>
    <p class="muted small nota">
      Toca un punto para ver quién es, quién lo trajo y toda su red. Rueda del mouse o los botones para acercar; arrastra para moverte.
      <?= $grafo['mostrados'] < $grafo['total'] ? 'Se muestran los ' . num($grafo['mostrados']) . ' registros más recientes de ' . num($grafo['total']) . '.' : '' ?>
    </p>
  </section>

  <aside class="card red-panel" id="rPanel" aria-live="polite"></aside>
</div>

<section class="card">
  <h3>Árbol de la red <span class="muted small" style="font-weight:500">· quién trajo a quién, de mayor a menor red</span></h3>
  <div class="red-arbol" id="rArbol"></div>
</section>

<script src="<?= asset('../assets/vendor/d3-force.min.js') ?>"></script>
<script src="<?= asset('assets/js/red.js') ?>"></script>
<script>
(function () {
  const PALETA = <?= json_encode(PALETA) ?>;
  const NIVELES = <?= json_encode($niveles, JSON_UNESCAPED_UNICODE) ?>;
  const COMPROMISOS = <?= json_encode(array_map(null, array_keys(COMPROMISOS), array_values(COMPROMISOS)), JSON_UNESCAPED_UNICODE) ?>;
  const LEYENDA_EQUIPO = <?= json_encode($grafo['leyenda'], JSON_UNESCAPED_UNICODE) ?>;
  const URL_SIMP = <?= json_encode(url('simpatizantes') . $sep . 'q=') ?>;
  const C_COMP = { indeciso: '#B9B3CC', simpatizante: '#7C3AED', voto_seguro: '#1baf7a', voluntario: '#2a78d6', testigo: '#eb6834', votante_confirmado: '#1baf7a' };
  const C_NIVEL = ['#C9C2DE', '#eda100', '#E0186C', '#7C3AED'];
  const C_CAPA = ['#7C3AED', '#E0186C', '#eb6834', '#2a78d6', '#1baf7a'];
  const EQUIPO = '#1C1630';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const normal = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const num = n => Number(n).toLocaleString('es-CO');
  const nivelDe = n => { let k = 0; NIVELES.forEach((x, i) => { if ((n.p ?? 0) >= x.min) k = i; }); return k; };
  const compNombre = c => (COMPROMISOS.find(x => x[0] === c) || [c, c || '—'])[1];

  let modo = 'equipo';
  const colorear = n => {
    if (modo === 'equipo') return null;
    if (n.t === 'equipo') return EQUIPO;
    if (modo === 'compromiso') return C_COMP[n.c] || '#B9B3CC';
    if (modo === 'nivel') return C_NIVEL[nivelDe(n)];
    return C_CAPA[Math.min(n.capa, 5) - 1];
  };

  const red = pintarRed(document.getElementById('redGrande'),
    <?= json_encode(['nodos' => $grafo['nodos'], 'enlaces' => $grafo['enlaces']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    { paleta: PALETA, interactivo: true, etiquetas: true, colorear, alElegir: mostrar });
  const nodos = red.nodos;
  const personas = nodos.filter(n => n.t === 'simp');
  document.getElementById('rAcercar').onclick = red.acercar;
  document.getElementById('rAlejar').onclick = red.alejar;
  document.getElementById('rCentrar').onclick = () => { red.elegir(null); red.centrar(); };

  /* ---------- cifras ---------- */
  const invitados = personas.filter(n => n.padre && n.padre.t === 'simp').length;
  const promotores = personas.filter(n => n.hijos.some(h => h.t === 'simp'));
  const capas = personas.reduce((m, n) => Math.max(m, n.capa), 0);
  const top = [...promotores].sort((a, b) => b.total - a.total);
  const pct = personas.length ? Math.round(invitados * 100 / personas.length) : 0;
  document.getElementById('rCifras').innerHTML = [
    ['Personas en la red', num(personas.length), ''],
    ['Llegaron invitados por otra persona', num(invitados), pct + '% del total'],
    ['Promotores (ya invitaron gente)', num(promotores.length), ''],
    ['Capas de profundidad', num(capas), capas >= 3 ? 'la red crece sola' : 'invita a invitar'],
    ['Mejor promotor', top[0] ? esc(top[0].n) : '—', top[0] ? 'red de ' + num(top[0].total) : ''],
  ].map(([t, v, s]) => `<div><span>${t}</span><b>${v}</b>${s ? `<small>${s}</small>` : ''}</div>`).join('');

  /* ---------- leyenda según el modo ---------- */
  function leyenda() {
    const punto = (c, t, n) => `<li><span class="sw" style="background:${c};border-radius:50%"></span>${esc(t)}${n !== undefined ? ` <span class="muted">${num(n)}</span>` : ''}</li>`;
    let h = punto('#1C1630', '<?= $esLider ? 'Tú (centro)' : 'Centro de la red' ?>');
    if (modo === 'equipo') LEYENDA_EQUIPO.forEach(l => { h += punto(l.g >= 0 ? PALETA[l.g] : '#B9B3CC', l.nombre, l.total); });
    else {
      h += punto(EQUIPO, 'Equipo de campaña');
      if (modo === 'compromiso') COMPROMISOS.forEach(([k, t]) => { h += punto(C_COMP[k], t, personas.filter(n => n.c === k).length); });
      if (modo === 'nivel') NIVELES.forEach((x, i) => { h += punto(C_NIVEL[i], x.emoji + ' ' + x.nombre, personas.filter(n => nivelDe(n) === i).length); });
      if (modo === 'capa') for (let i = 1; i <= Math.min(Math.max(capas, 1), 5); i++) {
        h += punto(C_CAPA[i - 1], i === 5 ? 'Capa 5 o más' : 'Capa ' + i, personas.filter(n => Math.min(n.capa, 5) === i).length);
      }
    }
    h += `<li class="muted" style="margin-left:auto">● tamaño = tamaño de su red</li>`;
    document.getElementById('rLeyenda').innerHTML = h;
  }
  document.querySelectorAll('[data-modo]').forEach(b => b.onclick = () => {
    modo = b.dataset.modo;
    document.querySelectorAll('[data-modo]').forEach(x => x.className = 'btn btn-mini ' + (x === b ? 'btn-primary' : 'btn-ghost'));
    leyenda(); red.repintar();
  });
  leyenda();

  /* ---------- panel de detalle ---------- */
  const panel = document.getElementById('rPanel');
  const enlace = n => `<a href="#" data-ir="${n.i}">${esc(n.t === 'simp' ? (n.nc || n.n) : n.n)}</a>`;
  function filaPersona(h) {
    return `<li><a href="#" data-ir="${h.i}"><span class="pt" style="background:${colorear(h) || (h.g >= 0 ? PALETA[h.g] : '#B9B3CC')}"></span>${esc(h.nc || h.n)}</a>
      ${h.total ? `<span class="tag tag-blue">red ${num(h.total)}</span>` : ''}</li>`;
  }
  function mostrar(n) {
    if (!n) return inicio();
    const camino = [];
    for (let p = n.padre; p; p = p.padre) camino.unshift(p);
    const hijos = [...n.hijos].sort((a, b) => b.total - a.total);
    const directos = n.hijos.filter(h => h.t === 'simp').length;
    let h = `<button type="button" class="red-cerrar" data-ir="-1" aria-label="Cerrar">✕</button>`;
    if (n.t === 'simp') {
      const niv = NIVELES[nivelDe(n)];
      h += `<span class="muted small">Capa ${n.capa} · ${n.padre && n.padre.t === 'simp' ? 'llegó invitado/a' : 'registrado/a por el equipo'}</span>
        <h3 class="red-nombre">${esc(n.nc || n.n)}</h3>
        <div class="red-tags">
          <span class="tag" style="background:${C_COMP[n.c] || '#B9B3CC'}22;color:${C_COMP[n.c] || '#6B6580'}">${esc(compNombre(n.c))}</span>
          <span class="tag tag-gold">${niv.emoji} ${esc(niv.nombre)}${n.p !== null && n.p !== undefined ? ' · ' + num(n.p) + ' pts' : ''}</span>
        </div>
        <dl class="red-datos">
          <dt>Barrio o vereda</dt><dd>${esc(n.z || '—')}</dd>
          <dt>Lo trajo</dt><dd>${n.padre ? enlace(n.padre) : '—'}</dd>
          <dt>Registrado</dt><dd>${n.f ? n.f.split('-').reverse().join('/') : '—'}</dd>
        </dl>`;
    } else {
      h += `<span class="muted small">${n.t === 'raiz' ? 'Centro de la red' : esc(n.r || 'Equipo de campaña')}</span><h3 class="red-nombre">${esc(n.n)}</h3>`;
    }
    h += `<div class="red-dos"><div><b>${num(directos)}</b><span>${n.t === 'simp' ? 'invitó directamente' : 'registró directamente'}</span></div>
          <div><b>${num(n.total)}</b><span>personas en toda su red</span></div></div>`;
    if (camino.length) h += `<p class="red-camino"><span class="muted small">Camino desde el centro</span><br>${camino.map(enlace).join(' › ')} › <b>${esc(n.nc || n.n)}</b></p>`;
    if (hijos.length) h += `<p class="muted small" style="margin:12px 0 4px">${n.t === 'simp' ? 'Personas que invitó' : 'Su red directa'} (${hijos.length})</p>
      <ul class="red-lista">${hijos.slice(0, 40).map(filaPersona).join('')}</ul>${hijos.length > 40 ? `<p class="muted small">y ${hijos.length - 40} más…</p>` : ''}`;
    else if (n.t === 'simp') h += `<p class="muted small" style="margin-top:12px">Aún no ha invitado a nadie. Puede invitar con su enlace personal desde su panel.</p>`;
    if (n.t === 'simp') h += `<a class="btn btn-ghost btn-mini" style="margin-top:12px" href="${URL_SIMP + encodeURIComponent(n.nc || n.n)}">Ver en Simpatizantes</a>`;
    panel.innerHTML = h;
  }
  function inicio() {
    panel.innerHTML = `<h3>Quién está trayendo más gente</h3>
      <p class="muted small" style="margin-bottom:8px">Toca un punto del grafo o un nombre para ver su red.</p>
      ${top.length ? `<ol class="red-top">${top.slice(0, 10).map(n => `<li><a href="#" data-ir="${n.i}">${esc(n.nc || n.n)}</a>
        <span><b>${num(n.hijos.filter(h => h.t === 'simp').length)}</b> invitó · red <b>${num(n.total)}</b></span></li>`).join('')}</ol>`
        : '<p class="muted small">Aún nadie ha invitado a otra persona con su enlace. Cuando lo hagan, aparecerán aquí.</p>'}
      <h3 style="margin-top:18px">Equipo</h3>
      <ul class="red-lista">${nodos.filter(n => n.t === 'equipo').sort((a, b) => b.total - a.total).map(filaPersona).join('') || '<li class="muted small">Sin líderes con red.</li>'}</ul>`;
  }
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-ir]'); if (!a) return;
    e.preventDefault();
    const i = +a.dataset.ir;
    if (i < 0) { red.elegir(null); return; }
    red.enfocar(i);
    if (a.closest('#rArbol')) document.getElementById('redGrande').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  inicio();

  /* ---------- buscador ---------- */
  const buscar = document.getElementById('rBuscar'), res = document.getElementById('rResultados');
  buscar.addEventListener('input', () => {
    const q = normal(buscar.value.trim());
    if (q.length < 2) { res.hidden = true; return; }
    const hallados = nodos.filter(n => n.t !== 'raiz' && normal(n.nc || n.n).includes(q)).slice(0, 8);
    res.innerHTML = hallados.length ? hallados.map(n => `<li><a href="#" data-ir="${n.i}">${esc(n.nc || n.n)}<small>${n.t === 'simp' ? 'capa ' + n.capa + (n.z ? ' · ' + esc(n.z) : '') : esc(n.r || 'equipo')}</small></a></li>`).join('')
      : '<li class="muted small" style="padding:8px 12px">Sin resultados</li>';
    res.hidden = false;
  });
  res.addEventListener('click', () => { res.hidden = true; buscar.value = ''; });
  document.addEventListener('click', e => { if (!e.target.closest('.red-buscar')) res.hidden = true; });

  /* ---------- árbol desplegable ---------- */
  const arbol = document.getElementById('rArbol');
  function ramas(n) {
    return [...n.hijos].sort((a, b) => b.total - a.total || (a.nc || a.n).localeCompare(b.nc || b.n)).map(h => {
      const directos = h.hijos.length;
      return `<li>
        <div class="fila">${directos ? `<button type="button" class="abrir" data-abrir="${h.i}" aria-expanded="false" aria-label="Ver su red">▸</button>` : '<span class="abrir sin"></span>'}
          <a href="#" data-ir="${h.i}"><span class="pt" style="background:${h.t === 'equipo' ? EQUIPO : (C_COMP[h.c] || '#B9B3CC')}"></span>${esc(h.nc || h.n)}</a>
          ${h.t === 'equipo' ? `<span class="tag tag-grey">${esc(h.r || 'equipo')}</span>` : ''}
          ${directos ? `<span class="muted small">${directos} ${h.t === 'simp' ? (directos === 1 ? 'invitado' : 'invitados') : (directos === 1 ? 'directo' : 'directos')}${h.total !== directos ? ' · red de ' + num(h.total) : ''}</span>` : ''}
        </div><ul class="hijos" hidden></ul></li>`;
    }).join('');
  }
  arbol.innerHTML = `<ul class="raiz"><li><div class="fila"><span class="pt" style="background:#1C1630"></span><b>${esc(nodos[0].n)}</b>
    <span class="muted small">${num(personas.length)} personas</span></div><ul class="hijos">${ramas(nodos[0])}</ul></li></ul>`;
  arbol.addEventListener('click', e => {
    const b = e.target.closest('[data-abrir]'); if (!b) return;
    const ul = b.closest('li').querySelector(':scope > .hijos');
    const abrir = ul.hidden;
    if (abrir && !ul.innerHTML) ul.innerHTML = ramas(nodos[+b.dataset.abrir]);
    ul.hidden = !abrir; b.textContent = abrir ? '▾' : '▸'; b.setAttribute('aria-expanded', abrir);
  });
})();
</script>
<?php endif; ?>
