<?php
namespace Core;

/**
 * Estado de autenticación y autorización por roles.
 * Roles: direccion, coordinador, lider, digitador
 */
class Auth
{
    public static function ingresar(array $usuario): void
    {
        Session::regenerar(); // evita fijación de sesión
        Session::set('usuario', [
            'id'     => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'rol'    => $usuario['rol'],
        ]);
    }

    public static function salir(): void { Session::destruir(); }

    public static function usuario(): ?array { return Session::get('usuario'); }

    public static function id(): ?int { return self::usuario()['id'] ?? null; }

    public static function verificado(): bool { return self::usuario() !== null; }

    public static function tieneRol(string ...$roles): bool
    {
        $u = self::usuario();
        return $u !== null && in_array($u['rol'], $roles, true);
    }

    /** Corta la petición si no hay sesión iniciada. */
    public static function requerir(): void
    {
        if (!self::verificado()) {
            header('Location: ' . \url('auth/login'));
            exit;
        }
    }

    /** Corta la petición si el rol no está autorizado. */
    public static function requerirRol(string ...$roles): void
    {
        self::requerir();
        if (!self::tieneRol(...$roles)) {
            http_response_code(403);
            die('403 — No tienes permisos para esta sección. El intento quedó registrado.');
        }
    }
}
