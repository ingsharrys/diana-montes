<?php
/**
 * Portal del simpatizante ("Mi panel"): acceso con clave, puntos, niveles,
 * permisos por nivel y tareas. Compartido entre el portal (/mi/), la landing
 * y el admin.
 *
 * Escalera de compromiso (inspirada en la "ladder of engagement" y en las apps
 * de organización relacional como Reach, Empower u OutreachCircle): cada
 * persona sube de nivel con puntos y cada nivel desbloquea más permisos.
 *
 *   Puntos: +10 por cada invitado que se registra con tu enlace
 *           +5  extra si el equipo confirma a tu invitado como voto seguro (o más)
 *           +N  por cada tarea que el equipo valida (N depende de la tarea)
 */

/** Puntos por invitado y bono por invitado confirmado como voto seguro. */
const RED_PUNTOS_INVITADO = 10;
const RED_PUNTOS_SEGURO   = 5;

/**
 * Permisos del portal: clave => [índice del nivel mínimo en PROMOTOR_NIVELES, descripción].
 * 0 Simpatizante · 1 Promotor · 2 Súper Promotor · 3 Embajador
 */
const RED_PERMISOS = [
    'panel'        => [0, 'Tu panel, tu enlace personal y tu QR'],
    'invitados'    => [0, 'Ver a las personas que invitaste'],
    'tareas'       => [0, 'Recibir tareas y apuntarte a eventos de la campaña'],
    'contactar'    => [1, 'Escribir por WhatsApp a tus invitados directos'],
    'red_completa' => [1, 'Ver toda tu red: los invitados de tus invitados'],
    'convocar'     => [2, 'Convocar reuniones y eventos con tu red'],
    'asignar'      => [3, 'Asignar tareas a las personas de tu red'],
    'lider'        => [3, 'Ser postulado como líder del equipo de Diana'],
];

/**
 * Tipos de tarea: clave => [nombre, emoji, puntos sugeridos, ¿quien la recibe asiste
 * (evento) en vez de hacerla?, qué se reporta al terminar].
 * Una "reunión" asignada es organizarla; un "evento" asignado es asistir.
 */
const TAREA_TIPOS = [
    'reunion'      => ['Organizar una reunión',   '🏠', 30, false, 'personas que asistieron'],
    'evento'       => ['Asistir a un evento',     '🎉', 10, true,  'personas que llevaste'],
    'convocatoria' => ['Convocar personas',       '📣', 20, false, 'personas convocadas'],
    'puerta'       => ['Puerta a puerta',         '🚪', 25, false, 'casas visitadas'],
    'llamadas'     => ['Llamadas',                '📞', 15, false, 'llamadas hechas'],
    'redes'        => ['Compartir en redes',      '📱', 5,  false, 'publicaciones compartidas'],
    'otra'         => ['Otra tarea',              '✅', 10, false, 'cantidad lograda'],
];

/** Puntos por asistir a una reunión que convoca un promotor de la red. */
const RED_PUNTOS_ASISTENCIA = 5;

/** Estados de una asignación y cómo se muestran. */
const ASIGNACION_ESTADOS = [
    'pendiente' => ['Por responder', 'gris'],
    'aceptada'  => ['En curso',      'azul'],
    'rechazada' => ['No puede',      'gris'],
    'hecha'     => ['Por validar',   'oro'],
    'validada'  => ['Validada',      'verde'],
    'no_valida' => ['No validada',   'rojo'],
];

const RED_CLAVE_MIN       = 6;
const RED_MAX_INTENTOS    = 5;
const RED_BLOQUEO_MINUTOS = 15;

/* ======================= Niveles y puntos ======================= */

/** ¿Ya existen las columnas/tablas del portal? */
function red_portal_listo(PDO $db): bool
{
    return esquema_tiene($db, 'simpatizantes', 'clave_hash') && esquema_tiene($db, 'simpatizantes', 'puntos');
}

function red_tareas_listas(PDO $db): bool
{
    return esquema_tiene($db, 'tareas') && esquema_tiene($db, 'tarea_asignaciones');
}

/** Índice del nivel (0-3) según los puntos. */
function red_nivel_indice(int $puntos): int
{
    $i = 0;
    foreach (PROMOTOR_NIVELES as $n => $nivel) if ($puntos >= $nivel[0]) $i = $n;
    return $i;
}

/** ¿El nivel $indice tiene el permiso $clave? */
function red_puede(int $indice, string $clave): bool
{
    return isset(RED_PERMISOS[$clave]) && $indice >= RED_PERMISOS[$clave][0];
}

