<?php
namespace Api;

use PDO;
use Models\Usuario;

/**
 * Inicio de sesión de la app:
 *  - Simpatizantes: con su celular de WhatsApp y un código de 6 dígitos (OTP)
 *    que llega con la plantilla de autenticación de Meta.
 *  - Equipo: con correo y contraseña (mismo bloqueo por intentos que la web).
 */
final class AuthApi
{
    private const OTP_VIGENCIA_MIN   = 10;
    private const OTP_ESPERA_SEG     = 60;   // entre un código y el siguiente
    private const OTP_MAX_HORA_TEL   = 5;
    private const OTP_MAX_HORA_IP    = 20;
    private const OTP_MAX_INTENTOS   = 5;

    private const MSG_ENVIADO = 'Si tu número está registrado en la red de Diana, te llegará un código por WhatsApp en unos segundos.';

    private static function hashCodigo(string $tel, string $codigo): string
    {
        return hash('sha256', $tel . '|' . $codigo . '|' . (defined('APP_NAME') ? APP_NAME : ''));
    }

    /** POST auth/otp/solicitar {telefono} */
    public static function otpSolicitar(PDO $db, array $in): void
    {
        $tel = normalizar_celular((string)($in['telefono'] ?? ''));
        if ($e = error_celular($tel)) Nucleo::error($e, 422, ['campo' => 'telefono']);
        $ip = Nucleo::ip();

        // Límites: por número (1 por minuto, 5 por hora) y por conexión (20 por hora)
        $st = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(creado_at), NOW()) AS hace, SUM(creado_at > NOW() - INTERVAL 1 HOUR) AS hora
                            FROM otp_codigos WHERE telefono = :t AND creado_at > NOW() - INTERVAL 1 HOUR');
        $st->execute(['t' => $tel]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        if ($f['hace'] !== null && (int)$f['hace'] < self::OTP_ESPERA_SEG) {
            $espera = self::OTP_ESPERA_SEG - (int)$f['hace'];
            Nucleo::error("Espera $espera segundos para pedir otro código.", 429, ['espera' => $espera]);
        }
        if ((int)$f['hora'] >= self::OTP_MAX_HORA_TEL) Nucleo::error('Pediste demasiados códigos. Intenta de nuevo en una hora.', 429);
        $st = $db->prepare('SELECT COUNT(*) FROM otp_codigos WHERE ip = :ip AND creado_at > NOW() - INTERVAL 1 HOUR');
        $st->execute(['ip' => $ip]);
        if ((int)$st->fetchColumn() >= self::OTP_MAX_HORA_IP) Nucleo::error('Demasiadas solicitudes desde esta conexión. Intenta más tarde.', 429);

        $st = $db->prepare('SELECT id, nombre FROM simpatizantes WHERE telefono = :t LIMIT 1');
        $st->execute(['t' => $tel]);
        $persona = $st->fetch(PDO::FETCH_ASSOC);

        $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare('UPDATE otp_codigos SET usado = 1 WHERE telefono = :t AND usado = 0')->execute(['t' => $tel]);
        // Se guarda aunque el número no esté registrado: así los límites funcionan igual y la
        // respuesta no revela quién apoya la campaña (dato sensible: opinión política)
        $db->prepare('INSERT INTO otp_codigos (telefono, codigo_hash, expira_at, ip) VALUES (:t, :h, DATE_ADD(NOW(), INTERVAL :m MINUTE), :ip)')
           ->execute(['t' => $tel, 'h' => $persona ? self::hashCodigo($tel, $codigo) : str_repeat('0', 64), 'm' => self::OTP_VIGENCIA_MIN, 'ip' => $ip]);
        $otpId = (int)$db->lastInsertId();

        $resp = ['msg' => self::MSG_ENVIADO, 'espera' => self::OTP_ESPERA_SEG, 'vigencia' => self::OTP_VIGENCIA_MIN * 60];
        if (!$persona) Nucleo::ok($resp);

        if (!wa_configurado()) {
            // Sin WhatsApp configurado solo se permite en desarrollo (el código va en la respuesta)
            if (defined('APP_ENV') && APP_ENV === 'prod') {
                $db->prepare('UPDATE otp_codigos SET usado = 1 WHERE id = :id')->execute(['id' => $otpId]);
                Nucleo::error('El ingreso con WhatsApp aún no está activo. Entra con tu documento y clave en dianamontes.com/mi', 503);
            }
            Nucleo::ok($resp + ['codigo_dev' => $codigo]);
        }
        $r = wa_enviar_otp($tel, $codigo);
        if (!$r['ok']) {
            error_log('[api otp] ' . $r['codigo'] . ' ' . $r['detalle']);
            $db->prepare('UPDATE otp_codigos SET usado = 1 WHERE id = :id')->execute(['id' => $otpId]);
            Nucleo::error('No pudimos enviarte el código por WhatsApp. Intenta de nuevo en unos minutos.', 502);
        }
        Nucleo::ok($resp);
    }

