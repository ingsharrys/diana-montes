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

    /** Quién invitó a este simpatizante (id) o null. */
    public function referidoPor(int $id): ?int
    {
        if (!esquema_tiene($this->db, 'simpatizantes', 'referido_por')) return null;
        $f = $this->consultarUno('SELECT referido_por FROM simpatizantes WHERE id = :id', ['id' => $id]);
        return $f && $f['referido_por'] ? (int)$f['referido_por'] : null;
    }

    /** Datos mínimos para generar una clave temporal (respeta la red del líder). */
    public function paraClave(int $id, ?int $soloLiderId): ?array
    {
        $sql = 'SELECT id, nombre, telefono, documento, lider_id FROM simpatizantes WHERE id = :id';
        $p = ['id' => $id];
        if ($soloLiderId !== null) { $sql .= ' AND lider_id = :l'; $p['l'] = $soloLiderId; }
        return $this->consultarUno($sql, $p);
    }

    public function existeTelefono(string $telefono): bool
    {
        return $this->consultarUno(
            'SELECT id FROM simpatizantes WHERE telefono = :t LIMIT 1', ['t' => $telefono]
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
              . (red_portal_listo($this->db) ? ', s.puntos, (s.clave_hash IS NOT NULL) AS tiene_clave, s.ultimo_acceso' : '')
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

    /** ¿La base ya tiene la columna de género? (se crea con "Actualizar plataforma") */
    public function pideGenero(): bool
    {
        return esquema_tiene($this->db, 'simpatizantes', 'genero');
    }

    /**
     * Mismo guardado que la landing (inc/esquema.php): también los registros
     * hechos por el equipo quedan con su enlace de promotor y su nivel en la red.
     */
    public function crear(array $d): int
    {
        return simpatizante_insertar($this->db, $d)['id'];
    }

    /* ============ Duplicados: documento o celular repetidos ============ */

    /** Registros cuyo documento o celular se repite, agrupados por el dato repetido. */
    public function duplicados(): array
    {
        $grupos = [];
        foreach (['documento' => 'Documento', 'telefono' => 'Celular'] as $col => $etiqueta) {
            $filas = $this->consultar(
                "SELECT s.id, s.nombre, s.documento, s.telefono, s.created_at, s.$col AS valor,
                        z.nombre AS zona, u.nombre AS lider,
                        (SELECT COUNT(*) FROM simpatizantes r WHERE r.referido_por = s.id) AS invitados
                 FROM simpatizantes s
                 JOIN (SELECT $col AS v FROM simpatizantes GROUP BY $col HAVING COUNT(*) > 1) d ON d.v = s.$col
                 LEFT JOIN zonas z ON z.id = s.zona_id
                 LEFT JOIN usuarios u ON u.id = s.lider_id
                 ORDER BY s.$col, s.id"
            );
            foreach ($filas as $f) $grupos[$etiqueta . ' ' . $f['valor']][] = $f;
        }
        return $grupos;
    }

    /**
     * Elimina un registro sobrante. Sus invitados pasan a quien lo invitó a él
     * y su historial de WhatsApp se borra. Devuelve el nombre eliminado.
     */
    public function eliminar(int $id): ?string
    {
        $s = $this->consultarUno('SELECT id, nombre, referido_por FROM simpatizantes WHERE id = :id', ['id' => $id]);
        if (!$s) return null;
        $this->db->beginTransaction();
        try {
            $this->ejecutar('UPDATE simpatizantes SET referido_por = :padre WHERE referido_por = :id',
                            ['padre' => $s['referido_por'], 'id' => $id]);
            if (esquema_tiene($this->db, 'wa_mensajes'))  $this->ejecutar('DELETE FROM wa_mensajes WHERE simpatizante_id = :id', ['id' => $id]);
            if (esquema_tiene($this->db, 'wa_entrantes')) $this->ejecutar('UPDATE wa_entrantes SET simpatizante_id = NULL WHERE simpatizante_id = :id', ['id' => $id]);
            if (esquema_tiene($this->db, 'tarea_asignaciones')) $this->ejecutar('DELETE FROM tarea_asignaciones WHERE simpatizante_id = :id', ['id' => $id]);
            $this->ejecutar('DELETE FROM simpatizantes WHERE id = :id', ['id' => $id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        if ($s['referido_por']) red_actualizar_puntos($this->db, (int)$s['referido_por']);
        return $s['nombre'];
    }

    /* ============ Base de votos: completar y verificar ============ */

    public function porIdBasico(int $id): ?array
    {
        return $this->consultarUno('SELECT id, nombre, lider_id, puesto_id, mesa, nivel FROM simpatizantes WHERE id = :id', ['id' => $id]);
    }

    /** ¿Ya existe la verificación por llamada? (se crea con "Actualizar plataforma") */
    public function conVerificacion(): bool
    {
        return esquema_tiene($this->db, 'simpatizantes', 'verificado_at');
    }

    /**
     * Condiciones de cada filtro de la cola. "Pendiente" = le falta puesto,
     * mesa o la verificación por llamada.
     */
    private function condicionEstado(string $estado): string
    {
        $sinPuesto = "(s.puesto_id IS NULL OR s.mesa IS NULL OR s.mesa = '')";
        if (!$this->conVerificacion()) {
            return in_array($estado, ['pendientes', 'sin_puesto'], true) ? $sinPuesto : '1=1';
        }
        return match ($estado) {
            'sin_puesto'    => $sinPuesto,
            'sin_verificar' => 's.verificado_at IS NULL',
            'todos'         => '1=1',
            default         => "($sinPuesto OR s.verificado_at IS NULL)",
        };
    }

    /** Filtros comunes de la cola: búsqueda, zona y alcance del líder. */
    private function filtrosCola(array $f, ?int $soloLiderId): array
    {
        $sql = ''; $p = [];
        if (($f['q'] ?? '') !== '') {
            $sql .= ' AND (s.nombre LIKE :q1 OR s.documento LIKE :q2)';
            $p['q1'] = $p['q2'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['zona'])) { $sql .= ' AND s.zona_id = :zona'; $p['zona'] = (int)$f['zona']; }
        if ($soloLiderId !== null) { $sql .= ' AND s.lider_id = :lider'; $p['lider'] = $soloLiderId; }
        return [$sql, $p];
    }

    /** Cuántos hay en cada filtro (para las pestañas de la cola). */
    public function contarCola(array $f, ?int $soloLiderId): array
    {
        [$w, $p] = $this->filtrosCola($f, $soloLiderId);
        $r = [];
        foreach (['pendientes', 'sin_puesto', 'sin_verificar', 'todos'] as $estado) {
            $r[$estado] = (int)($this->consultarUno(
                'SELECT COUNT(*) c FROM simpatizantes s WHERE ' . $this->condicionEstado($estado) . $w, $p
            )['c'] ?? 0);
        }
        return $r;
    }

    public function colaVerificacion(array $f, ?int $soloLiderId, int $limite, int $desde): array
    {
        [$w, $p] = $this->filtrosCola($f, $soloLiderId);
        $verif = $this->conVerificacion()
            ? 's.verificado_at, v.nombre AS verificado_por_nombre'
            : 'NULL AS verificado_at, NULL AS verificado_por_nombre';
        $joinVerif = $this->conVerificacion() ? 'LEFT JOIN usuarios v ON v.id = s.verificado_por' : '';
        return $this->consultar(
            "SELECT s.id, s.nombre, s.documento, s.telefono, s.nivel, s.puesto_id, s.mesa, s.lider_id,
                    z.nombre AS zona, u.nombre AS lider, $verif
             FROM simpatizantes s
             LEFT JOIN zonas z  ON z.id = s.zona_id
             JOIN usuarios u    ON u.id = s.lider_id
             $joinVerif
             WHERE " . $this->condicionEstado($f['estado'] ?? 'pendientes') . "$w
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT " . (int)$limite . ' OFFSET ' . (int)$desde, $p
        );
    }

    /** Guarda puesto, mesa y compromiso; si $verificado, deja constancia de quién y cuándo llamó. */
    public function actualizarBase(int $id, ?int $puestoId, ?string $mesa, string $nivel, bool $verificado, int $usuarioId): void
    {
        $sql = 'UPDATE simpatizantes SET puesto_id = :p, mesa = :m, nivel = :n';
        $params = ['p' => $puestoId, 'm' => $mesa, 'n' => $nivel, 'id' => $id];
        if ($verificado && $this->conVerificacion()) {
            $sql .= ', verificado_at = NOW(), verificado_por = :u';
            $params['u'] = $usuarioId;
        }
        $this->ejecutar($sql . ' WHERE id = :id', $params);
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
        $pts = red_portal_listo($this->db) ? 'MAX(p.puntos)' : 'NULL';
        $sql = 'SELECT p.id, p.nombre, z.nombre AS zona, u.nombre AS lider, COUNT(r.id) AS invitados, ' . $pts . ' AS puntos
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
