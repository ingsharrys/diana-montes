<?php
/**
 * Webhook de WhatsApp (Cloud API de Meta).
 * URL para configurar en Meta: https://<dominio>/admin/whatsapp-webhook.php
 *
 * GET : verificación de la suscripción (hub.verify_token = WA_VERIFY_TOKEN).
 * POST: estados de los mensajes (enviado, entregado, leído, con error) y
 *       mensajes entrantes. Solo se acepta si la firma X-Hub-Signature-256
 *       coincide con WA_APP_SECRET.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $token = (string)wa_cfg('WA_VERIFY_TOKEN', '');
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && $token !== '' && hash_equals($token, (string)($_GET['hub_verify_token'] ?? ''))) {
        wa_traza('GET verificación OK');
        echo (string)($_GET['hub_challenge'] ?? '');
        exit;
    }
    if (isset($_GET['hub_mode'])) wa_traza('GET verificación RECHAZADA: el token de Meta no coincide con WA_VERIFY_TOKEN');
    http_response_code(403);
    exit('Token de verificación inválido.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$crudo = (string)file_get_contents('php://input');
$resumen = wa_traza_resumen(json_decode($crudo, true) ?: []);
if (!wa_firma_valida($crudo, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null)) {
    // Sin firma: alguien que no es Meta. Con firma distinta: WA_APP_SECRET no es la clave secreta de esta app.
    wa_traza('POST RECHAZADO ' . (empty($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? '(sin firma de Meta)' : '(firma distinta: revisa WA_APP_SECRET)') . " $resumen");
    http_response_code(401);
    exit('Firma inválida.');
}

try {
    $r = wa_procesar_webhook(Core\Database::conexion(), json_decode($crudo, true) ?: []);
    wa_traza("POST OK $resumen → " . json_encode($r));
    echo json_encode($r);
} catch (Throwable $e) {
    // 500: Meta reintentará la notificación más tarde
    error_log('[whatsapp-webhook] ' . $e->getMessage());
    wa_traza("POST ERROR $resumen → " . $e->getMessage());
    http_response_code(500);
}
