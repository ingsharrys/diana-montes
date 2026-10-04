<?php
/**
 * Red de promotores ciudadanos ("Súper Promotores") + geolocalización.
 *
 * Archivo compartido entre la landing (index.php, registrar.php, promotor.php)
 * y el admin. Todas las funciones reciben el PDO por parámetro para no
 * depender de la configuración de ninguno de los dos lados.
 *
 * Cada simpatizante recibe:
 *   - codigo_promotor : su código público (?ref=pxxxxxx) para invitar a otros
 *   - token_panel     : llave secreta de su panel (promotor.php?t=...)
 *   - referido_por    : el simpatizante que lo invitó (árbol de referidos)
 *   - lat / lng       : ubicación aproximada, solo si la autorizó
 */
require_once __DIR__ . '/esquema.php';
require_once __DIR__ . '/whatsapp.php';

/** Niveles de la gamificación: [invitados mínimos, nombre, emoji]. */
const PROMOTOR_NIVELES = [
    [0,  'Simpatizante',   '🤍'],
    [3,  'Promotor',       '⭐'],
    [10, 'Súper Promotor', '🚀'],
    [25, 'Embajador',      '🏆'],
];

/** ¿Está activa la red de promotores? (existen las columnas del enlace personal) */
function promotor_esquema_listo(PDO $db): bool
{
    foreach (['codigo_promotor', 'token_panel', 'referido_por', 'lat', 'lng'] as $columna) {
        if (!esquema_tiene($db, 'simpatizantes', $columna)) return false;
    }
    return true;
}

/** Asigna código y token a los simpatizantes que aún no los tienen. */
function promotor_completar_codigos(PDO $db): int
{
    if (!promotor_esquema_listo($db)) return 0;
    $ids = $db->query('SELECT id FROM simpatizantes WHERE codigo_promotor IS NULL OR token_panel IS NULL')
              ->fetchAll(PDO::FETCH_COLUMN);
    $st = $db->prepare(
        'UPDATE simpatizantes
         SET codigo_promotor = COALESCE(codigo_promotor, :c), token_panel = COALESCE(token_panel, :t)
         WHERE id = :id'
    );
    foreach ($ids as $id) {
        $st->execute(['c' => promotor_nuevo_codigo($db), 't' => promotor_nuevo_token(), 'id' => $id]);
    }
    return count($ids);
}

/**
 * Código público corto: "p" + 6 caracteres sin ambigüedades (sin 0/o, 1/l/i).
 * Nunca choca con los codigo_ref del equipo (celulares o "nombre-gzNN").
 */
function promotor_nuevo_codigo(PDO $db): string
{
    $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';
    $chk = $db->prepare(
        'SELECT (SELECT COUNT(*) FROM simpatizantes WHERE codigo_promotor = :a)
              + (SELECT COUNT(*) FROM usuarios WHERE codigo_ref = :b)'
    );
    do {
        $codigo = 'p';
        for ($i = 0; $i < 6; $i++) $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        $chk->execute(['a' => $codigo, 'b' => $codigo]);
    } while ((int)$chk->fetchColumn() > 0);
    return $codigo;
}

/**
 * Resuelve un ?ref de invitación. Primero el equipo (usuarios.codigo_ref);
 * si no, un promotor ciudadano (simpatizantes.codigo_promotor), cuyo
 * invitado hereda el líder del promotor.
 *
 * Devuelve ['nombre', 'lider_id' (null si el líder está inactivo),
 * 'referido_por' (id del simpatizante que invita o null)] o null.
 */
function promotor_resolver_ref(PDO $db, string $ref): ?array
{
    if (!preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $ref)) return null;

    $st = $db->prepare('SELECT id, nombre FROM usuarios WHERE codigo_ref = :r AND activo = 1 LIMIT 1');
    $st->execute(['r' => $ref]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        return ['nombre' => $u['nombre'], 'lider_id' => (int)$u['id'], 'referido_por' => null];
    }

    if (!promotor_esquema_listo($db)) return null;
    $st = $db->prepare(
        'SELECT s.id, s.nombre, s.lider_id, u.activo AS lider_activo
         FROM simpatizantes s LEFT JOIN usuarios u ON u.id = s.lider_id
         WHERE s.codigo_promotor = :r LIMIT 1'
    );
    $st->execute(['r' => $ref]);
    if ($s = $st->fetch(PDO::FETCH_ASSOC)) {
        return [
            'nombre'       => promotor_nombre_corto($s['nombre']),
            'lider_id'     => $s['lider_activo'] ? (int)$s['lider_id'] : null,
            'referido_por' => (int)$s['id'],
        ];
    }
    return null;
}

/** Llave secreta del panel (128 bits). */
function promotor_nuevo_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Nivel actual según invitados directos.
 * Devuelve: actual [min,nombre,emoji], siguiente (o null), faltan, progreso (0-100).
 */
function promotor_nivel(int $invitados): array
{
    $actual = PROMOTOR_NIVELES[0];
    $siguiente = null;
    foreach (PROMOTOR_NIVELES as $nivel) {
        if ($invitados >= $nivel[0]) { $actual = $nivel; continue; }
        $siguiente = $nivel;
        break;
    }
    if ($siguiente === null) {
        return ['actual' => $actual, 'siguiente' => null, 'faltan' => 0, 'progreso' => 100];
    }
    $tramo = $siguiente[0] - $actual[0];
    return [
        'actual'    => $actual,
        'siguiente' => $siguiente,
        'faltan'    => $siguiente[0] - $invitados,
        'progreso'  => (int)round(($invitados - $actual[0]) * 100 / $tramo),
    ];
}

/** "María Fernanda Ortiz Gómez" -> "María F. O." (para rankings públicos). */
function promotor_nombre_corto(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    if (!$partes) return '';
    $corto = array_shift($partes);
    foreach (array_slice($partes, 0, 2) as $p) $corto .= ' ' . mb_strtoupper(mb_substr($p, 0, 1)) . '.';
    return $corto;
}

/**
 * Valida una coordenada enviada por el navegador. Se redondea a 3 decimales
 * (~100 m): ubicación APROXIMADA, nunca la casa exacta del ciudadano.
 */
function promotor_coordenada($valor, float $limite): ?float
{
    if (!is_string($valor) || !is_numeric($valor)) return null;
    $v = (float)$valor;
    if ($v < -$limite || $v > $limite || $v == 0.0) return null;
    return round($v, 3);
}
