<?php
namespace Models;

use Core\Model;

/**
 * Organización de la campaña: colaboradores (pagos y voluntarios) y sus
 * pagos, publicidad impresa (pedidos y entregas por zona), testigos
 * electorales por mesa y gastos, frente al tope de gastos.
 */
class Organizacion extends Model
{
    /** Roles de los colaboradores de la campaña. */
    public const ROLES = [
        'coordinador'   => 'Coordinador(a) de zona',
        'lider'         => 'Líder',
        'digitador'     => 'Digitador(a)',
        'logistica'     => 'Logística',
        'comunicaciones' => 'Comunicaciones y redes',
        'conductor'     => 'Conductor(a)',
        'perifoneo'     => 'Perifoneo',
        'juridico'      => 'Jurídico',
        'contable'      => 'Contable',
        'capacitador'   => 'Capacitador(a) de testigos',
        'otro'          => 'Otro',
    ];
    /** Cada cuánto se le paga a un colaborador y cuántas veces cabe en un mes. */
    public const PERIODOS = [
        'mensual'   => ['Mensual', 1],
        'quincenal' => ['Quincenal', 2],
        'semanal'   => ['Semanal', 4.33],
        'diario'    => ['Por día', 26],
        'evento'    => ['Por evento', 0],
        'unico'     => ['Pago único', 0],
    ];
    public const PUBLICIDAD = [
        'afiche' => 'Afiches', 'volante' => 'Volantes', 'pendon' => 'Pendones', 'valla' => 'Vallas',
        'camiseta' => 'Camisetas', 'gorra' => 'Gorras', 'calcomania' => 'Calcomanías', 'tarjeta' => 'Tarjetas', 'otro' => 'Otro',
    ];
    public const PUB_ESTADOS = ['cotizado' => 'Cotizado', 'pedido' => 'Pedido', 'recibido' => 'Recibido'];
    public const TESTIGO_ESTADOS = ['propuesto' => 'Propuesto', 'confirmado' => 'Confirmado', 'capacitado' => 'Capacitado', 'acreditado' => 'Acreditado'];
    public const GASTOS = [
        'transporte' => 'Transporte', 'alimentacion' => 'Alimentación', 'sede' => 'Sede y servicios', 'eventos' => 'Eventos',
        'digital' => 'Publicidad digital', 'sonido' => 'Sonido y perifoneo', 'papeleria' => 'Papelería', 'otros' => 'Otros',
    ];

    public function disponible(): bool
    {
        return esquema_tiene($this->db, 'org_gastos');
    }

    /* ---------------- Colaboradores ---------------- */

    public function colaboradores(array $f = []): array
    {
        $w = ['1=1']; $p = [];
        if (!empty($f['tipo'])) { $w[] = 'c.tipo = :t'; $p['t'] = $f['tipo']; }
        if (!empty($f['rol']))  { $w[] = 'c.rol = :r'; $p['r'] = $f['rol']; }
        if (($f['activo'] ?? '1') !== '') { $w[] = 'c.activo = :a'; $p['a'] = (int)($f['activo'] ?? 1); }
        return $this->consultar(
            'SELECT c.*, z.nombre AS zona, COALESCE(pg.pagado, 0) AS pagado, pg.ultimo_pago
             FROM org_colaboradores c
             LEFT JOIN zonas z ON z.id = c.zona_id
             LEFT JOIN (SELECT colaborador_id, SUM(valor) AS pagado, MAX(fecha) AS ultimo_pago FROM org_pagos GROUP BY colaborador_id) pg ON pg.colaborador_id = c.id
             WHERE ' . implode(' AND ', $w) . ' ORDER BY c.tipo, c.rol, c.nombre', $p
        );
    }

    public function colaborador(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM org_colaboradores WHERE id = :id', ['id' => $id]);
    }

    public function guardarColaborador(?int $id, array $d, int $usuario): int
    {
        $campos = ['nombre', 'documento', 'telefono', 'rol', 'tipo', 'valor', 'periodicidad', 'zona_id', 'inicio', 'fin', 'activo', 'notas'];
        $p = array_intersect_key($d, array_flip($campos));
        if ($id) {
            $this->ejecutar('UPDATE org_colaboradores SET ' . implode(', ', array_map(fn($c) => "$c = :$c", array_keys($p))) . ' WHERE id = :id', $p + ['id' => $id]);
            return $id;
        }
        $p['creado_por'] = $usuario;
        $this->ejecutar('INSERT INTO org_colaboradores (' . implode(', ', array_keys($p)) . ') VALUES (:' . implode(', :', array_keys($p)) . ')', $p);
        return $this->ultimoId();
    }

