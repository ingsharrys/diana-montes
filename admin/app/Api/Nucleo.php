<?php
namespace Api;

use PDO;

/**
 * Utilidades de la API de la app móvil: respuestas JSON, CORS, entrada,
 * sesiones con token (Bearer) y auditoría.
 *
 * Los tokens se guardan solo como hash SHA-256 en api_tokens: si alguien
 * lee la base de datos no puede usarlos. Las sesiones duran y se renuevan
 * solas con el uso (la app no vuelve a pedir el acceso mientras se use).
 */
final class Nucleo
{
    /** Días que dura una sesión sin usarse, por tipo. */
    public const DIAS_SESION = ['simpatizante' => 180, 'usuario' => 30];

    /** Orígenes de la app (Capacitor en Android/iOS) y de desarrollo. */
    private const ORIGENES = [
        'capacitor://localhost', 'ionic://localhost', 'http://localhost', 'https://localhost',
        'http://localhost:8100', 'http://localhost:4200',
    ];

    public static function cors(): void
    {
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        $extra = array_filter(array_map('trim', explode(',', (string)(defined('API_ORIGENES') ? API_ORIGENES : ''))));
        if ($origen !== '' && in_array($origen, array_merge(self::ORIGENES, $extra), true)) {
            header('Access-Control-Allow-Origin: ' . $origen);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Max-Age: 86400');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
    }

    public static function json($datos, int $http = 200): void
    {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(array $datos = []): void
    {
        self::json(['ok' => true] + $datos);
    }

    /** Error con mensaje para mostrar y, si aplica, el campo que lo causó. */
    public static function error(string $msg, int $http = 400, array $extra = []): void
    {
        self::json(['ok' => false, 'msg' => $msg] + $extra, $http);
    }

    /** Cuerpo JSON (o formulario) de la petición. */
    public static function entrada(): array
    {
        $crudo = (string)file_get_contents('php://input');
        $json = $crudo !== '' ? json_decode($crudo, true) : null;
        return is_array($json) ? $json + $_POST : $_POST;
    }

    public static function ip(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /* ---------------- Sesiones ---------------- */

    /** Crea una sesión y devuelve el token (solo se muestra esta vez). */
    public static function crearSesion(PDO $db, string $tipo, int $sujetoId, string $dispositivo = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $db->prepare(
            'INSERT INTO api_tokens (token_hash, tipo, sujeto_id, dispositivo, ultimo_uso, expira_at)
             VALUES (:h, :t, :s, :d, NOW(), DATE_ADD(NOW(), INTERVAL :dias DAY))'
        )->execute(['h' => hash('sha256', $token), 't' => $tipo, 's' => $sujetoId,
                    'd' => mb_substr(trim($dispositivo), 0, 80) ?: null, 'dias' => self::DIAS_SESION[$tipo]]);
        return $token;
    }

    /** Sesión del token Bearer de la petición, o null. Renueva el vencimiento con el uso. */
    public static function sesion(PDO $db): ?array
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($auth === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) $auth = $v;
        }
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim((string)$auth), $m)) return null;
        $st = $db->prepare(
            'SELECT id, tipo, sujeto_id, ultimo_uso FROM api_tokens
             WHERE token_hash = :h AND revocado = 0 AND expira_at > NOW() LIMIT 1'
        );
        $st->execute(['h' => hash('sha256', strtolower($m[1]))]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) return null;
        if (!$s['ultimo_uso'] || strtotime($s['ultimo_uso']) < time() - 3600) {
            $db->prepare('UPDATE api_tokens SET ultimo_uso = NOW(), expira_at = DATE_ADD(NOW(), INTERVAL :dias DAY) WHERE id = :id')
               ->execute(['dias' => self::DIAS_SESION[$s['tipo']], 'id' => $s['id']]);
        }
        // La persona o el usuario deben seguir existiendo (y el usuario, activo)
        if ($s['tipo'] === 'usuario') {
            $st = $db->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = :id AND activo = 1');
        } else {
            $st = $db->prepare('SELECT id, nombre FROM simpatizantes WHERE id = :id');
        }
        $st->execute(['id' => $s['sujeto_id']]);
        $sujeto = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sujeto) return null;
        return ['token_id' => (int)$s['id'], 'tipo' => $s['tipo'], 'id' => (int)$s['sujeto_id'], 'sujeto' => $sujeto];
    }

    public static function revocar(PDO $db, int $tokenId): void
    {
        $db->prepare('UPDATE api_tokens SET revocado = 1 WHERE id = :id')->execute(['id' => $tokenId]);
    }

    public static function auditar(PDO $db, ?int $usuarioId, string $accion, string $detalle = ''): void
    {
        $db->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (:u, :a, :d, :ip)')
           ->execute(['u' => $usuarioId, 'a' => $accion, 'd' => mb_substr($detalle, 0, 255), 'ip' => self::ip()]);
    }

    /** Fecha en formato ISO para la app (o null). */
    public static function iso(?string $fecha): ?string
    {
        return $fecha ? date('c', strtotime($fecha)) : null;
    }
}
