<?php require __DIR__ . '/_tabs.php'; ?>

<?php
$claves = [
    'WA_PHONE_NUMBER_ID' => ['Identificador del número', 'WhatsApp Manager → Configuración de la API', true],
    'WA_TOKEN'           => ['Token de acceso permanente', 'Usuario del sistema con permisos whatsapp_business_messaging y whatsapp_business_management', true],
    'WA_APP_SECRET'      => ['Clave secreta de la app', 'developers.facebook.com → tu app → Configuración → Básica (para validar el webhook)', true],
    'WA_VERIFY_TOKEN'    => ['Token de verificación del webhook', 'Una frase que inventas tú y pegas también en Meta', true],
    'WA_WABA_ID'         => ['Identificador de la cuenta de WhatsApp Business', 'Para traer las plantillas automáticamente', false],
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
      <button class="btn btn-primary" type="submit" <?= $configurado ? '' : 'disabled' ?>>Probar conexión con Meta</button>
    </form>
    <?php if ($conexion): ?>
      <div class="alert alert-ok" style="margin-top:12px;font-weight:500">
        📱 <b><?= e($conexion['display_phone_number'] ?? '') ?></b> · <?= e($conexion['verified_name'] ?? '') ?><br>
        Calidad: <b><?= e($conexion['quality_rating'] ?? '—') ?></b> · Límite de Meta: <b><?= e($conexion['messaging_limit_tier'] ?? '—') ?></b>
        <?= !empty($conexion['name_status']) ? ' · Nombre: ' . e($conexion['name_status']) : '' ?>
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
    <li>En <b>developers.facebook.com</b> crea una app tipo <i>Business</i> y agrega el producto <b>WhatsApp</b>.</li>
    <li>Registra un <b>número dedicado</b> a la campaña (no puede estar activo en la app normal de WhatsApp).</li>
    <li>Crea un <b>usuario del sistema</b>, asígnale la app y la cuenta de WhatsApp, y genera un <b>token permanente</b> con los permisos <code>whatsapp_business_messaging</code> y <code>whatsapp_business_management</code>.</li>
    <li>Configura el <b>webhook</b> con la URL y el token de verificación de arriba, y suscribe <b>messages</b>.</li>
    <li>Crea las <b>plantillas</b> (pestaña Plantillas tiene textos sugeridos) y espera su aprobación.</li>
    <li>Copia los identificadores y el token en <code>admin/config/config.php</code>, pulsa <b>Probar conexión</b> y activa las ocasiones.</li>
  </ol>
  <p class="alert alert-error" style="margin-top:12px;font-weight:500">⚠ Las políticas de Meta prohíben a campañas políticas usar la plataforma empresarial de WhatsApp y pueden bloquear el número.
    Úsalo bajo tu responsabilidad, solo con personas que dieron su consentimiento, y respeta las bajas. Los números nuevos empiezan con un límite de Meta de 250 personas por día; sube con buena calidad.</p>
</section>
