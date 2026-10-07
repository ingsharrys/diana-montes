<?php
/**
 * /mi/ — Panel del simpatizante (portal de la red de Diana Lucía Montes).
 *
 * Usuario: su número de documento. Clave: la que creó al registrarse.
 * También se entra con el enlace personal del panel (?t=TOKEN), que llega al
 * registrarse y por WhatsApp; quien entra así sin clave la crea en ese momento.
 *
 * Lo que cada quien ve y puede hacer depende de su nivel (ver RED_PERMISOS en
 * inc/red.php): Simpatizante, Promotor, Súper Promotor y Embajador.
 */
define('PORTAL', true);
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/promotores.php';
require __DIR__ . '/portal.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

portal_sesion();
$db = db();
$p  = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'inicio';

if (!red_portal_listo($db)) {
    $vista = 'no_listo';
    require __DIR__ . '/vistas/_layout.php';
    exit;
}

/* ---------- Entrada con el enlace personal del panel (?t=TOKEN) ---------- */
if (is_string($_GET['t'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_GET['t'])) {
    $st = $db->prepare('SELECT id FROM simpatizantes WHERE token_panel = :t LIMIT 1');
    $st->execute(['t' => $_GET['t']]);
    if ($id = $st->fetchColumn()) {
        portal_entrar((int)$id);
        $db->prepare('UPDATE simpatizantes SET ultimo_acceso = NOW() WHERE id = :id')->execute(['id' => $id]);
        portal_ir(); // quita la llave de la barra de direcciones
    }
    portal_aviso('error', 'Ese enlace no es válido. Entra con tu documento y tu clave.');
    portal_ir('login');
}

if ($p === 'salir') {
    $_SESSION = [];
    session_destroy();
    portal_ir('login');
}

/* ---------- Iniciar sesión ---------- */
$yo = isset($_SESSION['sid']) ? portal_yo($db, (int)$_SESSION['sid']) : null;
if (!$yo) {
    unset($_SESSION['sid']);
    $error = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!portal_csrf_valido()) {
            $error = 'La página se venció. Intenta de nuevo.';
        } else {
            $r = red_login($db, (string)($_POST['documento'] ?? ''), (string)($_POST['clave'] ?? ''));
            if ($r['ok']) { portal_entrar($r['id']); portal_ir(); }
            $error = $r['msg'];
        }
    }
    $vista = 'login';
    require __DIR__ . '/vistas/_layout.php';
    exit;
}

$id = (int)$yo['id'];

/* ---------- Quien entró sin clave (o con una temporal) la crea primero ---------- */
$debeCrearClave = !$yo['clave_hash'] || (int)$yo['clave_cambiar'] === 1;

