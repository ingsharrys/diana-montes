<?php
/**
 * Funciones del portal del simpatizante (/mi/): sesión propia, CSRF,
 * consultas de su panel y reglas de lo que puede hacer según su nivel.
 * La sesión del portal es independiente de la del equipo (admin).
 */
defined('PORTAL') or exit;

/* ======================= Sesión y seguridad ======================= */

function portal_base(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mi/index.php')), '/') . '/';
}

function portal_sesion(): void
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dias  = 30;
    ini_set('session.gc_maxlifetime', (string)($dias * 86400));
    session_name('dm_mi_panel');
    session_set_cookie_params([
        'lifetime' => $dias * 86400,   // queda abierta en el celular hasta que salga
        'path'     => portal_base(),   // la cookie solo viaja al portal, nunca al admin
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

function portal_url(string $p = '', array $q = []): string
{
    if ($p !== '') $q = ['p' => $p] + $q;
    return portal_base() . ($q ? '?' . http_build_query($q) : '');
}

function portal_ir(string $p = '', array $q = []): void
{
    header('Location: ' . portal_url($p, $q));
    exit;
}

function portal_csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function portal_campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . portal_csrf() . '">';
}

function portal_csrf_valido(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals(portal_csrf(), $_POST['csrf']);
}

function portal_aviso(string $tipo, string $msg): void
{
    $_SESSION['aviso'] = [$tipo, $msg];
}

function portal_entrar(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['sid'] = $id;
    unset($_SESSION['csrf']);
}

/* ======================= Datos del panel ======================= */

/** Simpatizante de la sesión con sus datos, puntos, nivel y permisos. */
function portal_yo(PDO $db, int $id): ?array
{
    $st = $db->prepare(
        'SELECT s.id, s.nombre, s.documento, s.telefono, s.codigo_promotor, s.token_panel, s.created_at,
                s.puntos, s.clave_hash, s.clave_cambiar, s.lider_id, s.zona_id, s.referido_por,
                z.nombre AS zona, u.nombre AS lider,
                (SELECT COUNT(*) FROM simpatizantes r WHERE r.referido_por = s.id) AS invitados
         FROM simpatizantes s
         LEFT JOIN zonas z ON z.id = s.zona_id
         LEFT JOIN usuarios u ON u.id = s.lider_id
         WHERE s.id = :id'
    );
    $st->execute(['id' => $id]);
    $yo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$yo) return null;
    $yo['puntos'] = (int)$yo['puntos'];
    $yo['nivel_i'] = red_nivel_indice($yo['puntos']);
    $yo['nivel'] = promotor_nivel($yo['puntos']);
    $yo['primer_nombre'] = mb_convert_case(mb_strtolower((string)strtok($yo['nombre'], ' ')), MB_CASE_TITLE);
    return $yo;
}

function portal_puede(array $yo, string $permiso): bool
{
    return red_puede($yo['nivel_i'], $permiso);
}

/** Enlace personal para invitar (en la carpeta de la landing). */
function portal_enlace_invitacion(array $yo): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host  = preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';
    $raiz  = rtrim(dirname(rtrim(portal_base(), '/')), '/\\');
    return ($https ? 'https' : 'http') . '://' . $host . $raiz . '/?ref=' . rawurlencode((string)$yo['codigo_promotor']) . '#sumate';
}

/** Ranking por puntos: los 10 primeros y el puesto de la persona. */
function portal_ranking(PDO $db, array $yo): array
{
    $top = $db->query(
        'SELECT id, nombre, puntos FROM simpatizantes WHERE puntos > 0 ORDER BY puntos DESC, id ASC LIMIT 10'
    )->fetchAll(PDO::FETCH_ASSOC);
    $puesto = null;
    if ($yo['puntos'] > 0) {
        $st = $db->prepare('SELECT COUNT(*) FROM simpatizantes WHERE puntos > :p OR (puntos = :p2 AND id < :id)');
        $st->execute(['p' => $yo['puntos'], 'p2' => $yo['puntos'], 'id' => $yo['id']]);
        $puesto = (int)$st->fetchColumn() + 1;
    }
    return [$top, $puesto];
}

