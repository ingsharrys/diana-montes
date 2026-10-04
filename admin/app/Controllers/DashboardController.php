<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\Simpatizante;
use Models\Usuario;
use Models\Auditoria;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requerir();

        $simp   = new Simpatizante();
        $yo     = Auth::usuario();
        $red    = $simp->redPromotoresActiva();
        // El líder solo ve los promotores de SU red
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        // "Mi avance": para quien tiene enlace de invitación y no es la dirección
        $usuarios = new Usuario();
        $miCuenta = $usuarios->porId((int)Auth::id());
        $avance   = (!empty($miCuenta['codigo_ref']) && $yo['rol'] !== 'direccion')
            ? $usuarios->avance((int)Auth::id()) : null;

        $this->vista('dashboard/index', [
            'titulo'          => 'Panel',
            'totalSimp'       => $simp->contar(),
            'nuevosSemana'    => $simp->registrosUltimaSemana(),
            'porZona'         => $simp->contarPorZona(),
            'redActiva'       => $red,
            'promotores'      => $red ? $simp->contarPromotoresActivos($soloLider) : 0,
            'conUbicacion'    => $red ? $simp->contarConUbicacion($soloLider) : 0,
            'topPromotores'   => $red ? $simp->rankingPromotores(8, $soloLider) : [],
            'avance'          => $avance,
            'rankingEquipo'   => Auth::tieneRol('direccion', 'coordinador') ? $usuarios->rankingEquipo(5) : [],
            'auditoria'       => Auth::tieneRol('direccion') ? (new Auditoria())->recientes(8) : [],
            'puedeActivarRed' => !$red && Auth::tieneRol('direccion'),
        ]);
    }

    /** Activa la red de promotores y el mapa (agrega las columnas en la BD). */
    public function activarred(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('dashboard');
        $this->validarCsrf();

        try {
            $pasos = promotor_migrar(Database::conexion());
            Auditoria::registrar('red_promotores_activada', implode(', ', $pasos) ?: 'sin cambios');
            Session::flash('ok', 'Red de Súper Promotores y mapa activados. Cada simpatizante ya tiene su enlace personal y su panel.');
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo activar automáticamente (' . $e->getMessage() . '). '
                . 'Ejecuta en phpMyAdmin el archivo admin/sql/001_red_promotores.sql y vuelve a intentarlo.');
        }
        $this->redirigir('dashboard');
    }
}
