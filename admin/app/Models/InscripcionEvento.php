<?php
namespace Models;

use Core\Model;
use DomainException;

/** Inscripciones a eventos, desde el MVC autenticado del administrador. */
class InscripcionEvento extends Model
{
    public const ESTADOS = [
        'registrado'  => 'Registrado',
        'confirmado' => 'Confirmado',
        'cancelado'  => 'Cancelado',
    ];

    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'evento_inscripciones');
    }

    /** Conteo limitado a los eventos que se muestran en la página actual. */
    public function conteoPorEventos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));
        if (!$ids) {
            return [];
        }

        $marcadores = [];
        $parametros = [];
        foreach ($ids as $i => $id) {
            $clave = 'ev' . $i;
            $marcadores[] = ':' . $clave;
            $parametros[$clave] = $id;
        }

        $filas = $this->consultar(
            'SELECT evento_id, COUNT(*) AS total FROM evento_inscripciones '
            . 'WHERE evento_id IN (' . implode(', ', $marcadores) . ') GROUP BY evento_id',
            $parametros
        );
        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(int)$fila['evento_id']] = (int)$fila['total'];
        }
        return $resultado;
    }

    /** @return array{registros:array,total:int,pagina:int,paginas:int} */
    public function listarPorEvento(int $eventoId, int $pagina = 1, int $porPagina = 20): array
    {
        if ($eventoId < 1) {
            throw new DomainException('El encuentro indicado no es válido.');
        }
        $porPagina = max(1, min(100, $porPagina));
        $total = (int)($this->consultarUno(
            'SELECT COUNT(*) AS n FROM evento_inscripciones WHERE evento_id = :id',
            ['id' => $eventoId]
        )['n'] ?? 0);
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $desde = ($pagina - 1) * $porPagina;

        $registros = $this->consultar(
            'SELECT id, evento_id, nombre, correo, celular, estado, '
            . 'consentimiento_datos, consentimiento_fecha, created_at '
            . 'FROM evento_inscripciones WHERE evento_id = :id '
            . 'ORDER BY created_at DESC, id DESC LIMIT ' . (int)$porPagina
            . ' OFFSET ' . (int)$desde,
            ['id' => $eventoId]
        );
        return compact('registros', 'total', 'pagina', 'paginas');
    }

    public function obtener(int $id): ?array
    {
        return $this->consultarUno(
            'SELECT id, evento_id, estado FROM evento_inscripciones WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Retorna el registro anterior para auditoría y redirección. */
    public function cambiarEstado(int $id, string $estado): array
    {
        if (!isset(self::ESTADOS[$estado])) {
            throw new DomainException('Estado de inscripción no válido.');
        }
        $actual = $this->obtener($id);
        if (!$actual) {
            throw new DomainException('La inscripción indicada ya no existe.');
        }
        $this->ejecutar(
            'UPDATE evento_inscripciones SET estado = :estado WHERE id = :id',
            ['estado' => $estado, 'id' => $id]
        );
        return $actual;
    }

    /** Elimina una inscripción, pero nunca elimina el evento. */
    public function eliminar(int $id): ?array
    {
        $actual = $this->obtener($id);
        if (!$actual) {
            return null;
        }
        $this->ejecutar(
            'DELETE FROM evento_inscripciones WHERE id = :id',
            ['id' => $id]
        );
        return $actual;
    }
}
