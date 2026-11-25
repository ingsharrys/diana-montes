<?php
namespace Models;

use Core\Model;
use DomainException;

/** Agenda de encuentros. Compatible con el modelo Historia del administrador. */
class Evento extends Model
{
    public const ESTADOS = [
        'borrador'  => 'Borrador',
        'publicado' => 'Publicado',
        'cancelado' => 'Cancelado',
    ];

    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'eventos');
    }

    /** @return array{registros:array,total:int,pagina:int,paginas:int} */
    public function listar(string $busqueda = '', string $estado = '', int $pagina = 1, int $porPagina = 20): array
    {
        $porPagina = max(1, min(100, $porPagina));
        $where = [];
        $parametros = [];

        if ($busqueda !== '') {
            // Marcadores diferentes: PDO::ATTR_EMULATE_PREPARES puede estar desactivado.
            $where[] = '(e.titulo LIKE :b1 OR e.categoria LIKE :b2 OR e.lugar LIKE :b3 OR e.descripcion LIKE :b4)';
            $parametros['b1'] = '%' . $busqueda . '%';
            $parametros['b2'] = '%' . $busqueda . '%';
            $parametros['b3'] = '%' . $busqueda . '%';
            $parametros['b4'] = '%' . $busqueda . '%';
        }
        if (isset(self::ESTADOS[$estado])) {
            $where[] = 'e.estado = :e';
            $parametros['e'] = $estado;
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) ($this->consultarUno(
            'SELECT COUNT(*) AS n FROM eventos e' . $sqlWhere,
            $parametros
        )['n'] ?? 0);

        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $desde = ($pagina - 1) * $porPagina;

        $registros = $this->consultar(
            'SELECT e.* FROM eventos e' . $sqlWhere .
            ' ORDER BY e.fecha_inicio DESC, e.id DESC LIMIT ' . (int) $porPagina .
            ' OFFSET ' . (int) $desde,
            $parametros
        );

        return compact('registros', 'total', 'pagina', 'paginas');
    }

    public function conteo(): array
    {
        return $this->db
            ->query('SELECT estado, COUNT(*) FROM eventos GROUP BY estado')
            ->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function obtener(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM eventos WHERE id = :id', ['id' => $id]);
    }

    /** Los registros creados desde administración siempre empiezan como borrador. */
    public function crear(array $datos): int
    {
        $this->ejecutar(
            "INSERT INTO eventos
                (titulo, categoria, descripcion, fecha_inicio, fecha_fin,
                 lugar, direccion, mapa_url, estado)
             VALUES
                (:titulo, :categoria, :descripcion, :fecha_inicio, :fecha_fin,
                 :lugar, :direccion, :mapa_url, 'borrador')",
            $this->camposGuardar($datos)
        );
        return $this->ultimoId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $actual = $this->obtener($id);
        if (!$actual) {
            throw new DomainException('El encuentro no existe.');
        }
        $estado = (string) ($datos['estado'] ?? '');
        if (!isset(self::ESTADOS[$estado])) {
            throw new DomainException('El estado seleccionado no es válido.');
        }
        // No convertir en publicado un evento que ya terminó.
        if ($estado === 'publicado' && $actual['estado'] !== 'publicado'
            && $datos['fecha_fin'] < $this->ahora()) {
            throw new DomainException('No puedes publicar un encuentro que ya terminó.');
        }

        $parametros = $this->camposGuardar($datos);
        $parametros['estado'] = $estado;
        $parametros['id'] = $id;
        $this->ejecutar(
            'UPDATE eventos SET
                titulo = :titulo, categoria = :categoria, descripcion = :descripcion,
                fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin,
                lugar = :lugar, direccion = :direccion, mapa_url = :mapa_url,
                estado = :estado
             WHERE id = :id',
            $parametros
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!isset(self::ESTADOS[$estado])) {
            throw new DomainException('El estado seleccionado no es válido.');
        }
        $actual = $this->obtener($id);
        if (!$actual) {
            throw new DomainException('El encuentro no existe.');
        }
        if ($estado === 'publicado' && $actual['fecha_fin'] < $this->ahora()) {
            throw new DomainException('No puedes publicar un encuentro que ya terminó.');
        }
        $this->ejecutar(
            'UPDATE eventos SET estado = :estado WHERE id = :id',
            ['estado' => $estado, 'id' => $id]
        );
    }

    public function eliminar(int $id): bool
    {
        return $this->ejecutar('DELETE FROM eventos WHERE id = :id', ['id' => $id]) > 0;
    }

    /** Solo eventos publicados y que todavía no han terminado. */
    public function proximosPublicos(int $limite = 3): array
    {
        $limite = max(1, min(20, $limite));
        return $this->consultar(
            "SELECT id, titulo, categoria, descripcion, fecha_inicio, fecha_fin,
                    lugar, direccion, mapa_url
               FROM eventos
              WHERE estado = 'publicado' AND fecha_fin >= :ahora
              ORDER BY fecha_inicio ASC, id ASC
              LIMIT " . $limite,
            ['ahora' => $this->ahora()]
        );
    }

    private function ahora(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->format('Y-m-d H:i:s');
    }

    private function camposGuardar(array $datos): array
    {
        return [
            'titulo'       => $datos['titulo'],
            'categoria'    => $datos['categoria'],
            'descripcion'  => $datos['descripcion'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin'    => $datos['fecha_fin'],
            'lugar'        => $datos['lugar'],
            'direccion'    => $datos['direccion'],
            'mapa_url'     => $datos['mapa_url'],
        ];
    }
}
