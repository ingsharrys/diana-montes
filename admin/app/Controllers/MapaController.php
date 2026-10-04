<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\Simpatizante;

/**
 * Mapa de simpatizantes que compartieron su ubicación aproximada.
 * El líder solo ve su propia red (misma regla que el listado).
 */
class MapaController extends Controller
{
    public function index(): void
    {
        Auth::requerir();

        $simp   = new Simpatizante();
        $activa = $simp->redPromotoresActiva();
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        $this->vista('mapa/index', [
            'titulo' => 'Mapa de la red',
            'activa' => $activa,
            'puntos' => $activa ? $simp->puntosMapa($soloLider) : [],
        ]);
    }
}
