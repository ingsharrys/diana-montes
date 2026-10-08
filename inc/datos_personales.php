<?php
/**
 * Datos personales (Ley 1581 de 2012): datos del responsable y solicitudes
 * de los titulares (eliminar, revocar, corregir o consultar sus datos).
 *
 * Lo usan las páginas públicas /privacidad/, /terminos/ y /eliminar-datos/,
 * el webhook de WhatsApp ("ELIMINAR MIS DATOS") y el admin (Datos personales).
 * Todas las funciones reciben el PDO por parámetro.
 */
require_once __DIR__ . '/esquema.php';
require_once __DIR__ . '/validacion.php';

/** Fecha desde la que rigen la política y las condiciones publicadas. */
const LEGAL_VIGENCIA = '8 de octubre de 2026';

/** Datos del responsable que se editan en Admin › Datos personales (tabla configuracion). */
const LEGAL_CAMPOS = [
    'legal_responsable' => ['Responsable del tratamiento', 'Campaña de Diana Lucía Montes a la Alcaldía de Garzón 2027'],
    'legal_documento'   => ['NIT o cédula del responsable', ''],
    'legal_direccion'   => ['Dirección', 'Garzón, Huila, Colombia'],
    'legal_email'       => ['Correo para temas de datos personales', ''],
    'legal_telefono'    => ['WhatsApp o teléfono de la campaña', ''],
];

const SOLICITUD_TIPOS = [
    'eliminar'   => 'Eliminar mis datos',
    'revocar'    => 'Revocar la autorización y no recibir más mensajes',
    'actualizar' => 'Corregir o actualizar mis datos',
    'consultar'  => 'Saber qué datos tienen de mí',
];

const SOLICITUD_ESTADOS = [
    'pendiente' => ['Recibida, en trámite', 'oro'],
    'atendida'  => ['Atendida',             'verde'],
    'cerrada'   => ['Cerrada',              'gris'],
];

const SOLICITUD_CANALES = ['web' => 'Página web', 'whatsapp' => 'WhatsApp', 'correo' => 'Correo', 'otro' => 'Otro'];

/** Mensajes de WhatsApp con los que una persona pide borrar sus datos. */
const WA_PALABRAS_ELIMINAR = ['eliminar mis datos', 'borrar mis datos', 'eliminar datos', 'borrar datos', 'eliminar mi registro', 'borrar mi registro'];

/** Datos del responsable: lo guardado en el admin o el valor por defecto. */
function legal_datos(?PDO $db): array
{
    $r = array_map(fn($c) => $c[1], LEGAL_CAMPOS);
    if (!$db) return $r;
    try {
        if (!esquema_tiene($db, 'configuracion')) return $r;
        $st = $db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'legal\\_%'");
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if (isset($r[$k]) && trim((string)$v) !== '') $r[$k] = trim((string)$v);
        }
    } catch (Throwable $e) { /* la página legal se muestra igual */ }
    return $r;
}

/** ¿Ya existe la tabla de solicitudes? (se crea con "Actualizar plataforma") */
function solicitudes_listas(PDO $db): bool
{
    return esquema_tiene($db, 'solicitudes_datos');
}

/** Oculta la parte central de un documento o celular: 310···344. */
function dato_oculto(?string $v): ?string
{
    $v = (string)$v;
    if ($v === '') return null;
    return strlen($v) <= 6 ? str_repeat('·', strlen($v)) : substr($v, 0, 3) . '···' . substr($v, -3);
}

/**
 * Registra una solicitud del titular y devuelve su radicado.
 * Busca el registro por documento y celular para que el equipo sepa a quién corresponde.
 * $d: tipo, canal, nombre, documento, telefono, correo, detalle, ip
 */
function solicitud_crear(PDO $db, array $d): string
{
    $doc = (string)($d['documento'] ?? '');
    $tel = (string)($d['telefono'] ?? '');
    $porDoc = $porTel = null;
    if ($doc !== '') {
        $st = $db->prepare('SELECT id FROM simpatizantes WHERE documento = :d LIMIT 1');
        $st->execute(['d' => $doc]);
        $porDoc = $st->fetchColumn() ?: null;
    }
    if ($tel !== '') {
        $st = $db->prepare('SELECT id FROM simpatizantes WHERE telefono = :t LIMIT 1');
        $st->execute(['t' => $tel]);
        $porTel = $st->fetchColumn() ?: null;
    }
    $coincide = match (true) {
        $porDoc && $porDoc === $porTel => 'total',
        $porDoc !== null              => 'documento',
        $porTel !== null              => 'telefono',
        default                       => 'ninguna',
    };
    for ($i = 0; $i < 5; $i++) {
        $radicado = 'SD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            $db->prepare(
                'INSERT INTO solicitudes_datos (radicado, tipo, canal, nombre, documento, telefono, correo, detalle, simpatizante_id, coincide, ip)
                 VALUES (:r, :tipo, :canal, :n, :doc, :tel, :c, :det, :s, :co, :ip)'
            )->execute([
                'r' => $radicado, 'tipo' => $d['tipo'], 'canal' => $d['canal'] ?? 'web',
                'n' => mb_substr((string)($d['nombre'] ?? ''), 0, 120), 'doc' => $doc ?: null, 'tel' => $tel ?: null,
                'c' => ($d['correo'] ?? '') ?: null, 'det' => ($d['detalle'] ?? '') ?: null,
                's' => $porDoc ?? $porTel, 'co' => $coincide, 'ip' => $d['ip'] ?? null,
            ]);
            return $radicado;
        } catch (PDOException $e) {
            if (!es_error_duplicado($e)) throw $e;   // radicado repetido: se intenta con otro
        }
    }
    throw new RuntimeException('No se pudo generar el radicado.');
}

