<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$disponible) return; ?>
<?php if (!$campanasListas): ?>
  <div class="activar-red" style="margin-top:14px"><span class="ar-ico">📣</span><span class="ar-txt"><b>Los mensajes masivos aún no están activos</b>
    La dirección debe pulsar "Actualizar plataforma" en la Vista rápida.</span></div>
<?php return; endif; ?>
<?php
$v = $previo ?? [];
$f = wa_campana_filtros($v['filtros'] ?? []);
$marcado = fn(array $lista, $id) => in_array((int)$id, $lista, true) ? 'checked' : '';
// Datos de cada plantilla para la vista previa
$infoPlantillas = [];
foreach ($plantillas as $p) {
    $infoPlantillas[(int)$p['id']] = [
        'cuerpo' => (string)$p['cuerpo'],
        'campos' => array_values(array_filter(explode(',', (string)$p['variables']))),
        'extras' => json_decode((string)($p['extras'] ?? ''), true) ?: null,
        'categoria' => $p['categoria'],
    ];
}
?>

<p style="margin-top:14px"><a class="btn btn-ghost btn-mini" href="<?= url('whatsapp/campanas') ?>">← Volver a los envíos</a></p>

<?php if (!$plantillas): ?>
<section class="card" style="margin-top:12px;border-left:4px solid var(--warn)">
  <h3>Primero necesitas una plantilla aprobada</h3>
  <p class="small" style="line-height:1.6">WhatsApp solo deja escribirle primero a alguien con una plantilla aprobada por Meta.
    Para escribir un texto distinto en cada envío, crea en la pestaña <a href="<?= url('whatsapp/plantillas') ?>">Plantillas</a> la sugerida
    <code>mensaje_campana</code> («Hola, {{1}}. Te compartimos un mensaje del equipo de Diana Montes: {{2}}…»). Cuando Meta la apruebe, vuelve aquí.</p>
</section>
<?php return; endif; ?>