/**
 * Puntos de un grupo de simpatizantes calculados desde los datos
 * (invitados, invitados confirmados y tareas validadas). [id => puntos]
 */
function red_calcular_puntos(PDO $db, ?array $ids = null): array
{
    $seguros = "'" . implode("','", COMPROMISOS_SEGUROS) . "'";
    $filtro  = '';
    $params  = [];
    if ($ids !== null) {
        if (!$ids) return [];
        $ids = array_values(array_map('intval', $ids));
        $filtro = ' AND referido_por IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = $ids;
    }
    $puntos = $ids !== null ? array_fill_keys($ids, 0) : [];

    $st = $db->prepare(
        "SELECT referido_por AS id, COUNT(*) AS invitados, COALESCE(SUM(nivel IN ($seguros)), 0) AS seguros
         FROM simpatizantes WHERE referido_por IS NOT NULL $filtro GROUP BY referido_por"
    );
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $puntos[(int)$f['id']] = ($puntos[(int)$f['id']] ?? 0)
            + RED_PUNTOS_INVITADO * (int)$f['invitados'] + RED_PUNTOS_SEGURO * (int)$f['seguros'];
    }

    if (red_tareas_listas($db)) {
        $filtroT = $ids !== null ? ' AND a.simpatizante_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' : '';
        $st = $db->prepare(
            "SELECT a.simpatizante_id AS id, SUM(IF(a.rol = 'asistente', t.puntos_asistencia, t.puntos)) AS p
             FROM tarea_asignaciones a JOIN tareas t ON t.id = a.tarea_id
             WHERE a.estado = 'validada' $filtroT GROUP BY a.simpatizante_id"
        );
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $puntos[(int)$f['id']] = ($puntos[(int)$f['id']] ?? 0) + (int)$f['p'];
        }
    }
    return $puntos;
}

/**
 * Recalcula y guarda los puntos de una persona. Si sube de nivel, encola el
 * WhatsApp de felicitación. Devuelve [puntos antes, puntos ahora].
 */
function red_actualizar_puntos(PDO $db, int $id): array
{
    if (!esquema_tiene($db, 'simpatizantes', 'puntos')) return [0, 0];
    $st = $db->prepare('SELECT puntos FROM simpatizantes WHERE id = :id');
    $st->execute(['id' => $id]);
    $antes = $st->fetchColumn();
    if ($antes === false) return [0, 0];
    $antes = (int)$antes;
    $ahora = red_calcular_puntos($db, [$id])[$id] ?? 0;
    if ($ahora !== $antes) {
        $db->prepare('UPDATE simpatizantes SET puntos = :p WHERE id = :id')->execute(['p' => $ahora, 'id' => $id]);
        $nivel = red_nivel_indice($ahora);
        if ($nivel > red_nivel_indice($antes)) {
            try { wa_evento($db, 'sube_nivel', $id, (string)PROMOTOR_NIVELES[$nivel][0]); } catch (Throwable $e) { /* nunca frena */ }
        }
    }
    return [$antes, $ahora];
}

/** Recalcula los puntos de todos (sin avisos por WhatsApp). Devuelve cuántos cambiaron. */
function red_recalcular_todos(PDO $db): int
{
    if (!esquema_tiene($db, 'simpatizantes', 'puntos')) return 0;
    $calculados = red_calcular_puntos($db);
    $actuales = $db->query('SELECT id, puntos FROM simpatizantes')->fetchAll(PDO::FETCH_KEY_PAIR);
    $st = $db->prepare('UPDATE simpatizantes SET puntos = :p WHERE id = :id');
    $n = 0;
    foreach ($actuales as $id => $p) {
        $nuevo = $calculados[(int)$id] ?? 0;
        if ($nuevo !== (int)$p) { $st->execute(['p' => $nuevo, 'id' => $id]); $n++; }
    }
    return $n;
}

/** Puntos de una fila de simpatizante (antes de "Actualizar plataforma": 10 por invitado). */
function red_puntos_fila(array $s): int
{
    if (array_key_exists('puntos', $s) && $s['puntos'] !== null) return (int)$s['puntos'];
    return RED_PUNTOS_INVITADO * (int)($s['invitados'] ?? 0);
}

/* ======================= Acceso con clave ======================= */

