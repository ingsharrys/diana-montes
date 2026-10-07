<?php require __DIR__ . '/_tabs.php'; ?>

<?php
$claves = [
    'WA_PHONE_NUMBER_ID' => ['Identificador del número', 'Lo muestra el botón Probar conexión (o la app → WhatsApp → Configuración de la API)', true],
    'WA_TOKEN'           => ['Token de acceso permanente', 'Usuario del sistema con permisos whatsapp_business_messaging y whatsapp_business_management', true],
    'WA_APP_SECRET'      => ['Clave secreta de la app', 'developers.facebook.com → tu app → Configuración → Básica (para validar el webhook)', true],
    'WA_VERIFY_TOKEN'    => ['Token de verificación del webhook', 'Una frase que inventas tú y pegas también en Meta', true],
    'WA_WABA_ID'         => ['Identificador de la cuenta de WhatsApp Business', 'El número que sale como asset_id en la dirección de WhatsApp Manager. Sirve para crear y traer plantillas', true],
];
?>
<div class="grid g2" style="margin-top:14px">
  <section class="card">
    <h3>Credenciales <span class="tag <?= $configurado ? 'tag-green' : 'tag-gold' ?>"><?= $configurado ? 'listas para enviar' : 'incompletas' ?></span></h3>
    <table>
      <?php foreach ($claves as $c => [$nombre, $ayuda, $obligatoria]): $ok = (bool)wa_cfg($c); ?>
      <tr>
        <td style="width:28px;font-size:16px"><?= $ok ? '✅' : ($obligatoria ? '⚠️' : '➖') ?></td>
        <td><b><?= e($nombre) ?></b> <code class="ref-code"><?= $c ?></code><br><span class="muted small"><?= e($ayuda) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted small nota">Se configuran en <code>admin/config/config.php</code> del servidor (nunca en GitHub).
      Tope diario actual: <b><?= num((int)wa_cfg('WA_LIMITE_DIARIO', 250)) ?></b> mensajes · horario de envío: 7:00 a <?= (int)wa_cfg('WA_HORA_FIN', 21) ?>:00.</p>
    <form method="post" action="<?= url('whatsapp/conexion') ?>" style="margin-top:12px">
      <?= \Core\Csrf::campo() ?>
      <button class="btn btn-primary" type="submit" <?= wa_cfg('WA_TOKEN') && (wa_cfg('WA_WABA_ID') || wa_cfg('WA_PHONE_NUMBER_ID')) ? '' : 'disabled title="Pon al menos WA_TOKEN y WA_WABA_ID"' ?>>Probar conexión con Meta</button>
    </form>
    <?php if (!empty($conexion['numero'])): $n = $conexion['numero']; ?>
      <div class="alert alert-ok" style="margin-top:12px;font-weight:500">
        📱 <b><?= e($n['display_phone_number'] ?? '') ?></b> · <?= e($n['verified_name'] ?? '') ?><br>
        Calidad: <b><?= e($n['quality_rating'] ?? '—') ?></b> · Límite de Meta: <b><?= e($n['messaging_limit_tier'] ?? '—') ?></b>
        <?= !empty($n['name_status']) ? ' · Nombre: ' . e($n['name_status']) : '' ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($conexion['cuenta']) && !$conexion['cuenta']['error']): $c = $conexion['cuenta']; ?>
      <div style="margin-top:12px">
        <b class="small">Números de la cuenta de WhatsApp (<?= e((string)wa_cfg('WA_WABA_ID')) ?>)</b>
        <table style="margin-top:6px">
          <tr><th>Número</th><th>Identificador del número</th><th>Estado</th></tr>
          <?php foreach ($c['numeros'] as $num): $esEste = (string)($num['id'] ?? '') === (string)wa_cfg('WA_PHONE_NUMBER_ID'); ?>
          <tr>
            <td><b><?= e($num['display_phone_number'] ?? '') ?></b><br><span class="muted small"><?= e($num['verified_name'] ?? '') ?></span></td>
            <td><code class="ref-code"><?= e($num['id'] ?? '') ?></code><br>
              <span class="small <?= $esEste ? '' : 'muted' ?>"><?= $esEste ? '✅ es el configurado en WA_PHONE_NUMBER_ID' : 'Cópialo en WA_PHONE_NUMBER_ID si es el de la campaña' ?></span></td>
            <td class="small">Calidad <?= e($num['quality_rating'] ?? '—') ?><?= !empty($num['code_verification_status']) ? '<br>Verificación: ' . e($num['code_verification_status']) : '' ?><?= !empty($num['name_status']) ? '<br>Nombre: ' . e($num['name_status']) : '' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$c['numeros']): ?><tr><td colspan="3" class="muted">La cuenta aún no tiene números registrados.</td></tr><?php endif; ?>
        </table>
        <p class="small" style="margin-top:10px">
          <?php if ($c['suscrita']): ?>✅ La app está conectada a la cuenta (<?= e(implode(', ', $c['apps'])) ?>): los estados entregado/leído y las respuestas llegan al webhook.
          <?php else: ?>⚠️ La app <b>no</b> está suscrita a la cuenta: sin esto no llegan los estados entregado/leído ni las respuestas.<?php endif; ?>
        </p>
        <?php if (!$c['suscrita']): ?>
        <form method="post" action="<?= url('whatsapp/suscribir') ?>"><?= \Core\Csrf::campo() ?><button class="btn btn-gold btn-mini" type="submit">Conectar la cuenta al webhook</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Pegar en Meta y en cPanel</h3>
    <p class="small"><b>1. Webhook</b> (developers.facebook.com → tu app → WhatsApp → Configuración):</p>
    <div class="copiable"><code><?= e($webhook) ?></code></div>
    <p class="muted small" style="margin:6px 0 12px">Token de verificación: el mismo de <code>WA_VERIFY_TOKEN</code>. Suscribe el campo <b>messages</b>.</p>
    <p class="small"><b>2. Cron</b> (cPanel → Trabajos de cron, cada 5 minutos):</p>
    <div class="copiable"><code><?= e($cron) ?></code></div>
    <p class="muted small nota">El cron prepara los saludos del día a las <?= (int)wa_cfg('WA_HORA_ENVIO', 9) ?>:00 y envía la cola cada 5 minutos. "Enviar ahora" en el Resumen hace lo mismo al instante.</p>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <h3>Paso a paso en Meta</h3>
  <ol class="pasos-wa">
    <li>En <b>business.facebook.com</b> usa una cuenta comercial de la campaña. <b>No uses la de la cafetería</b>: si Meta restringe la campaña, tu negocio no se ve afectado.</li>
    <li><b>Identificador de la cuenta de WhatsApp (WA_WABA_ID):</b> en WhatsApp Manager, la dirección del navegador trae <code>asset_id=…</code>: ese número es el de tu cuenta. El <code>business_id</code> es el del portafolio comercial y aquí no se usa.</li>
    <li>En <b>developers.facebook.com</b> crea una app tipo <i>Business</i>, agrégale el producto <b>WhatsApp</b> y conéctala a esa cuenta de WhatsApp.</li>
    <li>En el portafolio (Configuración del negocio → Usuarios del sistema) crea un <b>usuario del sistema</b> administrador, asígnale la app y la cuenta de WhatsApp con control total, y genera un <b>token</b> que no caduque con los permisos <code>whatsapp_business_messaging</code> y <code>whatsapp_business_management</code> (WA_TOKEN).</li>
    <li>En la app → Configuración → Básica copia la <b>clave secreta</b> (WA_APP_SECRET) e inventa una frase para WA_VERIFY_TOKEN.</li>
    <li>Pega todo en <code>admin/config/config.php</code> del servidor y pulsa <b>Probar conexión</b>: aparecerá el <b>identificador del número</b> para WA_PHONE_NUMBER_ID y si la cuenta está conectada al webhook.</li>
    <li>En la app → WhatsApp → Configuración pon la <b>URL del webhook</b> y el token de verificación de arriba y suscribe el campo <b>messages</b>. Luego pulsa <b>Conectar la cuenta al webhook</b> aquí si aparece.</li>
    <li>En la pestaña <b>Plantillas</b> crea las plantillas con un clic (quedan en revisión en Meta), y cuando estén aprobadas actívalas en <b>Ocasiones</b>.</li>
  </ol>
  <p class="alert alert-error" style="margin-top:12px;font-weight:500">⚠ Las políticas de Meta prohíben a campañas políticas usar la plataforma empresarial de WhatsApp y pueden bloquear el número.
    Úsalo bajo tu responsabilidad, solo con personas que dieron su consentimiento, y respeta las bajas. Los números nuevos empiezan con un límite de Meta de 250 personas por día; sube con buena calidad.</p>
</section>
