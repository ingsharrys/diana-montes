<?php require __DIR__ . '/_tabs.php'; ?>

<?php
$porNombre = [];
foreach ($plantillas ?? [] as $p) $porNombre[$p['nombre']] = $p;
$estados = ['APPROVED' => ['Aprobada', 'tag-green'], 'PENDING' => ['En revisión', 'tag-grey'], 'REJECTED' => ['Rechazada', 'tag-red'],
            'PAUSED' => ['Pausada', 'tag-gold'], 'DISABLED' => ['Desactivada', 'tag-red'], 'IN_APPEAL' => ['En apelación', 'tag-grey']];
$puedeCrear = wa_cfg('WA_WABA_ID') && wa_cfg('WA_TOKEN');
$catTag = function (?string $cat, ?string $pedida = null): string {
    if (!$cat) return '';
    $html = '<span class="tag ' . ($cat === 'UTILITY' ? 'tag-blue' : 'tag-gold') . ' small">' . e(WA_CATEGORIAS[$cat] ?? $cat) . '</span>';
    if ($pedida && $pedida !== $cat) $html .= '<br><span class="small" style="color:var(--warn)">Meta la cambió (pediste ' . e(WA_CATEGORIAS[$pedida] ?? $pedida) . ')</span>';
    return $html;
};
?>

<?php if ($disponible): ?>
<div class="grid g2" style="margin-top:14px">
  <section class="card">
    <h3>Plantillas de la cuenta</h3>
    <p class="muted small" style="line-height:1.6">Créalas aquí abajo con un clic: se envían a Meta, quedan <b>en revisión</b> (suele tardar minutos u horas) y luego pulsas <b>Traer plantillas de Meta</b> para ver si quedaron aprobadas y en qué categoría.</p>
    <?php if ($esDireccion): ?>
    <form method="post" action="<?= url('whatsapp/sincronizar') ?>" style="margin-top:12px">
      <?= \Core\Csrf::campo() ?>
      <button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled title="Falta WA_WABA_ID o WA_TOKEN"' ?>>⟳ Traer plantillas de Meta</button>
    </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>¿Servicio o marketing?</h3>
    <p class="small" style="line-height:1.6">
      <span class="tag tag-blue small">Servicio (utilidad)</span> avisos sobre la cuenta de la persona: registro confirmado, tarea asignada, invitación que pidió. Es la categoría <b>más barata</b> y, si la persona te escribió en las últimas 24 horas, <b>gratis</b>. No puede llevar nada promocional.<br>
      <span class="tag tag-gold small">Marketing</span> saludos, felicitaciones, invitaciones a apoyar: <b>todos los saludos de fechas especiales son marketing</b> y cuestan más por mensaje.<br>
      <span class="muted">Meta decide la categoría final: si pides “servicio” y el texto le parece promocional, la cambia a marketing. Aquí verás cuál quedó.</span></p>
  </section>
</div>