    public function pagos(?int $colaborador = null, int $limite = 200): array
    {
        return $this->consultar(
            'SELECT p.*, c.nombre, c.rol FROM org_pagos p JOIN org_colaboradores c ON c.id = p.colaborador_id'
            . ($colaborador ? ' WHERE p.colaborador_id = :c' : '') . ' ORDER BY p.fecha DESC, p.id DESC LIMIT ' . (int)$limite,
            $colaborador ? ['c' => $colaborador] : []
        );
    }

    public function registrarPago(int $colaborador, string $fecha, int $valor, string $concepto, string $medio, int $usuario): void
    {
        $this->ejecutar('INSERT INTO org_pagos (colaborador_id, fecha, valor, concepto, medio, creado_por) VALUES (:c, :f, :v, :co, :m, :u)',
            ['c' => $colaborador, 'f' => $fecha, 'v' => $valor, 'co' => $concepto ?: null, 'm' => $medio ?: null, 'u' => $usuario]);
    }

    /* ---------------- Publicidad impresa ---------------- */

    public function publicidad(): array
    {
        return $this->consultar(
            'SELECT p.*, COALESCE(e.entregado, 0) AS entregado, COALESCE(e.entregas, 0) AS entregas
             FROM org_publicidad p
             LEFT JOIN (SELECT publicidad_id, SUM(cantidad) AS entregado, COUNT(*) AS entregas FROM org_entregas GROUP BY publicidad_id) e ON e.publicidad_id = p.id
             ORDER BY p.estado = \'recibido\' DESC, p.fecha_pedido DESC, p.id DESC'
        );
    }

    public function pieza(int $id): ?array
    {
        return $this->consultarUno('SELECT p.*, (SELECT COALESCE(SUM(cantidad), 0) FROM org_entregas e WHERE e.publicidad_id = p.id) AS entregado FROM org_publicidad p WHERE p.id = :id', ['id' => $id]);
    }

    public function guardarPieza(?int $id, array $d, int $usuario): int
    {
        $campos = ['tipo', 'descripcion', 'cantidad', 'costo', 'proveedor', 'estado', 'fecha_pedido', 'fecha_entrega'];
        $p = array_intersect_key($d, array_flip($campos));
        if ($id) {
            $this->ejecutar('UPDATE org_publicidad SET ' . implode(', ', array_map(fn($c) => "$c = :$c", array_keys($p))) . ' WHERE id = :id', $p + ['id' => $id]);
            return $id;
        }
        $p['creado_por'] = $usuario;
        $this->ejecutar('INSERT INTO org_publicidad (' . implode(', ', array_keys($p)) . ') VALUES (:' . implode(', :', array_keys($p)) . ')', $p);
        return $this->ultimoId();
    }

    public function entregas(int $limite = 100): array
    {
        return $this->consultar(
            'SELECT e.*, p.tipo, p.descripcion, z.nombre AS zona FROM org_entregas e
             JOIN org_publicidad p ON p.id = e.publicidad_id LEFT JOIN zonas z ON z.id = e.zona_id
             ORDER BY e.fecha DESC, e.id DESC LIMIT ' . (int)$limite
        );
    }

    public function registrarEntrega(int $pieza, int $cantidad, ?int $zona, string $responsable, string $fecha, int $usuario): void
    {
        $this->ejecutar('INSERT INTO org_entregas (publicidad_id, cantidad, zona_id, responsable, fecha, creado_por) VALUES (:p, :c, :z, :r, :f, :u)',
            ['p' => $pieza, 'c' => $cantidad, 'z' => $zona, 'r' => $responsable ?: null, 'f' => $fecha, 'u' => $usuario]);
    }

    /** Piezas entregadas por zona (para ver dónde falta publicidad). */
    public function entregadoPorZona(): array
    {
        return $this->consultar(
            'SELECT COALESCE(z.nombre, \'Sin zona\') AS zona, p.tipo, SUM(e.cantidad) AS cantidad
             FROM org_entregas e JOIN org_publicidad p ON p.id = e.publicidad_id LEFT JOIN zonas z ON z.id = e.zona_id
             GROUP BY z.nombre, p.tipo ORDER BY cantidad DESC'
        );
    }

    /* ---------------- Testigos electorales ---------------- */

