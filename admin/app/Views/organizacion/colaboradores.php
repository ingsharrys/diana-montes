<?php
/** Organización › Colaboradores pagos y voluntarios */
use Models\Organizacion as O;
require __DIR__ . '/_tabs.php';
if (!$disponible) return;
$x = $editar ?? [];
if ($v) $x = array_merge($x, $v);                     // lo que se escribió antes de un error
$err = fn($k) => isset($errores[$k]) ? '<em>' . e($errores[$k]) . '</em>' : '';
$cls = fn($k) => isset($errores[$k]) ? 'has-error' : '';
$val = fn($k) => e((string)($x[$k] ?? ''));
$tipoSel = $x['tipo'] ?? 'voluntario';
$totalPagos = count(array_filter($lista, fn($c) => $c['tipo'] === 'pago'));
?>
<div class="grid g2" style="margin-top:14px;align-items:start">
  <section class="card">
    <h3>Equipo de trabajo <span class="tag tag-grey"><?= num(count($lista)) ?></span></h3>
    <form class="toolbar" method="get" action="<?= url('organizacion/colaboradores') ?>">
      <?php if (defined('USE_REWRITE') && USE_REWRITE === false): ?><input type="hidden" name="url" value="organizacion/colaboradores"><?php endif; ?>
      <select name="tipo" aria-label="Tipo"><option value="">Pagos y voluntarios</option>
        <option value="pago" <?= $filtro['tipo'] === 'pago' ? 'selected' : '' ?>>Solo pagos</option>
        <option value="voluntario" <?= $filtro['tipo'] === 'voluntario' ? 'selected' : '' ?>>Solo voluntarios</option></select>
      <select name="rol" aria-label="Rol"><option value="">Todos los roles</option>
        <?php foreach (O::ROLES as $k => $r): ?><option value="<?= $k ?>" <?= $filtro['rol'] === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
      <select name="activo" aria-label="Estado"><option value="1" <?= $filtro['activo'] === '1' ? 'selected' : '' ?>>Activos</option>
        <option value="0" <?= $filtro['activo'] === '0' ? 'selected' : '' ?>>Inactivos</option>
        <option value="" <?= $filtro['activo'] === '' ? 'selected' : '' ?>>Todos</option></select>
      <button class="btn btn-ghost btn-mini" type="submit">Filtrar</button>
    </form>
    <?php if (!$lista): ?>
      <p class="muted vacio">Aún no hay colaboradores con ese filtro. Agrégalos con el formulario.</p>
    <?php else: ?>
    <div class="tbl-wrap">
    <table class="cola">
      <tr><th>Persona</th><th>Rol</th><th>Tipo</th><?php if ($verDinero): ?><th style="text-align:right">Pago</th><th style="text-align:right">Pagado</th><?php endif; ?><th></th></tr>
      <?php foreach ($lista as $c): ?>
      <tr>
        <td><b><?= e($c['nombre']) ?></b><br><span class="muted small"><?= $c['telefono'] ? e($c['telefono']) : 'sin celular' ?><?= $c['zona'] ? ' · ' . e($c['zona']) : '' ?></span></td>
        <td class="small"><?= e(O::ROLES[$c['rol']] ?? $c['rol']) ?></td>
        <td><span class="tag <?= $c['tipo'] === 'pago' ? 'tag-gold' : 'tag-green' ?>"><?= $c['tipo'] === 'pago' ? 'Pago' : 'Voluntario' ?></span><?= $c['activo'] ? '' : ' <span class="tag tag-grey">inactivo</span>' ?></td>
        <?php if ($verDinero): ?>
        <td style="text-align:right" class="small"><?= $c['valor'] ? pesos((float)$c['valor']) . '<br><span class="muted">' . e(O::PERIODOS[$c['periodicidad']][0] ?? '') . '</span>' : '—' ?></td>
        <td style="text-align:right" class="small"><?= (float)$c['pagado'] ? pesos((float)$c['pagado']) . '<br><span class="muted">último ' . e(date('d/m', strtotime($c['ultimo_pago']))) . '</span>' : '—' ?></td>
        <?php endif; ?>
        <td style="white-space:nowrap">
          <a class="btn btn-ghost btn-mini" href="<?= url_con('organizacion/colaboradores', ['editar' => $c['id']] + $filtro) ?>">Editar</a>
          <?php if ($verDinero && $c['tipo'] === 'pago'): ?>
          <details class="pago-rapido"><summary class="btn btn-ghost btn-mini">+ Pago</summary>
            <form method="post" action="<?= url('organizacion/pago/' . (int)$c['id']) ?>">
              <?= \Core\Csrf::campo() ?>
              <input name="valor" data-tipo="numero" maxlength="10" placeholder="Valor" value="<?= $c['valor'] ? (int)$c['valor'] : '' ?>" required aria-label="Valor del pago">
              <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" aria-label="Fecha del pago">
              <input name="concepto" maxlength="150" placeholder="Concepto (ej. quincena oct)" aria-label="Concepto">
              <input name="soporte" maxlength="40" placeholder="Soporte (cuenta de cobro n.º)" aria-label="Soporte del pago">
              <select name="medio" aria-label="Medio de pago"><option value="">Medio…</option><option>Transferencia</option><option>Nequi</option><option>Daviplata</option><option>Efectivo</option></select>
              <button class="btn btn-primary btn-mini" type="submit">Registrar pago</button>
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
    <h3><?= !empty($editar) ? 'Editar colaborador' : 'Agregar colaborador' ?></h3>
    <form method="post" action="<?= url('organizacion/guardarcolaborador/' . (int)($editar['id'] ?? 0)) ?>" novalidate>
      <?= \Core\Csrf::campo() ?>
      <div class="form-grid">
        <label class="field full <?= $cls('nombre') ?>"><span>Nombre completo *</span>
          <input name="nombre" maxlength="120" value="<?= $val('nombre') ?>" required><?= $err('nombre') ?></label>
        <label class="field <?= $cls('documento') ?>"><span>Documento</span>
          <input name="documento" data-tipo="numero" maxlength="12" value="<?= $val('documento') ?>"><?= $err('documento') ?></label>
        <label class="field <?= $cls('telefono') ?>"><span>Celular</span>
          <input name="telefono" data-tipo="numero" maxlength="10" value="<?= $val('telefono') ?>" placeholder="3XX XXX XXXX"><?= $err('telefono') ?></label>
        <label class="field <?= $cls('rol') ?>"><span>Rol *</span>
          <select name="rol" required><option value="">Selecciona…</option>
            <?php foreach (O::ROLES as $k => $r): ?><option value="<?= $k ?>" <?= ($x['rol'] ?? '') === $k ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
          </select><?= $err('rol') ?></label>
        <label class="field"><span>Zona donde trabaja</span>
          <select name="zona_id"><option value="">Toda la campaña</option><?= zonas_opciones($zonas, $x['zona_id'] ?? null) ?></select></label>
        <div class="full">
          <span class="field-titulo">¿Recibe pago? *</span>
          <div class="opciones-modo" style="grid-template-columns:1fr 1fr">
            <label class="op-modo"><input type="radio" name="tipo" value="voluntario" <?= $tipoSel !== 'pago' ? 'checked' : '' ?>>
              <b>Voluntario</b><span>Colabora sin pago.</span></label>
            <label class="op-modo"><input type="radio" name="tipo" value="pago" <?= $tipoSel === 'pago' ? 'checked' : '' ?>>
              <b>Colaborador pago</b><span><?= $verDinero ? 'Se registran su valor y sus pagos.' : 'La dirección registra el valor.' ?></span></label>
          </div>
        </div>
        <?php if ($verDinero): ?>
        <label class="field <?= $cls('valor') ?> solo-pago"><span>Valor del pago</span>
          <input name="valor" data-tipo="numero" maxlength="10" value="<?= !empty($x['valor']) ? (int)$x['valor'] : '' ?>" placeholder="Ej: 1300000"><?= $err('valor') ?></label>
        <label class="field <?= $cls('periodicidad') ?> solo-pago"><span>Cada cuánto</span>
          <select name="periodicidad"><option value="">Selecciona…</option>
            <?php foreach (O::PERIODOS as $k => [$t]): ?><option value="<?= $k ?>" <?= ($x['periodicidad'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
          </select><?= $err('periodicidad') ?></label>
        <?php endif; ?>
        <label class="field"><span>Desde</span><input type="date" name="inicio" value="<?= $val('inicio') ?>"></label>
        <label class="field <?= $cls('fin') ?>"><span>Hasta</span><input type="date" name="fin" value="<?= $val('fin') ?>"><?= $err('fin') ?></label>
        <label class="field full"><span>Notas</span><input name="notas" maxlength="255" value="<?= $val('notas') ?>" placeholder="Ej: tiene moto, disponible fines de semana"></label>
        <label class="full chk"><input type="checkbox" name="activo" value="1" <?= ($x['activo'] ?? 1) ? 'checked' : '' ?>> Activo en la campaña</label>
        <p class="full muted small">⚖️ No vincules <b>servidores públicos</b> (tampoco contratistas que tengan esa restricción): no pueden participar en política (Constitución, art. 127). A quien recibe pago se le hace contrato o cuenta de cobro; ese pago es gasto de campaña y se reporta.</p>
      </div>
      <div class="form-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-primary" type="submit"><?= !empty($editar) ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if (!empty($editar)): ?><a class="btn btn-ghost" href="<?= url('organizacion/colaboradores') ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
    <?php if (!empty($editar) && $verDinero): ?>
      <form method="post" action="<?= url('organizacion/borrar/colaborador/' . (int)$editar['id']) ?>" style="margin-top:10px"
            onsubmit="return confirm('¿Eliminar a <?= e(addslashes($editar['nombre'])) ?> y su historial de pagos? Si solo dejó de trabajar, mejor desmarca “Activo”.')">
        <?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini txt-peligro" type="submit">Eliminar colaborador</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<?php if ($verDinero && $pagos): ?>
<section class="card" style="margin-top:16px">
  <h3>Últimos pagos</h3>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Fecha</th><th>Colaborador</th><th>Concepto</th><th>Medio</th><th style="text-align:right">Valor</th><th></th></tr>
    <?php foreach ($pagos as $p): ?>
    <tr><td><?= e(date('d/m/Y', strtotime($p['fecha']))) ?></td><td><b><?= e($p['nombre']) ?></b></td><td class="small"><?= e($p['concepto'] ?? '') ?></td>
      <td class="small"><?= e($p['medio'] ?? '') ?></td><td style="text-align:right"><b><?= pesos((float)$p['valor']) ?></b></td>
      <td><form method="post" action="<?= url('organizacion/borrar/pago/' . (int)$p['id']) ?>" onsubmit="return confirm('¿Eliminar este pago?')"><?= \Core\Csrf::campo() ?><button class="btn btn-ghost btn-mini" type="submit" aria-label="Eliminar pago">✕</button></form></td></tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="muted small nota">Guarda los soportes (cuenta de cobro, comprobante de transferencia): los gastos de la campaña se reportan en Cuentas Claras.</p>
</section>
<?php endif; ?>

<script>
// Valor y periodicidad solo aplican a colaboradores pagos
(function () {
  const radios = document.querySelectorAll('input[name="tipo"]');
  const campos = document.querySelectorAll('.solo-pago');
  const ver = () => { const pago = document.querySelector('input[name="tipo"]:checked')?.value === 'pago'; campos.forEach(c => c.hidden = !pago); };
  radios.forEach(r => r.addEventListener('change', ver)); ver();
})();
</script>
