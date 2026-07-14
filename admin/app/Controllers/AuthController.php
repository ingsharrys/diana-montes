<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Google;
use Core\Session;
use Models\Usuario;
use Models\Auditoria;

class AuthController extends Controller
{
    /**
     * Roles que ingresan SIEMPRE con correo y contraseña (nunca con Google).
     * El resto del equipo (lider, digitador) entra con Google.
     */
    private const ROLES_ADMIN = ['direccion', 'coordinador'];

    public function index(): void { $this->login(); }

    public function login(): void
    {
        if (Auth::verificado()) $this->redirigir('dashboard');

        // Muestra también los errores que dejó el flujo de Google (vía flash).
        $error = Session::flash('error');

        if ($this->esPost()) {
            $this->validarCsrf();

            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';

            $modelo  = new Usuario();
            $usuario = $modelo->porEmail($email);

            if ($usuario && $modelo->estaBloqueado($usuario)) {
                $error = 'Cuenta bloqueada temporalmente por intentos fallidos. Intenta en unos minutos.';
            } elseif ($usuario && password_verify($pass, $usuario['password_hash'])) {
                $modelo->loginExitoso((int)$usuario['id']);
                Auth::ingresar($usuario);
                Auditoria::registrar('login_ok', 'Inicio de sesión exitoso');
                $this->redirigir('dashboard');
            } else {
                if ($usuario) $modelo->intentoFallido((int)$usuario['id']);
                Auditoria::registrar('login_fallido', 'Email: ' . $email);
                // Mensaje genérico: no revelar si el correo existe o no
                $error = 'Credenciales incorrectas.';
            }
        }

        $this->vista('auth/login', ['error' => $error], 'auth');
    }

    /** Paso 1: envía al usuario a la pantalla de consentimiento de Google. */
    public function google(): void
    {
        if (Auth::verificado()) $this->redirigir('dashboard');

        if (!Google::configurado()) {
            $this->falloGoogle('El acceso con Google no está configurado todavía. Ingresa con tu contraseña o avisa a la dirección.');
        }

        header('Location: ' . Google::urlAutorizacion());
        exit;
    }

    /** Paso 2: Google regresa aquí (GOOGLE_REDIRECT_URI) con el "code". */
    public function callback(): void
    {
        if (Auth::verificado()) $this->redirigir('dashboard');

        if (!Google::configurado()) {
            $this->falloGoogle('El acceso con Google no está configurado.');
        }

        if (isset($_GET['error'])) {
            $this->falloGoogle('Se canceló el ingreso con Google.');
        }

        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? null;

        if ($code === '' || !Google::validarState($state)) {
            $this->falloGoogle('La sesión de Google no es válida o venció. Intenta de nuevo.');
        }

        $perfil = Google::perfilDesdeCodigo($code);
        if (!$perfil || !$perfil['email_verificado']) {
            $this->falloGoogle('No pudimos verificar tu cuenta de Google.');
        }

        $modelo  = new Usuario();
        $usuario = $modelo->porEmail($perfil['email']);

        // El correo debe estar previamente registrado y activo en la plataforma.
        if (!$usuario) {
            Auditoria::registrar('login_google_fallido', 'Correo no autorizado: ' . $perfil['email']);
            $this->falloGoogle('Tu correo de Google no está autorizado en la plataforma. Solicita acceso a la dirección.');
        }

        // Dirección y coordinación NO entran con Google: solo con contraseña.
        if (in_array($usuario['rol'], self::ROLES_ADMIN, true)) {
            Auditoria::registrar('login_google_bloqueado', 'Rol ' . $usuario['rol'] . ' intentó Google: ' . $perfil['email']);
            $this->falloGoogle('Las cuentas de dirección y coordinación ingresan con correo y contraseña, no con Google.');
        }

        if ($modelo->estaBloqueado($usuario)) {
            $this->falloGoogle('Cuenta bloqueada temporalmente. Intenta en unos minutos.');
        }

        $modelo->loginExitoso((int)$usuario['id']);
        Auth::ingresar($usuario);
        Auditoria::registrar('login_ok', 'Inicio de sesión con Google');
        $this->redirigir('dashboard');
    }

    public function logout(): void
    {
        Auditoria::registrar('logout', 'Cierre de sesión');
        Auth::salir();
        $this->redirigir('auth/login');
    }

    /** Deja el mensaje de error en flash y regresa al login. */
    private function falloGoogle(string $mensaje): void
    {
        Session::flash('error', $mensaje);
        $this->redirigir('auth/login');
    }
}
