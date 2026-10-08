<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\Insights;

/** Centro de mando › Red de contactos: el grafo completo de quién trajo a quién. */
class RedController extends Controller
{
    public function index(): void
    {
        Auth::requerir();
        // El líder solo ve su propia red (misma regla que el listado y el mapa)
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        $this->vista('red/index', [
            'titulo' => 'Red de contactos',
            'grafo'  => (new Insights($soloLider))->grafoRed(2500, true),
            'esLider' => $soloLider !== null,
        ]);
    }
}
