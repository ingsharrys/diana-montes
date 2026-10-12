<?php
namespace Models;

use Core\Model;

/**
 * Centro de mando › Metas y pronóstico: cuántos días faltan, cuánto hay que
 * sumar por día, semana y mes para llegar a la meta, a qué ritmo se viene
 * sumando y a dónde se llega si se sigue así.
 */
class Pronostico extends Model
{
    /** Elecciones territoriales 2027: último domingo de octubre. La dirección puede cambiarla. */
    public const FECHA_ELECCION = '2027-10-31';
    private const DIAS_MES = 30.44;

    /** Registros de simpatizantes por día: ['2026-10-04' => 12, …]. */
    public function registrosPorDia(): array
    {
        $r = [];
        foreach ($this->consultar('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM simpatizantes GROUP BY DATE(created_at) ORDER BY d') as $f) {
            $r[$f['d']] = (int)$f['n'];
        }
        return $r;
    }

    public function totales(): array
    {
        $f = $this->consultarUno(
            "SELECT COUNT(*) AS total, COALESCE(SUM(nivel IN ('" . implode("','", COMPROMISOS_SEGUROS) . "')), 0) AS seguros FROM simpatizantes"
        );
        return ['total' => (int)$f['total'], 'seguros' => (int)$f['seguros']];
    }

    /**
     * Todo el cálculo (sin base de datos, para poder probarlo).
     * $porDia: registros por fecha · $hoy y $limite: 'Y-m-d' (fecha en que debe cumplirse la meta).
     */
    public static function calcular(array $porDia, int $total, int $seguros, int $meta, int $metaVotos, string $hoy, string $limite): array
    {
        $dHoy = new \DateTimeImmutable($hoy);
        $dLim = new \DateTimeImmutable($limite);
        $restantes = $dLim >= $dHoy ? (int)$dHoy->diff($dLim)->days + 1 : 0;   // incluye hoy

        ksort($porDia);
        $primera = array_key_first($porDia);
        $diasActivos = $primera ? (int)(new \DateTimeImmutable($primera))->diff($dHoy)->days + 1 : 0;
        $suma = function (int $dias) use ($porDia, $dHoy): int {
            $desde = $dHoy->modify('-' . ($dias - 1) . ' days')->format('Y-m-d');
            return array_sum(array_filter($porDia, fn($n, $d) => $d >= $desde && $d <= $dHoy->format('Y-m-d'), ARRAY_FILTER_USE_BOTH));
        };
        $c7 = $suma(7);
        $c30 = $suma(30);
        $ritmo7  = $diasActivos ? $c7 / min(7, $diasActivos) : 0.0;
        $ritmo30 = $diasActivos ? $c30 / min(30, $diasActivos) : 0.0;
        $ritmo   = $ritmo30 > 0 ? $ritmo30 : $ritmo7;                         // referencia: último mes

        $faltan = max(0, $meta - $total);
        // La cuota de hoy cuenta desde el comienzo del día: lo registrado hoy abona a ella
        $faltanHoy = max(0, $meta - ($total - ($porDia[$hoy] ?? 0)));
        $requerido = $restantes ? $faltanHoy / $restantes : (float)$faltan;   // por día, sin redondear
        $proyectar = fn(float $r) => (int)round($total + $r * $restantes);
        $pronostico = $proyectar($ritmo);

        if ($meta <= 0) $estado = 'sin_meta';
        elseif ($faltan === 0) $estado = 'cumplida';
        elseif ($pronostico >= $meta) $estado = 'en_camino';
        elseif ($pronostico >= $meta * 0.8) $estado = 'en_riesgo';
        else $estado = 'atrasada';

        // Votos seguros: con la proporción actual de registrados que ya son voto seguro
        $tasa = $total > 0 ? $seguros / $total : 0.0;
        $faltanVotos = max(0, $metaVotos - $seguros);

        return [
            'hoy' => $hoy, 'limite' => $limite, 'restantes' => $restantes,
            'dias_hasta' => max(0, $restantes - 1),                              // días que faltan (sin contar hoy)
            'semanas' => $restantes / 7, 'meses' => $restantes / self::DIAS_MES,
            'total' => $total, 'meta' => $meta, 'faltan' => $faltan,
            'pct' => $meta > 0 ? min(100, $total * 100 / $meta) : null,
            'por_dia' => $faltan ? (int)ceil($requerido) : 0,
            'por_semana' => min($faltanHoy, (int)ceil($requerido * 7)),
            'por_mes' => min($faltanHoy, (int)ceil($requerido * self::DIAS_MES)),
            'requerido' => $requerido,
            'hoy_n' => $porDia[$hoy] ?? 0, 'ult7' => $c7, 'ult30' => $c30,
            'ritmo7' => $ritmo7, 'ritmo30' => $ritmo30, 'ritmo' => $ritmo, 'dias_activos' => $diasActivos,
            'pronostico' => $pronostico,
            'pron_min' => $proyectar(min($ritmo7, $ritmo30)), 'pron_max' => $proyectar(max($ritmo7, $ritmo30)),
            'multiplicador' => $ritmo > 0 && $faltan > 0 ? $requerido / $ritmo : null,
            'fecha_alcanza' => $ritmo > 0 && $faltan > 0 ? $dHoy->modify('+' . (int)ceil($faltan / $ritmo - 1) . ' days')->format('Y-m-d') : null,
            'estado' => $estado,
            'seguros' => $seguros, 'meta_votos' => $metaVotos, 'faltan_votos' => $faltanVotos, 'tasa' => $tasa,
            'votos_por_dia' => $restantes ? (int)ceil($faltanVotos / $restantes) : $faltanVotos,
            'pron_votos' => (int)round($pronostico * $tasa),
            'registros_para_votos' => $tasa > 0 && $metaVotos > 0 ? (int)ceil($metaVotos / $tasa) : null,
            'meses_plan' => self::planMensual($porDia, $dHoy, $dLim, $requerido),
            'serie' => self::acumulado($porDia, $total),
        ];
    }

