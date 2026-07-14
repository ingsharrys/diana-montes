<?php
namespace Core;

use PDO;
use PDOException;

/**
 * Conexión única (singleton) por petición usando PDO.
 * SIEMPRE consultas preparadas: nunca concatenar datos del usuario en SQL.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // preparadas nativas
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            } catch (PDOException $e) {
                if (APP_ENV === 'dev') {
                    die('Error de conexión a la base de datos: ' . $e->getMessage());
                }
                die('Servicio no disponible temporalmente.');
            }
        }
        return self::$pdo;
    }
}
