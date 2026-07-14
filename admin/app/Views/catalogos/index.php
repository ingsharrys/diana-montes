<style>
/* ===== estilos del módulo Catálogos (autocontenidos en esta vista) ===== */
.cat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;align-items:start}
@media(max-width:1020px){.cat-grid{grid-template-columns:1fr}}

.cat-form{display:grid;gap:8px;margin-bottom:14px;padding:12px;background:#F8FAFD;border:1.5px dashed var(--line);border-radius:12px}
.cat-form input,.cat-form select{border:1.5px solid var(--line);border-radius:9px;padding:9px 11px;background:#fff;font-size:13.5px;width:100%;font-family:inherit}
.cat-form input:focus,.cat-form select:focus{border-color:var(--navy2);outline:none}
.cat-form .cf-fila{display:flex;gap:8px}
.cat-form .cf-fila > *{flex:1}
.cat-form button{border:none;border-radius:9px;padding:10px;font-weight:700;font-size:13px;background:var(--navy2);color:#fff;cursor:pointer}
.cat-form button:hover{background:var(--navy)}
.cat-form .cf-label{font-size:11px;font-weight:700;color:var(--muted);margin-bottom:-4px}

.cat-lista{max-height:430px;overflow-y:auto;padding-right:4px}
.cat-lista::-webkit-scrollbar{width:6px}
.cat-lista::-webkit-scrollbar-thumb{background:#D5DAE6;border-radius:99px}

.cat-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;font-size:13px;border:1px solid transparent}
.cat-item:hover{background:#F8FAFD;border-color:var(--line)}
.cat-item + .cat-item{margin-top:2px}
.cat-nombre{flex:1;min-width:0;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.cat-nombre b{font-weight:600}
.cat-uso{color:var(--muted);font-size:11.5px;white-space:nowrap;background:#EEF1F6;border-radius:99px;padding:3px 9px;flex:none}
.cat-uso.con-gente{background:#E7EDF9;color:var(--navy2);font-weight:700}

.cat-item form{display:flex;flex:none;margin:0}
.cat-del{border:none;background:transparent;color:#C3C9D6;width:26px;height:26px;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .15s,color .15s}
.cat-item:hover .cat-del{color:var(--bad)}
.cat-del:hover{background:#FBE9E8;color:var(--bad)}
.cat-bloqueado{width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#C3C9D6;flex:none;cursor:help}
</style>

<p class="muted" style="margin-bottom:14px">Lo que agregues aquí aparece <b>al instante</b> en el formulario público de dianamontes.com (misma base de datos). El 🔒 indica que el elemento no puede eliminarse porque hay personas registradas con él.</p>

<div class="cat-grid">

  <!-- ================= ZONAS ================= -->
  <div class="card">
    <h3>🏘 Barrios y veredas <span class="tag tag-grey"><?= count($zonas) ?></span></h3>
    <form method="post" action="<?= url('catalogos/zona') ?>" class="cat-form">
      <?= \Core\Csrf::campo() ?>
      <div class="cf-fila">
        <input name="nombre" placeholder="Nombre del barrio o vereda" required>
        <select name="tipo" style="max-width:110px"><option value="urbano">Urbano</option><option value="rural">Rural</option></select>
      </div>
      <button type="submit">＋ Agregar zona</button>
    </form>
    <div class="cat-lista">
      <?php foreach ($zonas as $z): $uso = (int)$z['uso']; ?>
      <div class="cat-item">
        <span class="cat-nombre"><b><?= e($z['nombre']) ?></b>
          <span class="tag <?= $z['tipo']==='rural'?'tag-gold':'tag-blue' ?>"><?= e($z['tipo']) ?></span>
        </span>
        <span class="cat-uso <?= $uso ? 'con-gente' : '' ?>" title="Personas registradas con esta zona"><?= $uso ?> 👤</span>
        <?php if (!$uso): ?>
        <form method="post" action="<?= url('catalogos/eliminar/zonas/' . (int)$z['id']) ?>" onsubmit="return confirm('¿Eliminar <?= e($z['nombre']) ?>?')">
          <?= \Core\Csrf::campo() ?><button class="cat-del" title="Eliminar" aria-label="Eliminar <?= e($z['nombre']) ?>">✕</button>
        </form>
        <?php else: ?><span class="cat-bloqueado" title="En uso: no se puede eliminar">🔒</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ================= PUESTOS ================= -->
  <div class="card">
    <h3>🗳 Puestos de votación <span class="tag tag-grey"><?= count($puestos) ?></span></h3>
    <form method="post" action="<?= url('catalogos/puesto') ?>" class="cat-form">
      <?= \Core\Csrf::campo() ?>
      <input name="nombre" placeholder="Nombre del puesto" required>
      <div class="cf-fila">
        <input name="direccion" placeholder="Dirección (opcional)">
        <select name="zona_id">
          <option value="">Zona (opcional)</option>
          <?php foreach ($zonasSimple as $z): ?>
          <option value="<?= (int)$z['id'] ?>"><?= e($z['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit">＋ Agregar puesto</button>
    </form>
    <div class="cat-lista">
      <?php foreach ($puestos as $p): $uso = (int)$p['uso']; ?>
      <div class="cat-item">
        <span class="cat-nombre"><b><?= e($p['nombre']) ?></b>
          <?php if ($p['zona']): ?><span class="muted small">· <?= e($p['zona']) ?></span><?php endif; ?>
        </span>
        <span class="cat-uso <?= $uso ? 'con-gente' : '' ?>" title="Personas registradas con este puesto"><?= $uso ?> 👤</span>
        <?php if (!$uso): ?>
        <form method="post" action="<?= url('catalogos/eliminar/puestos_votacion/' . (int)$p['id']) ?>" onsubmit="return confirm('¿Eliminar <?= e($p['nombre']) ?>?')">
          <?= \Core\Csrf::campo() ?><button class="cat-del" title="Eliminar" aria-label="Eliminar <?= e($p['nombre']) ?>">✕</button>
        </form>
        <?php else: ?><span class="cat-bloqueado" title="En uso: no se puede eliminar">🔒</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ================= PROFESIONES ================= -->
  <div class="card">
    <h3>💼 Profesiones / ocupaciones <span class="tag tag-grey"><?= count($profesiones) ?></span></h3>
    <form method="post" action="<?= url('catalogos/profesion') ?>" class="cat-form">
      <?= \Core\Csrf::campo() ?>
      <input name="nombre" placeholder="Ej: Enfermera, Mototaxista…" required>
      <span class="cf-label">Día gremial (opcional, para los mensajes de WhatsApp)</span>
      <input type="date" name="dia_celebracion">
      <button type="submit">＋ Agregar profesión</button>
    </form>
    <div class="cat-lista">
      <?php foreach ($profesiones as $p): $uso = (int)$p['uso']; ?>
      <div class="cat-item">
        <span class="cat-nombre"><b><?= e($p['nombre']) ?></b>
          <?php if ($p['dia_celebracion']): ?><span class="tag tag-gold">🎉 <?= date('d M', strtotime($p['dia_celebracion'])) ?></span><?php endif; ?>
        </span>
        <span class="cat-uso <?= $uso ? 'con-gente' : '' ?>" title="Personas registradas con esta profesión"><?= $uso ?> 👤</span>
        <?php if (!$uso): ?>
        <form method="post" action="<?= url('catalogos/eliminar/profesiones/' . (int)$p['id']) ?>" onsubmit="return confirm('¿Eliminar <?= e($p['nombre']) ?>?')">
          <?= \Core\Csrf::campo() ?><button class="cat-del" title="Eliminar" aria-label="Eliminar <?= e($p['nombre']) ?>">✕</button>
        </form>
        <?php else: ?><span class="cat-bloqueado" title="En uso: no se puede eliminar">🔒</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>