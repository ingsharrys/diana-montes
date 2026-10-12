<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Pronostico;
use Models\Configuracion;
use Models\Usuario;
use Models\Auditoria;

/**
 * Centro de mando › Metas y pronóstico: días que faltan, meta diaria,
 * semanal y mensual, ritmo real y a dónde se llega si se sigue así.
 */
class MetasController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion', 'coordinador');
        $config = new Configuracion();
        $pron = new Pronostico();
        $eleccion = $config->obtener('fecha_eleccion', Pronostico::FECHA_ELECCION);
        $limite = $config->obtener('fecha_meta', $eleccion);
        $tot = $pron->totales();
        $c = Pronostico::calcular(
            $pron->registrosPorDia(), $tot['total'], $tot['seguros'],
            (int)$config->obtener('meta_inscripcion', '0'), (int)$config->obtener('meta_votos', '0'),
            date('Y-m-d'), $limite
        );

        // Meta diaria de cada miembro del equipo que tiene meta asignada
        $equipo = [];
        foreach ((new Usuario())->rankingEquipo(200) as $m) {
            if (!(int)$m['meta']) continue;
            $falta = max(0, (int)$m['meta'] - (int)$m['vinculados']);
            $equipo[] = $m + ['faltan' => $falta, 'por_dia' => $c['restantes'] ? (int)ceil($falta / $c['restantes']) : $falta,
                              'por_semana' => $c['restantes'] ? min($falta, (int)ceil($falta / $c['restantes'] * 7)) : $falta];
        }
        usort($equipo, fn($a, $b) => $b['por_dia'] <=> $a['por_dia']);

        $this->vista('metas/index', [
            'titulo' => 'Metas y pronóstico',
            'c' => $c,
            'eleccion' => $eleccion,
            'diasEleccion' => max(0, (int)(new \DateTimeImmutable(date('Y-m-d')))->diff(new \DateTimeImmutable($eleccion))->format('%r%a')),
            'equipo' => $equipo,
            'puedeEditar' => Auth::tieneRol('direccion') && $config->disponible(),
        ]);
    }

    /** Fecha de elecciones, fecha límite para cumplir la meta y meta de simpatizantes. */
    public function ajustes(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('metas');
        $this->validarCsrf();
        $config = new Configuracion();
        $fecha = fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v = (string)($_POST[$k] ?? '')) && strtotime($v) ? $v : null;
        $eleccion = $fecha('fecha_eleccion');
        $limite = $fecha('fecha_meta') ?? $eleccion;
        $meta = (int)preg_replace('/\D/', '', (string)($_POST['meta'] ?? ''));

        if (!$config->disponible()) Session::flash('error', 'Primero pulsa "Actualizar plataforma" en la Vista rápida.');
        elseif (!$eleccion || $eleccion <= date('Y-m-d')) Session::flash('error', 'La fecha de elecciones debe ser una fecha futura.');
        elseif ($limite <= date('Y-m-d') || $limite > $eleccion) Session::flash('error', 'La fecha para cumplir la meta debe estar entre mañana y el día de elecciones.');
        elseif ($meta < 1 || $meta > 10000000) Session::flash('error', 'Escribe una meta de simpatizantes válida.');
        else {
            $config->guardar('fecha_eleccion', $eleccion);
            $config->guardar('fecha_meta', $limite);
            $config->guardar('meta_inscripcion', (string)$meta);
            Auditoria::registrar('metas_actualizadas', "Meta $meta simpatizantes al $limite · elecciones $eleccion");
            Session::flash('ok', 'Listo: meta de ' . num($meta) . ' simpatizantes para el ' . fecha_corta($limite) . '.');
        }
        $this->redirigir('metas');
    }
}