/** Valida una clave nueva. Devuelve el mensaje de error o null. */
function error_clave(string $clave, string $documento = ''): ?string
{
    if (mb_strlen($clave) < RED_CLAVE_MIN) return 'La clave debe tener al menos ' . RED_CLAVE_MIN . ' caracteres.';
    if (mb_strlen($clave) > 72) return 'La clave es demasiado larga (máximo 72 caracteres).';
    if (preg_match('/^(.)\1+$/u', $clave)) return 'La clave no puede ser un solo carácter repetido.';
    if ($documento !== '' && $clave === $documento) return 'La clave no puede ser tu número de documento.';
    if (in_array($clave, ['123456', '1234567', '12345678', '123456789', '654321', 'abcdef', 'qwerty', 'diana2027', 'garzon'], true)) {
        return 'Esa clave es muy fácil de adivinar. Elige otra.';
    }
    return null;
}

/** Guarda la clave (hash). $temporal = la debe cambiar al entrar. */
function red_guardar_clave(PDO $db, int $id, string $clave, bool $temporal = false): void
{
    $db->prepare('UPDATE simpatizantes SET clave_hash = :h, clave_cambiar = :c, clave_intentos = 0, clave_bloqueo = NULL WHERE id = :id')
       ->execute(['h' => password_hash($clave, PASSWORD_DEFAULT), 'c' => $temporal ? 1 : 0, 'id' => $id]);
}

/** Clave temporal fácil de dictar: "Diana-4821". */
function red_clave_temporal(): string
{
    return 'Diana-' . random_int(1000, 9999);
}

/**
 * Inicia sesión con documento y clave. Bloquea 15 minutos tras 5 intentos fallidos.
 * Devuelve ['ok' => bool, 'id' => ?int, 'msg' => string].
 */
function red_login(PDO $db, string $documento, string $clave): array
{
    $documento = normalizar_documento($documento);
    $st = $db->prepare('SELECT id, clave_hash, clave_intentos, clave_bloqueo FROM simpatizantes WHERE documento = :d LIMIT 1');
    $st->execute(['d' => $documento]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    $malo = ['ok' => false, 'id' => null, 'msg' => 'Documento o clave incorrectos.'];

    if (!$s) { password_verify($clave, '$2y$10$' . str_repeat('a', 53)); return $malo; } // mismo tiempo de respuesta
    if ($s['clave_bloqueo'] && strtotime($s['clave_bloqueo']) > time()) {
        $min = max(1, (int)ceil((strtotime($s['clave_bloqueo']) - time()) / 60));
        return ['ok' => false, 'id' => null, 'msg' => "Por seguridad tu acceso quedó bloqueado. Intenta de nuevo en $min minuto" . ($min === 1 ? '' : 's') . '.'];
    }
    if (!$s['clave_hash']) {
        return ['ok' => false, 'id' => null, 'msg' => 'Aún no tienes clave. Entra con el enlace de tu panel que te llegó al registrarte, o pídele a tu líder una clave temporal.'];
    }
    if (!password_verify($clave, $s['clave_hash'])) {
        $intentos = (int)$s['clave_intentos'] + 1;
        $bloqueo  = $intentos >= RED_MAX_INTENTOS ? date('Y-m-d H:i:s', time() + RED_BLOQUEO_MINUTOS * 60) : null;
        $db->prepare('UPDATE simpatizantes SET clave_intentos = :i, clave_bloqueo = :b WHERE id = :id')
           ->execute(['i' => $bloqueo ? 0 : $intentos, 'b' => $bloqueo, 'id' => $s['id']]);
        return $bloqueo
            ? ['ok' => false, 'id' => null, 'msg' => 'Demasiados intentos. Tu acceso queda bloqueado ' . RED_BLOQUEO_MINUTOS . ' minutos.']
            : $malo;
    }
    if (password_needs_rehash($s['clave_hash'], PASSWORD_DEFAULT)) red_guardar_clave($db, (int)$s['id'], $clave);
    $db->prepare('UPDATE simpatizantes SET clave_intentos = 0, clave_bloqueo = NULL, ultimo_acceso = NOW() WHERE id = :id')
       ->execute(['id' => $s['id']]);
    return ['ok' => true, 'id' => (int)$s['id'], 'msg' => ''];
}

/* ======================= Red ======================= */

/**
 * Personas de la red de $id (invitados, invitados de invitados…), capa por capa.
 * Devuelve [id => profundidad relativa (1 = invitado directo)].
 */
function red_descendientes(PDO $db, int $id, int $max = 5000, int $capas = 50): array
{
    $red = [];
    $capa = [$id];
    for ($nivel = 1; $capa && $nivel <= $capas && count($red) < $max; $nivel++) {
        $st = $db->prepare('SELECT id FROM simpatizantes WHERE referido_por IN (' . implode(',', array_fill(0, count($capa), '?')) . ')');
        $st->execute($capa);
        $capa = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $hijo) {
            $hijo = (int)$hijo;
            if ($hijo === $id || isset($red[$hijo])) continue; // evita ciclos
            $red[$hijo] = $nivel;
            $capa[] = $hijo;
            if (count($red) >= $max) break;
        }
    }
    return $red;
}

