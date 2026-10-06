<?php
/**
 * registrar.php — endpoint público del formulario de simpatizantes.
 *
 * GET  ?ref=CODIGO      -> JSON {ok, lider} para pintar el chip "Te invita…"
 * POST (form fields)    -> valida, guarda en `simpatizantes` y notifica por correo.
 *                          Si la red de promotores está activa, responde además
 *                          {promotor: {codigo, token}} para su enlace y su panel.
 *
 * El ?ref puede ser el codigo_ref de un miembro del equipo (usuarios) o el
 * codigo_promotor de un simpatizante (crecimiento orgánico).
 *
 * Seguridad: consultas preparadas, validación estricta del lado servidor,
 * honeypot anti-bots, cooldown por sesión, consentimiento Ley 1581 obligatorio.
 */
require __DIR__ . '/config.php';
require __DIR__ . '/inc/promotores.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

function salir(bool $ok, string $msg, int $http = 200, array $extra = []): void {
    http_response_code($http);
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Error de validación ligado a un campo: el formulario lo muestra debajo de ese campo. */
function error_campo(string $campo, string $msg): void {
    salir(false, $msg, 200, ['campo' => $campo]);
}

// Cualquier fallo inesperado (base de datos caída, etc.) responde en JSON
// para que el formulario muestre un mensaje en vez de quedarse en silencio.
set_exception_handler(function (Throwable $e) {
    error_log('[registrar.php] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Tuvimos un problema al guardar tu registro. Intenta de nuevo en unos minutos.'], JSON_UNESCAPED_UNICODE);
});

/* ---------- GET: resolver nombre del líder que invita ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ref = trim($_GET['ref'] ?? '');
    if ($ref === '' || !preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $ref)) salir(false, 'ref inválido', 400);

    $invita = promotor_resolver_ref(db(), $ref);
    if (!$invita) salir(false, 'Código de invitación no encontrado', 404);
    salir(true, $invita['nombre']);
}

/* ---------- POST: registro ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') salir(false, 'Método no permitido', 405);

// Honeypot: campo invisible que los humanos dejan vacío
if (!empty($_POST['web'] ?? '')) salir(true, '¡Gracias!'); // bot: fingimos éxito y no guardamos

// Cooldown: máximo un registro cada 30 s por sesión (frena scripts simples)
if (isset($_SESSION['ultimo_registro']) && time() - $_SESSION['ultimo_registro'] < 30) {
    salir(false, 'Espera un momento antes de enviar otro registro.', 429);
}

$nombre    = normalizar_nombre((string)($_POST['nombre'] ?? ''));
$documento = normalizar_documento((string)($_POST['documento'] ?? ''));
$telefono  = normalizar_celular((string)($_POST['telefono'] ?? ''));
$cumple    = trim((string)($_POST['fecha_nacimiento'] ?? ''));
$genero    = is_string($_POST['genero'] ?? null) ? $_POST['genero'] : '';
$zonaId    = (int)($_POST['zona_id'] ?? 0);
$profId    = (int)($_POST['profesion_id'] ?? 0);
$ref       = trim((string)($_POST['ref'] ?? ''));
$consent   = !empty($_POST['consentimiento']);

// Ubicación aproximada: solo si el ciudadano marcó la casilla y el navegador la entregó
$lat = $lng = null;
if (!empty($_POST['ubicacion'])) {
    $lat = promotor_coordenada($_POST['lat'] ?? null, 90);
    $lng = promotor_coordenada($_POST['lng'] ?? null, 180);
    if ($lat === null || $lng === null) $lat = $lng = null;
}

/* Validaciones */
if ($error = error_nombre_persona($nombre))            error_campo('nombre', $error);
if ($error = error_documento($documento))             error_campo('documento', $error);
if ($error = error_celular($telefono))                error_campo('telefono', $error);
if ($error = simpatizante_error_nacimiento($cumple))  error_campo('fecha_nacimiento', $error);

$db = db();

// El género se pide desde que la plataforma se actualiza (columna creada)
$pideGenero = esquema_tiene($db, 'simpatizantes', 'genero');
if ($pideGenero && !isset(GENEROS[$genero])) error_campo('genero', 'Selecciona tu género.');

