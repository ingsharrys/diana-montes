<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\Simpatizante;
use Models\Catalogo;
use Models\Auditoria;

class SimpatizantesController extends Controller
{
    public function index(): void
    {
        Auth::requerir();

        $busqueda  = trim($_GET['q'] ?? '');
        $profesion = (int)($_GET['profesion'] ?? 0);

        // Seguridad por roles: el líder SOLO ve su propia red
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        $modelo = new Simpatizante();

        // Enlace de invitación del usuario logueado (su codigo_ref = su celular).
        // Si no tiene (p. ej. el admin), se usa el enlace general de la red de la candidata.
        $yo = (new \Models\Usuario())->porId((int)Auth::id());
        $miCodigo = $yo['codigo_ref'] ?? null;
        $miEnlace = LANDING_URL . '/?ref=' . ($miCodigo ?: 'diana') . '#sumate';

        $this->vista('simpatizantes/index', [
            'titulo'       => 'Simpatizantes',
            'lista'        => $modelo->listar($busqueda, $profesion, $soloLider),
            'profesiones'  => (new Catalogo())->profesiones(),
            'busqueda'     => $busqueda,
            'profesionSel' => $profesion,
            'esDireccion'  => Auth::tieneRol('direccion', 'coordinador'),
            'miEnlace'     => $miEnlace,
            'esEnlacePropio' => (bool)$miCodigo,
        ]);
    }

    public function crear(): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');

        $cat = new Catalogo();
        $this->vista('simpatizantes/crear', [
            'titulo'      => 'Nuevo simpatizante',
            'zonas'       => $cat->zonas(),
            'profesiones' => $cat->profesiones(),
            'puestos'     => $cat->puestos(),
            'lideres'     => $cat->lideres(),
            'errores'     => [],
            'v'           => [], // valores previos si el formulario falla
        ]);
    }

    public function guardar(): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');
        if (!$this->esPost()) $this->redirigir('simpatizantes/crear');
        $this->validarCsrf();

        $v = [
            'nombre'           => trim($_POST['nombre'] ?? ''),
            'documento'        => preg_replace('/\D/', '', $_POST['documento'] ?? ''),
            'telefono'         => preg_replace('/\D/', '', $_POST['telefono'] ?? ''),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            'zona_id'          => (int)($_POST['zona_id'] ?? 0),
            'puesto_id'        => (int)($_POST['puesto_id'] ?? 0) ?: null,
            'mesa'             => trim($_POST['mesa'] ?? '') ?: null,
            'profesion_id'     => (int)($_POST['profesion_id'] ?? 0),
            'nivel'            => in_array($_POST['nivel'] ?? '', ['simpatizante','voluntario','votante_confirmado'], true)
                                    ? $_POST['nivel'] : 'simpatizante',
            'lider_id'         => (int)($_POST['lider_id'] ?? 0),
        ];

        // El líder solo puede vincular a SU red
        if (Auth::tieneRol('lider')) $v['lider_id'] = Auth::id();

        $modelo  = new Simpatizante();
        $errores = $this->validar($v, $modelo);

        if (empty($_POST['consentimiento'])) {
            $errores['consentimiento'] = 'Sin la autorización de datos (Ley 1581 de 2012) no se puede guardar el registro.';
        }

        if ($errores) {
            $cat = new Catalogo();
            $this->vista('simpatizantes/crear', [
                'titulo' => 'Nuevo simpatizante',
                'zonas' => $cat->zonas(), 'profesiones' => $cat->profesiones(),
                'puestos' => $cat->puestos(), 'lideres' => $cat->lideres(),
                'errores' => $errores, 'v' => $v,
            ]);
            return;
        }

        $v['created_by'] = Auth::id();
        $modelo->crear($v);

        Auditoria::registrar('simpatizante_creado', 'Documento ' . enmascarar($v['documento']) . ' · zona ' . $v['zona_id']);
        \Core\Session::flash('ok', 'Registro guardado correctamente. ¡Gracias por hacer crecer la campaña!');
        $this->redirigir('simpatizantes');
    }

    private function validar(array $v, Simpatizante $modelo): array
    {
        $e = [];
        if (mb_strlen($v['nombre']) < 5)                  $e['nombre'] = 'Escribe el nombre completo.';
        if (strlen($v['documento']) < 6)                  $e['documento'] = 'Documento inválido.';
        elseif ($modelo->existeDocumento($v['documento'])) $e['documento'] = 'Este documento ya está registrado. No se permiten duplicados.';
        if (strlen($v['telefono']) !== 10)                $e['telefono'] = 'El celular debe tener 10 dígitos.';
        if ($v['zona_id'] <= 0)                           $e['zona_id'] = 'Selecciona la zona.';
        if ($v['profesion_id'] <= 0)                      $e['profesion_id'] = 'Selecciona la profesión: es la clave de los mensajes.';
        if ($v['lider_id'] <= 0)                          $e['lider_id'] = 'Selecciona el líder que vincula.';
        return $e;
    }
}
