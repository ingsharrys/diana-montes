<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Territorio;
use Models\Configuracion;
use Models\Auditoria;

/**
 * Centro de mando › Territorio: dónde están (y dónde faltan) los votos.
 * El líder ve sus números por puesto; la meta por puesto es de la campaña.
 */
class TerritorioController extends Controller
{
    public function index(): void
    {
        Auth::requerir();
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        $ter    = new Territorio($soloLider);
        $config = new Configuracion();
        $metaVotos = $soloLider ? 0 : (int)$config->obtener('meta_votos', '0');
        $puestos   = Territorio::conMetas($ter->porPuesto(), $metaVotos);

        $this->vista('territorio/index', [
            'titulo'       => 'Territorio',
            'soloLider'    => $soloLider !== null,
            'puestos'      => $puestos,
            'totales'      => $ter->totales(),
            'metaVotos'    => $metaVotos,
            'conPotencial' => $ter->conPotencial(),
            'puedeEditar'  => Auth::tieneRol('direccion') && $config->disponible() && $ter->conPotencial(),
            'sinPotencial' => count(array_filter($puestos, fn($p) => !$p['potencial'])),
            'potencialTotal' => array_sum(array_map(fn($p) => (int)$p['potencial'], $puestos)),
        ]);
    }

    /** Meta de votos para ganar (la reparte entre los puestos según su potencial). */
    public function meta(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('territorio');
        $this->validarCsrf();

        $meta = (int)preg_replace('/\D/', '', (string)($_POST['meta_votos'] ?? ''));
        $config = new Configuracion();
        if ($meta < 1 || $meta > 10000000 || !$config->disponible()) {
            Session::flash('error', 'Escribe una meta de votos válida.');
        } else {
            $config->guardar('meta_votos', (string)$meta);
            Auditoria::registrar('meta_votos_actualizada', 'Meta de votos: ' . $meta);
            Session::flash('ok', 'Meta de votos para ganar: ' . num($meta) . '. Ya está repartida por puesto.');
        }
        $this->redirigir('territorio');
    }

    /** Mesas y potencial electoral de un puesto (datos de la Registraduría). */
    public function puesto(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('territorio');
        $this->validarCsrf();

        $mesas     = (int)preg_replace('/\D/', '', (string)($_POST['mesas'] ?? '')) ?: null;
        $potencial = (int)preg_replace('/\D/', '', (string)($_POST['potencial'] ?? '')) ?: null;
        if (($mesas !== null && $mesas > 999) || ($potencial !== null && $potencial > 1000000)) {
            Session::flash('error', 'Revisa las mesas o el potencial del puesto.');
            $this->redirigir('territorio');
        }

        (new Territorio())->actualizarPuesto((int)$id, $mesas, $potencial);
        Auditoria::registrar('puesto_actualizado', 'Puesto #' . (int)$id . ": $mesas mesas, $potencial habilitados");
        Session::flash('ok', 'Puesto actualizado.');
        $this->redirigir('territorio');
    }
}
