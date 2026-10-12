<?php
/** Organización › Publicidad impresa: pedidos, bodega y entregas por zona */
use Models\Organizacion as O;
require __DIR__ . '/_tabs.php';
if (!$disponible) return;
$x = $editar ?? [];
$estTag = ['cotizado' => 'tag-grey', 'pedido' => 'tag-gold', 'recibido' => 'tag-green'];
?>
<div class="grid g2" style="margin-top:14px;align-items:start">
  <section class="card">
    <h3>Piezas <span class="tag tag-grey"><?= num(count($piezas)) ?></span></h3>
    <?php if (!$piezas): ?>
      <p class="muted vacio">Registra el primer pedido de afiches, volantes, pendones o camisetas con el formulario.</p>
    <?php else: ?>
    <div class="tbl-wrap">
    <table class="cola">
      <tr><th>Pieza</th><th>Estado</th><th style="text-align:right">Cantidad</th><th style="min-width:120px">Entregado</th><?php if ($verDinero): ?><th style="text-align:right">Costo</th><?php endif; ?><th></th></tr>
      <?php foreach ($piezas as $p): $bodega = (int)$p['cantidad'] - (int)$p['entregado']; $pe = $p['cantidad'] ? (int)$p['entregado'] * 100 / (int)$p['cantidad'] : 0; ?>
      <tr>
        <td><b><?= e(O::PUBLICIDAD[$p['tipo']] ?? $p['tipo']) ?></b><?= $p['descripcion'] ? '<br><span class="small">' . e($p['descripcion']) . '</span>' : '' ?>
          <br><span class="muted small"><?= e($p['proveedor'] ?? 'sin proveedor') ?><?= $p['fecha_entrega'] ? ' · llega ' . e(date('d/m', strtotime($p['fecha_entrega']))) : '' ?></span></td>
        <td><span class="tag <?= $estTag[$p['estado']] ?>"><?= e(O::PUB_ESTADOS[$p['estado']]) ?></span></td>
        <td style="text-align:right"><b><?= num((int)$p['cantidad']) ?></b></td>
        <td><?php if ($p['estado'] === 'recibido'): ?><div class="progreso mini"><i style="width:<?= round($pe, 1) ?>%"></i></div>
          <span class="muted small"><?= num((int)$p['entregado']) ?> · quedan <b><?= num($bodega) ?></b></span><?php else: ?><span class="muted small">—</span><?php endif; ?></td>
        <?php if ($verDinero): ?><td style="text-align:right" class="small"><?= $p['costo'] ? pesos((float)$p['costo']) . '<br><span class="muted">' . pesos((float)$p['costo'] / max(1, (int)$p['cantidad'])) . ' c/u</span>' : '—' ?></td><?php endif; ?>
        <td style="white-space:nowrap">
          <a class="btn btn-ghost btn-mini" href="<?= url_con('organizacion/publicidad', ['editar' => $p['id']]) ?>#form">Editar</a>
          <?php if ($p['estado'] === 'recibido' && $bodega > 0): ?>
          <details class="pago-rapido"><summary class="btn btn-primary btn-mini">Entregar</summary>
            <form method="post" action="<?= url('organizacion/entrega/' . (int)$p['id']) ?>">
              <?= \Core\Csrf::campo() ?>
              <input name="cantidad" data-tipo="numero" maxlength="7" placeholder="Cantidad (máx. <?= num($bodega) ?>)" required aria-label="Cantidad a entregar">
              <select name="zona_id" aria-label="Zona"><option value="">Zona…</option><?= zonas_opciones($zonas) ?></select>
              <input name="responsable" maxlength="120" placeholder="Quién la recibe" aria-label="Quién la recibe">
              <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" aria-label="Fecha de entrega">
              <button class="btn btn-primary btn-mini" type="submit">Registrar entrega</button>
            </form></details>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="card card-form" id="form">
    <h3><?= $x ? 'Editar pieza' : 'Nuevo pedido' ?></h3>
    <form method="post" action="<?= url('organizacion/guardarpieza/' . (int)($x['id'] ?? 0)) ?>" novalidate>
      <?= \Core\Csrf::campo() ?>
      <div class="form-grid">
        <label class="field"><span>Tipo *</span>
          <select name="tipo" required><option value="">Selecciona…</option>
            <?php foreach (O::PUBLICIDAD as $k => $t): ?><option value="<?= $k ?>" <?= ($x['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
          </select></label>
        <label class="field"><span>Cantidad *</span>
          <input name="cantidad" data-tipo="numero" maxlength="8" value="<?= e((string)($x['cantidad'] ?? '')) ?>" required placeholder="Ej: 5000"></label>
        <label class="field full"><span>Descripción</span>
          <input name="descripcion" maxlength="150" value="<?= e($x['descripcion'] ?? '') ?>" placeholder="Ej: Afiche 50×70 «Conoce a Diana», full color"></label>
        <label class="field"><span>Proveedor</span>
          <input name="proveedor" maxlength="120" value="<?= e($x['proveedor'] ?? '') ?>"></label>
        <label class="field"><span>Estado</span>
          <select name="estado"><?php foreach (O::PUB_ESTADOS as $k => $t): ?><option value="<?= $k ?>" <?= ($x['estado'] ?? 'pedido') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
        <?php if ($verDinero): ?>
        <label class="field"><span>Costo total</span>
          <input name="costo" data-tipo="numero" maxlength="11" value="<?= !empty($x['costo']) ? (int)$x['costo'] : '' ?>" placeholder="Ej: 1800000"></label>
        <?php endif; ?>
        <label class="field"><span>Fecha del pedido</span><input type="date" name="fecha_pedido" value="<?= e($x['fecha_pedido'] ?? date('Y-m-d')) ?>"></label>
        <label class="field"><span>Fecha de entrega del proveedor</span><input type="date" name="fecha_entrega" value="<?= e($x['fecha_entrega'] ?? '') ?>"></label>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-primary" type="submit"><?= $x ? 'Guardar cambios' : 'Registrar pedido' ?></button>
        <?php if ($x): ?><a class="btn btn-ghost" href="<?= url('organizacion/publicidad') ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <?php if ($x && $verDinero): ?>
      <form method="post" action="<?= url('organizacion/borrar/pieza/' . (int)$x['id']) ?>" style="margin-top:10px" onsubmit="return confirm('¿Eliminar esta pieza y sus entregas?')">
        <?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini txt-peligro" type="submit">Eliminar pieza</button></form>
    <?php endif; ?>
    <p class="muted small nota">Las piezas pasan a la bodega cuando quedan en estado <b>Recibido</b>. Desde ahí se entregan a cada zona.</p>
  </section>
</div>

<div class="grid g2" style="margin-top:16px;align-items:start">
  <section class="card embudo">
    <h3>Entregado por zona</h3>
    <?php if (!$porZona): ?><p class="muted vacio">Aún no hay entregas.</p><?php else: ?>
    <?= grafica_barras(array_map(fn($z) => [$z['zona'] . ' · ' . (O::PUBLICIDAD[$z['tipo']] ?? $z['tipo']), (int)$z['cantidad'], '#7C3AED', num((int)$z['cantidad']) . ' piezas'], array_slice($porZona, 0, 12))) ?>
    <?php endif; ?>
  </section>
  <section class="card">
    <h3>Últimas entregas</h3>
    <?php if (!$entregas): ?><p class="muted vacio">Aún no hay entregas.</p><?php else: ?>
    <div class="tbl-wrap"><table class="cola">
      <tr><th>Fecha</th><th>Pieza</th><th>Para</th><th style="text-align:right">Cantidad</th><th></th></tr>
      <?php foreach ($entregas as $en): ?>
      <tr><td><?= e(date('d/m', strtotime($en['fecha']))) ?></td><td class="small"><?= e(O::PUBLICIDAD[$en['tipo']] ?? $en['tipo']) ?></td>
        <td class="small"><?= e(trim(($en['zona'] ?? '') . ($en['responsable'] ? ' · ' . $en['responsable'] : ''), ' ·')) ?></td>
        <td style="text-align:right"><b><?= num((int)$en['cantidad']) ?></b></td>
        <td><form method="post" action="<?= url('organizacion/borrar/entrega/' . (int)$en['id']) ?>" onsubmit="return confirm('¿Eliminar esta entrega? Las piezas vuelven a la bodega.')"><?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini" type="submit" aria-label="Eliminar entrega">✕</button></form></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>
</div>
