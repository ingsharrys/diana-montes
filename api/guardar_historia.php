<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderHistoria(int $http, bool $ok, string $mensaje): void
{
    http_response_code($http);
    echo json_encode(
        ['ok' => $ok, 'message' => $mensaje],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function valorPostHistoria(string $clave): string
{
    $valor = $_POST[$clave] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderHistoria(405, false, 'Método no permitido.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Reutiliza el token CSRF que ya puede estar creado en index.php.
$token = valorPostHistoria('csrf_token');
if (
    $token === '' ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string) $_SESSION['csrf_token'], $token)
) {
    responderHistoria(403, false, 'La sesión expiró. Actualiza la página e inténtalo nuevamente.');
}

// Campo invisible opcional para mitigar envíos automatizados simples.
if (valorPostHistoria('sitio_web') !== '') {
    responderHistoria(422, false, 'No fue posible validar el envío.');
}

if (
    isset($_SESSION['ultima_historia_enviada']) &&
    time() - (int) $_SESSION['ultima_historia_enviada'] < 30
) {
    responderHistoria(429, false, 'Espera unos segundos antes de enviar otra historia.');
}

$nombre = valorPostHistoria('nombre');
$mensaje = valorPostHistoria('mensaje');
$barrio = valorPostHistoria('barrio_id');
$consentimiento = !empty($_POST['datos']);
$publicar = !empty($_POST['publicar']) ? 1 : 0;

// Normalizar texto sin eliminar los saltos de línea del relato.
$nombre = preg_replace('/\s+/u', ' ', $nombre);
$mensaje = preg_replace('/\r\n?/', "\n", $mensaje);

if ($nombre === null || $mensaje === null) {
    responderHistoria(422, false, 'El texto contiene caracteres no válidos.');
}

if (!function_exists('mb_strlen')) {
    error_log('[guardar_historia.php] La extensión mbstring de PHP no está disponible.');
    responderHistoria(500, false, 'El registro de historias no está disponible temporalmente.');
}

if (mb_strlen($nombre, 'UTF-8') < 3 || mb_strlen($nombre, 'UTF-8') > 120) {
    responderHistoria(422, false, 'Escribe tu nombre completo (máximo 120 caracteres).');
}

if (mb_strlen($mensaje, 'UTF-8') < 10 || mb_strlen($mensaje, 'UTF-8') > 5000) {
    responderHistoria(422, false, 'Tu historia debe tener entre 10 y 5000 caracteres.');
}

if (!$consentimiento) {
    responderHistoria(422, false, 'Debes aceptar la política de tratamiento de datos.');
}

$zonaId = null;
if ($barrio !== '') {
    if (!ctype_digit($barrio) || (int) $barrio < 1 || (float) $barrio > 4294967295) {
        responderHistoria(422, false, 'El barrio o vereda seleccionado no es válido.');
    }
    $zonaId = (int) $barrio;
}

try {
    require_once dirname(__DIR__) . '/config/config.php';
    $pdo = db();

    if ($zonaId !== null) {
        $consultaZona = $pdo->prepare('SELECT 1 FROM zonas WHERE id = :id LIMIT 1');
        $consultaZona->execute([':id' => $zonaId]);
        if (!$consultaZona->fetchColumn()) {
            responderHistoria(422, false, 'Selecciona un barrio o vereda existente.');
        }
    }

    $sql = 'INSERT INTO historias_ciudadanas
                (nombre, zona_id, mensaje, consentimiento_datos,
                 consentimiento_fecha, autoriza_publicacion,
                 autorizacion_publicacion_fecha)
            VALUES
                (:nombre, :zona_id, :mensaje, 1, NOW(),
                 :autoriza_publicacion, :fecha_publicacion)';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':zona_id', $zonaId, $zonaId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
    $stmt->bindValue(':autoriza_publicacion', $publicar, PDO::PARAM_INT);
    $fechaPublicacion = $publicar === 1 ? date('Y-m-d H:i:s') : null;
    $stmt->bindValue(
        ':fecha_publicacion',
        $fechaPublicacion,
        $fechaPublicacion === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
    $stmt->execute();

    $_SESSION['ultima_historia_enviada'] = time();

    responderHistoria(
        201,
        true,
        '¡Gracias por compartir tu historia! La recibimos correctamente y será revisada. Solo podrá publicarse si lo autorizaste.'
    );
} catch (Throwable $e) {
    error_log('[guardar_historia.php] ' . $e->getMessage());
    responderHistoria(500, false, 'No fue posible guardar tu historia. Inténtalo nuevamente más tarde.');
}
