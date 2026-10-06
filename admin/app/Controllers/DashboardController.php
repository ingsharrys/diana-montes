<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\Simpatizante;
use Models\Usuario;
use Models\Insights;
use Models\Configuracion;
use Models\Auditoria;

/**
 * Centro de mando › Vista rápida.
 * El líder ve todo limitado a su red y con su propia meta; el resto del
 * equipo ve la campaña completa y la meta de inscripción de la campaña.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requerir();

        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;
        $ins       = new Insights($soloLider);
        $simp      = new Simpatizante();
        $usuarios  = new Usuario();
        $config    = new Configuracion();
        $total     = $ins->total();

        // Meta: la del líder (tabla metas) o la de inscripción de la campaña
        $miCuenta = $usuarios->porId((int)Auth::id());
        $avance   = (!empty($miCuenta['codigo_ref']) && Auth::usuario()['rol'] !== 'direccion')
            ? $usuarios->avance((int)Auth::id()) : null;
        $meta = $soloLider ? (int)($avance['meta'] ?? 0) : (int)$config->obtener('meta_inscripcion', '0');

        $red = $simp->redPromotoresActiva();

        $this->vista('dashboard/index', [
            'titulo'        => 'Vista rápida',
            'soloLider'     => $soloLider !== null,
            'total'         => $total,
            'meta'          => $meta,
            'avance'        => $avance,
            'puedeEditarMeta' => !$soloLider && Auth::tieneRol('direccion') && $config->disponible(),
            'porNivel'      => $ins->porNivelRed(),
            'compromiso'    => $ins->porCompromiso(),
            'calidad'       => $ins->calidad(),
            'metaVotos'     => $soloLider ? 0 : (int)$config->obtener('meta_votos', '0'),
            'promotores'    => $ins->promotores(),
            'grafo'         => $ins->grafoRed(500),
            'puntos'        => $red ? $simp->puntosMapa($soloLider) : [],
            'redActiva'     => $red,
            'genero'        => $ins->porGenero(),
            'edad'          => $ins->porEdad(),
            'semana'        => $ins->ultimos7Dias(),
            'topPromotores' => $red ? $simp->rankingPromotores(6, $soloLider) : [],
            'rankingEquipo' => Auth::tieneRol('direccion', 'coordinador') ? $usuarios->rankingEquipo(6) : [],
            'porZona'       => $simp->contarPorZona(),
            'auditoria'     => Auth::tieneRol('direccion') ? (new Auditoria())->recientes(8) : [],
            'pendientes'    => Auth::tieneRol('direccion') ? esquema_pendientes(Database::conexion()) : [],
        ]);
    }

    /** Aplica las actualizaciones pendientes de la base de datos (idempotente). */
    public function actualizar(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('dashboard');
        $this->validarCsrf();

        try {
            $avisos = [];
            $pasos = esquema_actualizar(Database::conexion(), $avisos);
            Auditoria::registrar('plataforma_actualizada', implode(', ', $pasos) ?: 'sin cambios');
            Session::flash('ok', 'Plataforma actualizada: red de promotores, mapa, género, nivel en la red y meta de la campaña listos.');
            if ($avisos) Session::flash('error', implode(' ', $avisos));
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo actualizar automáticamente (' . $e->getMessage() . '). '
                . 'Ejecuta en phpMyAdmin los archivos de admin/sql/ (en orden) y vuelve a intentarlo.');
        }
        $this->redirigir('dashboard');
    }

    /** Define la meta de inscripción de simpatizantes de la campaña. */
    public function meta(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('dashboard');
        $this->validarCsrf();

        $meta = (int)preg_replace('/\D/', '', (string)($_POST['meta'] ?? ''));
        $config = new Configuracion();
        if ($meta < 1 || $meta > 10000000 || !$config->disponible()) {
            Session::flash('error', 'Escribe una meta válida (un número entre 1 y 10.000.000).');
        } else {
            $config->guardar('meta_inscripcion', (string)$meta);
            Auditoria::registrar('meta_actualizada', 'Meta de inscripción: ' . $meta);
            Session::flash('ok', 'Meta de la campaña actualizada a ' . num($meta) . ' simpatizantes.');
        }
        $this->redirigir('dashboard');
    }
}
