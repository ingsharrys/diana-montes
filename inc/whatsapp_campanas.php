<?php
/**
 * Mensajes masivos de WhatsApp ("campañas"): una plantilla aprobada enviada a
 * todos los registros o a un grupo (zonas, líder, puesto de votación, nivel de
 * compromiso, género, profesión, edad, red de un promotor…).
 *
 * Cada campaña crea una ocasión interna (clave "campana_<id>", inactiva) para
 * reutilizar la cola, los reintentos, el webhook y las métricas de WhatsApp.
 * Sus mensajes no vencen y salen después de los saludos y avisos del día,
 * respetando el tope diario (WA_LIMITE_DIARIO) y el horario del cron.
 *
 * Solo se escribe a quien dio su consentimiento y no se ha dado de baja (wa_sql_contactable).
 */

/** Prefijo de la clave de las ocasiones internas de las campañas. */
const WA_CAMPANA_PREFIJO = 'campana_';

/** ¿Ya existe la tabla de campañas? */
function wa_campanas_disponible(PDO $db): bool
{
    return wa_disponible($db) && esquema_tiene($db, 'wa_campanas');
}

/** Rangos de edad rápidos del formulario. */
const WA_RANGOS_EDAD = ['18-25' => '18 a 25', '26-35' => '26 a 35', '36-50' => '36 a 50', '51-65' => '51 a 65', '66-120' => 'Más de 65'];

/**
 * Normaliza los filtros que llegan del formulario: solo valores válidos.
 * Claves: zonas[], zona_tipo, lideres[], puestos[], niveles[], genero,
 * profesiones[], edad_min, edad_max, red (id del promotor), verificados, promotores.
 */
function wa_campana_filtros(array $in): array
{
    $ids = fn($v) => array_values(array_unique(array_filter(array_map('intval', (array)$v), fn($x) => $x > 0)));
    $f = [
        'zonas'       => $ids($in['zonas'] ?? []),
        'zona_tipo'   => in_array($in['zona_tipo'] ?? '', ['urbano', 'rural'], true) ? $in['zona_tipo'] : '',
        'lideres'     => $ids($in['lideres'] ?? []),
        'puestos'     => $ids($in['puestos'] ?? []),
        'niveles'     => array_values(array_intersect(array_keys(COMPROMISOS), (array)($in['niveles'] ?? []))),
        'genero'      => in_array($in['genero'] ?? '', ['mujer', 'hombre'], true) ? $in['genero'] : '',
        'profesiones' => $ids($in['profesiones'] ?? []),
        'edad_min'    => max(0, min(120, (int)($in['edad_min'] ?? 0))),
        'edad_max'    => max(0, min(120, (int)($in['edad_max'] ?? 0))),
        'red'         => max(0, (int)($in['red'] ?? 0)),
        'verificados' => !empty($in['verificados']),
        'promotores'  => !empty($in['promotores']),
    ];
    if ($f['edad_min'] && $f['edad_max'] && $f['edad_min'] > $f['edad_max']) [$f['edad_min'], $f['edad_max']] = [$f['edad_max'], $f['edad_min']];
    return $f;
}

/** ¿Los filtros están vacíos (= todos los registros)? */
function wa_campana_es_todos(array $f): bool
{
    return !$f['zonas'] && !$f['zona_tipo'] && !$f['lideres'] && !$f['puestos'] && !$f['niveles'] && !$f['genero']
        && !$f['profesiones'] && !$f['edad_min'] && !$f['edad_max'] && !$f['red'] && !$f['verificados'] && !$f['promotores'];
}

/**
 * Condición SQL (sobre simpatizantes s) de los filtros, sin la de contactable.
 * Devuelve [sql que empieza por " AND …" o '', parámetros].
 */
function wa_campana_condicion(PDO $db, array $f): array
{
    $sql = ''; $p = []; $n = 0;
    $lista = function (string $col, array $valores) use (&$sql, &$p, &$n) {
        if (!$valores) return;
        $marcas = [];
        foreach ($valores as $v) { $k = 'f' . (++$n); $marcas[] = ":$k"; $p[$k] = $v; }
        $sql .= " AND $col IN (" . implode(',', $marcas) . ')';
    };
    $lista('s.zona_id', $f['zonas']);
    if ($f['zona_tipo'] !== '') { $sql .= ' AND s.zona_id IN (SELECT id FROM zonas WHERE tipo = :ztipo)'; $p['ztipo'] = $f['zona_tipo']; }
    $lista('s.lider_id', $f['lideres']);
    $lista('s.puesto_id', $f['puestos']);
    $lista('s.nivel', $f['niveles']);
    if ($f['genero'] !== '') { $sql .= ' AND s.genero = :gen'; $p['gen'] = $f['genero']; }
    $lista('s.profesion_id', $f['profesiones']);
    if ($f['edad_min']) { $sql .= ' AND s.fecha_nacimiento <= DATE_SUB(CURDATE(), INTERVAL :emin YEAR)'; $p['emin'] = $f['edad_min']; }
    if ($f['edad_max']) { $sql .= ' AND s.fecha_nacimiento > DATE_SUB(CURDATE(), INTERVAL :emax YEAR)'; $p['emax'] = $f['edad_max'] + 1; }
    if ($f['red'] && esquema_tiene($db, 'simpatizantes', 'referido_por')) {
        // El promotor y toda su red (invitados, invitados de sus invitados…)
        $sql .= ' AND s.id IN (WITH RECURSIVE red_c (id, prof) AS (
                     SELECT id, 0 FROM simpatizantes WHERE id = :red
                     UNION ALL SELECT x.id, red_c.prof + 1 FROM simpatizantes x JOIN red_c ON x.referido_por = red_c.id WHERE red_c.prof < 30
                   ) SELECT id FROM red_c)';
        $p['red'] = $f['red'];
    }
    if ($f['verificados'] && esquema_tiene($db, 'simpatizantes', 'verificado_at')) $sql .= ' AND s.verificado_at IS NOT NULL';
    if ($f['promotores'] && esquema_tiene($db, 'simpatizantes', 'referido_por')) $sql .= ' AND EXISTS (SELECT 1 FROM simpatizantes i WHERE i.referido_por = s.id)';
    return [$sql, $p];
}

