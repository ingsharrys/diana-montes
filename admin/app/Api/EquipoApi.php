<?php
namespace Api;

use PDO;
use Models\Insights;
use Models\Simpatizante;
use Models\Usuario;
use Models\Configuracion;
use Models\Catalogo;
use Models\Tarea;

/**
 * Administración de la campaña desde la app (equipo). Mismas reglas que la web:
 *  - el líder solo ve y toca su red;
 *  - documentos y teléfonos completos solo para dirección y coordinación;
 *  - tareas: dirección, coordinación y líderes (el digitador no).
 */
final class EquipoApi
{
    private static function rol(array $s): string { return (string)$s['sujeto']['rol']; }
    private static function soloLider(array $s): ?int { return self::rol($s) === 'lider' ? $s['id'] : null; }
    private static function verTodo(array $s): bool { return in_array(self::rol($s), ['direccion', 'coordinador'], true); }
    private static function dato(array $s, ?string $v): ?string { return $v === null ? null : (self::verTodo($s) ? $v : enmascarar($v)); }

    /** GET equipo/resumen — la "Vista rápida" del centro de mando. */
    public static function resumen(PDO $db, array $in, array $s): void
    {
        $lider = self::soloLider($s);
        $ins = new Insights($lider);
        $simp = new Simpatizante();
        $usuarios = new Usuario();
        $config = new Configuracion();
        $yo = $usuarios->porId($s['id']);
        $avance = (!empty($yo['codigo_ref']) && self::rol($s) !== 'direccion') ? $usuarios->avance($s['id']) : null;
        $meta = $lider ? (int)($avance['meta'] ?? 0) : (int)$config->obtener('meta_inscripcion', '0');
        $total = $ins->total();
        $compromiso = [];
        foreach ($ins->porCompromiso() as $k => $n) $compromiso[] = ['clave' => $k, 'nombre' => compromiso_etiqueta($k), 'total' => $n, 'seguro' => in_array($k, COMPROMISOS_SEGUROS, true)];
        $red = $simp->redPromotoresActiva();
        $tareas = null;
        if (in_array(self::rol($s), ['direccion', 'coordinador', 'lider'], true) && red_tareas_listas($db)) $tareas = (new Tarea())->resumen($lider);
        Nucleo::ok([
            'soloSuRed' => $lider !== null,
            'total' => $total, 'meta' => $meta,
            'progreso' => $meta > 0 ? min(100, (int)round($total * 100 / $meta)) : null,
            'avance' => $avance,
            'semana' => $ins->ultimos7Dias(),
            'compromiso' => $compromiso,
            'calidad' => $ins->calidad(),
            'metaVotos' => $lider ? 0 : (int)$config->obtener('meta_votos', '0'),
            'promotores' => $ins->promotores(),
            'topPromotores' => $red ? array_map(fn($p) => [
                'nombre' => $p['nombre'], 'zona' => $p['zona'], 'lider' => $p['lider'], 'invitados' => (int)$p['invitados'],
                'puntos' => red_puntos_fila($p), 'nivelEmoji' => promotor_nivel(red_puntos_fila($p))['actual'][2],
            ], $simp->rankingPromotores(5, $lider)) : [],
            'rankingEquipo' => self::verTodo($s) ? array_map(fn($u) => [
                'nombre' => $u['nombre'], 'rol' => $u['rol'], 'vinculados' => (int)$u['vinculados'], 'meta' => $u['meta'] !== null ? (int)$u['meta'] : null,
            ], $usuarios->rankingEquipo(5)) : [],
            'porZona' => array_slice(array_values(array_filter(array_map(fn($z) => ['nombre' => $z['nombre'], 'total' => (int)$z['total']], $simp->contarPorZona()), fn($z) => $z['total'] > 0)), 0, 8),
            'tareas' => $tareas,
        ]);
    }

