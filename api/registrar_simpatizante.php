<?php
/**
 * API de registro para /prueba/api/registrar_simpatizante.php
 * Usa /prueba/config/config.php, que define db().
 * Requiere PHP 7.4+, PDO MySQL y la tabla simpatizantes compartida.
 */
declare(strict_types=1);

// Todas las nuevas inscripciones pertenecen al líder de campaña de ID 2.
const LIDER_ID_REGISTRO = 2;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderRegistro(int $http, string $mensaje, ?string $campo = null, array $extra = []): void
{
    http_response_code($http);
    $respuesta = [
        'ok' => $http >= 200 && $http < 300,
        'message' => $mensaje,
        'msg' => $mensaje, // Compatibilidad con el JavaScript antiguo.
    ];
    if ($campo !== null) {
        $respuesta['campo'] = $campo;
    }
    echo json_encode($respuesta + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function campoRegistro(string $nombre): string
{
    $valor = $_POST[$nombre] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

function errorCampoRegistro(string $campo, string $mensaje): void
{
    responderRegistro(422, $mensaje, $campo);
}

/** Busca un ID positivo sin convertir valores inválidos en un entero válido. */
function idRegistro(string $nombre): int
{
    $valor = filter_var(campoRegistro($nombre), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    return $valor === false ? 0 : (int) $valor;
}

/**
 * Regla de red pública:
 * - lider_id siempre 2.
 * - ref vacío o "diana" => referido_por NULL.
 * - cualquier otro ref válido debe corresponder a simpatizantes.codigo_promotor.
 * - un ref inválido no debe convertirse silenciosamente en registro directo.
 * @return array{lider_id:int,referido_por:?int}
 */
function resolverRedRegistro(PDO $db, string $ref): array
{
    $liderId = LIDER_ID_REGISTRO;

    // Verificar que el líder obligatorio exista y esté activo.
    $st = $db->prepare('SELECT id FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1');
    $st->execute([':id' => $liderId]);
    if ($st->fetchColumn() === false) {
        error_log('Registro: líder público ID ' . $liderId . ' inexistente o inactivo.');
        responderRegistro(503, 'El registro no está disponible temporalmente.');
    }

    if ($ref === '' || strcasecmp($ref, 'diana') === 0) {
        return ['lider_id' => $liderId, 'referido_por' => null];
    }

    if (!preg_match('/\A[a-zA-Z0-9_-]{2,30}\z/', $ref)) {
        errorCampoRegistro('ref', 'El enlace de invitación no tiene un código válido.');
    }

    $st = $db->prepare(
        'SELECT id FROM simpatizantes WHERE codigo_promotor = :ref LIMIT 1'
    );
    $st->execute([':ref' => $ref]);
    $referidoId = (int) ($st->fetchColumn() ?: 0);

    if ($referidoId < 1) {
        errorCampoRegistro('ref', 'El código de invitación no existe. Solicita un enlace válido.');
    }

    return ['lider_id' => $liderId, 'referido_por' => $referidoId];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderRegistro(405, 'Método no permitido.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// El token se crea en index.php, antes de mostrar el formulario.
$tokenSesion = $_SESSION['csrf_token'] ?? '';
$tokenRecibido = campoRegistro('csrf_token');
if (!is_string($tokenSesion) || $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenRecibido)) {
    responderRegistro(419, 'Tu sesión expiró. Actualiza la página e inténtalo de nuevo.');
}

// Honeypot: completar este campo oculto indica actividad automatizada.
if (campoRegistro('web') !== '') {
    responderRegistro(200, 'Gracias por registrarte.');
}

if (isset($_SESSION['registro_ultimo_exito']) &&
    time() - (int) $_SESSION['registro_ultimo_exito'] < 30) {
    responderRegistro(429, 'Espera unos segundos antes de enviar otro registro.');
}

$nombre = preg_replace('/\s+/u', ' ', campoRegistro('nombre'));
$documento = campoRegistro('documento');
$telefono = campoRegistro('celular');        // HTML: celular => MySQL: telefono
$zonaId = idRegistro('zona_id');
$profesionId = idRegistro('prof');           // HTML: prof => MySQL: profesion_id
$fecha = campoRegistro('fecha');            // HTML: fecha => MySQL: fecha_nacimiento
$generoEntrada = campoRegistro('genero');
$clave = $_POST['clave'] ?? '';
$consentimiento = in_array(campoRegistro('datos'), ['on', '1'], true);
$ref = campoRegistro('ref');                 // Opcional: ?ref=...

// ==========================================
// HONEYPOT - PROTECCIÓN ANTIBOTS
// ==========================================

$trampa = $_POST['web'] ?? '';

if (!is_string($trampa) || trim($trampa) !== '') {

    http_response_code(422);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'message' => 'No fue posible procesar la solicitud.',
        'msg' => 'No fue posible procesar la solicitud.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$generos = [
    'Femenino' => 'mujer',
    'Masculino' => 'hombre',
    'Otro' => 'otro',
    'Prefiero no decirlo' => 'no_dice',
    'mujer' => 'mujer',
    'hombre' => 'hombre',
    'otro' => 'otro',
    'no_dice' => 'no_dice',
];
$genero = $generos[$generoEntrada] ?? null;

if (!is_string($nombre) ||
    !preg_match("/\A[\p{L}\p{M} .\x{2019}'-]{3,120}\z/u", $nombre) ||
    !preg_match('/\S+\s+\S+/u', $nombre)) {
    errorCampoRegistro('nombre', 'Escribe tu nombre y apellido (máximo 120 caracteres).');
}
if (!preg_match('/\A\d{6,10}\z/', $documento)) {
    errorCampoRegistro('documento', 'El documento debe tener entre 6 y 10 dígitos.');
}
if (!preg_match('/\A3\d{9}\z/', $telefono)) {
    errorCampoRegistro('celular', 'El celular debe tener 10 dígitos y comenzar por 3.');
}
if ($zonaId < 1) {
    errorCampoRegistro('zona_id', 'Selecciona tu barrio o vereda.');
}
if ($profesionId < 1) {
    errorCampoRegistro('prof', 'Selecciona tu profesión u oficio.');
}

$zonaHoraria = new DateTimeZone('America/Bogota');
$nacimiento = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $zonaHoraria);
$hoy = new DateTimeImmutable('today', $zonaHoraria);
if (!$nacimiento || $nacimiento->format('Y-m-d') !== $fecha ||
    $nacimiento > $hoy->modify('-18 years')) {
    errorCampoRegistro('fecha', 'Debes tener 18 años o más e indicar una fecha válida.');
}
if ($genero === null) {
    errorCampoRegistro('genero', 'Selecciona una opción válida de género.');
}
if (!is_string($clave) || strlen($clave) < 12 || strlen($clave) > 72) {
    errorCampoRegistro('clave', 'La clave debe tener entre 12 y 72 bytes.');
}
if (!$consentimiento) {
    errorCampoRegistro('datos', 'Debes autorizar expresamente el tratamiento de datos para registrarte.');
}

// Solo enviar coordenadas si el usuario marcó voluntariamente la opción.
$lat = $lng = null;
if (in_array(campoRegistro('ubicacion'), ['on', '1'], true)) {
    $latTexto = campoRegistro('lat');
    $lngTexto = campoRegistro('lng');
    $latNum = filter_var($latTexto, FILTER_VALIDATE_FLOAT);
    $lngNum = filter_var($lngTexto, FILTER_VALIDATE_FLOAT);
    if ($latTexto === '' || $lngTexto === '' ||
        $latNum === false || $lngNum === false ||
        $latNum < -90 || $latNum > 90 || $lngNum < -180 || $lngNum > 180) {
        errorCampoRegistro('ubicacion', 'No se pudo validar la ubicación. Desmarca la opción para continuar.');
    }
    $lat = round((float) $latNum, 3);
    $lng = round((float) $lngNum, 3);
}

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    error_log('Registro: no existe config/config.php en el proyecto.');
    responderRegistro(500, 'No se pudo inicializar el registro.');
}

try {
    require_once $configPath; // Define db() y LIDER_RAIZ_REF.
    if (!function_exists('db')) {
        throw new RuntimeException('Falta la función db() en config.php');
    }
    $db = db();

    // Comprobar claves foráneas antes del INSERT.
    foreach ([
        ['tabla' => 'zonas', 'id' => $zonaId, 'campo' => 'zona_id', 'error' => 'El barrio o vereda no existe.'],
        ['tabla' => 'profesiones', 'id' => $profesionId, 'campo' => 'prof', 'error' => 'La profesión no existe.'],
    ] as $verificacion) {
        $st = $db->prepare('SELECT id FROM `' . $verificacion['tabla'] . '` WHERE id = :id LIMIT 1');
        $st->execute([':id' => $verificacion['id']]);
        if ($st->fetchColumn() === false) {
            errorCampoRegistro($verificacion['campo'], $verificacion['error']);
        }
    }

    $red = resolverRedRegistro($db, $ref);

    // Códigos compatibles con el tamaño de las columnas actuales.
    // No se devuelve token_panel en la respuesta: es una credencial sensible.
    $codigoPromotor = strtoupper(bin2hex(random_bytes(8))); // 16 caracteres
    $tokenPanel = bin2hex(random_bytes(16));                // 32 caracteres
    $claveHash = password_hash($clave, PASSWORD_DEFAULT);

    $sql = 'INSERT INTO simpatizantes (
                nombre, documento, telefono, fecha_nacimiento, zona_id,
                profesion_id, genero, lider_id, referido_por,
                nivel, consentimiento_datos, consentimiento_fecha,
                clave_hash, codigo_promotor, token_panel, lat, lng
            ) VALUES (
                :nombre, :documento, :telefono, :fecha_nacimiento, :zona_id,
                :profesion_id, :genero, :lider_id, :referido_por,
                :nivel, :consentimiento_datos, :consentimiento_fecha,
                :clave_hash, :codigo_promotor, :token_panel, :lat, :lng
            )';

    $st = $db->prepare($sql);
    $st->execute([
        ':nombre' => $nombre,
        ':documento' => $documento,
        ':telefono' => $telefono,
        ':fecha_nacimiento' => $fecha,
        ':zona_id' => $zonaId,
        ':profesion_id' => $profesionId,
        ':genero' => $genero,
        ':lider_id' => $red['lider_id'],
        ':referido_por' => $red['referido_por'],
        ':nivel' => 'simpatizante',
        ':consentimiento_datos' => 1,
        ':consentimiento_fecha' => (new DateTimeImmutable('now', $zonaHoraria))->format('Y-m-d H:i:s'),
        ':clave_hash' => $claveHash,
        ':codigo_promotor' => $codigoPromotor,
        ':token_panel' => $tokenPanel,
        ':lat' => $lat,
        ':lng' => $lng,
    ]);

    $_SESSION['registro_ultimo_exito'] = time();
    responderRegistro(201, 'Registro guardado correctamente.', null, [
        'registro_confirmado' => true,
        'promotor' => ['codigo' => $codigoPromotor],
    ]);

} catch (PDOException $e) {
    $codigoMysql = (int) ($e->errorInfo[1] ?? 0);
    if ($codigoMysql === 1062) {
        // Mensaje genérico: evita revelar si otra persona pertenece a esta red.
        responderRegistro(409, 'No se pudo completar el registro. Verifica si ya tienes una cuenta.');
    }
    if ($codigoMysql === 1452) {
        responderRegistro(422, 'Una de las opciones del registro ya no está disponible.');
    }
    error_log('Registro: error PDO, código MySQL ' . $codigoMysql);
    responderRegistro(500, 'No fue posible guardar el registro. Inténtalo más tarde.');
} catch (Throwable $e) {
    error_log('Registro: error interno (' . get_class($e) . ').');
    responderRegistro(500, 'No fue posible procesar el registro. Inténtalo más tarde.');
}