/**
 * Cuántos coinciden con los filtros y cuántos recibirían el mensaje.
 * Devuelve ['coinciden', 'recibiran', 'sin_consentimiento', 'bajas', 'sin_celular', 'por_zona' => [[zona, n]…]].
 */
function wa_campana_contar(PDO $db, array $f): array
{
    [$w, $p] = wa_campana_condicion($db, $f);
    $st = $db->prepare(
        "SELECT COUNT(*) AS coinciden,
                COALESCE(SUM(" . wa_sql_contactable() . "), 0) AS recibiran,
                COALESCE(SUM(s.consentimiento_datos <> 1 OR s.consentimiento_datos IS NULL), 0) AS sin_consentimiento,
                COALESCE(SUM(s.consentimiento_datos = 1 AND s.wa_baja_at IS NOT NULL), 0) AS bajas,
                COALESCE(SUM(s.consentimiento_datos = 1 AND s.wa_baja_at IS NULL AND (s.telefono IS NULL OR s.telefono NOT REGEXP '^3[0-9]{9}$')), 0) AS sin_celular
         FROM simpatizantes s WHERE 1=1$w"
    );
    $st->execute($p);
    $r = array_map('intval', $st->fetch(PDO::FETCH_ASSOC) ?: []);
    $st = $db->prepare(
        "SELECT COALESCE(z.nombre, 'Sin zona') AS zona, COUNT(*) AS n
         FROM simpatizantes s LEFT JOIN zonas z ON z.id = s.zona_id
         WHERE " . wa_sql_contactable() . "$w GROUP BY z.id, z.nombre ORDER BY n DESC LIMIT 6"
    );
    $st->execute($p);
    $r['por_zona'] = array_map(fn($x) => [$x['zona'], (int)$x['n']], $st->fetchAll(PDO::FETCH_ASSOC));
    return $r;
}

/** Los filtros en palabras ("Zonas: Centro, Las Mercedes · Líder: Ana · 18 a 30 años"). */
function wa_campana_resumen(PDO $db, array $f): string
{
    if (wa_campana_es_todos($f)) return 'Todos los registros';
    $nombres = function (string $tabla, array $ids) use ($db): string {
        if (!$ids) return '';
        $st = $db->prepare("SELECT nombre FROM $tabla WHERE id IN (" . implode(',', array_map('intval', $ids)) . ') ORDER BY nombre');
        $st->execute();
        $n = $st->fetchAll(PDO::FETCH_COLUMN);
        return count($n) > 4 ? implode(', ', array_slice($n, 0, 4)) . ' y ' . (count($n) - 4) . ' más' : implode(', ', $n);
    };
    $partes = [];
    if ($f['zonas'])       $partes[] = 'Zonas: ' . $nombres('zonas', $f['zonas']);
    if ($f['zona_tipo'])   $partes[] = $f['zona_tipo'] === 'urbano' ? 'Casco urbano' : 'Zona rural';
    if ($f['lideres'])     $partes[] = 'Líder: ' . $nombres('usuarios', $f['lideres']);
    if ($f['puestos'])     $partes[] = 'Puesto: ' . $nombres('puestos_votacion', $f['puestos']);
    if ($f['niveles'])     $partes[] = 'Compromiso: ' . implode(', ', array_map(fn($k) => COMPROMISOS[$k], $f['niveles']));
    if ($f['genero'])      $partes[] = $f['genero'] === 'mujer' ? 'Mujeres' : 'Hombres';
    if ($f['profesiones']) $partes[] = 'Profesión: ' . $nombres('profesiones', $f['profesiones']);
    if ($f['edad_min'] || $f['edad_max']) $partes[] = 'Edad: ' . ($f['edad_min'] ?: '…') . ' a ' . ($f['edad_max'] ?: '…') . ' años';
    if ($f['red']) {
        $st = $db->prepare('SELECT nombre FROM simpatizantes WHERE id = :id');
        $st->execute(['id' => $f['red']]);
        $partes[] = 'Red de ' . (promotor_nombre_corto((string)$st->fetchColumn()) ?: 'un promotor');
    }
    if ($f['verificados']) $partes[] = 'Solo verificados';
    if ($f['promotores'])  $partes[] = 'Solo promotores';
    return mb_substr(implode(' · ', $partes), 0, 255);
}

