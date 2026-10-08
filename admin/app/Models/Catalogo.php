<?php
namespace Models;

use Core\Model;

/** Catálogos simples para poblar formularios. */
class Catalogo extends Model
{
    public function zonas(): array
    {
        return zonas_listar($this->db);
    }

    public function profesiones(): array
    {
        return $this->consultar('SELECT id, nombre FROM profesiones ORDER BY nombre');
    }

    public function puestos(): array
    {
        return $this->consultar('SELECT id, nombre FROM puestos_votacion ORDER BY nombre');
    }

    /** Puestos con su zona y número de mesas (si ya se registró), para los selectores. */
    public function puestosDetalle(): array
    {
        $mesas = esquema_tiene($this->db, 'puestos_votacion', 'mesas') ? 'p.mesas' : 'NULL';
        return $this->consultar(
            "SELECT p.id, p.nombre, z.nombre AS zona, $mesas AS mesas
             FROM puestos_votacion p LEFT JOIN zonas z ON z.id = p.zona_id
             ORDER BY p.nombre"
        );
    }

    public function lideres(): array
    {
        return $this->consultar(
            "SELECT id, nombre FROM usuarios WHERE rol IN ('lider','coordinador') AND activo = 1 ORDER BY nombre"
        );
    }

    public function coordinadores(): array
    {
        return $this->consultar(
            "SELECT id, nombre FROM usuarios WHERE rol IN ('direccion','coordinador') AND activo = 1 ORDER BY nombre"
        );
    }

    /* ============ Administración de catálogos (módulo Catálogos) ============ */

    public function zonasConUso(): array
    {
        $uso = [];
        foreach ($this->consultar('SELECT zona_id, COUNT(*) AS c FROM simpatizantes GROUP BY zona_id') as $f) $uso[(int)$f['zona_id']] = (int)$f['c'];
        return array_map(fn($z) => $z + ['uso' => $uso[(int)$z['id']] ?? 0], zonas_listar($this->db));
    }

    public function puestosConUso(): array
    {
        return $this->consultar(
            "SELECT p.id, p.nombre, p.direccion, z.nombre AS zona,
                    (SELECT COUNT(*) FROM simpatizantes s WHERE s.puesto_id = p.id) AS uso
             FROM puestos_votacion p LEFT JOIN zonas z ON z.id = p.zona_id
             ORDER BY p.nombre");
    }

    public function profesionesConUso(): array
    {
        return $this->consultar(
            "SELECT p.id, p.nombre, p.dia_celebracion,
                    (SELECT COUNT(*) FROM simpatizantes s WHERE s.profesion_id = p.id) AS uso
             FROM profesiones p ORDER BY p.nombre");
    }

    /** Crea una zona. Lo único es nombre + clasificación (hay barrios y veredas con el mismo nombre). */
    public function crearZona(string $nombre, string $tipo, string $clase = 'Barrio'): bool
    {
        try {
            if (esquema_tiene($this->db, 'zonas', 'clase')) {
                $existe = $this->consultarUno('SELECT id FROM zonas WHERE nombre = :n AND clase = :c', ['n' => $nombre, 'c' => $clase]);
                if ($existe) return false;
                $this->ejecutar('INSERT INTO zonas (nombre, tipo, clase) VALUES (:n, :t, :c)', ['n' => $nombre, 't' => $tipo, 'c' => $clase]);
                return true;
            }
            $this->ejecutar('INSERT INTO zonas (nombre, tipo) VALUES (:n, :t)', ['n' => $nombre, 't' => $tipo]);
            return true;
        } catch (\PDOException $e) { return false; } // duplicado
    }

    public function crearPuesto(string $nombre, ?string $direccion, ?int $zonaId): bool
    {
        try {
            $this->ejecutar('INSERT INTO puestos_votacion (nombre, direccion, zona_id) VALUES (:n, :d, :z)',
                ['n' => $nombre, 'd' => $direccion, 'z' => $zonaId]);
            return true;
        } catch (\PDOException $e) { return false; }
    }

    public function crearProfesion(string $nombre, ?string $dia): bool
    {
        try {
            $this->ejecutar('INSERT INTO profesiones (nombre, dia_celebracion) VALUES (:n, :d)',
                ['n' => $nombre, 'd' => $dia]);
            return true;
        } catch (\PDOException $e) { return false; }
    }

    /** Elimina un ítem SOLO si ningún registro lo usa (las FK protegen igual). */
    public function eliminar(string $tabla, int $id): bool
    {
        $permitidas = ['zonas', 'puestos_votacion', 'profesiones'];
        if (!in_array($tabla, $permitidas, true)) return false;
        try {
            return $this->ejecutar("DELETE FROM {$tabla} WHERE id = :id", ['id' => $id]) > 0;
        } catch (\PDOException $e) { return false; } // FK: está en uso
    }
}