    /** Cada mes, desde el primer registro hasta la fecha límite: real, meta del mes y acumulado esperado al cierre. */
    private static function planMensual(array $porDia, \DateTimeImmutable $hoy, \DateTimeImmutable $lim, float $requerido): array
    {
        $porMes = [];
        foreach ($porDia as $d => $n) $porMes[substr($d, 0, 7)] = ($porMes[substr($d, 0, 7)] ?? 0) + $n;
        $inicio = $porMes ? new \DateTimeImmutable(array_key_first($porMes) . '-01') : $hoy->modify('first day of this month');
        $fin = max($lim, $hoy)->modify('first day of this month');
        $antesDeHoy = array_sum(array_filter($porDia, fn($d) => $d < $hoy->format('Y-m-d'), ARRAY_FILTER_USE_KEY));
        $acum = 0; $esperado = $antesDeHoy;
        $meses = [];
        for ($m = $inicio; $m <= $fin; $m = $m->modify('+1 month')) {
            $clave = $m->format('Y-m');
            $real = $porMes[$clave] ?? 0;
            $acum += $real;
            $finMes = $m->modify('last day of this month');
            $desde = max($m, $hoy);
            $hasta = min($finMes, $lim);
            $diasPlan = $hasta >= $desde ? (int)$desde->diff($hasta)->days + 1 : 0;
            $previo = $clave === $hoy->format('Y-m')
                ? array_sum(array_filter($porDia, fn($d) => str_starts_with($d, $clave) && $d < $hoy->format('Y-m-d'), ARRAY_FILTER_USE_KEY)) : 0;
            $metaMes = $finMes < $hoy ? null : (int)ceil($previo + $requerido * $diasPlan);
            if ($metaMes !== null) $esperado += $requerido * $diasPlan;
            $meses[] = [
                'mes' => $clave, 'real' => $real, 'meta' => $metaMes, 'dias_plan' => $diasPlan,
                'acumulado' => $finMes < $hoy ? $acum : null,
                'esperado' => $metaMes !== null ? (int)ceil($esperado) : null,
                'pasado' => $finMes < $hoy, 'actual' => $clave === $hoy->format('Y-m'),
            ];
        }
        return $meses;
    }

    /** Acumulado de registros por día (para la gráfica). El total real manda si hay registros sin fecha. */
    private static function acumulado(array $porDia, int $total): array
    {
        $serie = []; $a = $total - array_sum($porDia);
        foreach ($porDia as $d => $n) { $a += $n; $serie[$d] = $a; }
        return $serie;
    }
}