/* Zona y profesión deben existir en los catálogos */
$st = $db->prepare('SELECT id FROM zonas WHERE id = :id'); $st->execute(['id' => $zonaId]);
if (!$st->fetch()) error_campo('zona_id', 'Selecciona tu barrio o vereda.');
$st = $db->prepare('SELECT id FROM profesiones WHERE id = :id'); $st->execute(['id' => $profId]);
if (!$st->fetch()) error_campo('profesion_id', 'Cuéntanos a qué te dedicas.');
if (!$consent) error_campo('consentimiento', 'Para registrarte necesitamos tu autorización de datos (Ley 1581 de 2012).');

/* Duplicados */
// Duplicados: ni el documento ni el celular pueden repetirse
function salir_duplicado(?string $campo): void {
    if ($campo === 'documento') error_campo('documento', '¡Este documento ya está registrado en la red! Gracias por acompañarnos.');
    if ($campo === 'telefono')  error_campo('telefono', 'Este celular ya está registrado en la red. Si es de otra persona de tu familia, usa tu propio número.');
}
salir_duplicado(simpatizante_duplicado($db, $documento, $telefono));

/* Resolver quién invita: un miembro del equipo, un promotor ciudadano
   (su invitado hereda el líder del promotor) o la red directa de la candidata.
   La raíz se busca por su codigo_ref (LIDER_RAIZ_REF), nunca por un ID fijo. */
$liderId = null;
$referidoPor = null;
$liderNombre = 'Red directa de la candidata';
if ($ref !== '' && ($invita = promotor_resolver_ref($db, $ref))) {
    $liderId     = $invita['lider_id'];
    $referidoPor = $invita['referido_por'];
    $liderNombre = $referidoPor ? 'Invitado por el promotor ' . $invita['nombre'] : $invita['nombre'];
}
if ($liderId === null) {
    $st = $db->prepare('SELECT id FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => LIDER_RAIZ_REF]);
    $liderId = (int)($st->fetchColumn() ?: 0);
    if (!$liderId) salir(false, 'La campaña está configurando el registro. Intenta más tarde.', 503);
}

/* Guardar. Con la red de promotores activa, el nuevo simpatizante recibe
   de una vez su código para invitar, la llave de su panel y su nivel en la red. */
try {
    $nuevo = simpatizante_insertar($db, [
        'nombre' => $nombre, 'documento' => $documento, 'telefono' => $telefono,
        'fecha_nacimiento' => $cumple, 'genero' => $pideGenero ? $genero : null,
        'zona_id' => $zonaId, 'profesion_id' => $profId,
        'nivel' => 'simpatizante', 'lider_id' => $liderId,
        'referido_por' => $referidoPor, 'lat' => $lat, 'lng' => $lng,
    ]);
} catch (PDOException $e) {
    // Dos envíos al mismo tiempo: el índice único de la base frena el segundo
    if (es_error_duplicado($e)) salir_duplicado(simpatizante_duplicado($db, $documento, $telefono) ?? 'documento');
    throw $e;
}
$promotor = $nuevo['token'] ? ['codigo' => $nuevo['codigo'], 'token' => $nuevo['token']] : null;

/* Auditoría del registro público */
$db->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip)
              VALUES (NULL, \'registro_landing\', :d, :ip)')
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
        . "Nacimiento: $cumple\n"
        . ($pideGenero ? "Género:     " . GENEROS[$genero] . "\n" : '')
        . "Red:        $liderNombre\n"
        . ($lat !== null ? "Ubicación:  https://maps.google.com/?q=$lat,$lng (aprox.)\n" : '')
        . "Fecha:      " . date('d/m/Y g:i a') . "\n"
        . "IP:         " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
$cab = "From: Campaña Diana Montes <" . NOTIF_FROM . ">\r\n"
     . "Content-Type: text/plain; charset=UTF-8\r\n";
@mail(NOTIF_EMAIL, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cab);

salir(true, '¡Bienvenida/o a la red! En los próximos días recibirás el saludo de Diana por WhatsApp.',
      200, $promotor ? ['promotor' => $promotor] : []);
