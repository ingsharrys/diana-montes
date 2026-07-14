<?php
namespace Core;

/**
 * "Iniciar sesión con Google" (OAuth 2.0 / OpenID Connect).
 *
 * No requiere librerías externas ni Composer: habla directamente con los
 * endpoints de Google usando cURL. El flujo es el estándar de "authorization
 * code":
 *   1) urlAutorizacion()      -> se envía al usuario a Google
 *   2) Google regresa con ?code=... a la GOOGLE_REDIRECT_URI
 *   3) perfilDesdeCodigo()    -> canjea el code por el perfil del usuario
 *
 * Requiere en config/config.php:
 *   define('GOOGLE_CLIENT_ID',     '...apps.googleusercontent.com');
 *   define('GOOGLE_CLIENT_SECRET', '...');
 *   define('GOOGLE_REDIRECT_URI',  'https://tu-dominio/admin/auth/callback');
 */
class Google
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** ¿Están definidas las credenciales de Google en config.php? */
    public static function configurado(): bool
    {
        return defined('GOOGLE_CLIENT_ID')     && GOOGLE_CLIENT_ID     !== ''
            && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== ''
            && defined('GOOGLE_REDIRECT_URI')  && GOOGLE_REDIRECT_URI  !== '';
    }

    /** URL a la que se envía al usuario para autenticarse con Google. */
    public static function urlAutorizacion(): string
    {
        // "state" anti-CSRF: se guarda en sesión y se compara en el callback.
        $state = bin2hex(random_bytes(16));
        Session::set('_google_state', $state);

        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
    }

    /** Compara el "state" devuelto por Google contra el guardado en sesión. */
    public static function validarState(?string $state): bool
    {
        $guardado = Session::get('_google_state');
        Session::set('_google_state', null); // un solo uso
        return is_string($state) && is_string($guardado) && $guardado !== ''
            && hash_equals($guardado, $state);
    }

    /**
     * Canjea el "code" por el perfil verificado del usuario.
     * Devuelve ['email' => ..., 'email_verificado' => bool, 'nombre' => ...]
     * o null si algo falla.
     */
    public static function perfilDesdeCodigo(string $code): ?array
    {
        $resp = self::postToken([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);
        if (!$resp || empty($resp['id_token'])) return null;

        $claims = self::decodificarIdToken($resp['id_token']);
        if (!$claims) return null;

        // El id_token llegó directo del endpoint de Google por TLS: podemos
        // confiar en él sin verificar la firma. Aun así validamos que la
        // audiencia (aud) sea nuestra propia app.
        if (($claims['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
        if (empty($claims['email'])) return null;

        $verificado = $claims['email_verified'] ?? false;

        return [
            'email'            => strtolower(trim($claims['email'])),
            'email_verificado' => $verificado === true || $verificado === 'true' || $verificado === 1,
            'nombre'           => $claims['name'] ?? '',
        ];
    }

    /** POST application/x-www-form-urlencoded al endpoint de tokens de Google. */
    private static function postToken(array $datos): ?array
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($datos),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body   = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $codigo !== 200) return null;
        $json = json_decode((string)$body, true);
        return is_array($json) ? $json : null;
    }

    /** Decodifica el payload del JWT (id_token). Firma no verificada: llegó por TLS. */
    private static function decodificarIdToken(string $jwt): ?array
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) return null;

        $claims = json_decode(self::base64UrlDecode($partes[1]), true);
        return is_array($claims) ? $claims : null;
    }

    private static function base64UrlDecode(string $s): string
    {
        $s   = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return (string)base64_decode($s);
    }
}