/** Estado público de una solicitud (sin datos personales). */
function solicitud_por_radicado(PDO $db, string $radicado): ?array
{
    $st = $db->prepare('SELECT radicado, tipo, estado, created_at, atendida_at, respuesta FROM solicitudes_datos WHERE radicado = :r');
    $st->execute(['r' => strtoupper(trim($radicado))]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Solicitudes recientes desde la misma IP (freno contra abusos del formulario). */
function solicitudes_recientes_ip(PDO $db, string $ip): int
{
    $st = $db->prepare('SELECT COUNT(*) FROM solicitudes_datos WHERE ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $st->execute(['ip' => $ip]);
    return (int)$st->fetchColumn();
}

/**
 * Borra todo lo que la plataforma guarda de un simpatizante: su registro, sus
 * mensajes de WhatsApp, sus tareas, sus sesiones de la app y sus códigos.
 * Sus invitados pasan a quien lo invitó. Devuelve el nombre o null si no existe.
 */
function datos_eliminar_simpatizante(PDO $db, int $id): ?string
{
    $st = $db->prepare('SELECT id, nombre, telefono, referido_por FROM simpatizantes WHERE id = :id');
    $st->execute(['id' => $id]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) return null;
    $ej = fn(string $sql, array $p) => $db->prepare($sql)->execute($p);
    $db->beginTransaction();
    try {
        $ej('UPDATE simpatizantes SET referido_por = :padre WHERE referido_por = :id', ['padre' => $s['referido_por'], 'id' => $id]);
        if (esquema_tiene($db, 'wa_mensajes'))        $ej('DELETE FROM wa_mensajes WHERE simpatizante_id = :id', ['id' => $id]);
        if (esquema_tiene($db, 'wa_entrantes'))       $ej('DELETE FROM wa_entrantes WHERE simpatizante_id = :id OR telefono = :t', ['id' => $id, 't' => $s['telefono']]);
        if (esquema_tiene($db, 'tarea_asignaciones')) $ej('DELETE FROM tarea_asignaciones WHERE simpatizante_id = :id', ['id' => $id]);
        if (esquema_tiene($db, 'tareas', 'creada_por_simpatizante')) $ej('UPDATE tareas SET creada_por_simpatizante = NULL WHERE creada_por_simpatizante = :id', ['id' => $id]);
        if (esquema_tiene($db, 'api_tokens'))         $ej("DELETE FROM api_tokens WHERE tipo = 'simpatizante' AND sujeto_id = :id", ['id' => $id]);
        if (esquema_tiene($db, 'otp_codigos'))        $ej('DELETE FROM otp_codigos WHERE telefono = :t', ['t' => $s['telefono']]);
        $ej('DELETE FROM simpatizantes WHERE id = :id', ['id' => $id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    if ($s['referido_por'] && function_exists('red_actualizar_puntos')) red_actualizar_puntos($db, (int)$s['referido_por']);
    return $s['nombre'];
}

/**
 * Cierra una solicitud. Si se borraron los datos, la solicitud conserva solo
 * datos ocultos: queda como prueba de que se atendió, sin guardar a la persona.
 */
function solicitud_cerrar(PDO $db, int $id, string $estado, string $respuesta, ?int $usuarioId, bool $ocultar): void
{
    $sql = 'UPDATE solicitudes_datos SET estado = :e, respuesta = :r, atendida_at = NOW(), atendida_por = :u';
    $p = ['e' => $estado, 'r' => mb_substr($respuesta, 0, 500), 'u' => $usuarioId, 'id' => $id];
    if ($ocultar) {
        $st = $db->prepare('SELECT nombre, documento, telefono FROM solicitudes_datos WHERE id = :id');
        $st->execute(['id' => $id]);
        $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $sql .= ', nombre = :n, documento = :d, telefono = :t, correo = NULL, detalle = NULL, simpatizante_id = NULL';
        $p += [
            'n' => mb_substr(explode(' ', trim((string)($f['nombre'] ?? '')))[0] ?? '', 0, 40) . ' ···',
            'd' => dato_oculto($f['documento'] ?? null), 't' => dato_oculto($f['telefono'] ?? null),
        ];
    }
    $db->prepare($sql . ' WHERE id = :id')->execute($p);
}
