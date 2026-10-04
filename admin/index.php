<?php
/**
 * Front controller de la plataforma (versión /admin, estructura plana).
 * Toda petición entra aquí gracias al .htaccess de esta carpeta.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/graficas.php';
require_once __DIR__ . '/../inc/promotores.php'; // red de promotores (compartido con la landing)

spl_autoload_register(function ($clase) {
    $ruta = __DIR__ . '/app/' . str_replace('\\', '/', $clase) . '.php';
    if (is_file($ruta)) require_once $ruta;
});

Core\Session::iniciar();
(new Core\App())->ejecutar();
