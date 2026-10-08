<?php
namespace Api;

use PDO;

/**
 * Panel del simpatizante en la app: lo mismo que /mi/ en la web (nivel,
 * puntos, red, tareas) con los mismos permisos por nivel.
 */
final class MiApi
{
    private static function yo(PDO $db, array $s): array
    {
        $yo = portal_yo($db, $s['id']);
        if (!$yo) Nucleo::error('Sesión vencida.', 401);
        return $yo;
    }

    private static function enlace(array $yo): string
    {
        return rtrim((string)(defined('LANDING_URL') ? LANDING_URL : ''), '/') . '/?ref=' . rawurlencode((string)$yo['codigo_promotor']) . '#sumate';
    }

    /** Tarea/asignación lista para la app. */
    private static function tarea(array $t, int $yoId): array
    {
        $tipo = TAREA_TIPOS[$t['tipo']] ?? TAREA_TIPOS['otra'];
        $esAsistente = ($t['rol'] ?? 'responsable') === 'asistente';
        $estado = isset($t['asig_id']) ? $t['estado'] : null;   // en las tareas abiertas 'estado' es el de la tarea
        $estadoTxt = $estado ? ASIGNACION_ESTADOS[$estado][0] : null;
        if ($esAsistente && $estado === 'aceptada') $estadoTxt = 'Asistirás';
        if ($esAsistente && $estado === 'hecha') $estadoTxt = 'Asististe';
        return [
            'asignacion' => isset($t['asig_id']) ? (int)$t['asig_id'] : null,
            'tarea'      => (int)$t['id'],
            'tipo'       => $t['tipo'], 'tipoNombre' => $tipo[0], 'emoji' => $tipo[1],
            'titulo'     => $t['titulo'], 'descripcion' => $t['descripcion'],
            'fecha'      => Nucleo::iso($t['fecha']), 'fechaTexto' => red_fecha($t['fecha']),
            'lugar'      => $t['lugar'], 'meta' => $t['meta'] !== null ? (int)$t['meta'] : null,
            'puntos'     => $esAsistente || ($tipo[3] && !isset($t['asig_id'])) ? (int)$t['puntos_asistencia'] : (int)$t['puntos'],
            'rol'        => $t['rol'] ?? null, 'esInvitacion' => $esAsistente || (!isset($t['asig_id']) && $tipo[3]),
            'estado'     => $estado, 'estadoTexto' => $estadoTxt, 'color' => $estado ? ASIGNACION_ESTADOS[$estado][1] : null,
            'asignadaPor' => $t['asignada_por'] ?? null,
            'resultado'  => isset($t['resultado']) ? ($t['resultado'] === null ? null : (int)$t['resultado']) : null,
            'nota'       => $t['nota'] ?? null, 'queReportar' => $tipo[4],
            'abierta'    => isset($t['asig_id']) ? $t['tarea_estado'] === 'abierta' : $t['estado'] === 'abierta',
        ];
    }

    /** GET mi/inicio */
    public static function inicio(PDO $db, array $in, array $s): void
    {
        $yo = self::yo($db, $s);
        [$ranking, $puesto] = portal_ranking($db, $yo);
        $mis = array_values(array_filter(portal_mis_tareas($db, $yo['id']), fn($t) => (int)$t['creada_por_simpatizante'] !== $yo['id']));
        $porHacer = array_values(array_filter($mis, fn($t) => $t['estado'] === 'pendiente' || ($t['estado'] === 'aceptada' && $t['rol'] === 'responsable')));
        $enlace = self::enlace($yo);
        Nucleo::ok([
            'nombre' => $yo['nombre'], 'primerNombre' => $yo['primer_nombre'],
            'puntos' => $yo['puntos'],
            'nivel' => [
                'indice' => $yo['nivel_i'], 'nombre' => $yo['nivel']['actual'][1], 'emoji' => $yo['nivel']['actual'][2],
                'siguiente' => $yo['nivel']['siguiente'] ? ['nombre' => $yo['nivel']['siguiente'][1], 'emoji' => $yo['nivel']['siguiente'][2], 'puntos' => $yo['nivel']['siguiente'][0]] : null,
                'faltan' => $yo['nivel']['faltan'], 'progreso' => $yo['nivel']['progreso'],
            ],
            'kpis' => ['invitados' => (int)$yo['invitados'], 'red' => portal_tamano_red($db, $yo['id']), 'porHacer' => count($porHacer), 'puesto' => $puesto],
            'enlace' => $enlace,
            'textoInvitacion' => '¡Hola! 👋 Me sumé a la campaña de Diana Lucía Montes a la Alcaldía de Garzón. Súmate tú también, toma menos de un minuto: ' . $enlace,
            'proximas' => array_map(fn($t) => self::tarea($t, $yo['id']), array_slice(array_values(array_filter($mis, fn($t) => in_array($t['estado'], ['pendiente', 'aceptada'], true))), 0, 3)),
            'permisos' => array_map(fn($k, $p) => [
                'clave' => $k, 'texto' => $p[1], 'desbloqueado' => $yo['nivel_i'] >= $p[0],
                'nivel' => PROMOTOR_NIVELES[$p[0]][1], 'emoji' => PROMOTOR_NIVELES[$p[0]][2], 'puntos' => PROMOTOR_NIVELES[$p[0]][0],
            ], array_keys(RED_PERMISOS), RED_PERMISOS),
            'niveles' => array_map(fn($n) => ['nombre' => $n[1], 'emoji' => $n[2], 'puntos' => $n[0]], PROMOTOR_NIVELES),
            'puntosPor' => ['invitado' => RED_PUNTOS_INVITADO, 'seguro' => RED_PUNTOS_SEGURO],
            'ranking' => array_map(fn($r, $i) => [
                'puesto' => $i + 1, 'nombre' => promotor_nombre_corto($r['nombre']), 'puntos' => (int)$r['puntos'], 'esYo' => (int)$r['id'] === $yo['id'],
            ], $ranking, array_keys($ranking)),
            'zona' => $yo['zona'], 'lider' => $yo['lider'],
        ]);
    }

