<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Models\Auditoria;
use Models\Evento;
use Models\InscripcionEvento;
use PDOException;

/** Agenda de encuentros: mismo patrón MVC y de seguridad que HistoriasController. */
class EventosController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion');
        $m = new Evento();
        if (!$m->disponible()) {
            $this->vista('eventos/index', ['titulo' => 'Agenda de encuentros', 'listo' => false]);
            return;
        }

        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
        $estado = isset(Evento::ESTADOS[$_GET['estado'] ?? '']) ? (string) $_GET['estado'] : '';
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $editar = (int) ($_GET['editar'] ?? 0);

        // Inscritos visibles únicamente en el administrador autenticado.
        $listado = $m->listar($q, $estado, $pagina);
        $insModel = new InscripcionEvento();
        $inscripcionesListas = $insModel->disponible();
        $conteosInscripciones = $inscripcionesListas
            ? $insModel->conteoPorEventos(array_column($listado['registros'], 'id'))
            : [];
        $eventoAsistentes = null;
        $inscripciones = null;
        $verAsistentes = max(0, (int) ($_GET['asistentes'] ?? 0));
        if ($inscripcionesListas && $verAsistentes > 0) {
            $eventoAsistentes = $m->obtener($verAsistentes);
            if ($eventoAsistentes) {
                $paginaInscripciones = max(1, (int) ($_GET['pagina_inscripciones'] ?? 1));
                $inscripciones = $insModel->listarPorEvento($verAsistentes, $paginaInscripciones);
            }
        }

        $this->vista('eventos/index', [
            'titulo'   => 'Agenda de encuentros',
            'listo'    => true,
            'q'        => $q,
            'estado'   => $estado,
            'listado'  => $listado,
            'inscripcionesListas' => $inscripcionesListas,
            'conteosInscripciones' => $conteosInscripciones,
            'eventoAsistentes' => $eventoAsistentes,
            'inscripciones' => $inscripciones,
            'conteo'   => $m->conteo(),
            'editar'   => $editar > 0 ? $m->obtener($editar) : null,
            'nuevo'    => ($_GET['nuevo'] ?? '') === '1',
            'borrador' => Session::get('evento_borrador') ?: [],
        ]);
        Session::set('evento_borrador', null);
    }

    /** POST eventos/guardar — crea (sin id) o actualiza (con id). */
    public function guardar(): void
    {
        $this->post();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $datos = $this->datos($id === 0);
            $m = new Evento();
            if ($id === 0) {
                $id = $m->crear($datos);
                Auditoria::registrar('evento_creado', 'Encuentro #' . $id . ': ' . $datos['titulo']);
                Session::flash('ok', 'Encuentro registrado (#' . $id . '). Queda en borrador.');
            } else {
                $m->actualizar($id, $datos);
                Auditoria::registrar('evento_editado', 'Encuentro #' . $id);
                Session::flash('ok', 'Encuentro #' . $id . ' actualizado.');
            }
            $this->redirigir('eventos');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            Session::set('evento_borrador', $_POST);
            header('Location: ' . url_con('eventos', $id ? ['editar' => $id] : ['nuevo' => 1]));
            exit;
        }
    }

    /** POST eventos/estado — cambia entre borrador, publicado y cancelado. */
    public function estado(): void
    {
        $this->post();
        $id = (int) ($_POST['id'] ?? 0);
        $estado = (string) ($_POST['estado'] ?? '');
        try {
            (new Evento())->cambiarEstado($id, $estado);
            Auditoria::registrar('evento_estado', 'Encuentro #' . $id . ' -> ' . $estado);
            Session::flash('ok', 'Encuentro #' . $id . ': ' . (Evento::ESTADOS[$estado] ?? $estado) . '.');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->volver();
    }

    /** POST eventos/eliminar — elimina definitivamente. */
    public function eliminar(): void
    {
        $this->post();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if ((new Evento())->eliminar($id)) {
                Auditoria::registrar('evento_eliminado', 'Encuentro #' . $id);
                Session::flash('ok', 'Encuentro #' . $id . ' eliminado.');
            } else {
                Session::flash('error', 'El encuentro ya no existe.');
            }
        } catch (PDOException $e) {
            // Por ejemplo, si hay inscripciones relacionadas mediante llave foránea.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Session::flash('error', 'No se puede eliminar este encuentro porque tiene registros relacionados. Puedes cancelarlo.');
        }
        $this->volver();
    }

    /** POST eventos/asistenciaEstado — confirmar, cancelar o restablecer una inscripción. */
    public function asistenciaEstado(): void
    {
        $this->post();
        $id = (int) ($_POST['inscripcion_id'] ?? 0);
        $nuevoEstado = (string) ($_POST['estado_inscripcion'] ?? '');
        $eventoId = (int) ($_POST['evento_id'] ?? 0);
        try {
            $actual = (new InscripcionEvento())->cambiarEstado($id, $nuevoEstado);
            $eventoId = (int) $actual['evento_id'];
            Auditoria::registrar(
                'evento_inscripcion_estado',
                'Inscripción #' . $id . ' del encuentro #' . $eventoId . ' → ' . $nuevoEstado
            );
            Session::flash('ok', 'Estado de la inscripción #' . $id . ' actualizado.');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->volverAsistentes($eventoId);
    }

    /** POST eventos/asistenciaEliminar — elimina una inscripción; no afecta al evento. */
    public function asistenciaEliminar(): void
    {
        $this->post();
        $id = (int) ($_POST['inscripcion_id'] ?? 0);
        $eventoId = (int) ($_POST['evento_id'] ?? 0);
        $actual = (new InscripcionEvento())->eliminar($id);
        if ($actual) {
            $eventoId = (int) $actual['evento_id'];
            Auditoria::registrar('evento_inscripcion_eliminada',
                'Inscripción #' . $id . ' del encuentro #' . $eventoId);
            Session::flash('ok', 'Inscripción #' . $id . ' eliminada.');
        } else {
            Session::flash('error', 'La inscripción ya no existe.');
        }
        $this->volverAsistentes($eventoId);
    }

    /** Regresa al panel de inscritos conservando los filtros del listado de eventos. */
    private function volverAsistentes(int $eventoId): void
    {
        $parametros = [];
        $q = $_POST['q'] ?? '';
        if (is_string($q) && $q !== '') {
            $parametros['q'] = mb_substr($q, 0, 100);
        }
        $filtro = $_POST['filtro_estado'] ?? '';
        if (is_string($filtro) && isset(Evento::ESTADOS[$filtro])) {
            $parametros['estado'] = $filtro;
        }
        $parametros['pagina'] = max(1, (int) ($_POST['pagina'] ?? 1));
        $parametros['pagina_inscripciones'] = max(1, (int) ($_POST['pagina_inscripciones'] ?? 1));
        if ($eventoId > 0) {
            $parametros['asistentes'] = $eventoId;
        }
        header('Location: ' . url_con('eventos', $parametros) . '#inscripciones');
        exit;
    }

    private function post(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) {
            $this->redirigir('eventos');
        }
        $this->validarCsrf();
    }

    /** Regresa a la búsqueda/filtro/página original tras cambiar estado o borrar. */
    private function volver(): void
    {
        $filtros = [];
        if (isset($_POST['q']) && is_string($_POST['q'])) {
            $filtros['q'] = mb_substr($_POST['q'], 0, 100);
        }
        if (isset($_POST['filtro_estado']) && is_string($_POST['filtro_estado'])
            && isset(Evento::ESTADOS[$_POST['filtro_estado']])) {
            $filtros['estado'] = $_POST['filtro_estado'];
        }
        $filtros['pagina'] = max(1, (int) ($_POST['pagina'] ?? 1));
        header('Location: ' . url_con('eventos', $filtros));
        exit;
    }

    private function valor(string $campo): string
    {
        $valor = $_POST[$campo] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }

    private function fecha(string $valor, string $etiqueta): string
    {
        $zona = new DateTimeZone('America/Bogota');
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valor, $zona);
        $errores = DateTimeImmutable::getLastErrors();
        if (!$fecha || $fecha->format('Y-m-d\TH:i') !== $valor
            || ($errores !== false && ($errores['warning_count'] || $errores['error_count']))) {
            throw new DomainException('Indica una ' . $etiqueta . ' válida.');
        }
        return $fecha->format('Y-m-d H:i:s');
    }

    private function datos(bool $creando): array
    {
        $titulo = preg_replace('/\s+/u', ' ', $this->valor('titulo'));
        $categoria = preg_replace('/\s+/u', ' ', $this->valor('categoria'));
        $lugar = preg_replace('/\s+/u', ' ', $this->valor('lugar'));
        $descripcion = str_replace(["\r\n", "\r"], "\n", $this->valor('descripcion'));
        $direccion = preg_replace('/\s+/u', ' ', $this->valor('direccion'));
        $mapaUrl = $this->valor('mapa_url');

        if ($titulo === null || mb_strlen($titulo) < 4 || mb_strlen($titulo) > 180) {
            throw new DomainException('El título debe tener entre 4 y 180 caracteres.');
        }
        if ($categoria === null || mb_strlen($categoria) < 3 || mb_strlen($categoria) > 100) {
            throw new DomainException('La categoría debe tener entre 3 y 100 caracteres.');
        }
        if (mb_strlen($descripcion) < 10 || mb_strlen($descripcion) > 5000) {
            throw new DomainException('La descripción debe tener entre 10 y 5.000 caracteres.');
        }
        if ($lugar === null || mb_strlen($lugar) < 3 || mb_strlen($lugar) > 180) {
            throw new DomainException('El lugar debe tener entre 3 y 180 caracteres.');
        }
        if ($direccion === null || mb_strlen($direccion) > 255) {
            throw new DomainException('La dirección es demasiado larga.');
        }
        if ($mapaUrl !== '' && (
            strlen($mapaUrl) > 600 || !filter_var($mapaUrl, FILTER_VALIDATE_URL)
            || strtolower((string) parse_url($mapaUrl, PHP_URL_SCHEME)) !== 'https'
        )) {
            throw new DomainException('El enlace del mapa debe ser una URL HTTPS válida.');
        }

        $inicio = $this->fecha($this->valor('fecha_inicio'), 'fecha y hora de inicio');
        $fin = $this->fecha($this->valor('fecha_fin'), 'fecha y hora de finalización');
        if ($fin <= $inicio) {
            throw new DomainException('La finalización debe ser posterior al inicio del encuentro.');
        }
        $estado = $creando ? 'borrador' : $this->valor('estado');
        if (!isset(Evento::ESTADOS[$estado])) {
            throw new DomainException('El estado seleccionado no es válido.');
        }

        return [
            'titulo' => $titulo,
            'categoria' => $categoria,
            'descripcion' => $descripcion,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'lugar' => $lugar,
            'direccion' => $direccion === '' ? null : $direccion,
            'mapa_url' => $mapaUrl === '' ? null : $mapaUrl,
            'estado' => $estado,
        ];
    }
}
