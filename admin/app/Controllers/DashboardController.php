<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\Simpatizante;
use Models\Auditoria;

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requerir();

        $simp = new Simpatizante();

        $this->vista('dashboard/index', [
            'titulo'        => 'Panel',
            'totalSimp'     => $simp->contar(),
            'nuevosSemana'  => $simp->registrosUltimaSemana(),
            'porZona'       => $simp->contarPorZona(),
            'auditoria'     => Auth::tieneRol('direccion') ? (new Auditoria())->recientes(8) : [],
        ]);
    }
}
