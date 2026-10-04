<?php
/**
 * Front controller de la plataforma (versión /admin, estructura plana).
 * Toda petición entra aquí gracias al .htaccess de esta carpeta.
 */
require_once __DIR__ . '/bootstrap.php';

Core\Session::iniciar();
(new Core\App())->ejecutar();
