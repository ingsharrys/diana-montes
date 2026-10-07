<?php require __DIR__ . '/_tabs.php'; ?>

<?php
// Plantillas sugeridas para crear en WhatsApp Manager (Meta las revisa antes de aprobarlas)
$sugeridas = [
    ['saludo_cumpleanos', 'Marketing', 'primer_nombre',
     "¡Feliz cumpleaños, {{1}}! 🎂 Que este nuevo año de vida llegue lleno de salud y alegrías para ti y tu familia. Un abrazo grande de Diana Lucía Montes y todo el equipo.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['saludo_profesion', 'Marketing', 'primer_nombre, profesion',
     "¡Feliz día, {{1}}! 🎉 Hoy celebramos a quienes, como tú, se dedican a {{2}}. Gracias por todo lo que aportas a Garzón con tu trabajo. Con cariño, Diana Lucía Montes.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['saludo_dia_mujer', 'Marketing', 'primer_nombre',
     "¡Feliz Día de la Mujer, {{1}}! 💜 Hoy y siempre, gracias por tu fuerza y por todo lo que haces por tu familia y por Garzón. Un abrazo de Diana Lucía Montes.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['saludo_dia_hombre', 'Marketing', 'primer_nombre',
     "¡Feliz Día del Hombre, {{1}}! 💪 Gracias por tu esfuerzo diario por tu familia y por Garzón. Un saludo de Diana Lucía Montes.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['saludo_amor_amistad', 'Marketing', 'primer_nombre',
     "¡Feliz Amor y Amistad, {{1}}! 💕 Gracias por ser parte de esta red de amigos que cree en Garzón. Un abrazo de Diana Lucía Montes.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['saludo_fecha_especial', 'Marketing', 'primer_nombre',
     "¡Hola, {{1}}! En esta fecha tan especial te enviamos un saludo lleno de cariño y buenos deseos para ti y los tuyos. Diana Lucía Montes.\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['bienvenida_red', 'Marketing', 'primer_nombre, enlace_panel',
     "¡Hola, {{1}}! 🤍 Ya haces parte de la red de Diana Lucía Montes. Gracias por sumarte. En tu panel tienes tu enlace personal para invitar a familiares y amigos: {{2}}\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['nuevo_invitado_red', 'Marketing', 'primer_nombre, invitado, enlace_panel',
     "¡{{1}}, buenas noticias! 🎉 {{2}} se unió a la red gracias a tu invitación. Mira tu avance y tu puesto en el ranking aquí: {{3}}\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['subiste_nivel', 'Marketing', 'primer_nombre, nivel_promotor, enlace_panel',
     "¡Felicitaciones, {{1}}! ⭐ Ya eres {{2}} de la red de Diana Lucía Montes. Sigue invitando y sube en el ranking: {{3}}\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['tarea_asignada', 'Utility', 'primer_nombre, tarea, fecha_tarea, enlace_portal',
     "Hola, {{1}}. Tienes una nueva tarea en la red de Diana Lucía Montes: {{2}} (fecha: {{3}}). Revísala y cuéntanos si la puedes hacer en tu panel: {{4}}\n\nSi no deseas recibir más mensajes, responde SALIR."],
    ['invitacion_evento', 'Utility', 'primer_nombre, tarea, fecha_tarea, lugar_tarea, enlace_portal',
     "Hola, {{1}}. Te invitamos a {{2}} el {{3}} en {{4}}. Confirma si asistirás desde tu panel: {{5}}\n\nSi no deseas recibir más mensajes, responde SALIR."],
];
?>

<?php if ($disponible): ?>
<div class="grid g2" style="margin-top:14px">
  <section class="card">
    <h3>Plantillas de la cuenta</h3>
    <p class="muted small" style="line-height:1.6">Las plantillas se crean y aprueban en <b>WhatsApp Manager</b> (Meta). Aquí se traen y se indica qué dato va en cada variable <b>{{1}}</b>, <b>{{2}}</b>…</p>
    <?php if ($esDireccion): ?>
    <form method="post" action="<?= url('whatsapp/sincronizar') ?>" style="margin-top:12px">
      <?= \Core\Csrf::campo() ?>
      <button class="btn btn-primary" type="submit" <?= wa_cfg('WA_WABA_ID') && $configurado ? '' : 'disabled title="Falta WA_WABA_ID o las credenciales"' ?>>⟳ Traer plantillas de Meta</button>
    </form>
    <?php endif; ?>
  </section>

  <?php if ($esDireccion): ?>
  <section class="card">
    <h3>Enviar una prueba</h3>
    <form method="post" action="<?= url('whatsapp/prueba') ?>" class="editar-meta" style="margin-top:0;flex-wrap:wrap">
      <?= \Core\Csrf::campo() ?>
      <select name="plantilla_id" style="flex:1;min-width:160px;border:1.5px solid var(--line);border-radius:9px;padding:8px" aria-label="Plantilla">
        <?php foreach ($plantillas as $p): if (!$p['compatible']) continue; ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?>
      </select>
      <input type="tel" name="telefono" data-tipo="celular" pattern="3[0-9]{9}" title="10 dígitos, empieza por 3" placeholder="Tu celular: 3XX XXX XXXX" aria-label="Celular de prueba" required>
      <button class="btn btn-gold btn-mini" type="submit" <?= $configurado ? '' : 'disabled' ?>>Enviar prueba</button>
    </form>
    <p class="muted small nota">Usa datos de ejemplo (María, Docente…). Mientras el número de WhatsApp esté en modo de prueba, solo llega a los números autorizados en Meta.</p>
  </section>
  <?php endif; ?>