<?php
[$otpNombre] = wa_plantilla_otp();
$necesarias = [$otpNombre => ['AUTHENTICATION', 'Código de acceso a la app móvil']];
foreach (WA_PLANTILLAS_SUGERIDAS as $n => [$cat]) $necesarias[$n] = [$cat, WA_PLANTILLAS_USO[$n] ?? ''];
$faltan = array_filter(array_keys($necesarias), fn($n) => !isset($porNombre[$n]));
$aprobadas = array_filter(array_keys($necesarias), fn($n) => ($porNombre[$n]['estado_meta'] ?? '') === 'APPROVED');
$marcaVieja = wa_con_marca_anterior($plantillas ?? []);
?>
<?php if ($marcaVieja): ?>
<section class="card" style="margin-top:16px;border-left:4px solid var(--warn)">
  <h3>Actualiza la marca en <?= count($marcaVieja) ?> plantilla<?= count($marcaVieja) === 1 ? '' : 's' ?></h3>
  <p class="small" style="line-height:1.6">Estas plantillas todavía dicen <b><?= e(WA_MARCA_ANTERIOR) ?></b>:
    <?= e(implode(', ', array_map(fn($p) => $p['nombre'], $marcaVieja))) ?>.
    Con el botón se cambian a <b><?= e(WA_MARCA) ?></b> en Meta. Lo demás del texto queda igual. Meta las vuelve a revisar, normalmente en minutos, y mientras tanto esos mensajes no salen.</p>
  <?php if ($esDireccion): ?>
  <form method="post" action="<?= url('whatsapp/actualizarmarca') ?>" style="margin-top:10px"
        onsubmit="return confirm('Se editarán en Meta <?= count($marcaVieja) ?> plantillas y quedarán en revisión. ¿Continuar?')">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled title="Falta WA_WABA_ID o WA_TOKEN"' ?>>✏️ Cambiar a "<?= e(WA_MARCA) ?>" en Meta</button>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<section class="card" style="margin-top:16px">
  <h3>Plantillas que necesita la plataforma
    <span class="tag tag-green small"><?= count($aprobadas) ?> de <?= count($necesarias) ?> aprobadas</span>
    <?php if ($faltan): ?><span class="tag tag-gold small"><?= count($faltan) ?> sin crear</span><?php endif; ?></h3>
  <p class="muted small" style="line-height:1.6;margin-bottom:10px">Cada aviso y saludo automático necesita su plantilla aprobada por Meta.
    <?php if ($esDireccion && $faltan): ?>Con el botón se envían a Meta todas las que faltan, con su texto, categoría y variables, y quedan asignadas a su ocasión. Puedes revisar o ajustar cada texto más abajo antes de enviarla.<?php endif; ?></p>
  <?php if ($esDireccion && $faltan): ?>
  <form method="post" action="<?= url('whatsapp/creartodas') ?>" style="margin-bottom:12px"
        onsubmit="return confirm('Se enviarán a Meta <?= count($faltan) ?> plantillas para revisión. ¿Continuar?')">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled title="Falta WA_WABA_ID o WA_TOKEN"' ?>>✨ <?= count($faltan) === 1 ? 'Crear en Meta la plantilla que falta' : 'Crear en Meta las ' . count($faltan) . ' plantillas que faltan' ?></button>
    <?php if (!$puedeCrear): ?><span class="small" style="color:var(--bad);margin-left:8px">Primero configura WA_WABA_ID y WA_TOKEN (pestaña Configuración).</span><?php endif; ?>
  </form>
  <?php endif; ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Plantilla</th><th>Para qué</th><th>Categoría</th><th>Estado en Meta</th></tr>
    <?php foreach ($necesarias as $n => [$cat, $uso]): $ya = $porNombre[$n] ?? null; $est = $ya ? ($estados[$ya['estado_meta'] ?? ''] ?? null) : null; ?>
    <tr>
      <td><b><?= e($n) ?></b></td>
      <td class="small"><?= e($uso) ?></td>
      <td><?= $ya ? $catTag($ya['categoria'], $ya['categoria_solicitada'] ?? $cat) : '<span class="tag ' . ($cat === 'UTILITY' ? 'tag-blue' : ($cat === 'AUTHENTICATION' ? 'tag-grey' : 'tag-gold')) . ' small">' . e(WA_CATEGORIAS[$cat] ?? $cat) . '</span>' ?></td>
      <td><?= $est ? '<span class="tag ' . $est[1] . ' small">' . e($est[0]) . '</span>' : ($ya ? '<span class="tag tag-grey small">' . e((string)$ya['estado_meta']) . '</span>' : '<span class="tag tag-gold small">Sin crear</span>') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</section>

