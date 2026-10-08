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

    /** Edita una zona. Devuelve false si ya existe otra con el mismo nombre y clasificación. */
    public function editarZona(int $id, string $nombre, string $tipo, string $clase): bool
    {
        try {
            if (esquema_tiene($this->db, 'zonas', 'clase')) {
                if ($this->consultarUno('SELECT id FROM zonas WHERE nombre = :n AND clase = :c AND id <> :id', ['n' => $nombre, 'c' => $clase, 'id' => $id])) return false;
                $this->ejecutar('UPDATE zonas SET nombre = :n, tipo = :t, clase = :c WHERE id = :id', ['n' => $nombre, 't' => $tipo, 'c' => $clase, 'id' => $id]);
                return true;
            }
            $this->ejecutar('UPDATE zonas SET nombre = :n, tipo = :t WHERE id = :id', ['n' => $nombre, 't' => $tipo, 'id' => $id]);
            return true;
        } catch (\PDOException $e) { return false; }
    }

    /** Crea (id 0) o edita un puesto. Mesas y potencial solo si ya existen las columnas. */
    public function guardarPuesto(int $id, string $nombre, ?string $direccion, ?int $zonaId, ?int $mesas, ?int $potencial): bool
    {
        $extra = esquema_tiene($this->db, 'puestos_votacion', 'mesas');
        $p = ['n' => $nombre, 'd' => $direccion, 'z' => $zonaId];
        if ($extra) $p += ['m' => $mesas, 'po' => $potencial];
        try {
            if ($this->consultarUno('SELECT id FROM puestos_votacion WHERE nombre = :n AND id <> :id', ['n' => $nombre, 'id' => $id])) return false;
            if ($id) {
                $this->ejecutar('UPDATE puestos_votacion SET nombre = :n, direccion = :d, zona_id = :z' . ($extra ? ', mesas = :m, potencial = :po' : '') . ' WHERE id = :id', $p + ['id' => $id]);
            } else {
                $this->ejecutar($extra
                    ? 'INSERT INTO puestos_votacion (nombre, direccion, zona_id, mesas, potencial) VALUES (:n, :d, :z, :m, :po)'
                    : 'INSERT INTO puestos_votacion (nombre, direccion, zona_id) VALUES (:n, :d, :z)', $p);
            }
            return true;
        } catch (\PDOException $e) { return false; }
    }

    public function editarProfesion(int $id, string $nombre, ?string $dia): bool
    {
        try {
            if ($this->consultarUno('SELECT id FROM profesiones WHERE nombre = :n AND id <> :id', ['n' => $nombre, 'id' => $id])) return false;
            $this->ejecutar('UPDATE profesiones SET nombre = :n, dia_celebracion = :d WHERE id = :id', ['n' => $nombre, 'd' => $dia, 'id' => $id]);
            return true;
        } catch (\PDOException $e) { return false; }
    }

    /** Puestos con todos sus datos (para editarlos desde la app). */
    public function puestosCompletos(): array
    {
        $extra = esquema_tiene($this->db, 'puestos_votacion', 'mesas') ? 'p.mesas, p.potencial' : 'NULL AS mesas, NULL AS potencial';
        return $this->consultar(
            "SELECT p.id, p.nombre, p.direccion, p.zona_id, z.nombre AS zona, $extra,
                    (SELECT COUNT(*) FROM simpatizantes s WHERE s.puesto_id = p.id) AS uso
             FROM puestos_votacion p LEFT JOIN zonas z ON z.id = p.zona_id
             ORDER BY p.nombre");
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