/** Invitados directos con sus propios invitados y nivel. */
function portal_invitados(PDO $db, int $id): array
{
    $st = $db->prepare(
        'SELECT s.id, s.nombre, s.telefono, s.created_at, s.puntos, z.nombre AS zona,
                (SELECT COUNT(*) FROM simpatizantes r WHERE r.referido_por = s.id) AS invitados
         FROM simpatizantes s LEFT JOIN zonas z ON z.id = s.zona_id
         WHERE s.referido_por = :id ORDER BY s.created_at DESC'
    );
    $st->execute(['id' => $id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Toda la red (sin los directos) con profundidad, para quien tiene el permiso. */
function portal_red_completa(PDO $db, int $id, int $max = 500): array
{
    $red = red_descendientes($db, $id, $max);
    $red = array_filter($red, fn($n) => $n > 1);
    if (!$red) return [];
    $ids = array_keys($red);
    $st = $db->prepare(
        'SELECT s.id, s.nombre, s.referido_por, s.created_at, z.nombre AS zona, p.nombre AS invito
         FROM simpatizantes s LEFT JOIN zonas z ON z.id = s.zona_id LEFT JOIN simpatizantes p ON p.id = s.referido_por
         WHERE s.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
    );
    $st->execute($ids);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as &$f) $f['nivel_red'] = $red[(int)$f['id']];
    unset($f);
    usort($filas, fn($a, $b) => [$a['nivel_red'], $a['nombre']] <=> [$b['nivel_red'], $b['nombre']]);
    return $filas;
}

/** Cuántas personas tiene la red completa (todas las capas). */
function portal_tamano_red(PDO $db, int $id): int
{
    return count(red_descendientes($db, $id, 20000));
}

/** Asignaciones de la persona con los datos de su tarea. */
function portal_mis_tareas(PDO $db, int $id): array
{
    $st = $db->prepare(
        "SELECT a.id AS asig_id, a.estado, a.rol, a.resultado, a.nota, a.created_at AS asignada_at,
                t.id, t.tipo, t.titulo, t.descripcion, t.lugar, t.fecha, t.meta, t.puntos, t.puntos_asistencia,
                t.estado AS tarea_estado, t.creada_por_simpatizante,
                COALESCE(u.nombre, ps.nombre) AS asignada_por
         FROM tarea_asignaciones a
         JOIN tareas t ON t.id = a.tarea_id
         LEFT JOIN usuarios u ON u.id = a.asignada_por_usuario
         LEFT JOIN simpatizantes ps ON ps.id = a.asignada_por_simpatizante
         WHERE a.simpatizante_id = :id AND t.estado <> 'cancelada'
         ORDER BY FIELD(a.estado, 'pendiente', 'aceptada', 'hecha', 'validada', 'no_valida', 'rechazada'),
                  COALESCE(t.fecha, '9999-12-31'), a.id DESC"
    );
    $st->execute(['id' => $id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Tareas abiertas a las que la persona se puede apuntar (por nivel, zona y líder). */
function portal_tareas_abiertas(PDO $db, array $yo): array
{
    $st = $db->prepare(
        "SELECT t.* FROM tareas t
         WHERE t.alcance = 'abierta' AND t.estado = 'abierta' AND t.nivel_minimo <= :nivel
           AND (t.zona_id IS NULL OR t.zona_id = :zona)
           AND (t.lider_id IS NULL OR t.lider_id = :lider)
           AND (t.fecha IS NULL OR t.fecha >= CURDATE())
           AND NOT EXISTS (SELECT 1 FROM tarea_asignaciones a WHERE a.tarea_id = t.id AND a.simpatizante_id = :id)
         ORDER BY COALESCE(t.fecha, '9999-12-31'), t.id DESC LIMIT 30"
    );
    $st->execute(['nivel' => $yo['nivel_i'], 'zona' => (int)$yo['zona_id'], 'lider' => (int)$yo['lider_id'], 'id' => $yo['id']]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Tareas y convocatorias que la persona creó para su red, con su avance. */
function portal_mis_convocatorias(PDO $db, int $id): array
{
    $st = $db->prepare(
        "SELECT t.*, c.invitados, c.confirmados, c.asistieron, c.responsables, c.cumplidas
         FROM tareas t
         LEFT JOIN (SELECT a.tarea_id,
                           SUM(a.rol = 'asistente') AS invitados,
                           SUM(a.rol = 'asistente' AND a.estado = 'aceptada') AS confirmados,
                           SUM(a.rol = 'asistente' AND a.estado IN ('hecha','validada')) AS asistieron,
                           SUM(a.rol = 'responsable' AND a.simpatizante_id <> :id1) AS responsables,
                           SUM(a.rol = 'responsable' AND a.simpatizante_id <> :id2 AND a.estado IN ('hecha','validada')) AS cumplidas
                    FROM tarea_asignaciones a GROUP BY a.tarea_id) c ON c.tarea_id = t.id
         WHERE t.creada_por_simpatizante = :id3 AND t.estado <> 'cancelada'
         ORDER BY t.id DESC LIMIT 30"
    );
    $st->execute(['id1' => $id, 'id2' => $id, 'id3' => $id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Etiqueta del botón para responder según el tipo de asignación. */
function portal_texto_aceptar(array $t): string
{
    return $t['rol'] === 'asistente' ? 'Asistiré' : 'La haré';
}