<?php if ($esDireccion): ?>
<section class="card" style="margin-top:16px">
  <h3>Enviar una prueba</h3>
  <form method="post" action="<?= url('whatsapp/prueba') ?>" class="editar-meta" style="margin-top:0;flex-wrap:wrap">
    <?= \Core\Csrf::campo() ?>
    <select name="plantilla_id" style="flex:1;min-width:160px;border:1.5px solid var(--line);border-radius:9px;padding:8px" aria-label="Plantilla">
      <?php foreach ($plantillas as $p): if (!$p['compatible'] || $p['estado_meta'] !== 'APPROVED') continue; ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?>
    </select>
    <input type="tel" name="telefono" data-tipo="celular" pattern="3[0-9]{9}" title="10 dígitos, empieza por 3" placeholder="Tu celular: 3XX XXX XXXX" aria-label="Celular de prueba" required>
    <button class="btn btn-gold btn-mini" type="submit" <?= $configurado ? '' : 'disabled' ?>>Enviar prueba</button>
  </form>
  <p class="muted small nota">Solo plantillas aprobadas. Usa datos de ejemplo (María, Docente…).</p>
</section>
<?php endif; ?>

<section class="card" style="margin-top:16px">
  <h3>Plantillas en la plataforma</h3>
  <?php if (!$plantillas): ?>
    <p class="muted vacio">Aún no hay plantillas. <?= $esDireccion ? 'Créalas abajo con un clic.' : '' ?></p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Plantilla</th><th>Categoría</th><th>Texto</th><th>Variables</th><?php if ($esDireccion): ?><th></th><?php endif; ?></tr>
    <?php foreach ($plantillas as $p): $fid = 'fpl' . (int)$p['id']; $asignadas = array_filter(explode(',', (string)$p['variables'])); $est = $estados[$p['estado_meta'] ?? ''] ?? null; ?>
    <tr>
      <td><b><?= e($p['nombre']) ?></b> <span class="muted small"><?= e($p['idioma']) ?></span><br>
        <?php if ($est): ?><span class="tag <?= $est[1] ?> small"><?= e($est[0]) ?></span><?php elseif ($p['estado_meta']): ?><span class="tag tag-grey small"><?= e($p['estado_meta']) ?></span><?php endif; ?>
        <?php if (!$p['compatible']): ?><br><span class="small" style="color:var(--bad)">No se puede enviar automáticamente (encabezado multimedia, botones dinámicos o variables con nombre).</span><?php endif; ?>
        <?php if ($p['estado_meta'] === 'REJECTED' && !empty($p['motivo_rechazo'])): ?><br><span class="small" style="color:var(--bad)"><b>Motivo:</b> <?= e(WA_MOTIVOS_RECHAZO[$p['motivo_rechazo']] ?? $p['motivo_rechazo']) ?></span><?php endif; ?>
        <?php if ($esDireccion): ?><form id="<?= $fid ?>" method="post" action="<?= url('whatsapp/guardarplantilla/' . (int)$p['id']) ?>"><?= \Core\Csrf::campo() ?></form><?php endif; ?></td>
      <td><?= $catTag($p['categoria'], $p['categoria_solicitada'] ?? null) ?: '<span class="muted">—</span>' ?></td>
      <?php $x = json_decode((string)($p['extras'] ?? ''), true) ?: []; ?>
      <td class="small" style="max-width:340px">
        <?php if (!empty($x['encabezado'])): ?><b><?= e($x['encabezado']) ?></b><br><?php endif; ?>
        <span style="white-space:pre-line"><?= $p['cuerpo'] ? e($p['cuerpo']) : '<span class="muted">—</span>' ?></span>
        <?php if (!empty($x['pie'])): ?><br><span class="muted"><?= e($x['pie']) ?></span><?php endif; ?>
        <?php if (!empty($x['botones'])): ?><div class="mini-botones"><?php foreach ($x['botones'] as $bt): ?><span><?= e($bt['texto']) ?></span><?php endforeach; ?></div><?php endif; ?></td>
      <td>
        <?php if (!(int)$p['num_variables']): ?><span class="muted small">Sin variables</span><?php endif; ?>
        <?php for ($i = 1; $i <= (int)$p['num_variables']; $i++): ?>
          <div class="var-fila"><b>{{<?= $i ?>}}</b>
            <?php if ($esDireccion && $p['compatible']): ?>
            <select name="var<?= $i ?>" form="<?= $fid ?>" aria-label="Dato para la variable <?= $i ?>">
              <option value="">Elige el dato…</option>
              <?php foreach (WA_VARIABLES as $campo => $etiqueta): ?>
                <option value="<?= $campo ?>" <?= ($asignadas[$i - 1] ?? '') === $campo ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
              <?php endforeach; ?>
            </select>
            <?php else: ?><span class="small"><?= e(WA_VARIABLES[$asignadas[$i - 1] ?? ''] ?? 'sin asignar') ?></span><?php endif; ?>
          </div>
        <?php endfor; ?>
      </td>
      <?php if ($esDireccion): ?><td style="white-space:nowrap"><?php if ((int)$p['num_variables'] && $p['compatible']): ?><button class="btn btn-primary btn-mini" type="submit" form="<?= $fid ?>">Guardar</button><?php endif; ?>
        <?php if ($puedeCrear && $p['categoria'] !== 'AUTHENTICATION'): ?>
        <form method="post" action="<?= url('whatsapp/eliminarplantilla') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar la plantilla «<?= e($p['nombre']) ?>» en Meta y aquí? Las ocasiones que la usan quedan pausadas.')">
          <?= \Core\Csrf::campo() ?><input type="hidden" name="nombre" value="<?= e($p['nombre']) ?>"><button class="btn btn-ghost btn-mini txt-peligro" type="submit" aria-label="Eliminar plantilla">Eliminar</button></form>
        <?php endif; ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($esDireccion): ?>
