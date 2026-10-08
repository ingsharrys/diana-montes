<?php
namespace Api;

use PDO;
use Models\Catalogo;

/**
 * Catálogos desde la app (solo dirección, igual que en la web): barrios y
 * veredas, puestos de votación y profesiones. Se pueden crear, editar y
 * eliminar (eliminar solo si nadie los usa).
 */
final class CatalogosApi
{
    private static function exigeDireccion(array $s): void
    {
        if (($s['sujeto']['rol'] ?? '') !== 'direccion') Nucleo::error('Solo la dirección de campaña administra los catálogos.', 403);
    }

    /** GET equipo/catalogos/admin */
    public static function listar(PDO $db, array $in, array $s): void
    {
        self::exigeDireccion($s);
        $cat = new Catalogo();
        Nucleo::ok([
            'clases' => array_map(fn($k, $v) => ['clave' => $k, 'nombre' => $v], array_keys(ZONAS_GRUPOS), ZONAS_GRUPOS),
            'zonas' => array_map(fn($z) => [
                'id' => (int)$z['id'], 'nombre' => $z['nombre'], 'clase' => $z['clase'],
                'grupo' => zona_grupo_etiqueta($z), 'uso' => (int)$z['uso'],
            ], $cat->zonasConUso()),
            'puestos' => array_map(fn($p) => [
                'id' => (int)$p['id'], 'nombre' => $p['nombre'], 'direccion' => $p['direccion'],
                'zonaId' => $p['zona_id'] !== null ? (int)$p['zona_id'] : null, 'zona' => $p['zona'],
                'mesas' => $p['mesas'] !== null ? (int)$p['mesas'] : null,
                'potencial' => $p['potencial'] !== null ? (int)$p['potencial'] : null, 'uso' => (int)$p['uso'],
            ], $cat->puestosCompletos()),
            'profesiones' => array_map(fn($p) => [
                'id' => (int)$p['id'], 'nombre' => $p['nombre'],
                'dia' => $p['dia_celebracion'] ? substr((string)$p['dia_celebracion'], 5, 5) : null,   // MM-DD
                'uso' => (int)$p['uso'],
            ], $cat->profesionesConUso()),
        ]);
    }

    /** POST equipo/catalogos/zona {id?, nombre, clase} */
    public static function zona(PDO $db, array $in, array $s): void
    {
        self::exigeDireccion($s);
        $id = (int)($in['id'] ?? 0);
        $nombre = limpiar_texto_catalogo((string)($in['nombre'] ?? ''), 80) ?? '';
        $clase = array_key_exists($in['clase'] ?? '', ZONAS_GRUPOS) ? $in['clase'] : 'Barrio';
        $tipo = in_array($clase, ZONAS_CLASES_RURALES, true) ? 'rural' : 'urbano';
        if (mb_strlen($nombre) < 3) Nucleo::error('Escribe el nombre del barrio o vereda (letras y números, sin símbolos raros).', 422, ['campo' => 'nombre']);
        $cat = new Catalogo();
        $ok = $id ? $cat->editarZona($id, $nombre, $tipo, $clase) : $cat->crearZona($nombre, $tipo, $clase);
        if (!$ok) Nucleo::error('Ya existe "' . $nombre . '" como ' . mb_strtolower($clase) . '.', 409, ['campo' => 'nombre']);
        Nucleo::auditar($db, $s['id'], $id ? 'catalogo_zona_editada' : 'catalogo_zona_creada', 'Desde la app · ' . $nombre . ' (' . $clase . ')');
        Nucleo::ok(['msg' => $id ? 'Zona actualizada.' : 'Zona "' . $nombre . '" creada: ya aparece en los formularios.']);
    }

