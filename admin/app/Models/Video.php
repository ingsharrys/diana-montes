<?php
namespace Models;

use Core\Model;
use DomainException;

/** Serie editorial «¿Por qué hago las cosas?». Misma arquitectura que Historia.php. */
class Video extends Model
{
    public const TEMAS = [
        'cercania'       => 'Cercanía humana',
        'pertenencia'    => 'Pertenencia',
        'responsabilidad' => 'Responsabilidad',
        'perseverancia'  => 'Perseverancia',
        'autonomia'      => 'Autonomía',
    ];

    public const PLATAFORMAS = [
        'mp4'      => 'Archivo MP4',
        'facebook' => 'Facebook / Reel',
        'youtube'  => 'YouTube / Short',
        'vimeo'    => 'Vimeo',
    ];

    public const FORMATOS = [
        'horizontal' => 'Horizontal (16:9)',
        'vertical'   => 'Vertical (9:16)',
    ];

    public const ESTADOS = [
        'borrador'  => 'Borrador',
        'publicado' => 'Publicado',
    ];

    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'videos_editoriales');
    }

    /** @return array{registros:array,total:int,pagina:int,paginas:int} */
    public function listar(string $busqueda = '', string $estado = '', string $tema = '', int $pagina = 1, int $porPagina = 20): array
    {
        $porPagina = max(1, min(100, $porPagina));
        $where = [];
        $p = [];

        if ($busqueda !== '') {
            $where[] = '(v.titulo LIKE :q1 OR v.fuente LIKE :q2)';
            $p['q1'] = '%' . $busqueda . '%';
            $p['q2'] = '%' . $busqueda . '%';
        }
        if (isset(self::ESTADOS[$estado])) {
            $where[] = 'v.estado = :estado';
            $p['estado'] = $estado;
        }
        if (isset(self::TEMAS[$tema])) {
            $where[] = 'v.tema = :tema';
            $p['tema'] = $tema;
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) ($this->consultarUno(
            'SELECT COUNT(*) AS n FROM videos_editoriales v' . $sqlWhere,
            $p
        )['n'] ?? 0);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $offset = ($pagina - 1) * $porPagina;

        $registros = $this->consultar(
            'SELECT v.* FROM videos_editoriales v' . $sqlWhere .
            ' ORDER BY v.orden ASC, v.id DESC LIMIT ' . (int) $porPagina .
            ' OFFSET ' . (int) $offset,
            $p
        );
        return compact('registros', 'total', 'pagina', 'paginas');
    }

    public function conteo(): array
    {
        return $this->db->query(
            'SELECT estado, COUNT(*) FROM videos_editoriales GROUP BY estado'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function obtener(int $id): ?array
    {
        return $this->consultarUno(
            'SELECT * FROM videos_editoriales WHERE id = :id',
            ['id' => $id]
        );
    }

    public function crear(array $d): int
    {
        $p = $this->campos($d);
        // Los registros nuevos no aparecen automáticamente en el sitio.
        $this->ejecutar(
            "INSERT INTO videos_editoriales
                (titulo, tema, plataforma, fuente, formato, poster, orden, estado)
             VALUES (:titulo, :tema, :plataforma, :fuente, :formato, :poster, :orden, 'borrador')",
            $p
        );
        return $this->ultimoId();
    }

    public function actualizar(int $id, array $d): void
    {
        if (!$this->obtener($id)) {
            throw new DomainException('El video no existe.');
        }
        $estado = (string) ($d['estado'] ?? '');
        if (!isset(self::ESTADOS[$estado])) {
            throw new DomainException('Estado de video no válido.');
        }
        $p = $this->campos($d);
        $p['estado'] = $estado;
        $p['id'] = $id;
        $this->ejecutar(
            'UPDATE videos_editoriales
                SET titulo = :titulo, tema = :tema, plataforma = :plataforma,
                    fuente = :fuente, formato = :formato, poster = :poster,
                    orden = :orden, estado = :estado
              WHERE id = :id',
            $p
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!isset(self::ESTADOS[$estado])) {
            throw new DomainException('Estado de video no válido.');
        }
        if (!$this->obtener($id)) {
            throw new DomainException('El video no existe.');
        }
        $this->ejecutar(
            'UPDATE videos_editoriales SET estado = :estado WHERE id = :id',
            ['estado' => $estado, 'id' => $id]
        );
    }

    public function eliminar(int $id): bool
    {
        // No elimina físicamente el póster: podría estar compartido con otro video.
        return $this->ejecutar(
            'DELETE FROM videos_editoriales WHERE id = :id',
            ['id' => $id]
        ) > 0;
    }

    public function publicados(): array
    {
        return $this->consultar(
            "SELECT id, titulo, tema, plataforma, fuente, formato, poster
             FROM videos_editoriales
             WHERE estado = 'publicado'
             ORDER BY orden ASC, id DESC"
        );
    }

    private function campos(array $d): array
    {
        return [
            'titulo'     => $d['titulo'],
            'tema'       => $d['tema'],
            'plataforma' => $d['plataforma'],
            'fuente'     => $d['fuente'],
            'formato'    => $d['formato'],
            'poster'     => $d['poster'],
            'orden'      => $d['orden'],
        ];
    }
}
