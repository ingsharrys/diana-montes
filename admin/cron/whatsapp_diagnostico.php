<?php
/**
 * Diagnóstico de WhatsApp desde la terminal (SSH):
 *   php admin/cron/whatsapp_diagnostico.php
 *
 * Muestra si la plataforma está al día, si hay credenciales (sin mostrarlas),
 * el estado de las plantillas de "Conversemos" y lo último que llegó por el
 * webhook. Los celulares salen enmascarados.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../bootstrap.php';
date_default_timezone_set((string)wa_cfg('WA_ZONA_HORARIA', 'America/Bogota'));

$db = Core\Database::conexion();
$linea = fn(string $t = '') => print($t . PHP_EOL);
$si = fn(bool $b) => $b ? 'sí' : 'NO';

$linea('== Plataforma ==');
$head = @file_get_contents(__DIR__ . '/../../.git/HEAD');
$ref = $head && str_starts_with($head, 'ref: ') ? @file_get_contents(__DIR__ . '/../../.git/' . trim(substr($head, 5))) : $head;
$linea('Versión: ' . substr(trim((string)$ref), 0, 7));
$linea('Cambios de base de datos pendientes: ' . count(esquema_pendientes($db)));
$linea('Tabla de Conversemos: ' . $si(conv_listo($db)));
$linea('Credenciales: token ' . $si((bool)wa_cfg('WA_TOKEN')) . ' · número ' . $si((bool)wa_cfg('WA_PHONE_NUMBER_ID'))
     . ' · clave secreta del webhook ' . $si((bool)wa_cfg('WA_APP_SECRET')) . ' · sitio ' . wa_sitio());

$linea(PHP_EOL . '== Plantillas de Conversemos ==');
$st = $db->prepare('SELECT nombre, idioma, estado_meta, extras FROM wa_plantillas WHERE nombre IN (:a, :b)');
$st->execute(['a' => CONV_PLANTILLA, 'b' => CONV_PLANTILLA_DATOS]);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$filas) $linea('Ninguna está en la plataforma (pulsa "Traer plantillas de Meta" en Plantillas).');
foreach ($filas as $p) {
    $botones = array_map(fn($b) => $b['texto'] . ($b['tipo'] === 'URL' ? ' → ' . $b['valor'] : ''), json_decode((string)$p['extras'], true)['botones'] ?? []);
    $linea("{$p['nombre']} ({$p['idioma']}): {$p['estado_meta']} · botones: " . (implode(' | ', $botones) ?: 'ninguno'));
}

$linea(PHP_EOL . '== Últimos mensajes recibidos por el webhook ==');
$ent = $db->query('SELECT e.recibido_at, e.telefono, e.simpatizante_id, e.tipo, e.texto FROM wa_entrantes e ORDER BY e.id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
if (!$ent) $linea('No ha llegado ningún mensaje: el webhook no está recibiendo (revisa la URL del webhook y "Suscribir la app" en Configuración).');
foreach ($ent as $e) {
    $linea($e['recibido_at'] . '  ' . enmascarar($e['telefono']) . '  ' . ($e['simpatizante_id'] ? 'registrado' : 'SIN registro')
         . "  [{$e['tipo']}] " . mb_substr(preg_replace('/\s+/', ' ', (string)$e['texto']), 0, 60));
}

if (conv_listo($db)) {
    $linea(PHP_EOL . '== Últimas respuestas Sí / No ==');
    $conv = $db->query('SELECT c.respondio_at, s.telefono, c.campana_id, c.respuesta, c.enlace_estado, c.enlace_error, c.abierto_at, c.completado_at
                        FROM wa_conversemos c JOIN simpatizantes s ON s.id = c.simpatizante_id ORDER BY c.respondio_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    if (!$conv) $linea('Todavía no hay ninguna.');
    foreach ($conv as $c) {
        $linea($c['respondio_at'] . '  ' . enmascarar($c['telefono']) . '  ' . strtoupper((string)$c['respuesta'])
             . ($c['campana_id'] ? "  envío #{$c['campana_id']}" : '  (prueba)')
             . ($c['respuesta'] === 'si' ? '  enlace: ' . ($c['enlace_estado'] ?: '—') . ($c['enlace_error'] ? " ({$c['enlace_error']})" : '')
                 . ($c['completado_at'] ? ' · completó' : ($c['abierto_at'] ? ' · lo abrió' : '')) : ''));
    }
}
$linea(PHP_EOL . 'Errores recientes del webhook: grep -h "whatsapp-webhook\|conversemos" ~/logs/*.log | tail -20');
