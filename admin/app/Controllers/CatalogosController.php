<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Catalogo;
use Models\Auditoria;

/**
 * Catálogos que alimentan la landing y los formularios:
 * zonas (barrio/vereda), puestos de votación y profesiones.
 * Solo el rol dirección puede administrarlos.
 */
class CatalogosController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion');
        $cat = new Catalogo();
        $this->vista('catalogos/index', [
            'titulo'      => 'Catálogos de la landing',
            'zonas'       => $cat->zonasConUso(),
            'puestos'     => $cat->puestosConUso(),
            'profesiones' => $cat->profesionesConUso(),
            'zonasSimple' => $cat->zonas(),
            'gruposZona'  => $cat->gruposZona(),
        ]);
    }

    public function zona(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('catalogos');
        $this->validarCsrf();

        $nombre = limpiar_texto_catalogo((string)($_POST['nombre'] ?? '')) ?? '';
        $tipo   = ($_POST['tipo'] ?? '') === 'rural' ? 'rural' : 'urbano';
        $grupo  = limpiar_texto_catalogo((string)($_POST['grupo'] ?? ''), 60);

        if (mb_strlen($nombre) < 3) { Session::flash('error', 'Escribe el nombre del barrio o vereda (letras y números, sin símbolos raros).'); $this->redirigir('catalogos'); }

        if ((new Catalogo())->crearZona($nombre, $tipo, $grupo)) {
            Auditoria::registrar('catalogo_zona_creada', $nombre . ' (' . $tipo . ')');
            Session::flash('ok', 'Zona "' . $nombre . '" creada: ya aparece en la landing.');
        } else {
            Session::flash('error', 'Esa zona ya existe.');
        }
        $this->redirigir('catalogos');
    }

    public function puesto(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('catalogos');
        $this->validarCsrf();

        $nombre    = limpiar_texto_catalogo((string)($_POST['nombre'] ?? ''), 150) ?? '';
        $direccion = trim((string)($_POST['direccion'] ?? ''));
        if ($direccion !== '' && ($direccion = limpiar_texto_catalogo($direccion, 200)) === null) {
            Session::flash('error', 'Revisa la dirección: solo letras, números y # - . , /'); $this->redirigir('catalogos');
        }
        $direccion = $direccion ?: null;
        $zonaId    = (int)($_POST['zona_id'] ?? 0) ?: null;

        if (mb_strlen($nombre) < 3) { Session::flash('error', 'Escribe el nombre del puesto de votación (letras y números, sin símbolos raros).'); $this->redirigir('catalogos'); }

        if ((new Catalogo())->crearPuesto($nombre, $direccion, $zonaId)) {
            Auditoria::registrar('catalogo_puesto_creado', $nombre);
            Session::flash('ok', 'Puesto "' . $nombre . '" creado: ya aparece en la landing.');
        } else {
            Session::flash('error', 'Ese puesto ya existe.');
        }
        $this->redirigir('catalogos');
    }

    public function profesion(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('catalogos');
        $this->validarCsrf();

        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
        if (!preg_match('/^[\p{L}\p{M} ()\/]{3,100}$/u', $nombre)) $nombre = '';
        $dia    = trim((string)($_POST['dia_celebracion'] ?? ''));
        $dia    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? $dia : null;

        if (mb_strlen($nombre) < 3) { Session::flash('error', 'Escribe el nombre de la profesión u ocupación (solo letras).'); $this->redirigir('catalogos'); }

        if ((new Catalogo())->crearProfesion($nombre, $dia)) {
            Auditoria::registrar('catalogo_profesion_creada', $nombre);
            Session::flash('ok', 'Profesión "' . $nombre . '" creada: ya aparece en la landing.');
        } else {
            Session::flash('error', 'Esa profesión ya existe.');
        }
        $this->redirigir('catalogos');
    }

    /** Eliminar ítem de catálogo: solo si nadie lo usa (las llaves foráneas protegen). */
    public function eliminar(string $tabla = '', string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('catalogos');
        $this->validarCsrf();

        if ((new Catalogo())->eliminar($tabla, (int)$id)) {
            Auditoria::registrar('catalogo_eliminado', $tabla . ' #' . $id);
            Session::flash('ok', 'Elemento eliminado del catálogo.');
        } else {
            Session::flash('error', 'No se puede eliminar: hay personas registradas con ese dato.');
        }
        $this->redirigir('catalogos');
    }
}
