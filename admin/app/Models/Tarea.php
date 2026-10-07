<?php
namespace Models;

use Core\Model;

/**
 * Tareas y convocatorias de la red: las crea el equipo (o un Súper
 * Promotor/Embajador desde su panel) y las cumplen los simpatizantes.
 * El líder solo ve las tareas de su red (tareas.lider_id).
 */
class Tarea extends Model
{
    public function disponible(): bool
    {
        return red_tareas_listas($this->db) && red_portal_listo($this->db);
    }

    /** Listado con el avance de cada tarea. $estado: 'abierta' | 'cerrada' | 'todas'. */
    public function listar(?int $liderId, string $estado = 'abierta'): array
    {
        $w = [];
        $p = [];
        if ($liderId !== null) { $w[] = 't.lider_id = :lider'; $p['lider'] = $liderId; }
        if ($estado === 'abierta') $w[] = "t.estado = 'abierta'";
        elseif ($estado === 'cerrada') $w[] = "t.estado <> 'abierta'";
        return $this->consultar(
            "SELECT t.*, COALESCE(u.nombre, s.nombre) AS creador, (s.id IS NOT NULL) AS creada_en_red, z.nombre AS zona,
                    COALESCE(c.total, 0) AS total, COALESCE(c.aceptadas, 0) AS aceptadas,
                    COALESCE(c.por_validar, 0) AS por_validar, COALESCE(c.validadas, 0) AS validadas,
                    COALESCE(c.rechazadas, 0) AS rechazadas, COALESCE(c.resultado, 0) AS resultado
             FROM tareas t
             LEFT JOIN usuarios u ON u.id = t.creada_por_usuario
             LEFT JOIN simpatizantes s ON s.id = t.creada_por_simpatizante
             LEFT JOIN zonas z ON z.id = t.zona_id
             LEFT JOIN (SELECT tarea_id, COUNT(*) AS total,
                               SUM(estado = 'aceptada') AS aceptadas,
                               SUM(estado = 'hecha') AS por_validar,
                               SUM(estado = 'validada') AS validadas,
                               SUM(estado = 'rechazada') AS rechazadas,
                               SUM(CASE WHEN rol = 'responsable' AND estado IN ('hecha','validada') THEN COALESCE(resultado, 0) ELSE 0 END) AS resultado
                        FROM tarea_asignaciones GROUP BY tarea_id) c ON c.tarea_id = t.id
             " . ($w ? 'WHERE ' . implode(' AND ', $w) : '') . "
             ORDER BY (t.estado = 'abierta') DESC, COALESCE(t.fecha, t.created_at) DESC, t.id DESC
             LIMIT 200",
            $p
        );
    }

    /** Totales para las tarjetas de arriba. */
    public function resumen(?int $liderId): array
    {
        $w = $liderId !== null ? ' AND t.lider_id = :lider' : '';
        $p = $liderId !== null ? ['lider' => $liderId] : [];
        $f = $this->consultarUno(
            "SELECT COUNT(DISTINCT CASE WHEN t.estado = 'abierta' THEN t.id END) AS abiertas,
                    COALESCE(SUM(a.estado = 'hecha'), 0) AS por_validar,
                    COALESCE(SUM(a.estado IN ('aceptada','hecha','validada')), 0) AS comprometidas,
                    COUNT(DISTINCT CASE WHEN a.estado IN ('aceptada','hecha','validada') THEN a.simpatizante_id END) AS personas
             FROM tareas t LEFT JOIN tarea_asignaciones a ON a.tarea_id = t.id
             WHERE t.estado <> 'cancelada' $w",
            $p
        );
        return array_map('intval', $f ?: []);
    }

    public function porId(int $id, ?int $liderId): ?array
    {
        $sql = 'SELECT t.*, COALESCE(u.nombre, s.nombre) AS creador, z.nombre AS zona
                FROM tareas t
                LEFT JOIN usuarios u ON u.id = t.creada_por_usuario
                LEFT JOIN simpatizantes s ON s.id = t.creada_por_simpatizante
                LEFT JOIN zonas z ON z.id = t.zona_id
                WHERE t.id = :id';
        $p = ['id' => $id];
        if ($liderId !== null) { $sql .= ' AND t.lider_id = :lider'; $p['lider'] = $liderId; }
        return $this->consultarUno($sql, $p);
    }

