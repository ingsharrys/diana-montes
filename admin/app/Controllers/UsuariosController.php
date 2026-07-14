<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Usuario;
use Models\Catalogo;
use Models\Auditoria;

/**
 * Gestión de usuarios de la campaña (líderes, coordinadores, digitadores).
 * Solo la dirección puede administrar la estructura: los líderes
 * entran ÚNICAMENTE por invitación creada aquí.
 */
class UsuariosController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion');
        $this->vista('usuarios/index', [
            'titulo' => 'Equipo de campaña',
            'lista'  => (new Usuario())->listar(),
        ]);
    }

    public function crear(): void
    {
        Auth::requerirRol('direccion');
        $cat = new Catalogo();
        $this->vista('usuarios/crear', [
            'titulo'        => 'Invitar al equipo',
            'zonas'         => $cat->zonas(),
            'coordinadores' => $cat->coordinadores(),
            'errores'       => [],
            'v'             => [],
        ]);
    }

    public function guardar(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('usuarios/crear');
        $this->validarCsrf();

        $v = [
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'email'          => strtolower(trim($_POST['email'] ?? '')),
            'telefono'       => preg_replace('/\D/', '', $_POST['telefono'] ?? '') ?: null,
            'rol'            => in_array($_POST['rol'] ?? '', ['coordinador','lider','digitador'], true) ? $_POST['rol'] : 'lider',
            'zona_id'        => (int)($_POST['zona_id'] ?? 0) ?: null,
            'coordinador_id' => (int)($_POST['coordinador_id'] ?? 0) ?: null,
        ];
        $meta = (int)($_POST['meta'] ?? 0);

        $modelo  = new Usuario();
        $errores = [];
        if (mb_strlen($v['nombre']) < 5)                        $errores['nombre'] = 'Escribe el nombre completo.';
        if (strlen((string)$v['telefono']) !== 10 || ($v['telefono'][0] ?? '') !== '3')
                                                                $errores['telefono'] = 'El celular es obligatorio (10 dígitos, empieza por 3): es su enlace de invitación.';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL))    $errores['email'] = 'Correo inválido.';
        elseif ($modelo->existeEmail($v['email']))              $errores['email'] = 'Ya existe un usuario con este correo.';
        if ($v['rol'] === 'lider' && !$v['zona_id'])            $errores['zona_id'] = 'Todo líder debe tener su zona.';

        if ($errores) {
            $cat = new Catalogo();
            $this->vista('usuarios/crear', [
                'titulo' => 'Invitar al equipo',
                'zonas' => $cat->zonas(), 'coordinadores' => $cat->coordinadores(),
                'errores' => $errores, 'v' => $v + ['meta' => $meta],
            ]);
            return;
        }

        // Credenciales de la invitación: clave temporal + código de red
        $claveTemporal   = 'Garzon-' . random_int(1000, 9999) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        $v['hash']       = password_hash($claveTemporal, PASSWORD_BCRYPT);
        // El código de red ES el celular del líder: fácil de recordar y de dictar
        $v['codigo_ref'] = $v['telefono'];
        if ($modelo->existeCodigoRef($v['codigo_ref'])) $errores['telefono'] = 'Ese celular ya es el enlace de otro miembro del equipo.';
        if ($errores) { \Core\Session::flash('error', $errores['telefono']); $this->redirigir('usuarios/crear'); }

        $id = $modelo->crear($v);
        if ($meta > 0) $modelo->asignarMeta($id, $meta);

        Auditoria::registrar('usuario_creado', $v['rol'] . ' ' . $v['nombre'] . ' (enlace ' . $v['codigo_ref'] . ')');

        // La clave temporal se muestra UNA sola vez, para entregarla al invitado
        Session::flash('ok', 'Invitación creada para ' . $v['nombre'] . '. Contraseña temporal: ' . $claveTemporal
            . ' — entrégasela por un canal seguro; su enlace de invitación es ' . LANDING_URL . '/?ref=' . $v['codigo_ref'] . '#sumate');
        $this->redirigir('usuarios');
    }

    /** Activar / desactivar un usuario (no se elimina: la trazabilidad importa). */
    public function estado(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('usuarios');
        $this->validarCsrf();

        $modelo = new Usuario();
        $u = $modelo->porId((int)$id);
        if (!$u || (int)$u['id'] === Auth::id()) { Session::flash('error', 'Acción no permitida.'); $this->redirigir('usuarios'); }

        $nuevo = $u['activo'] ? 0 : 1;
        $modelo->cambiarEstado((int)$id, $nuevo);
        Auditoria::registrar($nuevo ? 'usuario_activado' : 'usuario_desactivado', $u['nombre']);
        Session::flash('ok', $u['nombre'] . ($nuevo ? ' fue activado.' : ' fue desactivado: ya no puede ingresar ni recibir registros.'));
        $this->redirigir('usuarios');
    }

    /** Nueva contraseña temporal (se muestra una sola vez). */
    public function resetclave(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('usuarios');
        $this->validarCsrf();

        $modelo = new Usuario();
        $u = $modelo->porId((int)$id);
        if (!$u) { Session::flash('error', 'Usuario no encontrado.'); $this->redirigir('usuarios'); }

        $clave = 'Garzon-' . random_int(1000, 9999) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
        $modelo->resetClave((int)$id, password_hash($clave, PASSWORD_BCRYPT));
        Auditoria::registrar('clave_reseteada', $u['nombre']);
        Session::flash('ok', 'Nueva contraseña temporal de ' . $u['nombre'] . ': ' . $clave . ' — entrégasela por un canal seguro.');
        $this->redirigir('usuarios');
    }
}
