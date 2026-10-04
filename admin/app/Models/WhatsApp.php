<?php
namespace Models;

use Core\Model;

/** Métricas y configuración del módulo de WhatsApp (ver inc/whatsapp.php). */
class WhatsApp extends Model
{
    public function disponible(): bool
    {
        return wa_disponible($this->db);
    }

    /** Condición de periodo sobre la fecha en que el mensaje entró a la cola. */
    private function periodo(int $dias, string $alias = 'm'): string
    {
        return $dias > 0 ? " AND $alias.creado_at >= NOW() - INTERVAL " . (int)$dias . ' DAY' : '';
    }

    /**
     * Totales del periodo. "Enviados" = aceptados por WhatsApp; "entregados" y
     * "leídos" llegan por el webhook. Las tasas se calculan sobre lo enviado.
     */
    public function metricas(int $dias): array
    {
        $f = $this->consultarUno(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(m.estado = 'pendiente'), 0)     AS pendientes,
                    COALESCE(SUM(m.enviado_at IS NOT NULL), 0)   AS enviados,
                    COALESCE(SUM(m.entregado_at IS NOT NULL), 0) AS entregados,
                    COALESCE(SUM(m.leido_at IS NOT NULL), 0)     AS leidos,
                    COALESCE(SUM(m.estado = 'error'), 0)         AS errores,
                    COALESCE(SUM(m.estado = 'omitido'), 0)       AS omitidos
             FROM wa_mensajes m WHERE 1=1" . $this->periodo($dias)
        );
        $f = array_map('intval', $f);
        $intentos = $f['enviados'] + $f['errores'];
        $f['tasa_entrega'] = $f['enviados'] ? $f['entregados'] * 100 / $f['enviados'] : null;
        $f['tasa_lectura'] = $f['enviados'] ? $f['leidos'] * 100 / $f['enviados'] : null;
        $f['tasa_error']   = $intentos ? $f['errores'] * 100 / $intentos : null;
        $f['respuestas']   = (int)($this->consultarUno(
            'SELECT COUNT(*) c FROM wa_entrantes e WHERE 1=1' . ($dias > 0 ? ' AND e.recibido_at >= NOW() - INTERVAL ' . (int)$dias . ' DAY' : '')
        )['c'] ?? 0);
        $f['bajas'] = (int)($this->consultarUno('SELECT COUNT(*) c FROM simpatizantes WHERE wa_baja_at IS NOT NULL')['c'] ?? 0);
        return $f;
    }

    /** Lo mismo, por ocasión (cumpleaños, profesión, Día de la Mujer…). */
    public function porOcasion(int $dias): array
    {
        return $this->consultar(
            "SELECT o.nombre, COUNT(m.id) AS total,
                    COALESCE(SUM(m.enviado_at IS NOT NULL), 0)   AS enviados,
                    COALESCE(SUM(m.entregado_at IS NOT NULL), 0) AS entregados,
                    COALESCE(SUM(m.leido_at IS NOT NULL), 0)     AS leidos,
                    COALESCE(SUM(m.estado = 'error'), 0)         AS errores,
                    COALESCE(SUM(m.estado = 'pendiente'), 0)     AS pendientes
             FROM wa_ocasiones o JOIN wa_mensajes m ON m.ocasion_id = o.id" . $this->periodo($dias) . "
             GROUP BY o.id, o.nombre ORDER BY total DESC"
        );
    }

    /** Enviados por día (últimos $dias días, con días vacíos en 0). */
    public function enviadosPorDia(int $dias = 14): array
    {
        $filas = $this->consultar(
            "SELECT t.d, COUNT(*) AS c FROM (SELECT DATE(enviado_at) AS d FROM wa_mensajes
              WHERE enviado_at >= CURDATE() - INTERVAL " . ($dias - 1) . " DAY) t GROUP BY t.d"
        );
        $porDia = array_column($filas, 'c', 'd');
        $hoy = $this->consultarUno('SELECT CURDATE() AS h')['h'];
        $r = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $ts = strtotime("$hoy -$i day");
            $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            $r[] = [date('j', $ts), $meses[(int)date('n', $ts)], (int)($porDia[date('Y-m-d', $ts)] ?? 0), $i === 0];
        }
        return $r;
    }

    public function erroresRecientes(int $limite = 8): array
    {
        return $this->consultar(
            "SELECT m.error_codigo, m.error_detalle, m.error_at, m.plantilla, s.nombre, o.nombre AS ocasion
             FROM wa_mensajes m JOIN simpatizantes s ON s.id = m.simpatizante_id JOIN wa_ocasiones o ON o.id = m.ocasion_id
             WHERE m.estado = 'error' ORDER BY m.error_at DESC, m.id DESC LIMIT " . (int)$limite
        );
    }

    public function respuestas(int $limite = 10): array
    {
        return $this->consultar(
            "SELECT e.texto, e.tipo, e.recibido_at, e.telefono, s.nombre
             FROM wa_entrantes e LEFT JOIN simpatizantes s ON s.id = e.simpatizante_id
             ORDER BY e.recibido_at DESC, e.id DESC LIMIT " . (int)$limite
        );
    }

    /* ================= Ocasiones ================= */

    public function ocasiones(): array
    {
        $filas = $this->consultar(
            "SELECT o.*, p.nombre AS plantilla, p.estado_meta, p.num_variables, p.variables
             FROM wa_ocasiones o LEFT JOIN wa_plantillas p ON p.id = o.plantilla_id
             ORDER BY FIELD(o.tipo, 'evento', 'cumpleanos', 'profesion', 'fecha'), o.id"
        );
        $hoy = new \DateTimeImmutable('today');
        foreach ($filas as &$o) {
            $o['proxima'] = $o['tipo'] === 'fecha' && $o['regla'] ? wa_regla_proxima($o['regla'], $hoy) : null;
            $o['audiencia'] = $this->audiencia($o);
        }
        unset($o);
        return $filas;
    }

    /** Cuántas personas recibirían el saludo de esta ocasión (con consentimiento y sin baja). */
    private function audiencia(array $o): ?int
    {
        $base = 'SELECT COUNT(*) c FROM simpatizantes s WHERE ' . wa_sql_contactable();
        $f = match ($o['tipo']) {
            'cumpleanos' => $this->consultarUno($base . ' AND s.fecha_nacimiento IS NOT NULL'),
            'profesion'  => $this->consultarUno($base . ' AND s.profesion_id IN (SELECT id FROM profesiones WHERE dia_celebracion IS NOT NULL)'),
            'fecha'      => $o['genero'] ? $this->consultarUno($base . ' AND s.genero = :g', ['g' => $o['genero']]) : $this->consultarUno($base),
            default      => null,
        };
        return $f === null ? null : (int)$f['c'];
    }

    public function guardarOcasion(int $id, ?int $plantillaId, bool $activa): void
    {
        $this->ejecutar('UPDATE wa_ocasiones SET plantilla_id = :p, activa = :a WHERE id = :id',
            ['p' => $plantillaId, 'a' => $activa ? 1 : 0, 'id' => $id]);
    }

    public function crearOcasionFecha(string $nombre, string $regla, ?string $genero): bool
    {
        $clave = 'f_' . substr(md5($nombre . $regla . microtime()), 0, 10);
        try {
            $this->ejecutar("INSERT INTO wa_ocasiones (clave, nombre, tipo, regla, genero) VALUES (:c, :n, 'fecha', :r, :g)",
                ['c' => $clave, 'n' => $nombre, 'r' => $regla, 'g' => $genero]);
            return true;
        } catch (\PDOException $e) { return false; }
    }

    /* ================= Plantillas ================= */

    public function plantillas(): array
    {
        return $this->consultar("SELECT * FROM wa_plantillas ORDER BY estado_meta = 'APPROVED' DESC, nombre");
    }

    public function plantilla(int $id): ?array
    {
        return $this->consultarUno('SELECT * FROM wa_plantillas WHERE id = :id', ['id' => $id]);
    }

    public function guardarVariables(int $id, array $campos): void
    {
        $this->ejecutar('UPDATE wa_plantillas SET variables = :v WHERE id = :id', ['v' => implode(',', $campos), 'id' => $id]);
    }

    public function crearPlantilla(string $nombre, string $idioma, int $numVariables, string $cuerpo): bool
    {
        try {
            $this->ejecutar(
                'INSERT INTO wa_plantillas (nombre, idioma, num_variables, cuerpo, estado_meta) VALUES (:n, :i, :v, :b, NULL)',
                ['n' => $nombre, 'i' => $idioma, 'v' => $numVariables, 'b' => $cuerpo ?: null]
            );
            return true;
        } catch (\PDOException $e) { return false; }
    }
}