    public function asignaciones(int $tareaId, string $estado = ''): array
    {
        $p = ['t' => $tareaId];
        $w = '';
        if (isset(ASIGNACION_ESTADOS[$estado])) { $w = ' AND a.estado = :e'; $p['e'] = $estado; }
        return $this->consultar(
            "SELECT a.*, s.nombre, s.telefono, s.puntos, z.nombre AS zona
             FROM tarea_asignaciones a
             JOIN simpatizantes s ON s.id = a.simpatizante_id
             LEFT JOIN zonas z ON z.id = s.zona_id
             WHERE a.tarea_id = :t $w
             ORDER BY FIELD(a.estado, 'hecha', 'aceptada', 'pendiente', 'validada', 'no_valida', 'rechazada'), s.nombre",
            $p
        );
    }

    /** Conteo de asignaciones por estado de una tarea. */
    public function conteo(int $tareaId): array
    {
        $r = array_fill_keys(array_keys(ASIGNACION_ESTADOS), 0);
        foreach ($this->consultar('SELECT estado, COUNT(*) AS c FROM tarea_asignaciones WHERE tarea_id = :t GROUP BY estado', ['t' => $tareaId]) as $f) {
            $r[$f['estado']] = (int)$f['c'];
        }
        return $r;
    }

    /**
     * Personas del segmento: nivel de promotor mínimo, zona y líder (opcionales).
     * Solo quienes autorizaron sus datos. Máximo $max.
     */
    public function segmento(int $nivelMinimo, ?int $zonaId, ?int $liderId, int $max = 3000): array
    {
        $sql = 'SELECT id FROM simpatizantes WHERE consentimiento_datos = 1 AND puntos >= :pts';
        $p = ['pts' => (int)(PROMOTOR_NIVELES[$nivelMinimo][0] ?? 0)];
        if ($zonaId)  { $sql .= ' AND zona_id = :z';  $p['z'] = $zonaId; }
        if ($liderId) { $sql .= ' AND lider_id = :l'; $p['l'] = $liderId; }
        return array_map('intval', array_column($this->consultar($sql . ' ORDER BY id LIMIT ' . (int)$max, $p), 'id'));
    }

    /** Cuántas personas hay en el segmento (para la vista previa). */
    public function contarSegmento(int $nivelMinimo, ?int $zonaId, ?int $liderId): int
    {
        return count($this->segmento($nivelMinimo, $zonaId, $liderId, 100000));
    }

    /**
     * Busca personas por documento o celular (una por línea o separadas por comas).
     * Devuelve [ids encontrados, valores no encontrados].
     */
    public function porDocumentos(string $texto, ?int $liderId): array
    {
        $valores = array_unique(array_filter(array_map(
            fn($v) => normalizar_celular($v), preg_split('/[\s,;]+/', $texto) ?: []
        )));
        $ids = [];
        $faltan = [];
        foreach (array_slice($valores, 0, 500) as $v) {
            $sql = 'SELECT id FROM simpatizantes WHERE (documento = :d OR telefono = :t)';
            $p = ['d' => $v, 't' => $v];
            if ($liderId !== null) { $sql .= ' AND lider_id = :l'; $p['l'] = $liderId; }
            $f = $this->consultarUno($sql . ' LIMIT 1', $p);
            if ($f) $ids[] = (int)$f['id']; else $faltan[] = $v;
        }
        return [$ids, $faltan];
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->ejecutar('UPDATE tareas SET estado = :e WHERE id = :id', ['e' => $estado, 'id' => $id]);
    }

    /** ¿La asignación pertenece a una tarea que el usuario puede gestionar? Devuelve su tarea_id. */
    public function tareaDeAsignacion(int $asignacionId, ?int $liderId): ?int
    {
        $sql = 'SELECT a.tarea_id FROM tarea_asignaciones a JOIN tareas t ON t.id = a.tarea_id WHERE a.id = :a';
        $p = ['a' => $asignacionId];
        if ($liderId !== null) { $sql .= ' AND t.lider_id = :l'; $p['l'] = $liderId; }
        $f = $this->consultarUno($sql, $p);
        return $f ? (int)$f['tarea_id'] : null;
    }

    public function idsPorValidar(int $tareaId): array
    {
        return array_map('intval', array_column(
            $this->consultar("SELECT id FROM tarea_asignaciones WHERE tarea_id = :t AND estado = 'hecha'", ['t' => $tareaId]), 'id'
        ));
    }
}