<form method="post" action="<?= url('whatsapp/crearcampana') ?>" id="formCampana" class="campana-grid" style="margin-top:12px">
  <?= \Core\Csrf::campo() ?>
  <input type="hidden" name="confirmar_total" id="confirmarTotal" value="-1">
  <div class="campana-pasos">

    <section class="card">
      <h3><span class="paso-n">1</span> ¿A quién le llega?</h3>
      <p class="muted small" style="margin-bottom:10px">Sin filtros le llega a todos los registros que aceptaron recibir mensajes. Cada filtro que marques reduce el grupo.</p>

      <div class="filtro-bloque">
        <span class="field-titulo">Barrios, veredas y corregimientos</span>
        <div class="seg">
          <label><input type="radio" name="zona_tipo" value="" <?= $f['zona_tipo'] === '' ? 'checked' : '' ?>> Todo el municipio</label>
          <label><input type="radio" name="zona_tipo" value="urbano" <?= $f['zona_tipo'] === 'urbano' ? 'checked' : '' ?>> Casco urbano</label>
          <label><input type="radio" name="zona_tipo" value="rural" <?= $f['zona_tipo'] === 'rural' ? 'checked' : '' ?>> Zona rural</label>
        </div>
        <details class="lista-filtro" <?= $f['zonas'] ? 'open' : '' ?>>
          <summary>Elegir barrios o veredas <span class="cuenta-sel" data-de="zonas[]"></span></summary>
          <input type="search" class="buscar-lista" placeholder="Buscar barrio o vereda…" aria-label="Buscar barrio o vereda">
          <div class="checks">
            <?php foreach ($zonas as $z): ?>
              <label><input type="checkbox" name="zonas[]" value="<?= (int)$z['id'] ?>" <?= $marcado($f['zonas'], $z['id']) ?>> <?= e(zona_etiqueta($z)) ?></label>
            <?php endforeach; ?>
          </div>
        </details>
      </div>

      <div class="filtro-bloque">
        <details class="lista-filtro" <?= $f['lideres'] ? 'open' : '' ?>>
          <summary>Por líder o coordinador <span class="cuenta-sel" data-de="lideres[]"></span></summary>
          <input type="search" class="buscar-lista" placeholder="Buscar líder…" aria-label="Buscar líder">
          <div class="checks">
            <?php foreach ($lideres as $l): ?>
              <label><input type="checkbox" name="lideres[]" value="<?= (int)$l['id'] ?>" <?= $marcado($f['lideres'], $l['id']) ?>> <?= e($l['nombre']) ?></label>
            <?php endforeach; ?>
            <?php if (!$lideres): ?><span class="muted small">No hay líderes activos.</span><?php endif; ?>
          </div>
        </details>
      </div>

      <div class="filtro-bloque">
        <details class="lista-filtro" <?= $f['puestos'] ? 'open' : '' ?>>
          <summary>Por puesto de votación <span class="cuenta-sel" data-de="puestos[]"></span></summary>
          <input type="search" class="buscar-lista" placeholder="Buscar puesto…" aria-label="Buscar puesto">
          <div class="checks">
            <?php foreach ($puestos as $p): ?>
              <label><input type="checkbox" name="puestos[]" value="<?= (int)$p['id'] ?>" <?= $marcado($f['puestos'], $p['id']) ?>> <?= e($p['nombre']) ?></label>
            <?php endforeach; ?>
          </div>
        </details>
      </div>

      <div class="filtro-bloque">
        <span class="field-titulo">Nivel de compromiso</span>
        <div class="chips-check">
          <?php foreach (COMPROMISOS as $k => $t): ?>
            <label><input type="checkbox" name="niveles[]" value="<?= $k ?>" <?= in_array($k, $f['niveles'], true) ? 'checked' : '' ?>><span><?= e($t) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <details class="filtro-mas" <?= ($f['genero'] || $f['edad_min'] || $f['edad_max'] || $f['profesiones'] || $f['red'] || $f['verificados'] || $f['promotores']) ? 'open' : '' ?>>
        <summary>Más filtros: género, edad, profesión, red de un promotor</summary>
        <div class="filtro-bloque">
          <span class="field-titulo">Género</span>
          <div class="seg">
            <label><input type="radio" name="genero" value="" <?= $f['genero'] === '' ? 'checked' : '' ?>> Todos</label>
            <label><input type="radio" name="genero" value="mujer" <?= $f['genero'] === 'mujer' ? 'checked' : '' ?>> Mujeres</label>
            <label><input type="radio" name="genero" value="hombre" <?= $f['genero'] === 'hombre' ? 'checked' : '' ?>> Hombres</label>
          </div>
        </div>
        <div class="filtro-bloque">
          <span class="field-titulo">Edad</span>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="number" name="edad_min" id="edadMin" min="14" max="120" placeholder="Desde" value="<?= $f['edad_min'] ?: '' ?>" style="width:90px" aria-label="Edad desde">
            <span class="muted">a</span>
            <input type="number" name="edad_max" id="edadMax" min="14" max="120" placeholder="Hasta" value="<?= $f['edad_max'] ?: '' ?>" style="width:90px" aria-label="Edad hasta">
            <span class="muted small">años</span>
            <?php foreach (WA_RANGOS_EDAD as $r => $t): [$a, $b] = explode('-', $r); ?>
              <button type="button" class="btn btn-ghost btn-mini" data-edad="<?= $a ?>,<?= $b ?>"><?= e($t) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="filtro-bloque">
          <details class="lista-filtro" <?= $f['profesiones'] ? 'open' : '' ?>>
            <summary>Por profesión u oficio <span class="cuenta-sel" data-de="profesiones[]"></span></summary>
            <input type="search" class="buscar-lista" placeholder="Buscar profesión…" aria-label="Buscar profesión">
            <div class="checks">
              <?php foreach ($profesiones as $p): ?>
                <label><input type="checkbox" name="profesiones[]" value="<?= (int)$p['id'] ?>" <?= $marcado($f['profesiones'], $p['id']) ?>> <?= e($p['nombre']) ?></label>
              <?php endforeach; ?>
            </div>
          </details>
        </div>
        <div class="filtro-bloque">
          <label class="field"><span>Red de un promotor (él y todos los que invitó, en cadena)</span>
            <select name="red">
              <option value="0">— Ninguno —</option>
              <?php foreach ($promotores as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $f['red'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?> (<?= num((int)$p['invitados']) ?> invitados directos)</option>
              <?php endforeach; ?>
            </select></label>
        </div>
        <div class="filtro-bloque chips-check">
          <label><input type="checkbox" name="verificados" value="1" <?= $f['verificados'] ? 'checked' : '' ?>><span>Solo verificados por llamada</span></label>
          <label><input type="checkbox" name="promotores" value="1" <?= $f['promotores'] ? 'checked' : '' ?>><span>Solo promotores (han invitado a alguien)</span></label>
        </div>
      </details>
    </section>

    <section class="card" style="margin-top:16px">
      <h3><span class="paso-n">2</span> ¿Qué mensaje?</h3>
      <div class="form-grid">
        <label class="field full"><span>Nombre del envío (solo para ti) *</span>
          <input name="nombre" maxlength="120" required value="<?= e($v['nombre'] ?? '') ?>" placeholder="Ej: Invitación jornada de limpieza quebrada Garzón"></label>
        <label class="field full"><span>Plantilla aprobada *</span>
          <select name="plantilla_id" id="plantillaSel" required>
            <option value="">Elige la plantilla…</option>
            <?php foreach ($plantillas as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= (int)($v['plantilla_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?> · <?= e(WA_CATEGORIAS[$p['categoria']] ?? (string)$p['categoria']) ?></option>
            <?php endforeach; ?>
          </select></label>
      </div>
      <label class="field" id="campoTexto" style="margin-top:10px;display:none"><span>Tu mensaje (va dentro de la plantilla, máximo 700 caracteres, en un solo párrafo)</span>
        <textarea name="texto" id="textoCampana" rows="4" maxlength="700" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"
          placeholder="Ej: este sábado a las 8 a. m. hacemos una jornada de limpieza en la quebrada Garzón. ¡Te esperamos con tu familia!"><?= e($v['texto'] ?? '') ?></textarea>
        <small class="muted"><b id="textoCuenta">0</b>/700 · WhatsApp no permite saltos de línea dentro del texto variable.</small></label>
      <span class="field-titulo" style="margin-top:12px;display:block">Así se verá (con el nombre de cada persona)</span>
      <div class="wa-chat"><div class="wa-burbuja" id="vistaPrevia"><span class="muted">Elige una plantilla para ver el mensaje.</span></div></div>
    </section>

    <section class="card" style="margin-top:16px">
      <h3><span class="paso-n">3</span> ¿Cuándo?</h3>
      <div class="seg">
        <label><input type="radio" name="cuando" value="ahora" <?= ($v['cuando'] ?? 'ahora') !== 'programar' ? 'checked' : '' ?>> Enviar ya</label>
        <label><input type="radio" name="cuando" value="programar" <?= ($v['cuando'] ?? '') === 'programar' ? 'checked' : '' ?>> Programar</label>
      </div>
      <label class="field" id="campoFecha" style="margin-top:10px;max-width:280px;<?= ($v['cuando'] ?? '') === 'programar' ? '' : 'display:none' ?>"><span>Fecha y hora</span>
        <input type="datetime-local" name="programada_para" value="<?= e($v['programada_para'] ?? '') ?>" min="<?= date('Y-m-d\TH:i') ?>"></label>
      <p class="muted small nota">Los mensajes salen entre las 7 a. m. y las <?= (int)wa_cfg('WA_HORA_FIN', 21) - 12 ?> p. m., hasta <?= num($cupo['tope']) ?> por día. Los saludos y avisos del día salen primero.</p>
    </section>
  </div>

  <aside class="campana-resumen">
    <section class="card">
      <span class="lbl">Lo recibirán</span>
      <div class="kpi-num" id="totalRecibiran">…</div>
      <span class="muted small" id="resumenFiltros">Calculando…</span>
      <ul class="excluidos small" id="excluidos"></ul>
      <div id="porZona" class="small"></div>
      <p class="small" id="estimado" style="margin-top:8px"></p>
      <button class="btn btn-primary btn-block" type="submit" id="btnEnviar" style="margin-top:12px">Enviar</button>
      <p class="muted small solo-escritorio" style="margin-top:8px">Después del envío verás cuántos se entregaron, leyeron y respondieron.</p>
    </section>
  </aside>
</form>

<script>
(function () {
  const form = document.getElementById('formCampana');
  const info = <?= json_encode($infoPlantillas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const ejemplos = <?= json_encode(WA_EJEMPLOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const tope = <?= (int)$cupo['tope'] ?>;
  const sel = document.getElementById('plantillaSel'), texto = document.getElementById('textoCampana');
  const campoTexto = document.getElementById('campoTexto'), vista = document.getElementById('vistaPrevia');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const fmt = n => Number(n).toLocaleString('es-CO');

  // Vista previa del mensaje
  function previa() {
    const p = info[sel.value];
    campoTexto.style.display = p && p.campos.includes('texto_campana') ? '' : 'none';
    document.getElementById('textoCuenta').textContent = texto.value.length;
    if (!p) { vista.innerHTML = '<span class="muted">Elige una plantilla para ver el mensaje.</span>'; return; }
    let cuerpo = esc(p.cuerpo).replace(/\{\{\s*(\d+)\s*\}\}/g, (m, n) => {
      const campo = p.campos[n - 1];
      const val = campo === 'texto_campana' ? (texto.value.trim() || '[tu mensaje]') : (ejemplos[campo] || '…');
      return '<mark>' + esc(val) + '</mark>';
    });
    const x = p.extras || {};
    vista.innerHTML = (x.encabezado ? '<b class="wa-cab">' + esc(x.encabezado) + '</b>' : '')
      + '<div class="wa-cuerpo">' + cuerpo + '</div>'
      + (x.pie ? '<div class="wa-pie">' + esc(x.pie) + '</div>' : '')
      + (x.botones && x.botones.length ? '<div class="wa-botones">' + x.botones.map(b => '<span>' + (b.tipo === 'URL' ? '↗ ' : b.tipo === 'PHONE_NUMBER' ? '☎ ' : '↩ ') + esc(b.texto) + '</span>').join('') + '</div>' : '');
  }
  sel.addEventListener('change', previa); texto.addEventListener('input', previa); previa();

  // Programar
  form.querySelectorAll('input[name=cuando]').forEach(r => r.addEventListener('change', () => {
    document.getElementById('campoFecha').style.display = form.cuando.value === 'programar' ? '' : 'none';
  }));

  // Buscar dentro de las listas y contar lo marcado
  form.querySelectorAll('.lista-filtro').forEach(d => {
    const q = d.querySelector('.buscar-lista');
    q && q.addEventListener('input', () => {
      const t = q.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
      d.querySelectorAll('.checks label').forEach(l => {
        l.style.display = l.textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(t) ? '' : 'none';
      });
    });
  });
  function cuentas() {
    form.querySelectorAll('.cuenta-sel').forEach(s => {
      const n = form.querySelectorAll('input[name="' + s.dataset.de + '"]:checked').length;
      s.textContent = n ? '· ' + n + ' elegido' + (n === 1 ? '' : 's') : '';
    });
  }
  form.querySelectorAll('[data-edad]').forEach(b => b.addEventListener('click', () => {
    const [a, c] = b.dataset.edad.split(',');
    document.getElementById('edadMin').value = a; document.getElementById('edadMax').value = c < 120 ? c : '';
    contar();
  }));

  // Conteo en vivo
  let timer, ultima = 0;
  function contar() {
    clearTimeout(timer); cuentas();
    timer = setTimeout(async () => {
      const id = ++ultima;
      const datos = new FormData(form); datos.delete('texto'); datos.delete('nombre');
      try {
        const r = await fetch(<?= json_encode(url('whatsapp/audiencia')) ?>, {method: 'POST', body: datos, credentials: 'same-origin'});
        const j = await r.json();
        if (id !== ultima) return;
        if (j.error) { document.getElementById('resumenFiltros').textContent = j.error; return; }
        document.getElementById('totalRecibiran').textContent = fmt(j.recibiran) + (j.recibiran === 1 ? ' persona' : ' personas');
        document.getElementById('resumenFiltros').textContent = j.resumen;
        document.getElementById('confirmarTotal').value = j.recibiran;
        const ex = [];
        if (j.sin_consentimiento) ex.push(fmt(j.sin_consentimiento) + ' sin autorización de datos');
        if (j.bajas) ex.push(fmt(j.bajas) + ' se dieron de baja');
        if (j.sin_celular) ex.push(fmt(j.sin_celular) + ' sin celular válido');
        document.getElementById('excluidos').innerHTML = ex.length ? '<li class="muted">De ' + fmt(j.coinciden) + ' registros que coinciden, no reciben:</li>' + ex.map(e => '<li>' + e + '</li>').join('') : '';
        document.getElementById('porZona').innerHTML = j.por_zona.length && j.recibiran ? '<span class="field-titulo">Dónde están</span>' + j.por_zona.map(z => '<div class="zona-fila"><span>' + esc(z[0]) + '</span><b>' + fmt(z[1]) + '</b></div>').join('') : '';
        const dias = Math.ceil(j.recibiran / Math.max(1, tope));
        document.getElementById('estimado').innerHTML = j.recibiran ? (dias > 1 ? '⏱️ Con el tope de ' + fmt(tope) + ' al día, el envío completo tarda unos <b>' + dias + ' días</b>.' : '⏱️ Sale completo hoy.') : '';
        document.getElementById('btnEnviar').disabled = !j.recibiran;
        document.getElementById('btnEnviar').textContent = j.recibiran ? 'Enviar a ' + fmt(j.recibiran) + (j.recibiran === 1 ? ' persona' : ' personas') : 'Nadie recibe con estos filtros';
      } catch (e) { document.getElementById('resumenFiltros').textContent = 'No se pudo calcular. Revisa tu conexión.'; }
    }, 250);
  }
  form.addEventListener('change', e => { if (!['texto', 'nombre', 'plantilla_id', 'cuando', 'programada_para'].includes(e.target.name)) contar(); });
  form.querySelectorAll('input[type=number]').forEach(i => i.addEventListener('input', contar));
  contar();

  form.addEventListener('submit', e => {
    const n = document.getElementById('confirmarTotal').value;
    const prog = form.cuando.value === 'programar';
    if (!confirm((prog ? '¿Programar' : '¿Enviar ya') + ' este mensaje a ' + fmt(n) + ' personas?')) e.preventDefault();
  });
})();
</script>