</div>

<section class="card" style="margin-top:16px">
  <?php if (!$plantillas): ?>
    <p class="muted vacio">Aún no hay plantillas. <?= $esDireccion ? 'Tráelas de Meta o agrégalas abajo.' : '' ?></p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Plantilla</th><th>Texto</th><th>Variables</th><?php if ($esDireccion): ?><th></th><?php endif; ?></tr>
    <?php foreach ($plantillas as $p): $fid = 'fpl' . (int)$p['id']; $asignadas = array_filter(explode(',', (string)$p['variables'])); ?>
    <tr>
      <td><b><?= e($p['nombre']) ?></b> <span class="muted small"><?= e($p['idioma']) ?></span><br>
        <?php if ($p['estado_meta']): ?><span class="tag <?= $p['estado_meta'] === 'APPROVED' ? 'tag-green' : ($p['estado_meta'] === 'REJECTED' ? 'tag-gold' : 'tag-grey') ?> small"><?= e(['APPROVED' => 'Aprobada', 'PENDING' => 'En revisión', 'REJECTED' => 'Rechazada'][$p['estado_meta']] ?? $p['estado_meta']) ?></span><?php endif; ?>
        <?php if ($p['categoria']): ?><span class="tag tag-grey small"><?= e(strtolower($p['categoria'])) ?></span><?php endif; ?>
        <?php if (!$p['compatible']): ?><br><span class="small" style="color:var(--bad)">No se puede enviar automáticamente (encabezado multimedia, botones dinámicos o variables con nombre).</span><?php endif; ?>
        <?php if ($esDireccion): ?><form id="<?= $fid ?>" method="post" action="<?= url('whatsapp/guardarplantilla/' . (int)$p['id']) ?>"><?= \Core\Csrf::campo() ?></form><?php endif; ?></td>
      <td class="small" style="max-width:340px;white-space:pre-line"><?= $p['cuerpo'] ? e($p['cuerpo']) : '<span class="muted">—</span>' ?></td>
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
      <?php if ($esDireccion): ?><td><?php if ((int)$p['num_variables'] && $p['compatible']): ?><button class="btn btn-primary btn-mini" type="submit" form="<?= $fid ?>">Guardar</button><?php endif; ?></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($esDireccion): ?>
<div class="grid g2" style="margin-top:16px">
  <section class="card">
    <h3>Agregar una plantilla a mano</h3>
    <p class="muted small" style="margin-bottom:10px">Si no tienes WA_WABA_ID configurado. El nombre y el idioma deben ser exactamente los de Meta.</p>
    <form method="post" action="<?= url('whatsapp/nuevaplantilla') ?>" style="display:grid;gap:10px">
      <?= \Core\Csrf::campo() ?>
      <div class="form-grid">
        <label class="field"><span>Nombre en Meta</span><input name="nombre" data-tipo="plantilla" maxlength="512" pattern="[a-z0-9_]+" title="Solo minúsculas, números y guion bajo, igual que en Meta" placeholder="saludo_cumpleanos" required></label>
        <label class="field"><span>Idioma</span><input name="idioma" value="es" pattern="[a-z]{2}(_[A-Z]{2})?" title="Código de idioma: es, es_CO, es_MX…" placeholder="es o es_CO" required></label>
      </div>
      <label class="field"><span>Texto del cuerpo (con {{1}}, {{2}}…)</span>
        <textarea name="cuerpo" rows="4" style="width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:inherit"></textarea></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">＋ Agregar</button></div>
    </form>
  </section>

  <section class="card">
    <h3>Plantillas sugeridas para crear en Meta</h3>
    <p class="muted small" style="margin-bottom:10px;line-height:1.6">Créalas en WhatsApp Manager → Plantillas de mensajes, categoría <b>Marketing</b>, idioma <b>Español</b>.
      Incluyen la opción de darse de baja (SALIR), que el sistema procesa solo.</p>
    <?php foreach ($sugeridas as [$nombre, $cat, $vars, $texto]): ?>
    <details class="sugerida">
      <summary><b><?= e($nombre) ?></b> <span class="muted small">· variables: <?= e($vars) ?></span></summary>
      <pre><?= e($texto) ?></pre>
      <button type="button" class="btn btn-ghost btn-mini" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent).then(()=>{this.textContent='¡Copiado!';setTimeout(()=>this.textContent='Copiar texto',1500)})">Copiar texto</button>
    </details>
    <?php endforeach; ?>
  </section>
</div>
<?php endif; ?>
<?php endif; ?>
