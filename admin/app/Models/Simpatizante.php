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

    /** ¿Ya se activó la red de promotores y el mapa (migración ejecutada)? */
    public function redPromotoresActiva(): bool
    {
        return promotor_esquema_listo($this->db);
    }

    /**
     * Listado con filtros. El líder solo ve SU red (seguridad por roles).
     * Con la red de promotores activa incluye su llave de panel y sus invitados.
     */
    public function listar(string $busqueda = '', int $profesionId = 0, ?int $soloLiderId = null, int $limite = 50): array
    {
        $extra = $this->redPromotoresActiva()
            ? ', s.token_panel, (SELECT COUNT(*) FROM simpatizantes r WHERE r.referido_por = s.id) AS invitados'
            : '';
        $sql = 'SELECT s.id, s.nombre, s.documento, s.telefono, s.nivel, s.created_at,
                       z.nombre AS zona, p.nombre AS profesion, u.nombre AS lider' . $extra . '
                FROM simpatizantes s
                JOIN zonas z        ON z.id = s.zona_id
                JOIN profesiones p  ON p.id = s.profesion_id
                JOIN usuarios u     ON u.id = s.lider_id
                WHERE 1=1';
        $params = [];

        if ($busqueda !== '') {
            // Con preparadas nativas un mismo marcador no puede repetirse: :q1 y :q2
            $sql .= ' AND (s.nombre LIKE :q1 OR s.documento LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $busqueda . '%';
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
        $id = $this->ultimoId();

        // También los registros hechos por el equipo quedan listos para invitar
        if ($this->redPromotoresActiva()) {
            $this->ejecutar(
                'UPDATE simpatizantes SET codigo_promotor = :c, token_panel = :t WHERE id = :id',
                ['c' => promotor_nuevo_codigo($this->db), 't' => promotor_nuevo_token(), 'id' => $id]
            );
        }
        return $id;
    }

    /* ============ Red de promotores y mapa (requieren la migración) ============ */

    /** Simpatizantes que ya invitaron al menos a una persona. */
    public function contarPromotoresActivos(?int $soloLiderId = null): int
    {
        $sql = 'SELECT COUNT(DISTINCT r.referido_por) c
                FROM simpatizantes r JOIN simpatizantes p ON p.id = r.referido_por';
        $params = [];
        if ($soloLiderId !== null) { $sql .= ' WHERE p.lider_id = :l'; $params['l'] = $soloLiderId; }
        return (int)($this->consultarUno($sql, $params)['c'] ?? 0);
    }

    public function contarConUbicacion(?int $soloLiderId = null): int
    {
        $sql = 'SELECT COUNT(*) c FROM simpatizantes WHERE lat IS NOT NULL';
        $params = [];
        if ($soloLiderId !== null) { $sql .= ' AND lider_id = :l'; $params['l'] = $soloLiderId; }
        return (int)($this->consultarUno($sql, $params)['c'] ?? 0);
    }

    /** Top de promotores ciudadanos por invitados directos. */
    public function rankingPromotores(int $limite = 10, ?int $soloLiderId = null): array
    {
        $sql = 'SELECT p.id, p.nombre, z.nombre AS zona, u.nombre AS lider, COUNT(r.id) AS invitados
                FROM simpatizantes r
                JOIN simpatizantes p ON p.id = r.referido_por
                JOIN usuarios u      ON u.id = p.lider_id
                LEFT JOIN zonas z    ON z.id = p.zona_id';
        $params = [];
        if ($soloLiderId !== null) { $sql .= ' WHERE p.lider_id = :l'; $params['l'] = $soloLiderId; }
        $sql .= ' GROUP BY p.id, p.nombre, z.nombre, u.nombre
                  ORDER BY invitados DESC, MIN(r.created_at) ASC LIMIT ' . (int)$limite;
        return $this->consultar($sql, $params);
    }

    /** Simpatizantes con ubicación aproximada, para el mapa. */
    public function puntosMapa(?int $soloLiderId = null): array
    {
        $sql = 'SELECT s.nombre, s.lat, s.lng, s.nivel, z.nombre AS zona, u.nombre AS lider,
                       (SELECT COUNT(*) FROM simpatizantes r WHERE r.referido_por = s.id) AS invitados
                FROM simpatizantes s
                JOIN usuarios u   ON u.id = s.lider_id
                LEFT JOIN zonas z ON z.id = s.zona_id
                WHERE s.lat IS NOT NULL AND s.lng IS NOT NULL';
        $params = [];
        if ($soloLiderId !== null) { $sql .= ' AND s.lider_id = :l'; $params['l'] = $soloLiderId; }
        $sql .= ' ORDER BY s.created_at DESC LIMIT 5000';
        return $this->consultar($sql, $params);
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
             GROUP BY z.id, z.nombre, z.tipo ORDER BY total DESC'
        );
    }

    public function registrosUltimaSemana(): int
    {
        return (int)($this->consultarUno(
            'SELECT COUNT(*) c FROM simpatizantes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )['c'] ?? 0);
    }
}
