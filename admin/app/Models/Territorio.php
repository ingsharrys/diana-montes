<?php
namespace Models;

use Core\Model;

/**
 * Centro de mando › Territorio: votos seguros por puesto de votación frente
 * a la meta de votos de la campaña, repartida según el potencial electoral
 * (habilitados) de cada puesto.
 */
class Territorio extends Model
{
    private ?int $lider;

    public function __construct(?int $soloLiderId = null)
    {
        parent::__construct();
        $this->lider = $soloLiderId;
    }

    public function conPotencial(): bool
    {
        return esquema_tiene($this->db, 'puestos_votacion', 'potencial');
    }

    private function alcance(): array
    {
        return $this->lider === null ? ['', []] : [' AND s.lider_id = :lider', ['lider' => $this->lider]];
    }

    /** Lista SQL de los niveles que cuentan como voto seguro. */
    private function seguros(): string
    {
        return "'" . implode("','", COMPROMISOS_SEGUROS) . "'";
    }

    /** Cada puesto con sus mesas, potencial, registrados y votos seguros. */
    public function porPuesto(): array
    {
        [$w, $p] = $this->alcance();
        $extra = $this->conPotencial() ? 'p.mesas, p.potencial' : 'NULL AS mesas, NULL AS potencial';
        return $this->consultar(
            "SELECT p.id, p.nombre, z.nombre AS zona, $extra,
                    COALESCE(a.registrados, 0) AS registrados, COALESCE(a.seguros, 0) AS seguros
             FROM puestos_votacion p
             LEFT JOIN zonas z ON z.id = p.zona_id
             LEFT JOIN (SELECT s.puesto_id, COUNT(*) AS registrados, SUM(s.nivel IN (" . $this->seguros() . ")) AS seguros
                        FROM simpatizantes s WHERE s.puesto_id IS NOT NULL $w GROUP BY s.puesto_id) a ON a.puesto_id = p.id
             ORDER BY p.nombre", $p
        );
    }

    /** Totales de la base: registrados, votos seguros y cuántos no tienen puesto aún. */
    public function totales(): array
    {
        [$w, $p] = $this->alcance();
        $f = $this->consultarUno(
            "SELECT COUNT(*) AS registrados,
                    COALESCE(SUM(s.nivel IN (" . $this->seguros() . ")), 0) AS seguros,
                    COALESCE(SUM(s.puesto_id IS NULL), 0) AS sin_puesto
             FROM simpatizantes s WHERE 1=1 $w", $p
        );
        return array_map('intval', $f);
    }

    public function actualizarPuesto(int $id, ?int $mesas, ?int $potencial): bool
    {
        return $this->ejecutar(
            'UPDATE puestos_votacion SET mesas = :m, potencial = :p WHERE id = :id',
            ['m' => $mesas, 'p' => $potencial, 'id' => $id]
        ) >= 0;
    }

    /**
     * Reparte la meta de votos entre los puestos según su potencial y calcula
     * avance y brecha. Ordena por brecha (dónde falta más) de mayor a menor.
     */
    public static function conMetas(array $puestos, int $metaVotos): array
    {
        $potencialTotal = array_sum(array_map(fn($p) => (int)$p['potencial'], $puestos));
        foreach ($puestos as &$p) {
            $p['meta'] = ($metaVotos > 0 && $potencialTotal > 0 && $p['potencial'])
                ? (int)round($metaVotos * $p['potencial'] / $potencialTotal) : null;
            $p['avance'] = $p['meta'] ? min(100, $p['seguros'] * 100 / $p['meta']) : null;
            $p['brecha'] = $p['meta'] !== null ? max(0, $p['meta'] - (int)$p['seguros']) : null;
            $p['cobertura'] = $p['potencial'] ? $p['registrados'] * 100 / $p['potencial'] : null;
        }
        unset($p);
        usort($puestos, fn($a, $b) => [($b['brecha'] ?? -1), $a['nombre']] <=> [($a['brecha'] ?? -1), $b['nombre']]);
        return $puestos;
    }
}
