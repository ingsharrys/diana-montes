<?php require __DIR__ . '/_tabs.php'; ?>

<?php if ($disponible):
  $tipos = ['evento' => 'Notificación', 'cumpleanos' => 'Cada día', 'profesion' => 'Cada día', 'fecha' => 'Fecha especial'];
  $listas = array_filter($plantillas, fn($p) => $p['compatible']); ?>

<section class="card" style="margin-top:14px">
  <h3>Ocasiones de saludo y notificaciones</h3>
  <p class="muted small" style="margin-bottom:12px;line-height:1.6">
    Cada ocasión usa una plantilla aprobada por Meta. Al <b>activarla</b>, sus mensajes salen solos: los saludos del día se preparan a las
    <?= (int)wa_cfg('WA_HORA_ENVIO', 9) ?>:00 y las notificaciones (bienvenida, nuevo invitado, subida de nivel) en cuanto ocurren.
    Nadie recibe dos veces el mismo saludo en el año.
  </p>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Ocasión</th><th>Cuándo</th><th style="text-align:right">Le llegaría a</th><th>Plantilla</th><th>Activa</th><?php if ($esDireccion): ?><th></th><?php endif; ?></tr>
    <?php foreach ($ocasiones as $o): $fid = 'fo' . (int)$o['id']; ?>
    <tr>
      <td><b><?= e($o['nombre']) ?></b><br><span class="tag <?= $o['tipo'] === 'evento' ? 'tag-blue' : 'tag-grey' ?> small"><?= $tipos[$o['tipo']] ?></span>
        <?php if ($o['genero']): ?><span class="tag tag-gold small">solo <?= $o['genero'] === 'mujer' ? 'mujeres' : 'hombres' ?></span><?php endif; ?>
        <?php if ($esDireccion): ?><form id="<?= $fid ?>" method="post" action="<?= url('whatsapp/guardarocasion/' . (int)$o['id']) ?>"><?= \Core\Csrf::campo() ?></form><?php endif; ?></td>
      <td class="small">
        <?php if ($o['tipo'] === 'cumpleanos'): ?>El día de su cumpleaños
        <?php elseif ($o['tipo'] === 'profesion'): ?>El día de su profesión (<a href="<?= url('catalogos') ?>">fechas en Catálogos</a>)
        <?php elseif ($o['tipo'] === 'evento'): ?>
          <?= ['bienvenida' => 'Al registrarse', 'nuevo_invitado' => 'Al promotor, cuando alguien se registra con su enlace', 'sube_nivel' => 'Al promotor, al llegar a ' . PROMOTOR_NIVELES[1][0] . ', ' . PROMOTOR_NIVELES[2][0] . ' o ' . PROMOTOR_NIVELES[3][0] . ' invitados'][$o['clave']] ?? 'Al ocurrir' ?>
        <?php else: ?><?= e(wa_regla_texto($o['regla'])) ?><?php if ($o['proxima']): ?><br><span class="muted">próxima: <?= $o['proxima']->format('d/m/Y') ?></span><?php endif; ?>
        <?php endif; ?>
      </td>
      <td style="text-align:right"><?= $o['audiencia'] !== null ? num($o['audiencia']) : '—' ?></td>
      <td>
        <?php if ($esDireccion): ?>
        <select name="plantilla_id" form="<?= $fid ?>" aria-label="Plantilla de <?= e($o['nombre']) ?>">
          <option value="">Sin plantilla</option>
          <?php foreach ($listas as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$o['plantilla_id'] === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['nombre']) ?> (<?= e($p['idioma']) ?>)<?= $p['estado_meta'] && $p['estado_meta'] !== 'APPROVED' ? ' · ' . e($p['estado_meta']) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php else: ?><?= $o['plantilla'] ? e($o['plantilla']) : '<span class="muted">—</span>' ?><?php endif; ?>
      </td>
      <td style="text-align:center">
        <?php if ($esDireccion): ?><input type="checkbox" class="chk-verif" name="activa" value="1" form="<?= $fid ?>" <?= $o['activa'] ? 'checked' : '' ?> aria-label="Activa">
        <?php else: ?><?= $o['activa'] ? '<span class="tag tag-green">Activa</span>' : '<span class="tag tag-grey">Pausada</span>' ?><?php endif; ?>
      </td>
      <?php if ($esDireccion): ?><td><button class="btn btn-primary btn-mini" type="submit" form="<?= $fid ?>">Guardar</button></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php if (!$listas): ?>
    <p class="alert alert-error" style="margin-top:12px;font-weight:500">Aún no hay plantillas utilizables. Créalas en Meta (WhatsApp Manager) y tráelas en la pestaña <a href="<?= url('whatsapp/plantillas') ?>">Plantillas</a>.</p>
  <?php endif; ?>
</section>

<?php if ($esDireccion): ?>
<section class="card" style="margin-top:16px;max-width:760px">
  <h3>Agregar otra fecha especial</h3>
  <form method="post" action="<?= url('whatsapp/nuevaocasion') ?>" class="form-grid">
    <?= \Core\Csrf::campo() ?>
    <label class="field"><span>Nombre</span><input name="nombre" data-tipo="texto" minlength="3" maxlength="100" placeholder="Ej: Día del Campesino" required></label>
    <label class="field"><span>Fecha (se repite cada año)</span><input type="date" name="fecha"></label>
    <label class="field"><span>Solo para</span>
      <select name="genero"><option value="">Todos</option><option value="mujer">Mujeres</option><option value="hombre">Hombres</option></select></label>
    <label class="field"><span>…o regla de día móvil (opcional)</span><input name="regla" pattern="[1-5]-[0-6]-(0[1-9]|1[0-2])" title="n-d-MM, por ejemplo 1-0-06" placeholder="1-0-06 = primer domingo de junio"></label>
    <div class="full form-actions"><button class="btn btn-primary" type="submit">＋ Agregar ocasión</button></div>
  </form>
  <p class="muted small nota">Regla de día móvil: <b>n-d-MM</b> = n-ésimo día <i>d</i> de la semana (0 domingo … 6 sábado) del mes MM. Ej.: Día del Campesino = <b>1-0-06</b>.</p>
</section>
<?php endif; ?>
<?php endif; ?>