/* ---------- Acciones (POST) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!portal_csrf_valido()) { portal_aviso('error', 'La página se venció. Intenta de nuevo.'); portal_ir($p); }
    $accion = (string)($_POST['accion'] ?? '');
    $volver = is_string($_POST['volver'] ?? null) && preg_match('/^[a-z]+$/', $_POST['volver']) ? $_POST['volver'] : 'tareas';

    switch ($accion) {
        case 'clave':
            $nueva = (string)($_POST['nueva'] ?? '');
            if (!$debeCrearClave && !password_verify((string)($_POST['actual'] ?? ''), (string)$yo['clave_hash'])) {
                portal_aviso('error', 'Tu clave actual no es correcta.'); portal_ir('perfil');
            }
            if ($err = error_clave($nueva, (string)$yo['documento'])) { portal_aviso('error', $err); portal_ir($debeCrearClave ? 'clave' : 'perfil'); }
            if ($nueva !== (string)($_POST['repetir'] ?? '')) { portal_aviso('error', 'Las dos claves no coinciden.'); portal_ir($debeCrearClave ? 'clave' : 'perfil'); }
            red_guardar_clave($db, $id, $nueva);
            session_regenerate_id(true);
            portal_aviso('ok', $debeCrearClave ? '¡Listo! Ya tienes tu clave. Tu usuario es tu número de documento.' : 'Cambiaste tu clave.');
            portal_ir($debeCrearClave ? 'inicio' : 'perfil');

        case 'responder': // aceptar o rechazar una tarea o invitación
            $resp = (string)($_POST['respuesta'] ?? '');
            $nuevo = ['aceptar' => 'aceptada', 'rechazar' => 'rechazada'][$resp] ?? null;
            $st = $db->prepare("UPDATE tarea_asignaciones a JOIN tareas t ON t.id = a.tarea_id
                                SET a.estado = :e, a.actualizada_at = NOW()
                                WHERE a.id = :a AND a.simpatizante_id = :s AND t.estado = 'abierta'
                                  AND a.estado IN ('pendiente','aceptada','rechazada')");
            $st->execute(['e' => $nuevo ?? 'x', 'a' => (int)($_POST['asig'] ?? 0), 's' => $id]);
            portal_aviso($st->rowCount() ? 'ok' : 'error', $st->rowCount()
                ? ($nuevo === 'aceptada' ? '¡Gracias! Quedó anotado. 💜' : 'Entendido, quedó anotado que no puedes.')
                : 'No se pudo actualizar esa tarea.');
            portal_ir($volver);

        case 'reportar': // contar lo que se logró en la tarea
            $resultado = (int)preg_replace('/\D/', '', (string)($_POST['resultado'] ?? ''));
            $nota = trim(mb_substr(preg_replace('/\s+/u', ' ', (string)($_POST['nota'] ?? '')), 0, 500));
            if ($resultado > 100000) $resultado = 100000;
            $st = $db->prepare("UPDATE tarea_asignaciones a JOIN tareas t ON t.id = a.tarea_id
                                SET a.estado = 'hecha', a.resultado = :r, a.nota = :n, a.actualizada_at = NOW()
                                WHERE a.id = :a AND a.simpatizante_id = :s AND a.rol = 'responsable'
                                  AND t.estado = 'abierta' AND a.estado IN ('pendiente','aceptada','hecha')");
            $st->execute(['r' => $resultado, 'n' => $nota ?: null, 'a' => (int)($_POST['asig'] ?? 0), 's' => $id]);
            portal_aviso($st->rowCount() ? 'ok' : 'error', $st->rowCount()
                ? '¡Reporte enviado! Cuando el equipo lo valide sumarás tus puntos.'
                : 'No se pudo enviar el reporte.');
            portal_ir($volver);

        case 'apuntarme': // tomar una tarea abierta
            $tareaId = (int)($_POST['tarea'] ?? 0);
            $abiertas = array_column(portal_tareas_abiertas($db, $yo), null, 'id');
            if (!isset($abiertas[$tareaId])) { portal_aviso('error', 'Esa tarea ya no está disponible.'); portal_ir('tareas'); }
            $esEvento = TAREA_TIPOS[$abiertas[$tareaId]['tipo']][3] ?? false;
            red_asignar($db, $tareaId, [$id], null, null, false, $esEvento ? 'asistente' : 'responsable', 'aceptada');
            portal_aviso('ok', '¡Te apuntaste! La encuentras en "Mis tareas".');
            portal_ir('tareas');

        case 'convocar': // reunión o evento con la propia red (Súper Promotor+)
            if (!portal_puede($yo, 'convocar')) { portal_aviso('error', 'Convocar reuniones se desbloquea en el nivel Súper Promotor.'); portal_ir('tareas'); }
            $titulo = limpiar_texto_catalogo((string)($_POST['titulo'] ?? ''), 150);
            $lugar  = limpiar_texto_catalogo((string)($_POST['lugar'] ?? ''), 200);
            $fecha  = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['fecha'] ?? ''));
            $desc   = trim(mb_substr((string)($_POST['descripcion'] ?? ''), 0, 1000));
            $quienes = ($_POST['quienes'] ?? '') === 'directos' ? 'directos' : 'toda';
            if (!$titulo || !$lugar || !$fecha || $fecha < new DateTime()) {
                portal_aviso('error', 'Escribe el nombre de la reunión, el lugar y una fecha futura.'); portal_ir('convocar');
            }
            $red = red_descendientes($db, $id, 1000, $quienes === 'directos' ? 1 : 50);
            if (!$red) { portal_aviso('error', 'Aún no tienes personas en tu red para convocar.'); portal_ir('convocar'); }
            $tareaId = red_crear_tarea($db, [
                'tipo' => 'reunion', 'titulo' => $titulo, 'descripcion' => $desc ?: null, 'lugar' => $lugar,
                'fecha' => $fecha->format('Y-m-d H:i:s'), 'puntos' => TAREA_TIPOS['reunion'][2],
                'puntos_asistencia' => RED_PUNTOS_ASISTENCIA, 'alcance' => 'red',
                'lider_id' => $yo['lider_id'], 'creada_por_simpatizante' => $id,
            ]);
            red_asignar($db, $tareaId, [$id], null, $id, false, 'responsable', 'aceptada');
            $n = red_asignar($db, $tareaId, array_keys($red), null, $id, true, 'asistente');
            portal_aviso('ok', "¡Convocatoria creada! Invitaste a $n persona" . ($n === 1 ? '' : 's') . ' de tu red.');
            portal_ir('convocatoria', ['id' => $tareaId]);

        case 'asistencia': // quien convocó marca quiénes asistieron
            $tareaId = (int)($_POST['tarea'] ?? 0);
            $st = $db->prepare("SELECT id FROM tareas WHERE id = :t AND creada_por_simpatizante = :s AND estado = 'abierta'");
            $st->execute(['t' => $tareaId, 's' => $id]);
            if (!$st->fetchColumn()) { portal_aviso('error', 'No puedes modificar esa convocatoria.'); portal_ir('tareas'); }
            $asistieron = array_map('intval', (array)($_POST['asistio'] ?? []));
            $db->prepare("UPDATE tarea_asignaciones SET estado = IF(estado = 'hecha', 'aceptada', estado), actualizada_at = NOW()
                          WHERE tarea_id = :t AND rol = 'asistente' AND estado = 'hecha'")->execute(['t' => $tareaId]);
            $marcar = $db->prepare("UPDATE tarea_asignaciones SET estado = 'hecha', actualizada_at = NOW()
                                    WHERE tarea_id = :t AND rol = 'asistente' AND id = :a AND estado IN ('pendiente','aceptada','rechazada')");
            $n = 0;
            foreach ($asistieron as $a) { $marcar->execute(['t' => $tareaId, 'a' => $a]); $n += $marcar->rowCount(); }
            $db->prepare("UPDATE tarea_asignaciones SET estado = 'hecha', resultado = :r, actualizada_at = NOW()
                          WHERE tarea_id = :t AND simpatizante_id = :s AND rol = 'responsable' AND estado IN ('pendiente','aceptada','hecha')")
               ->execute(['r' => $n, 't' => $tareaId, 's' => $id]);
            portal_aviso('ok', "Asistencia guardada: $n persona" . ($n === 1 ? '' : 's') . '. El equipo la validará para sumar los puntos.');
            portal_ir('convocatoria', ['id' => $tareaId]);

        case 'cancelar_convocatoria':
            $st = $db->prepare("UPDATE tareas SET estado = 'cancelada' WHERE id = :t AND creada_por_simpatizante = :s AND estado = 'abierta'");
            $st->execute(['t' => (int)($_POST['tarea'] ?? 0), 's' => $id]);
            portal_aviso($st->rowCount() ? 'ok' : 'error', $st->rowCount() ? 'Convocatoria cancelada.' : 'No se pudo cancelar.');
            portal_ir('tareas');

        case 'asignar': // tareas para la propia red (Embajador)
            if (!portal_puede($yo, 'asignar')) { portal_aviso('error', 'Asignar tareas se desbloquea en el nivel Embajador.'); portal_ir('tareas'); }
            $tipo   = array_key_exists($_POST['tipo'] ?? '', TAREA_TIPOS) && !TAREA_TIPOS[$_POST['tipo']][3] ? $_POST['tipo'] : null;
            $titulo = limpiar_texto_catalogo((string)($_POST['titulo'] ?? ''), 150);
            $desc   = trim(mb_substr((string)($_POST['descripcion'] ?? ''), 0, 1000));
            $fecha  = ($_POST['fecha'] ?? '') !== '' ? DateTime::createFromFormat('Y-m-d', (string)$_POST['fecha']) : null;
            $meta   = (int)preg_replace('/\D/', '', (string)($_POST['meta'] ?? '')) ?: null;
            $red    = red_descendientes($db, $id, 1000);
            $ids    = array_values(array_filter(array_map('intval', (array)($_POST['personas'] ?? [])), fn($x) => isset($red[$x])));
            if (!$tipo || !$titulo) { portal_aviso('error', 'Elige el tipo de tarea y escribe qué hay que hacer.'); portal_ir('asignar'); }
            if (!$ids) { portal_aviso('error', 'Elige al menos una persona de tu red.'); portal_ir('asignar'); }
            if (($_POST['fecha'] ?? '') !== '' && (!$fecha || $fecha->format('Y-m-d') < date('Y-m-d'))) {
                portal_aviso('error', 'La fecha límite debe ser hoy o una fecha futura.'); portal_ir('asignar');
            }
            $tareaId = red_crear_tarea($db, [
                'tipo' => $tipo, 'titulo' => $titulo, 'descripcion' => $desc ?: null,
                'fecha' => $fecha ? $fecha->format('Y-m-d 23:59:00') : null, 'meta' => $meta,
                'puntos' => TAREA_TIPOS[$tipo][2], 'alcance' => 'asignada',
                'lider_id' => $yo['lider_id'], 'creada_por_simpatizante' => $id,
            ]);
            $n = red_asignar($db, $tareaId, $ids, null, $id, true);
            portal_aviso('ok', "Tarea asignada a $n persona" . ($n === 1 ? '' : 's') . '. Verás su avance en "Lo que organizo".');
            portal_ir('convocatoria', ['id' => $tareaId]);
    }
    portal_ir($p);
}

/* ---------- Páginas ---------- */
if ($debeCrearClave && $p !== 'clave') portal_ir('clave');