<section class="card" style="margin-top:16px">
  <h3>Crear las plantillas de la campaña en Meta</h3>
  <p class="muted small" style="margin-bottom:10px;line-height:1.6">Ya traen el texto, la categoría y qué dato va en cada variable, y quedan asignadas a su ocasión.
    Puedes ajustar el texto antes de enviarla. Todas incluyen la opción de darse de baja (SALIR), que el sistema procesa solo.
    <?php if (!$puedeCrear): ?><br><b style="color:var(--bad)">Para crearlas faltan WA_WABA_ID y WA_TOKEN en config.php (ver Configuración).</b><?php endif; ?></p>
  <?php foreach (WA_PLANTILLAS_SUGERIDAS as $nombre => [$cat, $ocasiones, $vars, $texto]):
    $ya = $porNombre[$nombre] ?? null; $campos = array_values(array_filter(array_map('trim', explode(',', $vars)))); $est = $ya ? ($estados[$ya['estado_meta'] ?? ''] ?? null) : null;
    $sx = wa_extras_sugerida($nombre); ?>
  <details class="sugerida">
    <summary>
      <b><?= e($nombre) ?></b>
      <span class="tag <?= $cat === 'UTILITY' ? 'tag-blue' : 'tag-gold' ?> small">pedir como <?= e(WA_CATEGORIAS[$cat]) ?></span>
      <span class="muted small">· para: <?= e($ocasiones ? implode(', ', $ocasiones) : (WA_PLANTILLAS_USO[$nombre] ?? '')) ?></span>
      <?php if ($ya): ?> · <?= $est ? '<span class="tag ' . $est[1] . ' small">' . e($est[0]) . '</span>' : '' ?> <?= $catTag($ya['categoria'], $ya['categoria_solicitada'] ?? null) ?><?php endif; ?>
    </summary>
    <?php if ($ya && in_array($ya['estado_meta'], ['APPROVED', 'PENDING', 'IN_APPEAL'], true)): ?>
      <p class="small muted" style="margin:8px 0">Ya está creada en Meta. <?= $ya['estado_meta'] === 'APPROVED' ? 'Actívala en la pestaña Ocasiones.' : 'Espera la revisión y pulsa “Traer plantillas de Meta”.' ?></p>
      <pre><?= e((string)$ya['cuerpo']) ?></pre>
    <?php else: ?>
    <form method="post" action="<?= url('whatsapp/crearenmeta') ?>" class="form-sugerida">
      <?= \Core\Csrf::campo() ?>
      <input type="hidden" name="nombre" value="<?= e($nombre) ?>">
      <?php foreach ($campos as $c): ?><input type="hidden" name="campos[]" value="<?= e($c) ?>"><?php endforeach; ?>
      <?php if ($sx['pie'] !== '' || $sx['botones']): ?>
        <input type="hidden" name="encabezado" value="<?= e($sx['encabezado']) ?>"><input type="hidden" name="pie" value="<?= e($sx['pie']) ?>">
        <?php foreach ($sx['botones'] as $b): ?>
          <input type="hidden" name="boton_tipo[]" value="<?= e($b['tipo']) ?>"><input type="hidden" name="boton_texto[]" value="<?= e($b['texto']) ?>"><input type="hidden" name="boton_valor[]" value="<?= e($b['valor'] ?? '') ?>">
        <?php endforeach; ?>
        <p class="small muted" style="margin:6px 0">Pie: <i><?= e($sx['pie']) ?></i> · Botones:
          <?= implode(' ', array_map(fn($b) => '<span class="tag tag-grey small">' . e($b['texto']) . ($b['tipo'] === 'URL' ? ' ↗' : '') . '</span>', $sx['botones'])) ?>
          <?php if (array_filter($sx['botones'], fn($b) => str_contains($b['valor'] ?? '', '{{1}}'))): ?><br>El botón abre el enlace personal de cada persona al chat de datos (<?= e(wa_sitio()) ?>/conversemos/…).<?php endif; ?></p>
      <?php endif; ?>
      <label class="field"><span><?= $campos ? 'Texto (variables: ' . e(implode(' · ', array_map(fn($c, $i) => '{{' . ($i + 1) . '}} ' . (WA_VARIABLES[$c] ?? $c), $campos, array_keys($campos)))) . ')' : 'Texto' ?></span>
        <textarea name="cuerpo" rows="5" maxlength="1024" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= e($texto) ?></textarea></label>
      <div class="form-grid" style="margin-top:8px">
        <label class="field"><span>Categoría que pides</span>
          <select name="categoria"><?php foreach (['UTILITY', 'MARKETING'] as $k): ?><option value="<?= $k ?>" <?= $k === $cat ? 'selected' : '' ?>><?= e(WA_CATEGORIAS[$k]) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Idioma</span>
          <select name="idioma"><?php foreach (WA_IDIOMAS as $k => $t): ?><option value="<?= $k ?>"><?= e($t) ?></option><?php endforeach; ?></select></label>
      </div>
      <?php if ($ya && $ya['estado_meta'] === 'REJECTED'): ?><p class="small" style="color:var(--bad);margin-top:6px">Meta la rechazó. Ajusta el texto y envíala de nuevo con otro nombre si Meta no permite reutilizarlo.</p><?php endif; ?>
      <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled' ?>>Enviar a Meta para aprobación</button></div>
    </form>
    <?php endif; ?>
  </details>
  <?php endforeach; ?>
