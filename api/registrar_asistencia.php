<?php
/** API pública para registrar interés de asistencia a un evento publicado. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderAsistencia(int $codigo, bool $ok, string $mensaje, array $extra = []): void
{
    http_response_code($codigo);
    echo json_encode(
        ['ok' => $ok, 'message' => $mensaje] + $extra,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        responderAsistencia(405, false, 'Método no permitido.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Reutiliza el token CSRF de los formularios existentes en index.php.
    $tokenSesion = $_SESSION['csrf_token'] ?? '';
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenSesion) || $tokenSesion === '' ||
        !is_string($tokenEnviado) || !hash_equals($tokenSesion, $tokenEnviado)) {
        responderAsistencia(419, false, 'Tu sesión venció. Actualiza la página e inténtalo nuevamente.');
    }

    // Campo invisible para reducir envíos automatizados.
    if (!empty($_POST['pagina_web'] ?? '')) {
        responderAsistencia(422, false, 'No fue posible procesar la solicitud.');
    }

    // Pequeño intervalo por sesión entre registros exitosos.
    if (isset($_SESSION['ultima_asistencia']) &&
        time() - (int)$_SESSION['ultima_asistencia'] < 10) {
        responderAsistencia(429, false, 'Espera unos segundos antes de registrar otra asistencia.');
    }

    require_once dirname(__DIR__) . '/config/config.php';
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    $eventoId = filter_var(
        $_POST['evento_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $nombreRaw = $_POST['nombre'] ?? '';
    $correoRaw = $_POST['correo'] ?? '';
    $celularRaw = $_POST['celular'] ?? '';
    if (!is_int($eventoId) || !is_string($nombreRaw) ||
        !is_string($correoRaw) || !is_string($celularRaw)) {
        responderAsistencia(422, false, 'Revisa los datos del formulario.');
    }

    $nombre = trim(preg_replace('/\s+/u', ' ', $nombreRaw) ?? '');
    $correo = mb_strtolower(trim($correoRaw), 'UTF-8');
    $celular = preg_replace('/\D+/', '', $celularRaw);
    if (strlen($celular) === 12 && substr($celular, 0, 2) === '57') {
        $celular = substr($celular, 2);
    }

    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
        responderAsistencia(422, false, 'Indica tu nombre completo (3 a 120 caracteres).');
    }
    if (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responderAsistencia(422, false, 'Indica un correo electrónico válido.');
    }
    if (!preg_match('/\A3\d{9}\z/', $celular)) {
        responderAsistencia(422, false, 'Indica un celular colombiano válido de 10 dígitos.');
    }
    if (($_POST['autorizacion_datos'] ?? '') !== '1') {
        responderAsistencia(422, false, 'Debes autorizar el tratamiento de datos para este encuentro.');
    }

    $pdo = db();
    $consulta = $pdo->prepare(
        "SELECT id, titulo FROM eventos
         WHERE id = :id AND estado = 'publicado' AND fecha_fin >= :ahora LIMIT 1"
    );
    $consulta->execute([
        'id' => $eventoId,
        'ahora' => (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s'),
    ]);
    $evento = $consulta->fetch();
    if (!$evento) {
        responderAsistencia(422, false, 'Este encuentro ya no admite inscripciones.');
    }

    $insertar = $pdo->prepare(
        "INSERT INTO evento_inscripciones
         (evento_id, nombre, correo, celular, consentimiento_datos,
          consentimiento_fecha, estado)
         VALUES (:evento_id, :nombre, :correo, :celular, 1, :fecha_consentimiento, 'registrado')"
    );
    $insertar->execute([
        'evento_id' => $eventoId,
        'nombre' => $nombre,
        'correo' => $correo,
        'celular' => $celular,
        'fecha_consentimiento' => (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s'),
    ]);

    $_SESSION['ultima_asistencia'] = time();
    responderAsistencia(
        201,
        true,
        'Tu inscripción fue recibida correctamente. La organización confirmará los detalles del encuentro.',
        ['evento_titulo' => $evento['titulo']]
    );
} catch (PDOException $e) {
    error_log('[asistencia] PDO: ' . $e->getMessage());
    if (($e->errorInfo[1] ?? null) === 1062) {
        responderAsistencia(409, false, 'Este correo ya está inscrito en el encuentro seleccionado.');
    }
    responderAsistencia(500, false, 'No se pudo guardar tu inscripción. Intenta nuevamente.');
} catch (Throwable $e) {
    error_log('[asistencia] ' . $e->getMessage());
    responderAsistencia(500, false, 'Ocurrió un problema al procesar la inscripción.');
}