    /** POST equipo/catalogos/puesto {id?, nombre, direccion, zona_id, mesas, potencial} */
    public static function puesto(PDO $db, array $in, array $s): void
    {
        self::exigeDireccion($s);
        $id = (int)($in['id'] ?? 0);
        $nombre = limpiar_texto_catalogo((string)($in['nombre'] ?? ''), 120) ?? '';
        if (mb_strlen($nombre) < 3) Nucleo::error('Escribe el nombre del puesto de votación (letras y números, sin símbolos raros).', 422, ['campo' => 'nombre']);
        $direccion = trim((string)($in['direccion'] ?? ''));
        if ($direccion !== '' && ($direccion = limpiar_texto_catalogo($direccion, 160)) === null) {
            Nucleo::error('Revisa la dirección: solo letras, números y # - . , /', 422, ['campo' => 'direccion']);
        }
        $zonaId = (int)($in['zona_id'] ?? 0) ?: null;
        if ($zonaId) {
            $st = $db->prepare('SELECT 1 FROM zonas WHERE id = :id');
            $st->execute(['id' => $zonaId]);
            if (!$st->fetchColumn()) Nucleo::error('La zona no existe.', 422, ['campo' => 'zona_id']);
        }
        $num = function (string $k, int $max) use ($in): ?int {
            $v = preg_replace('/\D/', '', (string)($in[$k] ?? ''));
            if ($v === '') return null;
            if ((int)$v > $max) Nucleo::error('Revisa el número: es demasiado grande.', 422, ['campo' => $k]);
            return (int)$v ?: null;
        };
        $mesas = $num('mesas', 999);
        $potencial = $num('potencial', 500000);
        if (!(new Catalogo())->guardarPuesto($id, $nombre, $direccion ?: null, $zonaId, $mesas, $potencial)) {
            Nucleo::error('Ya existe un puesto con ese nombre.', 409, ['campo' => 'nombre']);
        }
        Nucleo::auditar($db, $s['id'], $id ? 'catalogo_puesto_editado' : 'catalogo_puesto_creado', 'Desde la app · ' . $nombre);
        Nucleo::ok(['msg' => $id ? 'Puesto actualizado.' : 'Puesto "' . $nombre . '" creado.']);
    }

    /** POST equipo/catalogos/profesion {id?, nombre, dia: 'MM-DD' | ''} */
    public static function profesion(PDO $db, array $in, array $s): void
    {
        self::exigeDireccion($s);
        $id = (int)($in['id'] ?? 0);
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($in['nombre'] ?? '')));
        if (!preg_match('/^[\p{L}\p{M} ()\/]{3,80}$/u', $nombre)) Nucleo::error('Escribe el nombre de la profesión u oficio (solo letras).', 422, ['campo' => 'nombre']);
        $dia = null;
        if (preg_match('/^(\d{2})-(\d{2})$/', (string)($in['dia'] ?? ''), $m)) {
            if (!checkdate((int)$m[1], (int)$m[2], 2000)) Nucleo::error('Revisa el día de celebración.', 422, ['campo' => 'dia']);
            $dia = "2000-{$m[1]}-{$m[2]}";   // el año no importa: se saluda cada año ese día
        }
        $cat = new Catalogo();
        $ok = $id ? $cat->editarProfesion($id, $nombre, $dia) : $cat->crearProfesion($nombre, $dia);
        if (!$ok) Nucleo::error('Esa profesión ya existe.', 409, ['campo' => 'nombre']);
        Nucleo::auditar($db, $s['id'], $id ? 'catalogo_profesion_editada' : 'catalogo_profesion_creada', 'Desde la app · ' . $nombre);
        Nucleo::ok(['msg' => $id ? 'Profesión actualizada.' : 'Profesión "' . $nombre . '" creada.']);
    }

    /** POST equipo/catalogos/eliminar {tipo: zona|puesto|profesion, id} */
    public static function eliminar(PDO $db, array $in, array $s): void
    {
        self::exigeDireccion($s);
        $tablas = ['zona' => 'zonas', 'puesto' => 'puestos_votacion', 'profesion' => 'profesiones'];
        $tabla = $tablas[$in['tipo'] ?? ''] ?? null;
        $id = (int)($in['id'] ?? 0);
        if (!$tabla || !$id) Nucleo::error('Elemento no válido.', 422);
        if (!(new Catalogo())->eliminar($tabla, $id)) {
            Nucleo::error('No se puede eliminar: hay personas o puestos que lo usan. Puedes editarlo en su lugar.', 409);
        }
        Nucleo::auditar($db, $s['id'], 'catalogo_eliminado', 'Desde la app · ' . $tabla . ' #' . $id);
        Nucleo::ok(['msg' => 'Eliminado del catálogo.']);
    }
}
