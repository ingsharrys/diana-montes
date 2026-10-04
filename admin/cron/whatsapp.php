<?php
/**
 * Proceso programado de WhatsApp: encola los saludos del día y envía la cola.
 *
 * En cPanel → Cron Jobs, cada 5 minutos:
 *   *\/5 * * * * /usr/local/bin/php /home/dianamontes/public_html/admin/cron/whatsapp.php >> /home/dianamontes/logs/whatsapp.log 2>&1
 *   (sin la barra invertida antes de /5)
 *
 * - Los saludos del día (cumpleaños, profesiones, fechas especiales) se
 *   encolan a partir de WA_HORA_ENVIO (9 a. m. por defecto).
 * - Los envíos solo salen entre las 7 a. m. y WA_HORA_FIN (9 p. m.), en
 *   lotes de WA_LOTE mensajes y sin pasar de WA_LIMITE_DIARIO al día.
 * - "--forzar" ignora el horario (para pruebas).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/../bootstrap.php';
date_default_timezone_set((string)wa_cfg('WA_ZONA_HORARIA', 'America/Bogota'));

$db     = Core\Database::conexion();
$ahora  = new DateTimeImmutable('now');
$hora   = (int)$ahora->format('G');
$forzar = in_array('--forzar', $argv ?? [], true);
$salida = [];

if (!wa_disponible($db)) {
    echo $ahora->format('Y-m-d H:i') . " WhatsApp aún no está activo: falta \"Actualizar plataforma\" en el admin.\n";
    exit;
}

if ($forzar || $hora >= (int)wa_cfg('WA_HORA_ENVIO', 9)) {
    $salida['encolados_hoy'] = wa_generar_del_dia($db, $ahora->setTime(0, 0));
}

if ($forzar || ($hora >= 7 && $hora < (int)wa_cfg('WA_HORA_FIN', 21))) {
    $salida['envio'] = wa_procesar_cola($db, (int)wa_cfg('WA_LOTE', 60));
} else {
    $salida['envio'] = 'fuera del horario de envío';
}

echo $ahora->format('Y-m-d H:i') . ' ' . json_encode($salida, JSON_UNESCAPED_UNICODE) . PHP_EOL;
