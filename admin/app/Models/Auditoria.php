<?php
namespace Models;

use Core\Model;
use Core\Auth;

class Auditoria extends Model
{
    /** Registra una acción. Llamar en cada operación sensible. */
    public static function registrar(string $accion, string $detalle = ''): void
    {
        (new self())->ejecutar(
            'INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (:u, :a, :d, :ip)',
            [
                'u'  => Auth::id(),
                'a'  => $accion,
                'd'  => $detalle,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        );
    }

    public function recientes(int $limite = 30): array
    {
        return $this->consultar(
            'SELECT a.*, u.nombre AS usuario
             FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.id DESC LIMIT ' . (int)$limite
        );
    }
}
