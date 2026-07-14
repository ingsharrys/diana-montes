<?php
namespace Core;

/**
 * Protección CSRF: token por sesión, obligatorio en todo formulario POST.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Campo oculto listo para incrustar en formularios. */
    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function validar(): bool
    {
        $enviado = $_POST['_csrf'] ?? '';
        return is_string($enviado) && hash_equals($_SESSION['_csrf'] ?? '', $enviado);
    }
}