</section>

<?php [$otpNombre] = wa_plantilla_otp(); $otp = $porNombre[$otpNombre] ?? null; $otpEst = $otp ? ($estados[$otp['estado_meta'] ?? ''] ?? null) : null; ?>
<section class="card" style="margin-top:16px">
  <h3>📱 Código de acceso de la app móvil <span class="tag tag-grey small">Autenticación</span>
    <?php if ($otpEst): ?><span class="tag <?= $otpEst[1] ?> small"><?= e($otpEst[0]) ?></span><?php endif; ?></h3>
  <p class="muted small" style="line-height:1.6">Los simpatizantes entran a la app con su número de WhatsApp: les llega un código de 6 números con un botón “Copiar código”.
    Meta exige una plantilla de categoría <b>Autenticación</b> (texto fijo de Meta: “<i>123456 es tu código de verificación. Por tu seguridad, no lo compartas.</i>”).
    Nombre: <code><?= e($otpNombre) ?></code>.</p>
  <?php if (!$otp || in_array($otp['estado_meta'], ['REJECTED', null], true)): ?>
  <form method="post" action="<?= url('whatsapp/crearotp') ?>" style="margin-top:10px">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled' ?>>Crear plantilla del código en Meta</button>
  </form>
  <?php else: ?>
  <p class="small" style="margin-top:8px"><?= $otp['estado_meta'] === 'APPROVED' ? '✅ Lista: la app ya puede enviar códigos.' : 'En revisión: pulsa “Traer plantillas de Meta” en unos minutos.' ?></p>
  <?php endif; ?>