    /** Cada puesto con sus mesas, testigos y mesas cubiertas. */
    public function coberturaPuestos(): array
    {
        $mesas = esquema_tiene($this->db, 'puestos_votacion', 'mesas') ? 'p.mesas' : 'NULL AS mesas';
        return $this->consultar(
            "SELECT p.id, p.nombre, z.nombre AS zona, $mesas,
                    COALESCE(t.testigos, 0) AS testigos, COALESCE(t.cubiertas, 0) AS cubiertas,
                    COALESCE(t.confirmados, 0) AS confirmados, COALESCE(t.capacitados, 0) AS capacitados, COALESCE(t.acreditados, 0) AS acreditados
             FROM puestos_votacion p
             LEFT JOIN zonas z ON z.id = p.zona_id
             LEFT JOIN (SELECT puesto_id, COUNT(*) AS testigos, COUNT(DISTINCT mesa) AS cubiertas,
                               SUM(estado <> 'propuesto') AS confirmados, SUM(estado IN ('capacitado','acreditado')) AS capacitados,
                               SUM(estado = 'acreditado') AS acreditados
                        FROM org_testigos GROUP BY puesto_id) t ON t.puesto_id = p.id
             ORDER BY p.nombre"
        );
    }

    public function testigos(?int $puesto = null): array
    {
        return $this->consultar(
            'SELECT t.*, p.nombre AS puesto FROM org_testigos t JOIN puestos_votacion p ON p.id = t.puesto_id'
            . ($puesto ? ' WHERE t.puesto_id = :p' : '') . ' ORDER BY p.nombre, t.mesa IS NULL, t.mesa, t.nombre',
            $puesto ? ['p' => $puesto] : []
        );
    }

    public function testigo(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM org_testigos WHERE id = :id', ['id' => $id]);
    }

    public function guardarTestigo(?int $id, array $d, int $usuario): int
    {
        $campos = ['nombre', 'documento', 'telefono', 'puesto_id', 'mesa', 'estado', 'notas'];
        $p = array_intersect_key($d, array_flip($campos));
        if ($id) {
            $this->ejecutar('UPDATE org_testigos SET ' . implode(', ', array_map(fn($c) => "$c = :$c", array_keys($p))) . ' WHERE id = :id', $p + ['id' => $id]);
            return $id;
        }
        $p['creado_por'] = $usuario;
        $this->ejecutar('INSERT INTO org_testigos (' . implode(', ', array_keys($p)) . ') VALUES (:' . implode(', :', array_keys($p)) . ')', $p);
        return $this->ultimoId();
    }

    /** ¿Esa mesa ya tiene testigo? (otro distinto al que se edita) */
    public function mesaOcupada(int $puesto, int $mesa, ?int $excepto): ?string
    {
        $f = $this->consultarUno('SELECT nombre FROM org_testigos WHERE puesto_id = :p AND mesa = :m AND id <> :id LIMIT 1',
            ['p' => $puesto, 'm' => $mesa, 'id' => (int)$excepto]);
        return $f['nombre'] ?? null;
    }

    /** Simpatizantes marcados como "Testigo electoral" que aún no tienen mesa asignada. */
    public function testigosSugeridos(int $limite = 30): array
    {
        if (!compromiso_escala_nueva($this->db)) return [];
        return $this->consultar(
            "SELECT s.id, s.nombre, s.documento, s.telefono, s.puesto_id, p.nombre AS puesto
             FROM simpatizantes s LEFT JOIN puestos_votacion p ON p.id = s.puesto_id
             WHERE s.nivel = 'testigo' AND NOT EXISTS (SELECT 1 FROM org_testigos t WHERE t.documento = s.documento)
             ORDER BY s.nombre LIMIT " . (int)$limite
        );
    }

    public function contarSugeridos(): int
    {
        if (!compromiso_escala_nueva($this->db)) return 0;
        return (int)($this->consultarUno(
            "SELECT COUNT(*) AS n FROM simpatizantes s
             WHERE s.nivel = 'testigo' AND NOT EXISTS (SELECT 1 FROM org_testigos t WHERE t.documento = s.documento)"
        )['n'] ?? 0);
    }

    /* ---------------- Gastos ---------------- */

    public function gastos(int $limite = 300): array
    {
        return $this->consultar('SELECT * FROM org_gastos ORDER BY fecha DESC, id DESC LIMIT ' . (int)$limite);
    }

    public function gasto(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM org_gastos WHERE id = :id', ['id' => $id]);
    }

