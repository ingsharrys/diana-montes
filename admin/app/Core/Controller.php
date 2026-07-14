<?php
namespace Core;

/**
 * Controlador base: render de vistas dentro del layout y helpers comunes.
 */
abstract class Controller
{
    /** Renderiza app/Views/{vista}.php dentro del layout principal. */
    protected function vista(string $vista, array $datos = [], string $layout = 'main'): void
    {
        extract($datos, EXTR_SKIP);
        $rutaVista = __DIR__ . '/../Views/' . $vista . '.php';
        if (!is_file($rutaVista)) { die('Vista no encontrada: ' . e($vista)); }

        ob_start();
        require $rutaVista;
        $contenido = ob_get_clean();

        require __DIR__ . '/../Views/layouts/' . $layout . '.php';
    }

    protected function redirigir(string $ruta): void
    {
        header('Location: ' . \url($ruta));
        exit;
    }

    protected function esPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Valida CSRF en POST; si falla, corta la petición. */
    protected function validarCsrf(): void
    {
        if (!Csrf::validar()) {
            http_response_code(419);
            die('Token de seguridad inválido o vencido. Regresa e intenta de nuevo.');
        }
    }
}