</section>

<section class="card" style="margin-top:16px">
  <h3>Crear otra plantilla</h3>
  <?php $b = $borrador ?? []; ?>
  <form method="post" action="<?= url('whatsapp/crearenmeta') ?>" id="formNueva" data-validar>
    <?= \Core\Csrf::campo() ?>
    <div class="form-grid">
      <label class="field"><span>Nombre (minúsculas, números y _)</span>
        <input name="nombre" data-tipo="plantilla" maxlength="512" pattern="[a-z0-9_]+" placeholder="ej. invitacion_cierre_campana" value="<?= e(isset(WA_PLANTILLAS_SUGERIDAS[$b['nombre'] ?? '']) ? '' : ($b['nombre'] ?? '')) ?>" required data-msg="Escribe el nombre de la plantilla."></label>
      <label class="field"><span>Categoría que pides</span>
        <select name="categoria"><?php foreach (['MARKETING', 'UTILITY'] as $k): ?><option value="<?= $k ?>" <?= ($b['categoria'] ?? '') === $k ? 'selected' : '' ?>><?= e(WA_CATEGORIAS[$k]) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Idioma</span>
        <select name="idioma"><?php foreach (WA_IDIOMAS as $k => $t): ?><option value="<?= $k ?>" <?= ($b['idioma'] ?? 'es') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="field" style="margin-top:8px"><span>Texto: escribe {{1}}, {{2}}… donde va cada dato. No empieces ni termines con una variable.</span>
      <textarea name="cuerpo" id="nuevaCuerpo" rows="5" maxlength="1024" required data-msg="Escribe el texto." style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"
        placeholder="Hola, {{1}}. Te esperamos este sábado en el cierre de campaña de Diana en {{2}}. ¡Trae a tu familia!&#10;&#10;Si no deseas recibir más mensajes, responde SALIR."><?= e(isset(WA_PLANTILLAS_SUGERIDAS[$b['nombre'] ?? '']) ? '' : ($b['cuerpo'] ?? '')) ?></textarea></label>
    <div id="nuevaVars" style="margin-top:8px"></div>
    <details class="filtro-mas" style="margin-top:10px" <?= !empty($b['encabezado']) || !empty($b['pie']) || array_filter(array_column($b['botones'] ?? [], 'texto')) ? 'open' : '' ?>>
      <summary>Encabezado, pie de página y botones (opcional)</summary>
      <div class="form-grid" style="margin-top:8px">
        <label class="field"><span>Encabezado (máx. 60, sin variables)</span>
          <input name="encabezado" id="nuevaCab" maxlength="60" value="<?= e($b['encabezado'] ?? '') ?>" placeholder="Ej: Invitación especial"></label>
        <label class="field"><span>Pie de página (máx. 60)</span>
          <input name="pie" id="nuevaPie" maxlength="60" value="<?= e($b['pie'] ?? '') ?>" placeholder="Ej: Responde SALIR para no recibir más"></label>
      </div>
      <span class="field-titulo" style="margin-top:10px;display:block">Botones</span>
      <?php for ($i = 0; $i < 3; $i++): $bt = $b['botones'][$i] ?? []; ?>
      <div class="boton-fila">
        <select name="boton_tipo[]" aria-label="Tipo del botón <?= $i + 1 ?>">
          <?php foreach (WA_TIPOS_BOTON as $k => $t): ?><option value="<?= $k ?>" <?= ($bt['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <input name="boton_texto[]" maxlength="25" placeholder="Texto del botón (máx. 25)" value="<?= e($bt['texto'] ?? '') ?>" aria-label="Texto del botón <?= $i + 1 ?>">
        <input name="boton_valor[]" placeholder="https://… o celular" value="<?= e($bt['valor'] ?? '') ?>" aria-label="Enlace o teléfono del botón <?= $i + 1 ?>">
      </div>
      <?php endfor; ?>
      <p class="muted small nota">Respuesta rápida: la persona toca y te responde ese texto (un botón «SALIR» la da de baja sola). Enlace: abre una página, por ejemplo <?= e(wa_sitio()) ?>. Llamar: marca un número.</p>
    </details>
    <span class="field-titulo" style="margin-top:12px;display:block">Vista previa</span>
    <div class="wa-chat"><div class="wa-burbuja" id="nuevaPrevia"><span class="muted">Escribe el texto para ver la vista previa.</span></div></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit" <?= $puedeCrear ? '' : 'disabled' ?>>Enviar a Meta para aprobación</button></div>
  </form>
</section>
<script>
(function () {
  const area = document.getElementById('nuevaCuerpo'), caja = document.getElementById('nuevaVars');
  const opciones = <?= json_encode(WA_VARIABLES, JSON_UNESCAPED_UNICODE) ?>;
  const previos = <?= json_encode(array_values($b['campos'] ?? []), JSON_UNESCAPED_UNICODE) ?>;
  function pintar() {
    const nums = [...new Set([...area.value.matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(m => +m[1]))].sort((a, b) => a - b);
    const actuales = [...caja.querySelectorAll('select')].map(s => s.value);
    caja.innerHTML = nums.length ? '<span class="field-titulo">¿Qué dato va en cada variable?</span>' : '';
    nums.forEach((n, i) => {
      const fila = document.createElement('div'); fila.className = 'var-fila';
      const sel = document.createElement('select'); sel.name = 'campos[]'; sel.required = true;
      sel.innerHTML = '<option value="">Elige el dato…</option>' + Object.entries(opciones).map(([k, t]) => '<option value="' + k + '">' + t.replace(/</g, '&lt;') + '</option>').join('');
      sel.value = actuales[i] || previos[i] || '';
      fila.innerHTML = '<b>{{' + n + '}}</b> '; fila.appendChild(sel); caja.appendChild(fila);
    });
  }
  area.addEventListener('input', pintar); pintar();

  // Vista previa tipo WhatsApp con datos de ejemplo
  const ejemplos = <?= json_encode(WA_EJEMPLOS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const form = document.getElementById('formNueva'), vista = document.getElementById('nuevaPrevia');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  function previa() {
    const campos = [...caja.querySelectorAll('select')].map(s => s.value);
    const cuerpo = esc(area.value).replace(/\{\{\s*(\d+)\s*\}\}/g, (m, n) => '<mark>' + esc(ejemplos[campos[n - 1]] || '{{' + n + '}}') + '</mark>');
    const cab = document.getElementById('nuevaCab').value.trim(), pie = document.getElementById('nuevaPie').value.trim();
    const tipos = [...form.querySelectorAll('select[name="boton_tipo[]"]')].map(s => s.value);
    const botones = [...form.querySelectorAll('input[name="boton_texto[]"]')].map((i, k) => [tipos[k], i.value.trim()]).filter(b => b[1]);
    vista.innerHTML = !area.value.trim() ? '<span class="muted">Escribe el texto para ver la vista previa.</span>'
      : (cab ? '<b class="wa-cab">' + esc(cab) + '</b>' : '') + '<div class="wa-cuerpo">' + cuerpo + '</div>'
        + (pie ? '<div class="wa-pie">' + esc(pie) + '</div>' : '')
        + (botones.length ? '<div class="wa-botones">' + botones.map(b => '<span>' + (b[0] === 'URL' ? '↗ ' : b[0] === 'PHONE_NUMBER' ? '☎ ' : '↩ ') + esc(b[1]) + '</span>').join('') + '</div>' : '');
  }
  form.addEventListener('input', previa); form.addEventListener('change', previa); previa();
})();
</script>
<?php endif; ?>
<?php endif; ?>
