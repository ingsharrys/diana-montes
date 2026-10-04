<?php
/** Cola "Completar y verificar" */
$pestanasCola = [
    'pendientes'    => 'Pendientes',
    'sin_puesto'    => 'Sin puesto o mesa',
    'sin_verificar' => 'Sin verificar',
    'todos'         => 'Todos',
];
$enlace = function (array $cambios) use ($filtros): string {
    $q = array_filter(array_merge($filtros, ['p' => null], $cambios), fn($v) => $v !== null && $v !== '' && $v !== 0);
    if (defined('USE_REWRITE') && USE_REWRITE === false) return url('simpatizantes/verificar') . '&' . http_build_query($q);
    return url('simpatizantes/verificar') . '?' . http_build_query($q);
};
?>
<section class="card">
  <h3>Completar y verificar la base de votos</h3>
  <p class="muted small" style="margin-bottom:12px;line-height:1.6">
    Un registro solo se vuelve voto si sabemos <b>dónde vota</b> y <b>qué tan comprometido está</b>.
    Completa el puesto y la mesa (consúltalos con la cédula en la Registraduría) y, cuando llames a la persona,
    marca <b>"Llamé y confirmé"</b> con su nivel de compromiso real.
  </p>

  <nav class="cola-tabs" aria-label="Filtrar la cola">
    <?php foreach ($pestanasCola as $clave => $etiqueta):
      if ($clave === 'sin_verificar' && !$conVerificacion) continue; ?>
      <a class="cola-tab <?= $filtros['estado'] === $clave ? 'activo' : '' ?>" href="<?= e($enlace(['estado' => $clave])) ?>">
        <?= e($etiqueta) ?> <b><?= num($conteo[$clave]) ?></b></a>
    <?php endforeach; ?>
  </nav>

  <form class="toolbar" method="get" action="<?= url('simpatizantes/verificar') ?>" style="margin-top:12px">
    <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?><input type="hidden" name="url" value="simpatizantes/verificar"><?php endif; ?>
    <input type="hidden" name="estado" value="<?= e($filtros['estado']) ?>">
    <input type="search" name="q" placeholder="Buscar nombre o documento…" value="<?= e($filtros['q']) ?>">
    <select name="zona" onchange="this.form.submit()" aria-label="Zona">
      <option value="0">Todas las zonas</option>
      <?php foreach ($zonas as $z): ?>
        <option value="<?= (int)$z['id'] ?>" <?= $filtros['zona'] === (int)$z['id'] ? 'selected' : '' ?>><?= e($z['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost" type="submit">Buscar</button>
  </form>
  <?php if (!$conVerificacion): ?>
    <p class="alert alert-error" style="font-weight:500">La dirección debe pulsar "Actualizar plataforma" en la Vista rápida para activar la escala de compromiso completa y la verificación por llamada.</p>
  <?php endif; ?>
</section>

<section class="card" style="margin-top:16px">
  <?php if (!$lista): ?>
    <p class="vacio muted" style="text-align:center">🎉 No hay registros en este filtro. <?= $filtros['estado'] === 'pendientes' ? '¡La base está al día!' : '' ?></p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr>
      <th>Simpatizante</th><th>Contacto</th><th>Puesto de votación</th><th>Mesa</th><th>Compromiso</th>
      <?php if ($conVerificacion): ?><th>Llamé y confirmé</th><?php endif; ?><th></th>
    </tr>
    <?php foreach ($lista as $s): $fid = 'fv' . (int)$s['id']; ?>
    <tr id="fila-<?= (int)$s['id'] ?>">
      <td>
        <form id="<?= $fid ?>" method="post" action="<?= url('simpatizantes/actualizar/' . (int)$s['id']) ?>" class="form-fila"><?= \Core\Csrf::campo() ?></form>
        <b><?= e($s['nombre']) ?></b><br>
        <span class="muted small">CC <?= e($s['documento']) ?> · <?= e($s['zona'] ?? '—') ?> · red de <?= e($s['lider']) ?></span>
        <?php if ($s['verificado_at']): ?>
          <br><span class="tag tag-green small" data-estado>✓ Verificado <?= fecha_co($s['verificado_at']) ?><?= $s['verificado_por_nombre'] ? ' · ' . e($s['verificado_por_nombre']) : '' ?></span>
        <?php else: ?>
          <br><span class="tag tag-grey small" data-estado>Sin verificar</span>
        <?php endif; ?>
      </td>
      <td class="masked">
        <?php if ($verTelefono): ?>
          <a class="tel" href="tel:+57<?= e($s['telefono']) ?>"><?= e($s['telefono']) ?></a><br>
          <a class="small" target="_blank" rel="noopener" href="https://wa.me/57<?= e($s['telefono']) ?>">WhatsApp</a>
        <?php else: ?><?= enmascarar($s['telefono']) ?><?php endif; ?>
      </td>
      <td>
        <select name="puesto_id" form="<?= $fid ?>" aria-label="Puesto de votación de <?= e($s['nombre']) ?>">
          <option value="">Sin asignar</option>
          <?php foreach ($puestos as $p): ?>
            <option value="<?= (int)$p['id'] ?>" data-mesas="<?= (int)$p['mesas'] ?>" <?= (int)$s['puesto_id'] === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['nombre']) ?><?= $p['zona'] ? ' · ' . e($p['zona']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input name="mesa" form="<?= $fid ?>" class="in-mesa" inputmode="numeric" maxlength="4" value="<?= e($s['mesa']) ?>" placeholder="—" aria-label="Mesa"></td>
      <td>
        <select name="nivel" form="<?= $fid ?>" aria-label="Compromiso">
          <?php foreach ($compromisos as $valor => $etiqueta): ?>
            <option value="<?= $valor ?>" <?= $s['nivel'] === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <?php if ($conVerificacion): ?>
      <td style="text-align:center"><input type="checkbox" name="verificado" value="1" form="<?= $fid ?>" class="chk-verif" aria-label="Llamé y confirmé"></td>
      <?php endif; ?>
      <td><button class="btn btn-primary btn-mini" type="submit" form="<?= $fid ?>">Guardar</button>
        <span class="fila-msg small" aria-live="polite"></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php if ($paginas > 1): ?>
  <div class="paginacion">
    <?php if ($pagina > 1): ?><a class="btn btn-ghost btn-mini" href="<?= e($enlace(['p' => $pagina - 1])) ?>">← Anterior</a><?php endif; ?>
    <span class="muted small">Página <?= $pagina ?> de <?= $paginas ?></span>
    <?php if ($pagina < $paginas): ?><a class="btn btn-ghost btn-mini" href="<?= e($enlace(['p' => $pagina + 1])) ?>">Siguiente →</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<script>
/* Guardado sin recargar: cada fila se envía por su cuenta y muestra el resultado ahí mismo */
document.querySelectorAll('form.form-fila').forEach(f => f.addEventListener('submit', async ev => {
  ev.preventDefault();
  const fila = document.getElementById('fila-' + f.id.slice(2));
  const msg = fila.querySelector('.fila-msg');
  const btn = fila.querySelector('button[type=submit]');
  btn.disabled = true; msg.textContent = 'Guardando…'; msg.className = 'fila-msg small muted';
  try {
    const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'fetch' } });
    const d = await r.json();
    msg.textContent = d.msg; msg.className = 'fila-msg small ' + (d.ok ? 'ok' : 'err');
    if (d.ok) {
      fila.classList.add('guardada');
      if (d.verificado) {
        const est = fila.querySelector('[data-estado]');
        est.className = 'tag tag-green small'; est.textContent = '✓ Verificado ' + d.verificado;
        const chk = fila.querySelector('.chk-verif'); if (chk) chk.checked = false;
      }
    }
  } catch (e) {
    msg.textContent = 'Sin conexión. Intenta de nuevo.'; msg.className = 'fila-msg small err';
  }
  btn.disabled = false;
}));
</script>
