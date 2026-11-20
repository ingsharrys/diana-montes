<?php
namespace Models;

use Core\Model;
use DomainException;

/**
 * Historias ciudadanas: relatos de la gente sobre Garzón.
 * Reglas de datos personales (Ley 1581 de 2012):
 * - No se guarda una historia sin autorización de tratamiento de datos.
 * - Solo se aprueba (publica) si además hay autorización de publicación.
 * - La autorización de publicación no se puede inventar después: solo revocar.
 */
class Historia extends Model
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];

    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'historias_ciudadanas');
    }

    /** @return array{registros: array, total: int, pagina: int, paginas: int} */
    public function listar(string $busqueda = '', string $estado = '', int $pagina = 1, int $porPagina = 20): array
    {
        $where = []; $p = [];
        if ($busqueda !== '') {
            $where[] = '(h.nombre LIKE :b1 OR h.mensaje LIKE :b2)';
            $p['b1'] = $p['b2'] = '%' . $busqueda . '%';
        }
        if (isset(self::ESTADOS[$estado])) { $where[] = 'h.estado = :e'; $p['e'] = $estado; }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total   = (int)($this->consultarUno('SELECT COUNT(*) AS n FROM historias_ciudadanas h' . $sqlWhere, $p)['n'] ?? 0);
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina  = min(max(1, $pagina), $paginas);
        $desde   = ($pagina - 1) * $porPagina;

        $registros = $this->consultar(
            'SELECT h.*, z.nombre AS zona_nombre FROM historias_ciudadanas h
             LEFT JOIN zonas z ON z.id = h.zona_id' . $sqlWhere .
            ' ORDER BY h.created_at DESC, h.id DESC LIMIT ' . (int)$porPagina . ' OFFSET ' . (int)$desde, $p);

        return ['registros' => $registros, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Cuántas hay en cada estado: ['pendiente' => 3, ...] */
    public function conteo(): array
    {
        return $this->db->query('SELECT estado, COUNT(*) FROM historias_ciudadanas GROUP BY estado')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function obtener(int $id): ?array
    {
        return $this->consultarUno(
            'SELECT h.*, z.nombre AS zona_nombre FROM historias_ciudadanas h
             LEFT JOIN zonas z ON z.id = h.zona_id WHERE h.id = :id', ['id' => $id]);
    }

    public function zonas(): array
    {
        return zonas_listar($this->db);
    }

    private function validarZona(?int $zonaId): void
    {
        if ($zonaId !== null && !$this->consultarUno('SELECT 1 AS ok FROM zonas WHERE id = :id', ['id' => $zonaId])) {
            throw new DomainException('El barrio o vereda seleccionado no existe.');
        }
    }

    /** Registra una historia contada en persona; queda pendiente de revisión. */
    public function crear(array $d): int
    {
        if (empty($d['consentimiento_datos'])) {
            throw new DomainException('Para guardar la historia, la persona debe autorizar el tratamiento de sus datos.');
        }
        $this->validarZona($d['zona_id']);
        $publicar = !empty($d['autoriza_publicacion']);
        $this->ejecutar(
            "INSERT INTO historias_ciudadanas
               (nombre, zona_id, mensaje, consentimiento_datos, consentimiento_fecha, autoriza_publicacion, autorizacion_publicacion_fecha, estado)
             VALUES (:n, :z, :m, 1, NOW(), :ap, " . ($publicar ? 'NOW()' : 'NULL') . ", 'pendiente')",
            ['n' => $d['nombre'], 'z' => $d['zona_id'], 'm' => $d['mensaje'], 'ap' => $publicar ? 1 : 0]);
        return $this->ultimoId();
    }

    /** Corrige el texto o la zona, cambia el estado o revoca la publicación. */
    public function actualizar(int $id, array $d): void
    {
        $actual = $this->obtener($id);
        if (!$actual) throw new DomainException('La historia no existe.');
        $this->validarZona($d['zona_id']);

        $autorizada = !empty($actual['autoriza_publicacion']) && empty($d['revocar_publicacion']);
        $estado = $d['estado'];
        // Revocar la publicación la retira: una historia aprobada vuelve a pendiente
        if (!$autorizada && $estado === 'aprobada' && !empty($d['revocar_publicacion'])) $estado = 'pendiente';
        if ($estado === 'aprobada' && (!$autorizada || empty($actual['consentimiento_datos']))) {
            throw new DomainException('No se puede aprobar una historia sin autorización de datos y de publicación.');
        }

        $this->ejecutar(
            'UPDATE historias_ciudadanas SET nombre = :n, zona_id = :z, mensaje = :m, estado = :e, autoriza_publicacion = :ap WHERE id = :id',
            ['n' => $d['nombre'], 'z' => $d['zona_id'], 'm' => $d['mensaje'], 'e' => $estado, 'ap' => $autorizada ? 1 : 0, 'id' => $id]);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!isset(self::ESTADOS[$estado])) throw new DomainException('Estado de revisión no válido.');
        $actual = $this->obtener($id);
        if (!$actual) throw new DomainException('La historia no existe.');
        if ($estado === 'aprobada' && (empty($actual['consentimiento_datos']) || empty($actual['autoriza_publicacion']))) {
            throw new DomainException('No puedes aprobar una historia sin autorización de publicación.');
        }
        $this->ejecutar('UPDATE historias_ciudadanas SET estado = :e WHERE id = :id', ['e' => $estado, 'id' => $id]);
    }

    public function eliminar(int $id): bool
    {
        return $this->ejecutar('DELETE FROM historias_ciudadanas WHERE id = :id', ['id' => $id]) > 0;
    }
}