/* ======================= Tareas ======================= */

/** Crea una tarea y devuelve su id. $t trae las columnas de `tareas`. */
function red_crear_tarea(PDO $db, array $t): int
{
    $cols = ['tipo', 'titulo', 'descripcion', 'lugar', 'fecha', 'meta', 'puntos', 'puntos_asistencia', 'alcance', 'nivel_minimo',
             'zona_id', 'lider_id', 'creada_por_usuario', 'creada_por_simpatizante'];
    $t = array_intersect_key($t, array_flip($cols));
    $db->prepare('INSERT INTO tareas (' . implode(', ', array_keys($t)) . ') VALUES (:' . implode(', :', array_keys($t)) . ')')
       ->execute($t);
    return (int)$db->lastInsertId();
}

/**
 * Asigna la tarea a varias personas (sin repetir). Encola el aviso por
 * WhatsApp (tarea o invitación a evento). Devuelve cuántas asignaciones nuevas.
 */
function red_asignar(PDO $db, int $tareaId, array $ids, ?int $porUsuario, ?int $porSimpatizante, bool $avisar = true,
                     string $rol = 'responsable', string $estado = 'pendiente'): int
{
    $st = $db->prepare('SELECT id FROM tareas WHERE id = :id');
    $st->execute(['id' => $tareaId]);
    if ($st->fetchColumn() === false) return 0;
    $ocasion = $rol === 'asistente' ? 'invitacion_evento' : 'tarea_asignada';

    $ins = $db->prepare(
        'INSERT IGNORE INTO tarea_asignaciones (tarea_id, simpatizante_id, rol, estado, asignada_por_usuario, asignada_por_simpatizante)
         VALUES (:t, :s, :r, :e, :u, :p)'
    );
    $n = 0;
    foreach (array_unique(array_map('intval', $ids)) as $sid) {
        if ($sid <= 0) continue;
        $ins->execute(['t' => $tareaId, 's' => $sid, 'r' => $rol, 'e' => $estado, 'u' => $porUsuario, 'p' => $porSimpatizante]);
        if ($ins->rowCount() === 0) continue;
        $n++;
        if ($avisar) {
            try { wa_evento($db, $ocasion, $sid, (string)$db->lastInsertId()); } catch (Throwable $e) { /* nunca frena */ }
        }
    }
    return $n;
}

/** Datos de la tarea de una asignación (para los mensajes de WhatsApp). */
function red_tarea_de_asignacion(PDO $db, int $asignacionId): ?array
{
    if (!red_tareas_listas($db)) return null;
    $st = $db->prepare('SELECT t.* FROM tarea_asignaciones a JOIN tareas t ON t.id = a.tarea_id WHERE a.id = :id');
    $st->execute(['id' => $asignacionId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Valida (o no) lo reportado en una asignación y actualiza los puntos.
 * Solo aplica a asignaciones "hechas" (o ya validadas, para corregir).
 */
function red_validar_asignacion(PDO $db, int $asignacionId, bool $valida, int $usuarioId): bool
{
    $st = $db->prepare('SELECT simpatizante_id, estado FROM tarea_asignaciones WHERE id = :id');
    $st->execute(['id' => $asignacionId]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a || !in_array($a['estado'], ['hecha', 'aceptada', 'validada', 'no_valida'], true)) return false;
    $db->prepare("UPDATE tarea_asignaciones SET estado = :e, validada_por = :u, actualizada_at = NOW() WHERE id = :id")
       ->execute(['e' => $valida ? 'validada' : 'no_valida', 'u' => $usuarioId, 'id' => $asignacionId]);
    red_actualizar_puntos($db, (int)$a['simpatizante_id']);
    return true;
}

/** Fecha corta en español: "sáb 18 oct, 4:00 p. m." */
function red_fecha(?string $fecha, bool $hora = true): string
{
    if (!$fecha) return '';
    $t = strtotime($fecha);
    $dias  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $txt = $dias[(int)date('w', $t)] . ' ' . date('j', $t) . ' ' . $meses[(int)date('n', $t) - 1];
    if ($hora && date('H:i', $t) !== '00:00') $txt .= ', ' . str_replace(['am', 'pm'], ['a. m.', 'p. m.'], date('g:i a', $t));
    return $txt;
}
