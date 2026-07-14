<?php
namespace Core;

/**
 * Manejo centralizado y endurecido de la sesión.
 */
class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,                       // JS no puede leer la cookie
            'samesite' => 'Lax',                      // mitiga CSRF
            'secure'   => APP_ENV === 'prod',         // solo HTTPS en producción
        ]);
        session_start();

        // Expiración por inactividad
        $ultimo = $_SESSION['_ultimo_uso'] ?? time();
        if (time() - $ultimo > SESSION_LIFETIME) {
            self::destruir();
            session_start();
        }
        $_SESSION['_ultimo_uso'] = time();
    }

    public static function set(string $clave, $valor): void { $_SESSION[$clave] = $valor; }
    public static function get(string $clave, $defecto = null) { return $_SESSION[$clave] ?? $defecto; }

    /** Mensaje flash: se muestra una sola vez (éxito/error entre redirecciones). */
    public static function flash(string $clave, ?string $valor = null): ?string
    {
        if ($valor !== null) { $_SESSION['_flash'][$clave] = $valor; return null; }
        $msg = $_SESSION['_flash'][$clave] ?? null;
        unset($_SESSION['_flash'][$clave]);
        return $msg;
    }

    public static function regenerar(): void { session_regenerate_id(true); }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
