<?php
/**
 * PLANTILLA DE CONFIGURACIÓN — cópiala como  admin/config/config.php  y
 * rellena tus valores reales. El archivo config.php real NO se versiona
 * (está en .gitignore) porque contiene credenciales.
 *
 * Solo hay que AÑADIR el bloque "GOOGLE" al final si vas a usar el
 * ingreso con Google; el resto ya existe en tu servidor.
 */

/* ---------- App ---------- */
define('APP_ENV',  'prod');                 // 'dev' o 'prod'
define('APP_NAME', 'Plataforma Electoral');
define('APP_URL',  'https://tu-dominio.com/admin');   // sin barra final
define('LANDING_URL', 'https://tu-dominio.com');      // landing público

// true si el .htaccess reescribe URLs bonitas; false = usa index.php?url=...
define('USE_REWRITE', true);

/* ---------- Base de datos (MySQL) ---------- */
define('DB_HOST',    'localhost');
define('DB_NAME',    'tu_base');
define('DB_USER',    'tu_usuario');
define('DB_PASS',    'tu_password');
define('DB_CHARSET', 'utf8mb4');

/* ---------- Sesión y seguridad ---------- */
define('SESSION_NAME',      'campana_sess');
define('SESSION_LIFETIME',  60 * 60 * 2);   // 2 horas de inactividad
define('MAX_LOGIN_ATTEMPTS', 5);            // intentos antes de bloquear
define('LOCK_MINUTES',       15);           // minutos de bloqueo

/* ============================================================
   GOOGLE — "Iniciar sesión con Google"
   ------------------------------------------------------------
   Líderes y digitadores ingresan con Google; dirección y
   coordinación siguen entrando con correo y contraseña.

   Cómo obtener las credenciales:
   1. Ve a  https://console.cloud.google.com/apis/credentials
   2. Crea un proyecto (o usa uno existente).
   3. Configura la "Pantalla de consentimiento de OAuth"
      (tipo Externo; agrega tu correo como usuario de prueba
      mientras esté en modo prueba).
   4. Crea "ID de cliente de OAuth" -> tipo "Aplicación web".
   5. En "URIs de redireccionamiento autorizados" agrega EXACTAMENTE
      la misma URL que pongas en GOOGLE_REDIRECT_URI abajo:
         - Con URLs bonitas (USE_REWRITE = true):
             https://tu-dominio.com/admin/auth/callback
         - Sin reescritura (USE_REWRITE = false):
             https://tu-dominio.com/admin/index.php?url=auth/callback
   6. Copia el Client ID y el Client Secret aquí.

   IMPORTANTE: el correo de cada usuario en la tabla `usuarios`
   debe coincidir con el correo de su cuenta de Google.
   ============================================================ */
define('GOOGLE_CLIENT_ID',     '');   // xxxx.apps.googleusercontent.com
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  'https://tu-dominio.com/admin/auth/callback');
