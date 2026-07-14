<?php
namespace Models;

use Core\Model;

class Simpatizante extends Model
{
    public function existeDocumento(string $documento): bool
    {
        return $this->consultarUno(
            'SELECT id FROM simpatizantes WHERE documento = :d LIMIT 1', ['d' => $documento]
        ) !== null;
    }

    /**
     * Listado con filtros. El líder solo ve SU red (seguridad por roles).
     */
    public function listar(string $busqueda = '', int $profesionId = 0, ?int $soloLiderId = null, int $limite = 50): array
    {
        $sql = 'SELECT s.id, s.nombre, s.documento, s.telefono, s.nivel, s.created_at,
                       z.nombre AS zona, p.nombre AS profesion, u.nombre AS lider
                FROM simpatizantes s
                JOIN zonas z        ON z.id = s.zona_id
                JOIN profesiones p  ON p.id = s.profesion_id
                JOIN usuarios u     ON u.id = s.lider_id
                WHERE 1=1';
        $params = [];

        if ($busqueda !== '') {
            $sql .= ' AND (s.nombre LIKE :q OR s.documento LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }
        if ($profesionId > 0) {
            $sql .= ' AND s.profesion_id = :prof';
            $params['prof'] = $profesionId;
        }
        if ($soloLiderId !== null) {
            $sql .= ' AND s.lider_id = :lider';
            $params['lider'] = $soloLiderId;
        }
        $sql .= ' ORDER BY s.created_at DESC LIMIT ' . (int)$limite;

        return $this->consultar($sql, $params);
    }

    public function crear(array $d): int
    {
        $this->ejecutar(
            'INSERT INTO simpatizantes
              (nombre, documento, telefono, fecha_nacimiento, zona_id, puesto_id, mesa,
               profesion_id, nivel, lider_id, consentimiento_datos, consentimiento_fecha, created_by)
             VALUES
              (:nombre, :documento, :telefono, :fecha_nacimiento, :zona_id, :puesto_id, :mesa,
               :profesion_id, :nivel, :lider_id, 1, NOW(), :created_by)',
            $d
        );
        return $this->ultimoId();
    }

    public function contar(): int
    {
        return (int)($this->consultarUno('SELECT COUNT(*) c FROM simpatizantes')['c'] ?? 0);
    }

    public function contarPorZona(): array
    {
        return $this->consultar(
            'SELECT z.nombre, z.tipo, COUNT(s.id) total
             FROM zonas z LEFT JOIN simpatizantes s ON s.zona_id = z.id
             GROUP BY z.id ORDER BY total DESC'
        );
    }

    public function registrosUltimaSemana(): int
    {
        return (int)($this->consultarUno(
            'SELECT COUNT(*) c FROM simpatizantes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )['c'] ?? 0);
    }
}
