<?php
/**
 * API de la app móvil (Ionic). JSON, sesiones con token Bearer.
 *
 *   https://<dominio>/admin/api.php?r=<ruta>
 *
 * Rutas públicas:   POST auth/otp/solicitar · POST auth/otp/verificar · POST auth/equipo
 * Con sesión:       GET yo · POST auth/salir
 * Simpatizante:     GET mi/inicio · GET mi/red · GET mi/tareas
 *                   POST mi/tareas/responder · POST mi/tareas/reportar · POST mi/tareas/apuntarme
 * Equipo:           GET equipo/resumen · GET equipo/simpatizantes · GET equipo/simpatizante
 *                   GET equipo/catalogos · POST equipo/simpatizantes · POST equipo/verificar
 *                   GET equipo/tareas · GET equipo/tarea · POST equipo/tareas/validar · POST equipo/tareas/validar-todas
 */
require_once __DIR__ . '/bootstrap.php';
define('PORTAL', true);
require_once __DIR__ . '/../mi/portal.php';   // consultas del panel del simpatizante (mismas que la web)

use Api\Nucleo;
use Api\AuthApi;
use Api\MiApi;
use Api\EquipoApi;

Nucleo::cors();

set_exception_handler(function (Throwable $e) {
    error_log('[api] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    Nucleo::error('Tuvimos un problema en el servidor. Intenta de nuevo en un momento.', 500);
});

$db = Core\Database::conexion();
if (!esquema_tiene($db, 'api_tokens') || !esquema_tiene($db, 'otp_codigos') || !red_portal_listo($db)) {
    Nucleo::error('La app aún no está activa: la dirección debe pulsar "Actualizar plataforma" en el panel web.', 503);
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = trim((string)($_GET['r'] ?? ''), '/');

// [método ruta] => [manejador, quién puede usarla: null (público) | 'sesion' | 'simpatizante' | 'usuario']
$rutas = [
    'POST auth/otp/solicitar'         => [[AuthApi::class, 'otpSolicitar'], null],
    'POST auth/otp/verificar'         => [[AuthApi::class, 'otpVerificar'], null],
    'POST auth/equipo'                => [[AuthApi::class, 'equipo'], null],
    'POST auth/salir'                 => [[AuthApi::class, 'salir'], 'sesion'],
    'GET yo'                          => [[AuthApi::class, 'yo'], 'sesion'],
    'GET mi/inicio'                   => [[MiApi::class, 'inicio'], 'simpatizante'],
    'GET mi/red'                      => [[MiApi::class, 'red'], 'simpatizante'],
    'GET mi/tareas'                   => [[MiApi::class, 'tareas'], 'simpatizante'],
    'POST mi/tareas/responder'        => [[MiApi::class, 'responder'], 'simpatizante'],
    'POST mi/tareas/reportar'         => [[MiApi::class, 'reportar'], 'simpatizante'],
    'POST mi/tareas/apuntarme'        => [[MiApi::class, 'apuntarme'], 'simpatizante'],
    'GET equipo/resumen'              => [[EquipoApi::class, 'resumen'], 'usuario'],
    'GET equipo/simpatizantes'        => [[EquipoApi::class, 'simpatizantes'], 'usuario'],
    'GET equipo/simpatizante'         => [[EquipoApi::class, 'simpatizante'], 'usuario'],
    'GET equipo/catalogos'            => [[EquipoApi::class, 'catalogos'], 'usuario'],
    'POST equipo/simpatizantes'       => [[EquipoApi::class, 'registrar'], 'usuario'],
    'POST equipo/verificar'           => [[EquipoApi::class, 'verificar'], 'usuario'],
    'GET equipo/tareas'               => [[EquipoApi::class, 'tareas'], 'usuario'],
    'GET equipo/tarea'                => [[EquipoApi::class, 'tarea'], 'usuario'],
    'POST equipo/tareas/validar'      => [[EquipoApi::class, 'validar'], 'usuario'],
    'POST equipo/tareas/validar-todas' => [[EquipoApi::class, 'validarTodas'], 'usuario'],
];

$clave = "$metodo $ruta";
if (!isset($rutas[$clave])) {
    $existe = array_filter(array_keys($rutas), fn($k) => explode(' ', $k, 2)[1] === $ruta);
    Nucleo::error($existe ? 'Método no permitido.' : 'Ruta no encontrada.', $existe ? 405 : 404);
}
[$manejador, $acceso] = $rutas[$clave];
$entrada = Nucleo::entrada();

if ($acceso === null) {
    $manejador($db, $entrada);
}
$sesion = Nucleo::sesion($db);
if (!$sesion) Nucleo::error('Tu sesión venció. Vuelve a ingresar.', 401);
if ($acceso !== 'sesion' && $sesion['tipo'] !== $acceso) Nucleo::error('Esta sección no está disponible para tu tipo de cuenta.', 403);
$manejador($db, $entrada, $sesion);
