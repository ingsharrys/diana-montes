<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use DomainException;
use Models\Auditoria;
use Models\Historia;

/**
 * Historias ciudadanas: la dirección registra, revisa, aprueba o rechaza los
 * relatos de la gente. Solo se aprueban los que tienen autorización de publicación.
 */
class HistoriasController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion');
        $m = new Historia();
        if (!$m->disponible()) {
            $this->vista('historias/index', ['titulo' => 'Historias', 'listo' => false]);
            return;
        }

        $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
        $estado = isset(Historia::ESTADOS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $editar = (int)($_GET['editar'] ?? 0);

        $this->vista('historias/index', [
            'titulo'  => 'Historias',
            'listo'   => true,
            'q'       => $q,
            'estado'  => $estado,
            'listado' => $m->listar($q, $estado, $pagina),
            'conteo'  => $m->conteo(),
            'zonas'   => $m->zonas(),
            'editar'  => $editar ? $m->obtener($editar) : null,
            'nuevo'   => ($_GET['nuevo'] ?? '') === '1',
            'borrador'=> Session::get('historia_borrador') ?: [],
        ]);
        Session::set('historia_borrador', null);
    }

    /** POST historias/guardar — crea (sin id) o actualiza (con id). */
    public function guardar(): void
    {
        $this->post();
        $id = (int)($_POST['id'] ?? 0);
        try {
            $datos = $this->datos($id === 0);
            $m = new Historia();
            if ($id === 0) {
                $id = $m->crear($datos);
                Auditoria::registrar('historia_creada', 'Historia #' . $id . ' de ' . $datos['nombre']);
                Session::flash('ok', 'Historia registrada (#' . $id . '). Queda pendiente de revisión.');
            } else {
                $m->actualizar($id, $datos);
                Auditoria::registrar('historia_editada', 'Historia #' . $id . ($datos['revocar_publicacion'] ? ' · publicación revocada' : ''));
                Session::flash('ok', 'Historia #' . $id . ' actualizada.');
            }
            $this->redirigir('historias');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            Session::set('historia_borrador', $_POST);
            header('Location: ' . url_con('historias', $id ? ['editar' => $id] : ['nuevo' => 1]));
            exit;
        }
    }

    /** POST historias/estado — aprobar, rechazar o devolver a pendiente. */
    public function estado(): void
    {
        $this->post();
        $id = (int)($_POST['id'] ?? 0);
        $estado = (string)($_POST['estado'] ?? '');
        try {
            (new Historia())->cambiarEstado($id, $estado);
            Auditoria::registrar('historia_estado', 'Historia #' . $id . ' → ' . $estado);
            Session::flash('ok', 'Historia #' . $id . ': ' . mb_strtolower(Historia::ESTADOS[$estado]) . '.');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->volver();
    }

    /** POST historias/eliminar — borra la historia definitivamente. */
    public function eliminar(): void
    {
        $this->post();
        $id = (int)($_POST['id'] ?? 0);
        if ((new Historia())->eliminar($id)) {
            Auditoria::registrar('historia_eliminada', 'Historia #' . $id);
            Session::flash('ok', 'Historia #' . $id . ' eliminada.');
        } else {
            Session::flash('error', 'La historia ya no existe.');
        }
        $this->volver();
    }

    private function post(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('historias');
        $this->validarCsrf();
    }

    /** Regresa al listado con los mismos filtros. */
    private function volver(): void
    {
        $q = array_intersect_key($_POST, array_flip(['q', 'estado', 'pagina']));
        header('Location: ' . url_con('historias', array_map('strval', $q)));
        exit;
    }

    private function datos(bool $creando): array
    {
        $nombre  = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')) ?? '');
        $mensaje = trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['mensaje'] ?? '')));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) throw new DomainException('El nombre debe tener entre 3 y 120 caracteres.');
        if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 5000) throw new DomainException('El relato debe tener entre 10 y 5.000 caracteres.');

        $zona = (int)($_POST['zona_id'] ?? 0);
        $estado = $creando ? 'pendiente' : (string)($_POST['estado'] ?? '');
        if (!isset(Historia::ESTADOS[$estado])) throw new DomainException('El estado seleccionado no es válido.');

        return [
            'nombre'               => $nombre,
            'mensaje'              => $mensaje,
            'zona_id'              => $zona > 0 ? $zona : null,
            'estado'               => $estado,
            'consentimiento_datos' => $creando && ($_POST['consentimiento_datos'] ?? '') === '1',
            'autoriza_publicacion' => $creando && ($_POST['autoriza_publicacion'] ?? '') === '1',
            'revocar_publicacion'  => !$creando && ($_POST['revocar_publicacion'] ?? '') === '1',
        ];
    }
}
