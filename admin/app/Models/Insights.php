<?php
namespace Models;

use Core\Model;

/**
 * Consultas del centro de mando (Vista rápida y Red de contactos).
 * Con $soloLiderId todo se limita a la red de ese líder (seguridad por roles).
 * Las tarjetas que dependen de columnas aún no creadas devuelven null.
 */
class Insights extends Model
{
    private ?int $lider;

    public function __construct(?int $soloLiderId = null)
    {
        parent::__construct();
        $this->lider = $soloLiderId;
    }

    /** Condición y parámetros para limitar a la red del líder. */
    private function alcance(string $alias = 's'): array
    {
        return $this->lider === null ? ['', []] : [" AND {$alias}.lider_id = :lider", ['lider' => $this->lider]];
    }

    private function tiene(string $columna): bool
    {
        return esquema_tiene($this->db, 'simpatizantes', $columna);
    }

    public function total(): int
    {
        [$w, $p] = $this->alcance();
        return (int)($this->consultarUno("SELECT COUNT(*) c FROM simpatizantes s WHERE 1=1 $w", $p)['c'] ?? 0);
    }

    /** Simpatizantes por nivel en la red: [1 => n, …, 5 => n (5 o más)]. */
    public function porNivelRed(): ?array
    {
        if (!$this->tiene('profundidad')) return null;
        [$w, $p] = $this->alcance();
        // Se agrupa sobre una subconsulta: portable con ONLY_FULL_GROUP_BY (MySQL y MariaDB)
        $filas = $this->consultar(
            "SELECT t.nivel, COUNT(*) AS c
             FROM (SELECT LEAST(COALESCE(s.profundidad, 1), 5) AS nivel FROM simpatizantes s WHERE 1=1 $w) t
             GROUP BY t.nivel", $p
        );
        $r = array_fill(1, 5, 0);
        foreach ($filas as $f) $r[(int)$f['nivel']] = (int)$f['c'];
        return $r;
    }

    /**
     * Promotores según sus invitados directos: 'activos' (>= 1), 'promotores'
     * (nivel Promotor o más) y 'super' (Súper Promotor o más).
     */
    public function promotores(): ?array
    {
        if (!promotor_esquema_listo($this->db)) return null;
        [$w, $p] = $this->alcance('pr');
        $umbralPromotor = (int)PROMOTOR_NIVELES[1][0];
        $umbralSuper    = (int)PROMOTOR_NIVELES[2][0];
        // Niveles por puntos (antes de actualizar la plataforma: 10 puntos por invitado)
        $puntos = red_portal_listo($this->db) ? 'MAX(pr.puntos)' : RED_PUNTOS_INVITADO . ' * COUNT(*)';
        $f = $this->consultarUno(
            "SELECT COUNT(*) AS activos,
                    COALESCE(SUM(t.p >= $umbralPromotor), 0) AS promotores,
                    COALESCE(SUM(t.p >= $umbralSuper), 0)    AS super
             FROM (SELECT r.referido_por, $puntos AS p
                   FROM simpatizantes r JOIN simpatizantes pr ON pr.id = r.referido_por
                   WHERE 1=1 $w GROUP BY r.referido_por) t", $p
        );
        return array_map('intval', $f ?: ['activos' => 0, 'promotores' => 0, 'super' => 0]);
    }

    /** Embudo de compromiso: [valor => cantidad] en el orden de la escala vigente. */
    public function porCompromiso(): array
    {
        [$w, $p] = $this->alcance();
        $filas = $this->consultar(
            "SELECT t.nivel, COUNT(*) AS c FROM (SELECT s.nivel FROM simpatizantes s WHERE 1=1 $w) t GROUP BY t.nivel", $p
        );
        $r = array_fill_keys(array_keys(compromisos_disponibles($this->db)), 0);
        foreach ($filas as $f) $r[$f['nivel']] = ($r[$f['nivel']] ?? 0) + (int)$f['c'];
        return $r;
    }

