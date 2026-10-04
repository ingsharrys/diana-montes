<?php
/**
 * Arranque común del admin: configuración, helpers, módulos compartidos y
 * autocarga de clases. Lo usan index.php (web), whatsapp-webhook.php y los
 * procesos programados de cron/.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/graficas.php';
require_once __DIR__ . '/../inc/promotores.php'; // red de promotores y WhatsApp (compartido con la landing)

spl_autoload_register(function ($clase) {
    $ruta = __DIR__ . '/app/' . str_replace('\\', '/', $clase) . '.php';
    if (is_file($ruta)) require_once $ruta;
});
