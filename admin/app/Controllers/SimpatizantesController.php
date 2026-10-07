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
            'redActiva'    => $modelo->redPromotoresActiva(),
        ]);
    }

    /**
     * Cola "Completar y verificar": el equipo completa puesto y mesa
     * (consultando con la cédula) y confirma el compromiso llamando.
     */
    public function verificar(): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');
        $soloLider = Auth::tieneRol('lider') ? Auth::id() : null;

        $estado = $_GET['estado'] ?? 'pendientes';
        if (!in_array($estado, ['pendientes', 'sin_puesto', 'sin_verificar', 'todos'], true)) $estado = 'pendientes';
        $filtros = [
            'estado' => $estado,
            'q'      => trim((string)($_GET['q'] ?? '')),
            'zona'   => (int)($_GET['zona'] ?? 0),
        ];
        $porPagina = 40;
        $pagina = max(1, (int)($_GET['p'] ?? 1));

        $modelo = new Simpatizante();
        $cat    = new Catalogo();
        $conteo = $modelo->contarCola($filtros, $soloLider);

        $this->vista('simpatizantes/verificar', [
            'titulo'       => 'Completar y verificar',
            'filtros'      => $filtros,
            'conteo'       => $conteo,
            'lista'        => $modelo->colaVerificacion($filtros, $soloLider, $porPagina, ($pagina - 1) * $porPagina),
            'pagina'       => $pagina,
            'paginas'      => max(1, (int)ceil($conteo[$estado] / $porPagina)),
            'puestos'      => $cat->puestosDetalle(),
            'zonas'        => $cat->zonas(),
            'compromisos'  => compromisos_disponibles(\Core\Database::conexion()),
            'conVerificacion' => $modelo->conVerificacion(),
            // Teléfono completo para llamar: dirección, coordinación y el líder (que solo ve su red)
            'verTelefono'  => Auth::tieneRol('direccion', 'coordinador', 'lider'),
        ]);
    }

    /** Guarda puesto, mesa y compromiso de una fila de la cola (responde JSON si se llama con fetch). */
    public function actualizar(string $id = '0'): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');
        $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
        $responder = function (bool $ok, string $msg, array $extra = []) use ($ajax): void {
            if ($ajax) {
                header('Content-Type: application/json; charset=utf-8');
                if (!$ok) http_response_code(422);
                echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
                exit;
            }
            \Core\Session::flash($ok ? 'ok' : 'error', $msg);
            $this->redirigir('simpatizantes/verificar');
        };

        if (!$this->esPost()) $this->redirigir('simpatizantes/verificar');
        $this->validarCsrf();

        $modelo = new Simpatizante();
        $s = $modelo->porIdBasico((int)$id);
        if (!$s) $responder(false, 'Simpatizante no encontrado.');
        // El líder solo puede tocar su propia red
        if (Auth::tieneRol('lider') && (int)$s['lider_id'] !== Auth::id()) {
            Auditoria::registrar('acceso_denegado', 'Intento de editar simpatizante #' . (int)$id . ' de otra red');
            $responder(false, 'Ese simpatizante no es de tu red.');
        }

        $puestoId = (int)($_POST['puesto_id'] ?? 0) ?: null;
        $mesa     = preg_replace('/\D/', '', (string)($_POST['mesa'] ?? '')) ?: null;
        $nivel    = (string)($_POST['nivel'] ?? '');
        $verificado = !empty($_POST['verificado']);

        $puesto = null;
        if ($puestoId) {
            foreach ((new Catalogo())->puestosDetalle() as $p) if ((int)$p['id'] === $puestoId) $puesto = $p;
            if (!$puesto) $responder(false, 'El puesto de votación no existe.');
        }
        if ($mesa !== null && ((int)$mesa < 1 || strlen($mesa) > 4)) $responder(false, 'Revisa el número de mesa.');
        if ($mesa !== null && $puesto && $puesto['mesas'] && (int)$mesa > (int)$puesto['mesas']) {
            $responder(false, 'Ese puesto solo tiene ' . (int)$puesto['mesas'] . ' mesas.');
        }
        if (!isset(compromisos_disponibles(\Core\Database::conexion())[$nivel])) $responder(false, 'Elige el nivel de compromiso.');

        $modelo->actualizarBase((int)$id, $puestoId, $mesa, $nivel, $verificado, (int)Auth::id());
        // Un invitado confirmado como voto seguro da puntos extra a quien lo invitó
        if ($padre = $modelo->referidoPor((int)$id)) red_actualizar_puntos(\Core\Database::conexion(), $padre);
        Auditoria::registrar($verificado ? 'simpatizante_verificado' : 'simpatizante_completado',
            $s['nombre'] . ' · ' . compromiso_etiqueta($nivel) . ($puesto ? ' · ' . $puesto['nombre'] . ($mesa ? " mesa $mesa" : '') : ''));

        $responder(true, $verificado ? 'Verificado ✓' : 'Guardado ✓', [
            'verificado' => $verificado && $modelo->conVerificacion() ? 'Hoy · ' . (Auth::usuario()['nombre'] ?? '') : null,
        ]);
    }

    /**
     * Genera una clave temporal para que la persona entre a su panel (/mi/).
     * Al entrar con ella, el panel le pide crear una propia.
     */
    public function clave(string $id = '0'): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider');
        if (!$this->esPost()) $this->redirigir('simpatizantes');
        $this->validarCsrf();
        $db = \Core\Database::conexion();
        if (!red_portal_listo($db)) { \Core\Session::flash('error', 'Primero pulsa "Actualizar plataforma" en Inicio.'); $this->redirigir('simpatizantes'); }

        $s = (new Simpatizante())->paraClave((int)$id, Auth::tieneRol('lider') ? Auth::id() : null);
        if (!$s) { \Core\Session::flash('error', 'Ese simpatizante no es de tu red.'); $this->redirigir('simpatizantes'); }

        $clave = red_clave_temporal();
        red_guardar_clave($db, (int)$s['id'], $clave, true);
        Auditoria::registrar('simpatizante_clave_temporal', $s['nombre'] . ' (#' . (int)$s['id'] . ')');
        \Core\Session::set('clave_temporal', [
            'nombre' => $s['nombre'], 'documento' => $s['documento'], 'telefono' => $s['telefono'], 'clave' => $clave,
        ]);
        $this->redirigir('simpatizantes');
    }

    /** Registros con documento o celular repetido, para dejar uno solo. */
    public function duplicados(): void
    {
        Auth::requerirRol('direccion');
        $this->vista('simpatizantes/duplicados', [
            'titulo'  => 'Registros duplicados',
            'grupos'  => (new Simpatizante())->duplicados(),
        ]);
    }

    /** Elimina un registro sobrante (solo dirección, solo si está duplicado). */
    public function eliminar(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('simpatizantes/duplicados');
        $this->validarCsrf();

        $modelo = new Simpatizante();
        $esDuplicado = false;
        foreach ($modelo->duplicados() as $grupo) {
            foreach ($grupo as $f) if ((int)$f['id'] === (int)$id) $esDuplicado = true;
        }
        if (!$esDuplicado) {
            \Core\Session::flash('error', 'Solo se pueden eliminar aquí registros duplicados.');
            $this->redirigir('simpatizantes/duplicados');
        }
        try {
            $nombre = $modelo->eliminar((int)$id);
            Auditoria::registrar('simpatizante_duplicado_eliminado', '#' . (int)$id . ' ' . $nombre);
            \Core\Session::flash('ok', 'Registro duplicado de ' . $nombre . ' eliminado.');
        } catch (\Throwable $e) {
            \Core\Session::flash('error', 'No se pudo eliminar: el registro está en uso (' . $e->getMessage() . ').');
        }
        $this->redirigir('simpatizantes/duplicados');
    }

    public function crear(): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');
        $this->formulario([], []);
    }

    /** Formulario de registro; $v trae los valores previos si la validación falla. */
    private function formulario(array $errores, array $v): void
    {
        $cat = new Catalogo();
        $this->vista('simpatizantes/crear', [
            'titulo'      => 'Nuevo simpatizante',
            'zonas'       => $cat->zonas(),
            'profesiones' => $cat->profesiones(),
            'puestos'     => $cat->puestos(),
            'lideres'     => $cat->lideres(),
            'pideGenero'  => (new Simpatizante())->pideGenero(),
            'compromisos' => compromisos_disponibles(\Core\Database::conexion()),
            'errores'     => $errores,
            'v'           => $v,
        ]);
    }

    public function guardar(): void
    {
        Auth::requerirRol('direccion', 'coordinador', 'lider', 'digitador');
        if (!$this->esPost()) $this->redirigir('simpatizantes/crear');
        $this->validarCsrf();

        $v = [
            'nombre'           => normalizar_nombre((string)($_POST['nombre'] ?? '')),
            'documento'        => normalizar_documento((string)($_POST['documento'] ?? '')),
            'telefono'         => normalizar_celular((string)($_POST['telefono'] ?? '')),
            'fecha_nacimiento' => trim((string)($_POST['fecha_nacimiento'] ?? '')),
            'genero'           => is_string($_POST['genero'] ?? null) ? $_POST['genero'] : '',
            'zona_id'          => (int)($_POST['zona_id'] ?? 0),
            'puesto_id'        => (int)($_POST['puesto_id'] ?? 0) ?: null,
            'mesa'             => preg_replace('/\D/', '', (string)($_POST['mesa'] ?? '')) ?: null,
            'profesion_id'     => (int)($_POST['profesion_id'] ?? 0),
            'nivel'            => isset(compromisos_disponibles(\Core\Database::conexion())[$_POST['nivel'] ?? ''])
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
            $this->formulario($errores, $v);
            return;
        }

        if (!$modelo->pideGenero()) $v['genero'] = null;
        $v['created_by'] = Auth::id();
        try {
            $modelo->crear($v);
        } catch (\PDOException $ex) {
            // Otro usuario lo guardó al mismo tiempo: el índice único de la base lo frena
            if (!es_error_duplicado($ex)) throw $ex;
            $this->formulario($this->validar($v, $modelo) ?: ['documento' => 'Este registro ya existe. No se permiten duplicados.'], $v);
            return;
        }

        Auditoria::registrar('simpatizante_creado', 'Documento ' . enmascarar($v['documento']) . ' · zona ' . $v['zona_id']);
        \Core\Session::flash('ok', 'Registro guardado correctamente. ¡Gracias por hacer crecer la campaña!');
        $this->redirigir('simpatizantes');
    }

    private function validar(array $v, Simpatizante $modelo): array
    {
        $e = [];
        if ($msg = error_nombre_persona($v['nombre'], 'el')) $e['nombre'] = $msg;
        if ($msg = error_documento($v['documento']))       $e['documento'] = $msg;
        if ($msg = error_celular($v['telefono']))          $e['telefono'] = $msg;
        // Sin duplicados: ni el documento ni el celular pueden repetirse
        if (!isset($e['documento']) && $modelo->existeDocumento($v['documento'])) {
            $e['documento'] = 'Este documento ya está registrado. No se permiten duplicados.';
        }
        if (!isset($e['telefono']) && $modelo->existeTelefono($v['telefono'])) {
            $e['telefono'] = 'Este celular ya está registrado a otra persona. No se permiten duplicados.';
        }
        if ($v['mesa'] !== null && ((int)$v['mesa'] < 1 || strlen($v['mesa']) > 4)) $e['mesa'] = 'Revisa el número de mesa.';
        if ($msg = simpatizante_error_nacimiento($v['fecha_nacimiento'])) $e['fecha_nacimiento'] = $msg;
        if ($modelo->pideGenero() && !isset(GENEROS[$v['genero']])) $e['genero'] = 'Selecciona el género.';
        if ($v['zona_id'] <= 0)                           $e['zona_id'] = 'Selecciona la zona.';
        if ($v['profesion_id'] <= 0)                      $e['profesion_id'] = 'Selecciona la profesión: es la clave de los mensajes.';
        if ($v['lider_id'] <= 0)                          $e['lider_id'] = 'Selecciona el líder que vincula.';
        return $e;
    }
}
