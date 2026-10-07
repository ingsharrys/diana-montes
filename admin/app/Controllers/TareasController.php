<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Tarea;
use Models\Catalogo;
use Models\Auditoria;

/**
 * Tareas de la red: reuniones, convocatorias, puerta a puerta, llamadas…
 * El equipo las asigna a personas, a un segmento (nivel, zona, líder) o las
 * deja abiertas para que la gente se apunte desde su panel (/mi/). Al validar
 * lo reportado, la persona suma los puntos de la tarea.
 *
 * Dirección y coordinación ven todo; el líder solo su red; el digitador no entra.
 */
class TareasController extends Controller
{
    private const ROLES = ['direccion', 'coordinador', 'lider'];

    /** Líder: solo su red (null = ve todo). */
    private function alcance(): ?int
    {
        return Auth::tieneRol('lider') ? Auth::id() : null;
    }

    private function modelo(): Tarea
    {
        $m = new Tarea();
        if (!$m->disponible()) {
            Session::flash('error', 'Las tareas se activan con "Actualizar plataforma" en Inicio.');
            $this->redirigir('dashboard');
        }
        return $m;
    }

    public function index(): void
    {
        Auth::requerirRol(...self::ROLES);
        $m = $this->modelo();
        $estado = in_array($_GET['estado'] ?? '', ['abierta', 'cerrada', 'todas'], true) ? $_GET['estado'] : 'abierta';
        $this->vista('tareas/index', [
            'titulo'  => 'Tareas de la red',
            'tareas'  => $m->listar($this->alcance(), $estado),
            'resumen' => $m->resumen($this->alcance()),
            'estado'  => $estado,
        ]);
    }

    public function crear(): void
    {
        Auth::requerirRol(...self::ROLES);
        $this->modelo();
        $this->formulario([], []);
    }

    private function formulario(array $errores, array $v): void
    {
        $cat = new Catalogo();
        $this->vista('tareas/crear', [
            'titulo'  => 'Nueva tarea',
            'zonas'   => $cat->zonas(),
            'lideres' => Auth::tieneRol('lider') ? [] : $cat->lideres(),
            'errores' => $errores,
            'v'       => $v,
        ]);
    }

    public function guardar(): void
    {
        Auth::requerirRol(...self::ROLES);
        if (!$this->esPost()) $this->redirigir('tareas/crear');
        $this->validarCsrf();
        $m = $this->modelo();

        $tipo = array_key_exists($_POST['tipo'] ?? '', TAREA_TIPOS) ? $_POST['tipo'] : '';
        $v = [
            'tipo'        => $tipo,
            'titulo'      => limpiar_texto_catalogo((string)($_POST['titulo'] ?? ''), 150) ?? '',
            'descripcion' => trim(mb_substr((string)($_POST['descripcion'] ?? ''), 0, 2000)),
            'lugar'       => trim((string)($_POST['lugar'] ?? '')),
            'fecha'       => (string)($_POST['fecha'] ?? ''),
            'meta'        => (int)preg_replace('/\D/', '', (string)($_POST['meta'] ?? '')) ?: null,
            'puntos'      => min(500, (int)preg_replace('/\D/', '', (string)($_POST['puntos'] ?? '0'))),
            'modo'        => in_array($_POST['modo'] ?? '', ['segmento', 'abierta', 'personas'], true) ? $_POST['modo'] : 'segmento',
            'nivel_minimo'=> max(0, min(3, (int)($_POST['nivel_minimo'] ?? 0))),
            'zona_id'     => (int)($_POST['zona_id'] ?? 0) ?: null,
            'lider_id'    => Auth::tieneRol('lider') ? Auth::id() : ((int)($_POST['lider_id'] ?? 0) ?: null),
            'personas'    => (string)($_POST['personas'] ?? ''),
        ];

        $e = [];
        if (!$tipo) $e['tipo'] = 'Elige el tipo de tarea.';
        if (mb_strlen($v['titulo']) < 3) $e['titulo'] = 'Escribe qué hay que hacer (letras y números, sin símbolos raros).';
        if ($v['lugar'] !== '' && ($v['lugar'] = limpiar_texto_catalogo($v['lugar'], 200) ?? '') === '') $e['lugar'] = 'Revisa el lugar: solo letras, números y # - . , /';
        $fecha = null;
        if ($v['fecha'] !== '') {
            $fecha = \DateTime::createFromFormat('Y-m-d\TH:i', $v['fecha']);
            if (!$fecha) $e['fecha'] = 'Fecha inválida.';
            elseif ($fecha < new \DateTime('-1 day')) $e['fecha'] = 'La fecha no puede estar en el pasado.';
        }
        if ($tipo && TAREA_TIPOS[$tipo][3] && !$fecha) $e['fecha'] = 'Un evento necesita fecha y hora.';

        $ids = [];
        if (!$e && $v['modo'] === 'segmento') {
            $ids = $m->segmento($v['nivel_minimo'], $v['zona_id'], $v['lider_id']);
            if (!$ids) $e['modo'] = 'Nadie cumple ese segmento todavía. Cambia el nivel, la zona o el líder.';
        } elseif (!$e && $v['modo'] === 'personas') {
            [$ids, $faltan] = $m->porDocumentos($v['personas'], $this->alcance());
            if ($faltan) $e['personas'] = 'No encontramos ' . (Auth::tieneRol('lider') ? 'en tu red ' : '') . 'estos documentos o celulares: ' . implode(', ', array_slice($faltan, 0, 10)) . '.';
            elseif (!$ids) $e['personas'] = 'Escribe al menos un documento o celular.';
        }
        if ($e) { $this->formulario($e, $v); return; }

        $esEvento = TAREA_TIPOS[$tipo][3];
        $tareaId = red_crear_tarea($this->db(), [
            'tipo' => $tipo, 'titulo' => $v['titulo'], 'descripcion' => $v['descripcion'] ?: null,
            'lugar' => $v['lugar'] ?: null, 'fecha' => $fecha ? $fecha->format('Y-m-d H:i:s') : null, 'meta' => $v['meta'],
            'puntos' => $v['puntos'], 'puntos_asistencia' => $esEvento ? $v['puntos'] : 0,
            'alcance' => $v['modo'] === 'abierta' ? 'abierta' : 'asignada',
            'nivel_minimo' => $v['modo'] === 'abierta' ? $v['nivel_minimo'] : 0,
            'zona_id' => $v['modo'] === 'abierta' ? $v['zona_id'] : null,
            'lider_id' => $v['lider_id'], 'creada_por_usuario' => Auth::id(),
        ]);
        $n = $ids ? red_asignar($this->db(), $tareaId, $ids, Auth::id(), null, true, $esEvento ? 'asistente' : 'responsable') : 0;

        Auditoria::registrar('tarea_creada', $v['titulo'] . ' (' . $tipo . ', ' . ($v['modo'] === 'abierta' ? 'abierta' : "$n personas") . ')');
        Session::flash('ok', $v['modo'] === 'abierta'
            ? 'Tarea publicada: la verán en su panel quienes cumplan el segmento y podrán apuntarse.'
            : "Tarea asignada a $n persona" . ($n === 1 ? '' : 's') . '. La verán en su panel y, si está activo, les llegará el aviso por WhatsApp.');
        $this->redirigir('tareas/ver/' . $tareaId);
    }