    public function guardarGasto(?int $id, array $d, int $usuario): int
    {
        $campos = ['fecha', 'categoria', 'concepto', 'valor', 'proveedor'];
        $p = array_intersect_key($d, array_flip($campos));
        if ($id) {
            $this->ejecutar('UPDATE org_gastos SET ' . implode(', ', array_map(fn($c) => "$c = :$c", array_keys($p))) . ' WHERE id = :id', $p + ['id' => $id]);
            return $id;
        }
        $p['creado_por'] = $usuario;
        $this->ejecutar('INSERT INTO org_gastos (' . implode(', ', array_keys($p)) . ') VALUES (:' . implode(', :', array_keys($p)) . ')', $p);
        return $this->ultimoId();
    }

    /** Borra un registro de las tablas del módulo (lista cerrada). Al borrar una pieza o un colaborador se van sus entregas o pagos. */
    public function borrar(string $tipo, int $id): bool
    {
        $tablas = ['colaborador' => 'org_colaboradores', 'pago' => 'org_pagos', 'pieza' => 'org_publicidad',
                   'entrega' => 'org_entregas', 'testigo' => 'org_testigos', 'gasto' => 'org_gastos'];
        if (!isset($tablas[$tipo])) return false;
        if ($tipo === 'colaborador') $this->ejecutar('DELETE FROM org_pagos WHERE colaborador_id = :id', ['id' => $id]);
        if ($tipo === 'pieza') $this->ejecutar('DELETE FROM org_entregas WHERE publicidad_id = :id', ['id' => $id]);
        return $this->ejecutar('DELETE FROM ' . $tablas[$tipo] . ' WHERE id = :id', ['id' => $id]) > 0;
    }

    /* ---------------- Resumen ---------------- */

    public function resumen(): array
    {
        $c = $this->consultarUno(
            "SELECT COUNT(*) AS total, COALESCE(SUM(tipo = 'pago'), 0) AS pagos, COALESCE(SUM(tipo = 'voluntario'), 0) AS voluntarios
             FROM org_colaboradores WHERE activo = 1"
        );
        $nomina = 0.0;
        foreach ($this->consultar("SELECT valor, periodicidad FROM org_colaboradores WHERE activo = 1 AND tipo = 'pago' AND valor IS NOT NULL") as $f) {
            $nomina += (float)$f['valor'] * (self::PERIODOS[$f['periodicidad'] ?? 'mensual'][1] ?? 0);
        }
        $pub = $this->consultarUno(
            "SELECT COALESCE(SUM(CASE WHEN estado = 'recibido' THEN cantidad END), 0) AS recibido,
                    COALESCE(SUM(CASE WHEN estado = 'pedido' THEN cantidad END), 0) AS pedido,
                    COALESCE(SUM(CASE WHEN estado <> 'cotizado' THEN costo END), 0) AS costo
             FROM org_publicidad"
        );
        $entregado = (int)($this->consultarUno('SELECT COALESCE(SUM(cantidad), 0) AS n FROM org_entregas')['n'] ?? 0);
        $puestos = $this->coberturaPuestos();
        $porCategoria = [];
        foreach ($this->consultar('SELECT categoria, SUM(valor) AS total FROM org_gastos GROUP BY categoria') as $f) $porCategoria[$f['categoria']] = (float)$f['total'];
        $pagado = (float)($this->consultarUno('SELECT COALESCE(SUM(valor), 0) AS n FROM org_pagos')['n'] ?? 0);

        return [
            'colaboradores' => array_map('intval', $c),
            'nomina_mensual' => (int)round($nomina),
            'pagado' => (int)$pagado,
            'publicidad' => ['recibido' => (int)$pub['recibido'], 'pedido' => (int)$pub['pedido'], 'entregado' => $entregado,
                             'bodega' => max(0, (int)$pub['recibido'] - $entregado), 'costo' => (int)$pub['costo']],
            'testigos' => [
                'mesas' => array_sum(array_map(fn($p) => (int)$p['mesas'], $puestos)),
                'cubiertas' => array_sum(array_map(fn($p) => (int)$p['cubiertas'], $puestos)),
                'testigos' => array_sum(array_map(fn($p) => (int)$p['testigos'], $puestos)),
                'confirmados' => array_sum(array_map(fn($p) => (int)$p['confirmados'], $puestos)),
                'capacitados' => array_sum(array_map(fn($p) => (int)$p['capacitados'], $puestos)),
                'acreditados' => array_sum(array_map(fn($p) => (int)$p['acreditados'], $puestos)),
                'sin_mesas' => count(array_filter($puestos, fn($p) => !$p['mesas'])),
            ],
            'gastos' => [
                'colaboradores' => (int)$pagado,
                'publicidad' => (int)$pub['costo'],
                'otros' => (int)array_sum($porCategoria),
                'por_categoria' => $porCategoria,
            ],
        ];
    }
}