/**
 * Pone en cola los mensajes de una campaña (al crearla o al llegar su hora).
 * Devuelve cuántos quedaron en cola.
 */
function wa_campana_lanzar(PDO $db, int $id): int
{
    $st = $db->prepare("SELECT * FROM wa_campanas WHERE id = :id AND estado = 'programada'");
    $st->execute(['id' => $id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return 0;

    // Ocasión interna (inactiva: solo la usa esta campaña) con la plantilla elegida
    $clave = WA_CAMPANA_PREFIJO . $c['id'];
    $db->prepare("INSERT INTO wa_ocasiones (clave, nombre, tipo, plantilla_id, activa) VALUES (:c, :n, 'evento', :p, 0)
                  ON DUPLICATE KEY UPDATE plantilla_id = VALUES(plantilla_id), nombre = VALUES(nombre)")
       ->execute(['c' => $clave, 'n' => mb_substr('Envío: ' . $c['nombre'], 0, 80), 'p' => $c['plantilla_id']]);
    $st = $db->prepare('SELECT id FROM wa_ocasiones WHERE clave = :c');
    $st->execute(['c' => $clave]);
    $ocasionId = (int)$st->fetchColumn();

    $f = wa_campana_filtros(json_decode((string)$c['filtros'], true) ?: []);
    [$w, $p] = wa_campana_condicion($db, $f);
    $ins = $db->prepare(
        "INSERT IGNORE INTO wa_mensajes (simpatizante_id, ocasion_id, clave)
         SELECT s.id, :o, :clave FROM simpatizantes s WHERE " . wa_sql_contactable() . $w
    );
    $ins->execute($p + ['o' => $ocasionId, 'clave' => 'campana:' . $c['id']]);
    $n = $ins->rowCount();
    $db->prepare("UPDATE wa_campanas SET estado = 'enviando', ocasion_id = :o, encolados = :n, lanzada_at = NOW() WHERE id = :id")
       ->execute(['o' => $ocasionId, 'n' => $n, 'id' => $c['id']]);
    return $n;
}

/** Lanza las campañas programadas cuya hora ya llegó (lo llama el cron). Devuelve [nombre => encolados]. */
function wa_lanzar_programadas(PDO $db): array
{
    if (!wa_campanas_disponible($db)) return [];
    $r = [];
    $lista = $db->query("SELECT id, nombre FROM wa_campanas WHERE estado = 'programada' AND (programada_para IS NULL OR programada_para <= NOW())")
                ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lista as $c) $r[$c['nombre']] = wa_campana_lanzar($db, (int)$c['id']);
    return $r;
}

/** Cancela una campaña: lo que no ha salido ya no sale. Devuelve cuántos mensajes se frenaron. */
function wa_campana_cancelar(PDO $db, int $id): int
{
    $st = $db->prepare("SELECT ocasion_id FROM wa_campanas WHERE id = :id AND estado <> 'cancelada'");
    $st->execute(['id' => $id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return 0;
    $n = 0;
    if ($c['ocasion_id']) {
        $up = $db->prepare("UPDATE wa_mensajes SET estado = 'omitido', error_detalle = 'Envío cancelado' WHERE ocasion_id = :o AND estado = 'pendiente'");
        $up->execute(['o' => $c['ocasion_id']]);
        $n = $up->rowCount();
    }
    $db->prepare("UPDATE wa_campanas SET estado = 'cancelada', cancelada_at = NOW() WHERE id = :id")->execute(['id' => $id]);
    return $n;
}

/** Texto libre de una campaña (variable texto_campana). */
function wa_campana_texto(PDO $db, int $id): string
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $st = $db->prepare('SELECT texto FROM wa_campanas WHERE id = :id');
        $st->execute(['id' => $id]);
        $cache[$id] = (string)$st->fetchColumn();
    }
    return $cache[$id];
}

/** Plantillas que sirven para un envío masivo: aprobadas, compatibles y con sus variables asignadas. */
function wa_plantillas_para_envio(PDO $db): array
{
    $todas = $db->query("SELECT * FROM wa_plantillas WHERE estado_meta = 'APPROVED' AND compatible = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    return array_values(array_filter($todas, function ($p) {
        $campos = array_filter(explode(',', (string)$p['variables']));
        if (count($campos) !== (int)$p['num_variables']) return false;
        // Variables que dependen de un evento concreto no tienen valor en un envío masivo
        return !array_intersect($campos, ['invitado', 'nivel_promotor', 'tarea', 'fecha_tarea', 'lugar_tarea']);
    }));
}