    public function ver(string $id = '0'): void
    {
        Auth::requerirRol(...self::ROLES);
        $m = $this->modelo();
        $tarea = $m->porId((int)$id, $this->alcance());
        if (!$tarea) { Session::flash('error', 'Tarea no encontrada.'); $this->redirigir('tareas'); }
        $filtro = (string)($_GET['estado'] ?? '');
        $this->vista('tareas/ver', [
            'titulo'        => 'Tarea',
            'tarea'         => $tarea,
            'asignaciones'  => $m->asignaciones((int)$id, $filtro),
            'conteo'        => $m->conteo((int)$id),
            'filtro'        => $filtro,
            'verTelefono'   => Auth::tieneRol('direccion', 'coordinador'),
        ]);
    }

    /** Valida (o no) el reporte de una persona: al validar suma sus puntos. */
    public function validar(string $asignacionId = '0'): void
    {
        Auth::requerirRol(...self::ROLES);
        if (!$this->esPost()) $this->redirigir('tareas');
        $this->validarCsrf();
        $m = $this->modelo();
        $tareaId = $m->tareaDeAsignacion((int)$asignacionId, $this->alcance());
        if (!$tareaId) { Session::flash('error', 'No puedes validar esa tarea.'); $this->redirigir('tareas'); }
        $ok = ($_POST['valor'] ?? '') === 'si';
        if (red_validar_asignacion($this->db(), (int)$asignacionId, $ok, (int)Auth::id())) {
            Auditoria::registrar($ok ? 'tarea_validada' : 'tarea_no_validada', 'Asignación #' . (int)$asignacionId . ' de la tarea #' . $tareaId);
            Session::flash('ok', $ok ? 'Validada: la persona ya sumó sus puntos.' : 'Marcada como no validada.');
        } else {
            Session::flash('error', 'Esa asignación aún no tiene nada que validar.');
        }
        $this->redirigir('tareas/ver/' . $tareaId);
    }

    /** Valida de una vez todo lo reportado ("por validar") de una tarea. */
    public function validartodas(string $tareaId = '0'): void
    {
        Auth::requerirRol(...self::ROLES);
        if (!$this->esPost()) $this->redirigir('tareas');
        $this->validarCsrf();
        $m = $this->modelo();
        if (!$m->porId((int)$tareaId, $this->alcance())) { Session::flash('error', 'Tarea no encontrada.'); $this->redirigir('tareas'); }
        $n = 0;
        foreach ($m->idsPorValidar((int)$tareaId) as $a) $n += red_validar_asignacion($this->db(), $a, true, (int)Auth::id()) ? 1 : 0;
        Auditoria::registrar('tarea_validada', "$n asignaciones de la tarea #" . (int)$tareaId);
        Session::flash('ok', "$n reporte" . ($n === 1 ? '' : 's') . ' validado' . ($n === 1 ? '' : 's') . '. Los puntos ya se sumaron.');
        $this->redirigir('tareas/ver/' . (int)$tareaId);
    }

    /** Cerrar, cancelar o reabrir una tarea. */
    public function estado(string $tareaId = '0'): void
    {
        Auth::requerirRol(...self::ROLES);
        if (!$this->esPost()) $this->redirigir('tareas');
        $this->validarCsrf();
        $m = $this->modelo();
        $estado = in_array($_POST['estado'] ?? '', ['abierta', 'cerrada', 'cancelada'], true) ? $_POST['estado'] : null;
        $tarea = $m->porId((int)$tareaId, $this->alcance());
        if (!$tarea || !$estado) { Session::flash('error', 'No se pudo cambiar la tarea.'); $this->redirigir('tareas'); }
        $m->cambiarEstado((int)$tareaId, $estado);
        Auditoria::registrar('tarea_' . $estado, $tarea['titulo']);
        Session::flash('ok', ['abierta' => 'Tarea reabierta.', 'cerrada' => 'Tarea cerrada: ya no recibe reportes.', 'cancelada' => 'Tarea cancelada: desaparece de los paneles.'][$estado]);
        $this->redirigir('tareas/ver/' . (int)$tareaId);
    }

    private function db(): \PDO
    {
        return \Core\Database::conexion();
    }
}