    /** GET equipo/simpatizantes?q=&estado=todos|pendientes|sin_puesto|sin_verificar&zona=&desde= */
    public static function simpatizantes(PDO $db, array $in, array $s): void
    {
        $f = [
            'q' => trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60)),
            'zona' => (int)($_GET['zona'] ?? 0),
            'estado' => in_array($_GET['estado'] ?? '', ['pendientes', 'sin_puesto', 'sin_verificar', 'todos'], true) ? $_GET['estado'] : 'todos',
        ];
        $desde = max(0, (int)($_GET['desde'] ?? 0));
        $m = new Simpatizante();
        $filas = $m->colaVerificacion($f, self::soloLider($s), 31, $desde);
        $hayMas = count($filas) > 30;
        Nucleo::ok([
            'conteo' => $m->contarCola($f, self::soloLider($s)),
            'hayMas' => $hayMas,
            'lista' => array_map(fn($r) => [
                'id' => (int)$r['id'], 'nombre' => $r['nombre'], 'documento' => self::dato($s, $r['documento']),
                'telefono' => self::dato($s, $r['telefono']), 'zona' => $r['zona'], 'lider' => $r['lider'],
                'nivel' => $r['nivel'], 'nivelNombre' => compromiso_etiqueta($r['nivel']),
                'conPuesto' => $r['puesto_id'] && $r['mesa'] !== null && $r['mesa'] !== '',
                'verificado' => (bool)$r['verificado_at'],
            ], array_slice($filas, 0, 30)),
        ]);
    }

    /** GET equipo/simpatizante?id= */
    public static function simpatizante(PDO $db, array $in, array $s): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $sql = 'SELECT s.*, ' . zona_sql_nombre($db) . ' AS zona, p.nombre AS profesion, u.nombre AS lider,
                       pv.nombre AS puesto, r.nombre AS invitado_por,
                       (SELECT COUNT(*) FROM simpatizantes x WHERE x.referido_por = s.id) AS invitados
                FROM simpatizantes s
                LEFT JOIN zonas z ON z.id = s.zona_id
                LEFT JOIN profesiones p ON p.id = s.profesion_id
                LEFT JOIN usuarios u ON u.id = s.lider_id
                LEFT JOIN puestos_votacion pv ON pv.id = s.puesto_id
                LEFT JOIN simpatizantes r ON r.id = s.referido_por
                WHERE s.id = :id';
        $p = ['id' => $id];
        if ($lider = self::soloLider($s)) { $sql .= ' AND s.lider_id = :l'; $p['l'] = $lider; }
        $st = $db->prepare($sql);
        $st->execute($p);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) Nucleo::error('Simpatizante no encontrado o no es de tu red.', 404);
        $puntos = red_puntos_fila($r);
        Nucleo::ok(['simpatizante' => [
            'id' => (int)$r['id'], 'nombre' => $r['nombre'],
            'documento' => self::dato($s, $r['documento']), 'telefono' => self::dato($s, $r['telefono']),
            'telefonoCompleto' => self::verTodo($s) || self::rol($s) === 'lider' ? $r['telefono'] : null,
            'fechaNacimiento' => $r['fecha_nacimiento'], 'genero' => isset($r['genero']) ? (GENEROS[$r['genero']] ?? null) : null,
            'zona' => $r['zona'], 'profesion' => $r['profesion'], 'lider' => $r['lider'],
            'puestoId' => $r['puesto_id'] ? (int)$r['puesto_id'] : null, 'puesto' => $r['puesto'], 'mesa' => $r['mesa'],
            'nivel' => $r['nivel'], 'nivelNombre' => compromiso_etiqueta($r['nivel']),
            'verificado' => !empty($r['verificado_at']), 'verificadoAt' => Nucleo::iso($r['verificado_at'] ?? null),
            'invitados' => (int)$r['invitados'], 'invitadoPor' => $r['invitado_por'],
            'puntos' => $puntos, 'nivelPromotor' => promotor_nivel($puntos)['actual'][1], 'nivelEmoji' => promotor_nivel($puntos)['actual'][2],
            'registrado' => Nucleo::iso($r['created_at']),
        ]]);
    }

    /** GET equipo/catalogos — listas para los formularios. */
    public static function catalogos(PDO $db, array $in, array $s): void
    {
        $cat = new Catalogo();
        Nucleo::ok([
            'zonas' => array_map(fn($z) => ['id' => (int)$z['id'], 'nombre' => zona_etiqueta($z), 'clase' => $z['clase'], 'grupo' => zona_grupo_etiqueta($z)], $cat->zonas()),
            'profesiones' => array_map(fn($p) => ['id' => (int)$p['id'], 'nombre' => $p['nombre']], $cat->profesiones()),
            'puestos' => array_map(fn($p) => ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'zona' => $p['zona'], 'mesas' => $p['mesas'] !== null ? (int)$p['mesas'] : null], $cat->puestosDetalle()),
            'lideres' => self::soloLider($s) ? [] : array_map(fn($l) => ['id' => (int)$l['id'], 'nombre' => $l['nombre']], $cat->lideres()),
            'compromisos' => array_map(fn($k, $v) => ['clave' => $k, 'nombre' => $v], array_keys(compromisos_disponibles($db)), compromisos_disponibles($db)),
            'generos' => (new Simpatizante())->pideGenero() ? array_map(fn($k, $v) => ['clave' => $k, 'nombre' => $v], array_keys(GENEROS), GENEROS) : [],
            'edadMinima' => EDAD_MINIMA,
        ]);
    }

    /** POST equipo/simpatizantes — registrar (mismas validaciones que la web). */
    public static function registrar(PDO $db, array $in, array $s): void
    {
        $m = new Simpatizante();
        $v = [
            'nombre' => normalizar_nombre((string)($in['nombre'] ?? '')),
            'documento' => normalizar_documento((string)($in['documento'] ?? '')),
            'telefono' => normalizar_celular((string)($in['telefono'] ?? '')),
            'fecha_nacimiento' => trim((string)($in['fecha_nacimiento'] ?? '')),
            'genero' => (string)($in['genero'] ?? ''),
            'zona_id' => (int)($in['zona_id'] ?? 0),
            'puesto_id' => (int)($in['puesto_id'] ?? 0) ?: null,
            'mesa' => preg_replace('/\D/', '', (string)($in['mesa'] ?? '')) ?: null,
            'profesion_id' => (int)($in['profesion_id'] ?? 0),
            'nivel' => isset(compromisos_disponibles($db)[$in['nivel'] ?? '']) ? $in['nivel'] : 'simpatizante',
            'lider_id' => self::soloLider($s) ?? (int)($in['lider_id'] ?? 0),
        ];
        $e = [];
        if ($msg = error_nombre_persona($v['nombre'], 'el')) $e['nombre'] = $msg;
        if ($msg = error_documento($v['documento'])) $e['documento'] = $msg;
        elseif ($m->existeDocumento($v['documento'])) $e['documento'] = 'Este documento ya está registrado. No se permiten duplicados.';
        if ($msg = error_celular($v['telefono'])) $e['telefono'] = $msg;
        elseif ($m->existeTelefono($v['telefono'])) $e['telefono'] = 'Este celular ya está registrado a otra persona. No se permiten duplicados.';
        if ($msg = simpatizante_error_nacimiento($v['fecha_nacimiento'])) $e['fecha_nacimiento'] = $msg;
        if ($m->pideGenero() && !isset(GENEROS[$v['genero']])) $e['genero'] = 'Selecciona el género.';
        $chk = fn(string $sql, int $id) => (function () use ($db, $sql, $id) { $st = $db->prepare($sql); $st->execute(['id' => $id]); return (bool)$st->fetchColumn(); })();
        if (!$v['zona_id'] || !$chk('SELECT 1 FROM zonas WHERE id = :id', $v['zona_id'])) $e['zona_id'] = 'Selecciona el barrio o vereda.';
        if (!$v['profesion_id'] || !$chk('SELECT 1 FROM profesiones WHERE id = :id', $v['profesion_id'])) $e['profesion_id'] = 'Selecciona la profesión: es la clave de los mensajes.';
        if (!$v['lider_id'] || !$chk('SELECT 1 FROM usuarios WHERE id = :id AND activo = 1', $v['lider_id'])) $e['lider_id'] = 'Selecciona el líder que vincula.';
        if ($v['puesto_id'] && !$chk('SELECT 1 FROM puestos_votacion WHERE id = :id', $v['puesto_id'])) $e['puesto_id'] = 'El puesto no existe.';
        if ($v['mesa'] !== null && ((int)$v['mesa'] < 1 || strlen($v['mesa']) > 4)) $e['mesa'] = 'Revisa el número de mesa.';
        if (empty($in['consentimiento'])) $e['consentimiento'] = 'Sin la autorización de datos (Ley 1581 de 2012) no se puede guardar el registro.';
        if ($e) Nucleo::error('Revisa los datos marcados.', 422, ['errores' => $e]);

        if (!$m->pideGenero()) $v['genero'] = null;
        $v['created_by'] = $s['id'];
        try {
            $nuevo = $m->crear($v);
        } catch (\PDOException $ex) {
            if (!es_error_duplicado($ex)) throw $ex;
            Nucleo::error('Este registro ya existe. No se permiten duplicados.', 409);
        }
        Nucleo::auditar($db, $s['id'], 'simpatizante_creado', 'Desde la app · documento ' . enmascarar($v['documento']));
        Nucleo::ok(['id' => $nuevo, 'msg' => 'Registro guardado. ¡Gracias por hacer crecer la campaña!']);
    }

    /** POST equipo/verificar {id, puesto_id, mesa, nivel, verificado} — completar y verificar. */
    public static function verificar(PDO $db, array $in, array $s): void
    {
        $m = new Simpatizante();
        $id = (int)($in['id'] ?? 0);
        $sim = $m->porIdBasico($id);
        if (!$sim) Nucleo::error('Simpatizante no encontrado.', 404);
        if (($lider = self::soloLider($s)) && (int)$sim['lider_id'] !== $lider) Nucleo::error('Ese simpatizante no es de tu red.', 403);
        $puestoId = (int)($in['puesto_id'] ?? 0) ?: null;
        $mesa = preg_replace('/\D/', '', (string)($in['mesa'] ?? '')) ?: null;
        $nivel = (string)($in['nivel'] ?? '');
        $verificado = !empty($in['verificado']);
        $puesto = null;
        if ($puestoId) {
            foreach ((new Catalogo())->puestosDetalle() as $p) if ((int)$p['id'] === $puestoId) $puesto = $p;
            if (!$puesto) Nucleo::error('El puesto de votación no existe.', 422, ['campo' => 'puesto_id']);
        }
        if ($mesa !== null && ((int)$mesa < 1 || strlen($mesa) > 4)) Nucleo::error('Revisa el número de mesa.', 422, ['campo' => 'mesa']);
        if ($mesa !== null && $puesto && $puesto['mesas'] && (int)$mesa > (int)$puesto['mesas']) {
            Nucleo::error('Ese puesto solo tiene ' . (int)$puesto['mesas'] . ' mesas.', 422, ['campo' => 'mesa']);
        }
        if (!isset(compromisos_disponibles($db)[$nivel])) Nucleo::error('Elige el nivel de compromiso.', 422, ['campo' => 'nivel']);
        $m->actualizarBase($id, $puestoId, $mesa, $nivel, $verificado, $s['id']);
        if ($padre = $m->referidoPor($id)) red_actualizar_puntos($db, $padre);
        Nucleo::auditar($db, $s['id'], $verificado ? 'simpatizante_verificado' : 'simpatizante_completado', 'Desde la app · ' . $sim['nombre'] . ' · ' . compromiso_etiqueta($nivel));
        Nucleo::ok(['msg' => $verificado ? 'Verificado ✓' : 'Guardado ✓']);
    }

    private static function exigeTareas(PDO $db, array $s): void
    {
        if (!in_array(self::rol($s), ['direccion', 'coordinador', 'lider'], true)) Nucleo::error('Tu rol no tiene acceso a las tareas.', 403);
        if (!red_tareas_listas($db)) Nucleo::error('Las tareas se activan con "Actualizar plataforma" en el panel web.', 503);
    }

    /** GET equipo/tareas?estado=abierta|cerrada|todas */
    public static function tareas(PDO $db, array $in, array $s): void
    {
        self::exigeTareas($db, $s);
        $estado = in_array($_GET['estado'] ?? '', ['abierta', 'cerrada', 'todas'], true) ? $_GET['estado'] : 'abierta';
        $t = new Tarea();
        Nucleo::ok([
            'resumen' => $t->resumen(self::soloLider($s)),
            'tareas' => array_map(fn($x) => [
                'id' => (int)$x['id'], 'titulo' => $x['titulo'], 'tipoNombre' => (TAREA_TIPOS[$x['tipo']] ?? TAREA_TIPOS['otra'])[0],
                'emoji' => (TAREA_TIPOS[$x['tipo']] ?? TAREA_TIPOS['otra'])[1], 'estado' => $x['estado'], 'alcance' => $x['alcance'],
                'fechaTexto' => red_fecha($x['fecha']), 'creador' => $x['creador'],
                'total' => (int)$x['total'], 'aceptadas' => (int)$x['aceptadas'], 'porValidar' => (int)$x['por_validar'],
                'validadas' => (int)$x['validadas'], 'resultado' => (int)$x['resultado'], 'meta' => $x['meta'] !== null ? (int)$x['meta'] : null,
            ], $t->listar(self::soloLider($s), $estado)),
        ]);
    }

    /** GET equipo/tarea?id=&estado= */
    public static function tarea(PDO $db, array $in, array $s): void
    {
        self::exigeTareas($db, $s);
        $t = new Tarea();
        $id = (int)($_GET['id'] ?? 0);
        $x = $t->porId($id, self::soloLider($s));
        if (!$x) Nucleo::error('Tarea no encontrada.', 404);
        $tipo = TAREA_TIPOS[$x['tipo']] ?? TAREA_TIPOS['otra'];
        Nucleo::ok([
            'tarea' => [
                'id' => (int)$x['id'], 'titulo' => $x['titulo'], 'tipoNombre' => $tipo[0], 'emoji' => $tipo[1], 'queReportar' => $tipo[4],
                'descripcion' => $x['descripcion'], 'lugar' => $x['lugar'], 'fechaTexto' => red_fecha($x['fecha']),
                'estado' => $x['estado'], 'alcance' => $x['alcance'], 'creador' => $x['creador'],
                'meta' => $x['meta'] !== null ? (int)$x['meta'] : null, 'puntos' => (int)$x['puntos'], 'puntosAsistencia' => (int)$x['puntos_asistencia'],
            ],
            'conteo' => $t->conteo($id),
            'estados' => array_map(fn($k, $v) => ['clave' => $k, 'nombre' => $v[0], 'color' => $v[1]], array_keys(ASIGNACION_ESTADOS), ASIGNACION_ESTADOS),
            'personas' => array_map(fn($a) => [
                'asignacion' => (int)$a['id'], 'nombre' => $a['nombre'], 'zona' => $a['zona'], 'rol' => $a['rol'],
                'estado' => $a['estado'], 'estadoTexto' => ASIGNACION_ESTADOS[$a['estado']][0], 'color' => ASIGNACION_ESTADOS[$a['estado']][1],
                'resultado' => $a['resultado'] !== null ? (int)$a['resultado'] : null, 'nota' => $a['nota'],
                'telefono' => self::verTodo($s) ? $a['telefono'] : null,
                'puedeValidar' => in_array($a['estado'], ['hecha', 'no_valida', 'validada'], true),
            ], $t->asignaciones($id, (string)($_GET['estado'] ?? ''))),
        ]);
    }

    /** POST equipo/tareas/validar {asignacion, valida: bool} */
    public static function validar(PDO $db, array $in, array $s): void
    {
        self::exigeTareas($db, $s);
        $asig = (int)($in['asignacion'] ?? 0);
        $tareaId = (new Tarea())->tareaDeAsignacion($asig, self::soloLider($s));
        if (!$tareaId) Nucleo::error('No puedes validar esa tarea.', 403);
        $ok = !empty($in['valida']);
        if (!red_validar_asignacion($db, $asig, $ok, $s['id'])) Nucleo::error('Esa asignación aún no tiene nada que validar.', 409);
        Nucleo::auditar($db, $s['id'], $ok ? 'tarea_validada' : 'tarea_no_validada', "Desde la app · asignación #$asig de la tarea #$tareaId");
        Nucleo::ok(['msg' => $ok ? 'Validada: la persona ya sumó sus puntos.' : 'Marcada como no validada.']);
    }

    /** POST equipo/tareas/validar-todas {tarea} */
    public static function validarTodas(PDO $db, array $in, array $s): void
    {
        self::exigeTareas($db, $s);
        $t = new Tarea();
        $id = (int)($in['tarea'] ?? 0);
        if (!$t->porId($id, self::soloLider($s))) Nucleo::error('Tarea no encontrada.', 404);
        $n = 0;
        foreach ($t->idsPorValidar($id) as $a) $n += red_validar_asignacion($db, $a, true, $s['id']) ? 1 : 0;
        Nucleo::auditar($db, $s['id'], 'tarea_validada', "Desde la app · $n asignaciones de la tarea #$id");
        Nucleo::ok(['msg' => "$n reporte" . ($n === 1 ? '' : 's') . ' validado' . ($n === 1 ? '' : 's') . '.']);
    }
}