    /** GET mi/red */
    public static function red(PDO $db, array $in, array $s): void
    {
        $yo = self::yo($db, $s);
        $contactar = portal_puede($yo, 'contactar');
        $completa = portal_puede($yo, 'red_completa');
        Nucleo::ok([
            'puedeContactar' => $contactar,
            'puedeVerTodo' => $completa,
            'nivelParaContactar' => PROMOTOR_NIVELES[RED_PERMISOS['contactar'][0]][1],
            'nivelParaVerTodo' => PROMOTOR_NIVELES[RED_PERMISOS['red_completa'][0]][1],
            'total' => portal_tamano_red($db, $yo['id']),
            'invitados' => array_map(fn($i) => [
                'id' => (int)$i['id'], 'nombre' => $i['nombre'], 'zona' => $i['zona'],
                'desde' => Nucleo::iso($i['created_at']), 'invitados' => (int)$i['invitados'],
                'nivelEmoji' => promotor_nivel(red_puntos_fila($i))['actual'][2],
                'telefono' => $contactar ? $i['telefono'] : null,
            ], portal_invitados($db, $yo['id'])),
            'red' => $completa ? array_map(fn($r) => [
                'nombre' => promotor_nombre_corto($r['nombre']), 'capa' => (int)$r['nivel_red'],
                'invito' => promotor_nombre_corto((string)$r['invito']), 'zona' => $r['zona'],
            ], portal_red_completa($db, $yo['id'])) : null,
        ]);
    }

    /** GET mi/tareas */
    public static function tareas(PDO $db, array $in, array $s): void
    {
        $yo = self::yo($db, $s);
        $mis = array_values(array_filter(portal_mis_tareas($db, $yo['id']), fn($t) => (int)$t['creada_por_simpatizante'] !== $yo['id']));
        $map = fn($t) => self::tarea($t, $yo['id']);
        Nucleo::ok([
            'porResponder' => array_values(array_map($map, array_filter($mis, fn($t) => $t['estado'] === 'pendiente' && $t['tarea_estado'] === 'abierta'))),
            'enCurso'      => array_values(array_map($map, array_filter($mis, fn($t) => $t['estado'] === 'aceptada' && $t['tarea_estado'] === 'abierta'))),
            'historial'    => array_values(array_map($map, array_filter($mis, fn($t) => !in_array($t['estado'], ['pendiente', 'aceptada'], true) || $t['tarea_estado'] !== 'abierta'))),
            'abiertas'     => array_map($map, portal_tareas_abiertas($db, $yo)),
            'organizo'     => array_map(fn($o) => [
                'tarea' => (int)$o['id'], 'titulo' => $o['titulo'], 'emoji' => (TAREA_TIPOS[$o['tipo']] ?? TAREA_TIPOS['otra'])[1],
                'fechaTexto' => red_fecha($o['fecha']), 'esConvocatoria' => $o['alcance'] === 'red',
                'invitados' => (int)$o['invitados'], 'confirmados' => (int)$o['confirmados'],
                'responsables' => (int)$o['responsables'], 'cumplidas' => (int)$o['cumplidas'], 'abierta' => $o['estado'] === 'abierta',
            ], portal_mis_convocatorias($db, $yo['id'])),
            'puedeConvocar' => portal_puede($yo, 'convocar'),
            'puedeAsignar'  => portal_puede($yo, 'asignar'),
            'portalWeb'     => rtrim((string)(defined('LANDING_URL') ? LANDING_URL : ''), '/') . '/mi/',
        ]);
    }

    /** POST mi/tareas/responder {asignacion, respuesta: aceptar|rechazar} */
    public static function responder(PDO $db, array $in, array $s): void
    {
        $resp = (string)($in['respuesta'] ?? '');
        if (!red_responder($db, $s['id'], (int)($in['asignacion'] ?? 0), $resp)) Nucleo::error('No se pudo actualizar esa tarea.', 409);
        Nucleo::ok(['msg' => $resp === 'aceptar' ? '¡Gracias! Quedó anotado. 💜' : 'Entendido, quedó anotado que no puedes.']);
    }

    /** POST mi/tareas/reportar {asignacion, resultado, nota} */
    public static function reportar(PDO $db, array $in, array $s): void
    {
        if (!isset($in['resultado']) || !preg_match('/^\d{1,6}$/', (string)$in['resultado'])) {
            Nucleo::error('Escribe un número (puede ser 0).', 422, ['campo' => 'resultado']);
        }
        if (!red_reportar($db, $s['id'], (int)($in['asignacion'] ?? 0), $in['resultado'], (string)($in['nota'] ?? ''))) {
            Nucleo::error('No se pudo enviar el reporte.', 409);
        }
        Nucleo::ok(['msg' => '¡Reporte enviado! Cuando el equipo lo valide sumarás tus puntos.']);
    }

    /** POST mi/tareas/apuntarme {tarea} */
    public static function apuntarme(PDO $db, array $in, array $s): void
    {
        $yo = self::yo($db, $s);
        if (!red_apuntarme($db, $yo['id'], (int)($in['tarea'] ?? 0), array_column(portal_tareas_abiertas($db, $yo), null, 'id'))) {
            Nucleo::error('Esa tarea ya no está disponible.', 409);
        }
        Nucleo::ok(['msg' => '¡Te apuntaste! La encuentras en tus tareas.']);
    }
}