    /** POST auth/otp/verificar {telefono, codigo, dispositivo} */
    public static function otpVerificar(PDO $db, array $in): void
    {
        $tel = normalizar_celular((string)($in['telefono'] ?? ''));
        $codigo = preg_replace('/\D/', '', (string)($in['codigo'] ?? ''));
        if (error_celular($tel)) Nucleo::error('Revisa tu número de celular.', 422, ['campo' => 'telefono']);
        if (strlen($codigo) !== 6) Nucleo::error('El código tiene 6 números.', 422, ['campo' => 'codigo']);

        $st = $db->prepare('SELECT id, codigo_hash, intentos, expira_at > NOW() AS vigente FROM otp_codigos
                            WHERE telefono = :t AND usado = 0 ORDER BY id DESC LIMIT 1');
        $st->execute(['t' => $tel]);
        $otp = $st->fetch(PDO::FETCH_ASSOC);
        if (!$otp || !(int)$otp['vigente']) Nucleo::error('El código venció o ya se usó. Pide uno nuevo.', 400, ['campo' => 'codigo']);
        if ((int)$otp['intentos'] >= self::OTP_MAX_INTENTOS) {
            $db->prepare('UPDATE otp_codigos SET usado = 1 WHERE id = :id')->execute(['id' => $otp['id']]);
            Nucleo::error('Demasiados intentos. Pide un código nuevo.', 429, ['campo' => 'codigo']);
        }
        if (!hash_equals($otp['codigo_hash'], self::hashCodigo($tel, $codigo))) {
            $db->prepare('UPDATE otp_codigos SET intentos = intentos + 1 WHERE id = :id')->execute(['id' => $otp['id']]);
            $quedan = self::OTP_MAX_INTENTOS - (int)$otp['intentos'] - 1;
            Nucleo::error($quedan > 0 ? "Código incorrecto. Te quedan $quedan intentos." : 'Código incorrecto. Pide uno nuevo.', 400, ['campo' => 'codigo']);
        }
        $db->prepare('UPDATE otp_codigos SET usado = 1 WHERE id = :id')->execute(['id' => $otp['id']]);

        $st = $db->prepare('SELECT id FROM simpatizantes WHERE telefono = :t LIMIT 1');
        $st->execute(['t' => $tel]);
        $id = (int)$st->fetchColumn();
        if (!$id) Nucleo::error('El código venció o ya se usó. Pide uno nuevo.', 400, ['campo' => 'codigo']);

        $token = Nucleo::crearSesion($db, 'simpatizante', $id, (string)($in['dispositivo'] ?? ''));
        if (esquema_tiene($db, 'simpatizantes', 'ultimo_acceso')) {
            $db->prepare('UPDATE simpatizantes SET ultimo_acceso = NOW() WHERE id = :id')->execute(['id' => $id]);
        }
        Nucleo::ok(['token' => $token, 'tipo' => 'simpatizante', 'perfil' => self::perfil($db, 'simpatizante', $id)]);
    }

    /** POST auth/equipo {email, password, dispositivo} */
    public static function equipo(PDO $db, array $in): void
    {
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $pass  = (string)($in['password'] ?? '');
        if ($email === '' || $pass === '') Nucleo::error('Escribe tu correo y tu contraseña.', 422);

        $modelo  = new Usuario();
        $usuario = $modelo->porEmail($email);
        if ($usuario && $modelo->estaBloqueado($usuario)) {
            Nucleo::error('Cuenta bloqueada temporalmente por intentos fallidos. Intenta en unos minutos.', 429);
        }
        if (!$usuario || !password_verify($pass, $usuario['password_hash'])) {
            if ($usuario) $modelo->intentoFallido((int)$usuario['id']);
            Nucleo::auditar($db, null, 'app_login_fallido', 'Email: ' . mb_substr($email, 0, 120));
            Nucleo::error('Correo o contraseña incorrectos.', 401);
        }
        $modelo->loginExitoso((int)$usuario['id']);
        $token = Nucleo::crearSesion($db, 'usuario', (int)$usuario['id'], (string)($in['dispositivo'] ?? ''));
        Nucleo::auditar($db, (int)$usuario['id'], 'app_login_ok', 'Inicio de sesión en la app');
        Nucleo::ok(['token' => $token, 'tipo' => 'usuario', 'perfil' => self::perfil($db, 'usuario', (int)$usuario['id'])]);
    }

    /** POST auth/salir */
    public static function salir(PDO $db, array $in, array $s): void
    {
        Nucleo::revocar($db, $s['token_id']);
        Nucleo::ok(['msg' => 'Sesión cerrada.']);
    }

    /** GET yo */
    public static function yo(PDO $db, array $in, array $s): void
    {
        Nucleo::ok(['tipo' => $s['tipo'], 'perfil' => self::perfil($db, $s['tipo'], $s['id'])]);
    }

    /** Datos básicos y permisos de quien inició sesión. */
    public static function perfil(PDO $db, string $tipo, int $id): array
    {
        if ($tipo === 'usuario') {
            $st = $db->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = :id');
            $st->execute(['id' => $id]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            $rol = $u['rol'];
            return [
                'id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $rol,
                'permisos' => [
                    'verDatosCompletos' => in_array($rol, ['direccion', 'coordinador'], true),
                    'soloSuRed'         => $rol === 'lider',
                    'tareas'            => in_array($rol, ['direccion', 'coordinador', 'lider'], true),
                    'catalogos'         => $rol === 'direccion',
                ],
                'panelWeb' => defined('APP_URL') ? APP_URL : null,
            ];
        }
        $st = $db->prepare('SELECT id, nombre, documento, telefono, puntos FROM simpatizantes WHERE id = :id');
        $st->execute(['id' => $id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        $puntos = (int)($p['puntos'] ?? 0);
        return [
            'id' => (int)$p['id'], 'nombre' => $p['nombre'],
            'primerNombre' => mb_convert_case(mb_strtolower((string)strtok($p['nombre'], ' ')), MB_CASE_TITLE),
            'documento' => $p['documento'], 'telefono' => $p['telefono'], 'puntos' => $puntos,
            'nivel' => PROMOTOR_NIVELES[red_nivel_indice($puntos)][1],
        ];
    }
}
