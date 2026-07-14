<?php
/**
 * registrar.php — endpoint público del formulario de simpatizantes.
 *
 * GET  ?ref=CODIGO      -> JSON {ok, lider} para pintar el chip "Te invita…"
 * POST (form fields)    -> valida, guarda en `simpatizantes` y notifica por correo.
 *
 * Seguridad: consultas preparadas, validación estricta del lado servidor,
 * honeypot anti-bots, cooldown por sesión, consentimiento Ley 1581 obligatorio.
 */
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

function salir(bool $ok, string $msg, int $http = 200): void {
    http_response_code($http);
    echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- GET: resolver nombre del líder que invita ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ref = trim($_GET['ref'] ?? '');
    if ($ref === '' || !preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $ref)) salir(false, 'ref inválido', 400);

    $st = db()->prepare('SELECT nombre FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => $ref]);
    $u = $st->fetch();
    if (!$u) salir(false, 'Código de invitación no encontrado', 404);
    salir(true, $u['nombre']);
}

/* ---------- POST: registro ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') salir(false, 'Método no permitido', 405);

// Honeypot: campo invisible que los humanos dejan vacío
if (!empty($_POST['web'] ?? '')) salir(true, '¡Gracias!'); // bot: fingimos éxito y no guardamos

// Cooldown: máximo un registro cada 30 s por sesión (frena scripts simples)
if (isset($_SESSION['ultimo_registro']) && time() - $_SESSION['ultimo_registro'] < 30) {
    salir(false, 'Espera un momento antes de enviar otro registro.', 429);
}

$nombre    = trim($_POST['nombre'] ?? '');
$documento = preg_replace('/\D/', '', $_POST['documento'] ?? '');
$telefono  = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
$cumple    = trim($_POST['fecha_nacimiento'] ?? '');
$zonaId    = (int)($_POST['zona_id'] ?? 0);
$profId    = (int)($_POST['profesion_id'] ?? 0);
$ref       = trim($_POST['ref'] ?? '');
$consent   = !empty($_POST['consentimiento']);

/* Validaciones */
if (mb_strlen($nombre) < 5 || mb_strlen($nombre) > 120) salir(false, 'Escribe tu nombre completo.');
if (strlen($documento) < 6 || strlen($documento) > 12)  salir(false, 'Revisa tu número de documento.');
if (strlen($telefono) !== 10 || $telefono[0] !== '3')   salir(false, 'El celular debe tener 10 dígitos y empezar por 3.');
if ($cumple !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cumple)) $cumple = '';
if (!$consent) salir(false, 'Necesitamos tu autorización de datos (Ley 1581 de 2012).');

$db = db();

/* Zona y profesión deben existir en los catálogos */
$st = $db->prepare('SELECT id FROM zonas WHERE id = :id'); $st->execute(['id' => $zonaId]);
if (!$st->fetch()) salir(false, 'Selecciona tu barrio o vereda.');
$st = $db->prepare('SELECT id FROM profesiones WHERE id = :id'); $st->execute(['id' => $profId]);
if (!$st->fetch()) salir(false, 'Cuéntanos a qué te dedicas.');

/* Duplicados */
$st = $db->prepare('SELECT id FROM simpatizantes WHERE documento = :d OR telefono = :t LIMIT 1');
$st->execute(['d' => $documento, 't' => $telefono]);
if ($st->fetch()) salir(false, '¡Ya estás registrado/a en la red! Gracias por acompañarnos.');

/* Resolver el líder: código de invitación o red directa de la candidata.
   La raíz se busca por su codigo_ref (LIDER_RAIZ_REF), nunca por un ID fijo. */
$liderId = null;
$liderNombre = 'Red directa de la candidata';
if ($ref !== '' && preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $ref)) {
    $st = $db->prepare('SELECT id, nombre FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => $ref]);
    if ($u = $st->fetch()) { $liderId = (int)$u['id']; $liderNombre = $u['nombre']; }
}
if ($liderId === null) {
    $st = $db->prepare('SELECT id FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => LIDER_RAIZ_REF]);
    $liderId = (int)($st->fetchColumn() ?: 0);
    if (!$liderId) salir(false, 'La campaña está configurando el registro. Intenta más tarde.', 503);
}

/* Guardar */
$st = $db->prepare(
    'INSERT INTO simpatizantes
       (nombre, documento, telefono, fecha_nacimiento, zona_id, profesion_id,
        nivel, lider_id, consentimiento_datos, consentimiento_fecha)
     VALUES
       (:nombre, :documento, :telefono, :cumple, :zona, :prof,
        "simpatizante", :lider, 1, NOW())'
);
$st->execute([
    'nombre' => $nombre, 'documento' => $documento, 'telefono' => $telefono,
    'cumple' => $cumple ?: null, 'zona' => $zonaId, 'prof' => $profId, 'lider' => $liderId,
]);

/* Auditoría del registro público */
$db->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip)
              VALUES (NULL, "registro_landing", :d, :ip)')
   ->execute([
       'd'  => 'Doc ' . substr($documento, 0, 3) . '··· vía ' . ($ref ?: 'red directa'),
       'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
   ]);

$_SESSION['ultimo_registro'] = time();

/* Correo de notificación a la campaña (no bloquea el registro si falla) */
$zonaNom = $db->query('SELECT nombre FROM zonas WHERE id = ' . (int)$zonaId)->fetchColumn();
$profNom = $db->query('SELECT nombre FROM profesiones WHERE id = ' . (int)$profId)->fetchColumn();

$asunto = '🤍 Nuevo simpatizante: ' . $nombre;
$cuerpo = "Nuevo registro desde dianamontes.com\n\n"
        . "Nombre:     $nombre\n"
        . "Documento:  $documento\n"
        . "WhatsApp:   $telefono\n"
        . "Zona:       $zonaNom\n"
        . "Profesión:  $profNom\n"
        . ($cumple ? "Cumpleaños: $cumple\n" : '')
        . "Red:        $liderNombre\n"
        . "Fecha:      " . date('d/m/Y g:i a') . "\n"
        . "IP:         " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
$cab = "From: Campaña Diana Montes <" . NOTIF_FROM . ">\r\n"
     . "Content-Type: text/plain; charset=UTF-8\r\n";
@mail(NOTIF_EMAIL, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cab);

salir(true, '¡Bienvenida/o a la red! En los próximos días recibirás el saludo de Diana por WhatsApp.');