$paginas = ['inicio', 'red', 'tareas', 'convocar', 'asignar', 'convocatoria', 'perfil', 'clave'];
$vista = in_array($p, $paginas, true) ? $p : 'inicio';
if ($vista === 'clave' && !$debeCrearClave) $vista = 'perfil';

// Datos comunes del encabezado y la navegación
$misTareas = portal_mis_tareas($db, $id);
// Lo que la persona organiza para su red se ve en "Lo que organizo", no en sus tareas
$misTareas = array_values(array_filter($misTareas, fn($t) => (int)$t['creada_por_simpatizante'] !== $id));
$porHacer  = count(array_filter($misTareas, fn($t) => $t['estado'] === 'pendiente'
                                                     || ($t['estado'] === 'aceptada' && $t['rol'] === 'responsable')));

switch ($vista) {
    case 'inicio':
        [$ranking, $puesto] = portal_ranking($db, $yo);
        $tamanoRed = portal_tamano_red($db, $id);
        $enlace = portal_enlace_invitacion($yo);
        break;
    case 'red':
        $invitados = portal_invitados($db, $id);
        $redCompleta = portal_puede($yo, 'red_completa') ? portal_red_completa($db, $id) : [];
        $tamanoRed = portal_tamano_red($db, $id);
        break;
    case 'tareas':
        $abiertas = portal_tareas_abiertas($db, $yo);
        $organizo = portal_mis_convocatorias($db, $id);
        break;
    case 'convocar':
        if (!portal_puede($yo, 'convocar')) { portal_aviso('error', 'Convocar reuniones se desbloquea en el nivel Súper Promotor.'); portal_ir('tareas'); }
        $directos = count(red_descendientes($db, $id, 1000, 1));
        $tamanoRed = portal_tamano_red($db, $id);
        break;
    case 'asignar':
        if (!portal_puede($yo, 'asignar')) { portal_aviso('error', 'Asignar tareas se desbloquea en el nivel Embajador.'); portal_ir('tareas'); }
        $red = portal_invitados($db, $id);
        $redCompleta = portal_red_completa($db, $id, 300);
        break;
    case 'convocatoria':
        $st = $db->prepare("SELECT * FROM tareas WHERE id = :t AND creada_por_simpatizante = :s");
        $st->execute(['t' => (int)($_GET['id'] ?? 0), 's' => $id]);
        $tarea = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tarea) portal_ir('tareas');
        $st = $db->prepare(
            "SELECT a.id, a.estado, a.rol, a.resultado, a.nota, s.nombre, s.telefono
             FROM tarea_asignaciones a JOIN simpatizantes s ON s.id = a.simpatizante_id
             WHERE a.tarea_id = :t AND a.simpatizante_id <> :s ORDER BY s.nombre"
        );
        $st->execute(['t' => $tarea['id'], 's' => $id]);
        $participantes = $st->fetchAll(PDO::FETCH_ASSOC);
        break;
}

require __DIR__ . '/vistas/_layout.php';
