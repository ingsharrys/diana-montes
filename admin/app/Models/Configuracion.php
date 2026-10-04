<?php
namespace Models;

use Core\Model;

/** Ajustes de la campaña (clave/valor), p. ej. la meta de inscripción. */
class Configuracion extends Model
{
    /** ¿Ya existe la tabla? (se crea con "Actualizar plataforma") */
    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'configuracion');
    }

    public function obtener(string $clave, ?string $defecto = null): ?string
    {
        if (!$this->disponible()) return $defecto;
        return $this->consultarUno('SELECT valor FROM configuracion WHERE clave = :c', ['c' => $clave])['valor'] ?? $defecto;
    }

    public function guardar(string $clave, string $valor): void
    {
        $this->ejecutar(
            'INSERT INTO configuracion (clave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = :v2',
            ['c' => $clave, 'v' => $valor, 'v2' => $valor]
        );
    }
}