    /** Calidad de la base: votos seguros, con puesto y mesa, verificados. */
    public function calidad(): array
    {
        [$w, $p] = $this->alcance();
        $seguros = "'" . implode("','", COMPROMISOS_SEGUROS) . "'";
        $verif = esquema_tiene($this->db, 'simpatizantes', 'verificado_at') ? 'COALESCE(SUM(s.verificado_at IS NOT NULL), 0)' : 'NULL';
        $f = $this->consultarUno(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(s.nivel IN ($seguros)), 0) AS seguros,
                    COALESCE(SUM(s.puesto_id IS NOT NULL AND s.mesa IS NOT NULL AND s.mesa <> ''), 0) AS con_puesto,
                    $verif AS verificados
             FROM simpatizantes s WHERE 1=1 $w", $p
        );
        return [
            'total'       => (int)$f['total'],
            'seguros'     => (int)$f['seguros'],
            'con_puesto'  => (int)$f['con_puesto'],
            'verificados' => $f['verificados'] === null ? null : (int)$f['verificados'],
        ];
    }

    /** Conteo por género, en el orden de GENEROS + 'sin_dato'. */
    public function porGenero(): ?array
    {
        if (!$this->tiene('genero')) return null;
        [$w, $p] = $this->alcance();
        $filas = $this->consultar(
            "SELECT t.g, COUNT(*) AS c
             FROM (SELECT COALESCE(s.genero, 'sin_dato') AS g FROM simpatizantes s WHERE 1=1 $w) t
             GROUP BY t.g", $p
        );
        $r = array_fill_keys(array_merge(array_keys(GENEROS), ['sin_dato']), 0);
        foreach ($filas as $f) $r[$f['g']] = (int)$f['c'];
        return $r;
    }

    /** Distribución por rangos de edad + 'sin_dato' (registros antiguos sin fecha). */
    public function porEdad(): array
    {
        [$w, $p] = $this->alcance();
        $filas = $this->consultar(
            "SELECT b.banda, COUNT(*) AS c FROM (
               SELECT CASE
                        WHEN t.edad IS NULL THEN 'sin_dato'
                        WHEN t.edad < 18 THEN 'menor'
                        WHEN t.edad < 25 THEN '18-24'
                        WHEN t.edad < 35 THEN '25-34'
                        WHEN t.edad < 45 THEN '35-44'
                        WHEN t.edad < 55 THEN '45-54'
                        WHEN t.edad < 65 THEN '55-64'
                        ELSE '65+'
                      END AS banda
               FROM (SELECT TIMESTAMPDIFF(YEAR, s.fecha_nacimiento, CURDATE()) AS edad
                     FROM simpatizantes s WHERE 1=1 $w) t
             ) b GROUP BY b.banda", $p
        );
        $r = array_fill_keys(['18-24', '25-34', '35-44', '45-54', '55-64', '65+', 'menor', 'sin_dato'], 0);
        foreach ($filas as $f) $r[$f['banda']] = (int)$f['c'];
        return $r;
    }

    /** Registros de los últimos 7 días (hoy incluido), con días sin registros en 0. */
    public function ultimos7Dias(): array
    {
        [$w, $p] = $this->alcance();
        $hoy = $this->consultarUno('SELECT CURDATE() AS h')['h'];
        $filas = $this->consultar(
            "SELECT t.d, COUNT(*) AS c
             FROM (SELECT DATE(s.created_at) AS d FROM simpatizantes s
                   WHERE s.created_at >= CURDATE() - INTERVAL 6 DAY $w) t
             GROUP BY t.d", $p
        );
        $porDia = array_column($filas, 'c', 'd');
        $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $r = [];
        for ($i = 6; $i >= 0; $i--) {
            $ts = strtotime("$hoy -$i day");
            $r[] = [
                'etiqueta' => $dias[(int)date('w', $ts)],
                'fecha'    => date('d/m', $ts),
                'total'    => (int)($porDia[date('Y-m-d', $ts)] ?? 0),
                'hoy'      => $i === 0,
            ];
        }
        return $r;
    }

    /**
     * Grafo "quién trajo a quién": raíz (la candidata, o el líder si el
     * alcance es su red) → equipo → simpatizantes → sus invitados.
     * Se limita a los $max registros más recientes para que el navegador
     * lo dibuje con fluidez.
     *
     * Devuelve nodos [{n: nombre, t: 'raiz'|'equipo'|'simp', g: grupo de color (-1 = sin color), v: invitados}],
     * enlaces [[hijo, padre]] por índice, la leyenda del equipo y totales.
     */
    /**
     * Grafo "quién trajo a quién". Con $detalle cada nodo trae además lo que
     * muestra el panel de la página Red: id, nombre completo (nc), zona (z),
     * compromiso (c), puntos (p), fecha de registro (f) y rol del equipo (r).
     */
    public function grafoRed(int $max = 600, bool $detalle = false): array
    {
        [$w, $p] = $this->alcance();
        $conRef = $this->tiene('referido_por');
        $extra = $detalle
            ? ', s.nivel, s.created_at, ' . ($this->tiene('puntos') ? 's.puntos' : 'NULL AS puntos') . ', z.nombre AS zona'
            : '';
        $join = $detalle ? ' LEFT JOIN zonas z ON z.id = s.zona_id' : '';
        $simp = $this->consultar(
            'SELECT s.id, s.nombre, s.lider_id, ' . ($conRef ? 's.referido_por' : 'NULL') . " AS referido_por $extra
             FROM simpatizantes s $join WHERE 1=1 $w ORDER BY s.id DESC LIMIT " . (int)$max, $p
        );

        $nodos = []; $enlaces = []; $indice = [];
        $agregar = function (string $clave, string $nombre, string $tipo, int $grupo, array $mas = []) use (&$nodos, &$indice, $detalle): int {
            $indice[$clave] = count($nodos);
            $nodos[] = ['n' => $nombre, 't' => $tipo, 'g' => $grupo, 'v' => 0] + ($detalle ? $mas : []);
            return $indice[$clave];
        };
        $roles = ['direccion' => 'Dirección', 'coordinador' => 'Coordinador/a', 'lider' => 'Líder', 'digitador' => 'Digitador/a'];

        // Raíz y equipo
        $leyenda = [];
        $grupoDe = [];          // usuario_id => índice de color (0-7) o -1
        if ($this->lider !== null) {
            $yo = $this->consultarUno('SELECT nombre FROM usuarios WHERE id = :id', ['id' => $this->lider]);
            $raiz = $agregar('u' . $this->lider, $yo['nombre'] ?? 'Mi red', 'raiz', 0);
            $grupoDe[$this->lider] = 0;
        } else {
            $cand = $this->consultarUno("SELECT nombre FROM usuarios WHERE rol = 'direccion' ORDER BY id LIMIT 1");
            $raiz = $agregar('raiz', $cand['nombre'] ?? 'Campaña', 'raiz', -1);
            $equipo = $this->consultar(
                "SELECT id, nombre, rol, coordinador_id FROM usuarios
                 WHERE activo = 1 AND rol <> 'direccion' ORDER BY id"
            );
            // La dirección ES la raíz: quienes entran directo forman la "red directa" (color 1)
            $direccion = array_map('intval', array_column($this->consultar("SELECT id FROM usuarios WHERE rol = 'direccion'"), 'id'));
            foreach ($direccion as $d) { $indice['u' . $d] = $raiz; $grupoDe[$d] = 0; }
            $leyenda[] = ['nombre' => 'Red directa de ' . strtok($cand['nombre'] ?? 'la campaña', ' '), 'g' => 0, 'ids' => $direccion];

            // Solo quien tiene red (o es líder/coordinador) aparece; el color sigue al
            // miembro por su id (fijo): posiciones 2-8 de la paleta y el resto en gris.
            $conRed = array_flip(array_map('intval', array_column($simp, 'lider_id')));
            $slot = 1;
            foreach ($equipo as $m) {
                $id = (int)$m['id'];
                if (!isset($conRed[$id]) && !in_array($m['rol'], ['lider', 'coordinador'], true)) continue;
                $grupoDe[$id] = $slot < 8 ? $slot : -1;
                $agregar('u' . $id, $m['nombre'], 'equipo', $grupoDe[$id], ['nc' => $m['nombre'], 'r' => $roles[$m['rol']] ?? $m['rol']]);
                $leyenda[] = ['nombre' => $m['nombre'], 'g' => $grupoDe[$id], 'ids' => [$id]];
                $slot++;
            }
            foreach ($equipo as $m) {
                if (!isset($indice['u' . $m['id']])) continue;
                $padre = $m['coordinador_id'] && isset($indice['u' . $m['coordinador_id']]) && (int)$m['coordinador_id'] !== (int)$m['id']
                    ? $indice['u' . $m['coordinador_id']] : $raiz;
                $enlaces[] = [$indice['u' . $m['id']], $padre];
            }
        }

        // Simpatizantes: primero todos los nodos, luego los enlaces (el padre puede venir después)
        foreach ($simp as $s) {
            $agregar('s' . $s['id'], promotor_nombre_corto($s['nombre']), 'simp', $grupoDe[(int)$s['lider_id']] ?? -1, [
                'id' => (int)$s['id'], 'nc' => $s['nombre'], 'z' => $s['zona'] ?? null, 'c' => $s['nivel'] ?? null,
                'p' => isset($s['puntos']) ? (int)$s['puntos'] : null, 'f' => isset($s['created_at']) ? substr((string)$s['created_at'], 0, 10) : null,
            ]);
        }
        $conteoLeyenda = [];
        foreach ($simp as $s) {
            $hijo = $indice['s' . $s['id']];
            if ($s['referido_por'] && isset($indice['s' . $s['referido_por']])) {
                $padre = $indice['s' . $s['referido_por']];
                $nodos[$padre]['v']++;
            } else {
                $padre = $indice['u' . $s['lider_id']] ?? $raiz;
            }
            $enlaces[] = [$hijo, $padre];
            $conteoLeyenda[(int)$s['lider_id']] = ($conteoLeyenda[(int)$s['lider_id']] ?? 0) + 1;
        }
        foreach ($leyenda as &$l) {
            $l['total'] = array_sum(array_map(fn($id) => $conteoLeyenda[$id] ?? 0, $l['ids']));
            unset($l['ids']);
        }
        unset($l);

        return [
            'nodos'    => $nodos,
            'enlaces'  => $enlaces,
            'leyenda'  => $leyenda,
            'mostrados' => count($simp),
            'total'    => $this->total(),
        ];
    }
}
