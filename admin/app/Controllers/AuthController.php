<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Usuario;
use Models\Auditoria;

class AuthController extends Controller
{
    public function index(): void { $this->login(); }

    public function login(): void
    {
        if (Auth::verificado()) $this->redirigir('dashboard');

        $error = null;

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

    public function logout(): void
    {
        Auditoria::registrar('logout', 'Cierre de sesión');
        Auth::salir();
        $this->redirigir('auth/login');
    }
}
