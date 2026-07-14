<?php
namespace Core;

/**
 * Router minimalista: /controlador/metodo/param1/param2
 * Ej: /simpatizantes/crear  ->  Controllers\SimpatizantesController::crear()
 */
class App
{
    private string $controladorDefecto = 'dashboard';
    private string $metodoDefecto = 'index';

    public function ejecutar(): void
    {
        $url = $this->parsearUrl();

        $nombre = ucfirst(strtolower($url[0] ?? $this->controladorDefecto));
        $clase  = 'Controllers\\' . $nombre . 'Controller';

        if (!class_exists($clase)) {
            $clase = 'Controllers\\' . ucfirst($this->controladorDefecto) . 'Controller';
            $url = [];
        }

        $controlador = new $clase();

        $metodo = $url[1] ?? $this->metodoDefecto;
        if (!method_exists($controlador, $metodo)) {
            $metodo = $this->metodoDefecto;
        }

        $params = array_slice($url, 2);
        call_user_func_array([$controlador, $metodo], $params);
    }

    private function parsearUrl(): array
    {
        $url = trim($_GET['url'] ?? '', '/');
        if ($url === '') return [];
        // Solo caracteres seguros en la ruta
        $url = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $url);
        return explode('/', filter_var($url, FILTER_SANITIZE_URL));
    }
}
