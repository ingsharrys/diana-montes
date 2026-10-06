<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\WhatsApp;
use Models\Auditoria;

/**
 * Módulo WhatsApp: métricas (enviados, entregados, leídos, con error,
 * respuestas, bajas), ocasiones de saludo, plantillas y configuración.
 * Ver: dirección y coordinación. Configurar y activar: solo la dirección.
 */
class WhatsappController extends Controller
{
    private function comun(string $pestana, string $titulo): array
    {
        return [
            'titulo'     => $titulo,
            'pestana'    => $pestana,
            'disponible' => (new WhatsApp())->disponible(),
            'configurado' => wa_configurado(),
            'esDireccion' => Auth::tieneRol('direccion'),
        ];
    }

    public function index(): void
    {
        Auth::requerirRol('direccion', 'coordinador');
        $dias = (int)($_GET['d'] ?? 30);
        if (!in_array($dias, [7, 30, 90, 0], true)) $dias = 30;
        $wa = new WhatsApp();
        $datos = $this->comun('resumen', 'WhatsApp') + ['dias' => $dias];
        if ($datos['disponible']) {
            $datos += [
                'm'         => $wa->metricas($dias),
                'ocasiones' => $wa->porOcasion($dias),
                'porDia'    => $wa->enviadosPorDia(14),
                'errores'   => $wa->erroresRecientes(),
                'respuestas' => $wa->respuestas(),
            ];
        }
        $this->vista('whatsapp/index', $datos);
    }

    public function ocasiones(): void
    {
        Auth::requerirRol('direccion', 'coordinador');
        $wa = new WhatsApp();
        $datos = $this->comun('ocasiones', 'WhatsApp · Ocasiones');
        if ($datos['disponible']) $datos += ['ocasiones' => $wa->ocasiones(), 'plantillas' => $wa->plantillas()];
        $this->vista('whatsapp/ocasiones', $datos);
    }

    public function plantillas(): void
    {
        Auth::requerirRol('direccion', 'coordinador');
        $datos = $this->comun('plantillas', 'WhatsApp · Plantillas');
        if ($datos['disponible']) $datos += ['plantillas' => (new WhatsApp())->plantillas()];
        $this->vista('whatsapp/plantillas', $datos);
    }

    public function configuracion(): void
    {
        Auth::requerirRol('direccion');
        $this->vista('whatsapp/configuracion', $this->comun('configuracion', 'WhatsApp · Configuración') + [
            'webhook'  => APP_URL . '/whatsapp-webhook.php',
            'cron'     => '*/5 * * * * ' . (PHP_BINDIR ? PHP_BINDIR . '/php' : 'php') . ' ' . realpath(__DIR__ . '/../../cron/whatsapp.php')
                          . ' >> ' . dirname(realpath(__DIR__ . '/../../..') ?: '/') . '/logs/whatsapp.log 2>&1',
            'conexion' => Session::get('wa_conexion'),
        ]);
        Session::set('wa_conexion', null);
    }

    /* ================= Acciones (solo dirección) ================= */

    private function accion(string $volver): WhatsApp
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir($volver);
        $this->validarCsrf();
        $wa = new WhatsApp();
        if (!$wa->disponible()) { Session::flash('error', 'Primero pulsa "Actualizar plataforma" en la Vista rápida.'); $this->redirigir($volver); }
        return $wa;
    }

    public function guardarocasion(string $id = '0'): void
    {
        $wa = $this->accion('whatsapp/ocasiones');
        $plantillaId = (int)($_POST['plantilla_id'] ?? 0) ?: null;
        $activa = !empty($_POST['activa']);

        if ($activa) {
            $p = $plantillaId ? $wa->plantilla($plantillaId) : null;
            $problema = match (true) {
                !$p                                 => 'Elige una plantilla antes de activar la ocasión.',
                !$p['compatible']                   => 'Esa plantilla usa encabezado multimedia, botones dinámicos o variables con nombre: no se puede enviar automáticamente.',
                $p['estado_meta'] !== null && $p['estado_meta'] !== 'APPROVED' => 'Meta aún no aprueba esa plantilla (estado: ' . $p['estado_meta'] . ').',
                count(array_filter(explode(',', (string)$p['variables']))) !== (int)$p['num_variables'] => 'Asigna primero qué dato va en cada variable de la plantilla (pestaña Plantillas).',
                default                             => null,
            };
            if ($problema) { Session::flash('error', $problema); $this->redirigir('whatsapp/ocasiones'); }
        }
        $wa->guardarOcasion((int)$id, $plantillaId, $activa);
        Auditoria::registrar('wa_ocasion_actualizada', "Ocasión #$id " . ($activa ? 'activada' : 'pausada'));
        Session::flash('ok', $activa ? 'Ocasión activada: sus mensajes saldrán automáticamente.' : 'Ocasión guardada (pausada).');
        $this->redirigir('whatsapp/ocasiones');
    }

    public function nuevaocasion(): void
    {
        $wa = $this->accion('whatsapp/ocasiones');
        $nombre = limpiar_texto_catalogo((string)($_POST['nombre'] ?? ''), 100) ?? '';
        $fecha  = (string)($_POST['fecha'] ?? '');
        $regla  = trim((string)($_POST['regla'] ?? ''));
        if ($regla === '' && preg_match('/^\d{4}-(\d{2}-\d{2})$/', $fecha, $m)) $regla = $m[1];
        $genero = in_array($_POST['genero'] ?? '', ['mujer', 'hombre'], true) ? $_POST['genero'] : null;

        if (mb_strlen($nombre) < 3 || !(preg_match('/^\d{2}-\d{2}$/', $regla) || preg_match('/^[1-5]-[0-6]-\d{2}$/', $regla))) {
            Session::flash('error', 'Escribe el nombre de la ocasión y su fecha.');
        } elseif ($wa->crearOcasionFecha($nombre, $regla, $genero)) {
            Auditoria::registrar('wa_ocasion_creada', "$nombre ($regla)");
            Session::flash('ok', 'Ocasión "' . $nombre . '" creada. Asígnale una plantilla y actívala.');
        } else {
            Session::flash('error', 'No se pudo crear la ocasión.');
        }
        $this->redirigir('whatsapp/ocasiones');
    }

    public function sincronizar(): void
    {
        $this->accion('whatsapp/plantillas');
        [$n, $error] = wa_sincronizar_plantillas(Database::conexion());
        if ($error) Session::flash('error', $error);
        else Session::flash('ok', "Se trajeron $n plantillas de Meta.");
        Auditoria::registrar('wa_plantillas_sincronizadas', $error ?: "$n plantillas");
        $this->redirigir('whatsapp/plantillas');
    }

    public function guardarplantilla(string $id = '0'): void
    {
        $wa = $this->accion('whatsapp/plantillas');
        $p = $wa->plantilla((int)$id);
        if (!$p) $this->redirigir('whatsapp/plantillas');
        $campos = [];
        for ($i = 1; $i <= (int)$p['num_variables']; $i++) {
            $campo = (string)($_POST["var$i"] ?? '');
            if (!isset(WA_VARIABLES[$campo])) { Session::flash('error', "Elige qué dato va en la variable {{{$i}}}."); $this->redirigir('whatsapp/plantillas'); }
            $campos[] = $campo;
        }
        $wa->guardarVariables((int)$id, $campos);
        Auditoria::registrar('wa_plantilla_variables', $p['nombre'] . ': ' . implode(',', $campos));
        Session::flash('ok', 'Variables de "' . $p['nombre'] . '" guardadas.');
        $this->redirigir('whatsapp/plantillas');
    }

    public function nuevaplantilla(): void
    {
        $wa = $this->accion('whatsapp/plantillas');
        $nombre = strtolower(trim((string)($_POST['nombre'] ?? '')));
        $idioma = trim((string)($_POST['idioma'] ?? 'es')) ?: 'es';
        $cuerpo = trim((string)($_POST['cuerpo'] ?? ''));
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $cuerpo, $m);
        $num = $cuerpo !== '' ? count(array_unique($m[1])) : (int)($_POST['num_variables'] ?? 0);
        if (!preg_match('/^[a-z0-9_]{3,120}$/', $nombre) || !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $idioma) || $num > 10) {
            Session::flash('error', 'Revisa el nombre (minúsculas, números y _ como en Meta) y el idioma (ej. es o es_CO).');
        } elseif ($wa->crearPlantilla($nombre, $idioma, $num, $cuerpo)) {
            Session::flash('ok', 'Plantilla "' . $nombre . '" agregada. Asigna sus variables.');
        } else {
            Session::flash('error', 'Esa plantilla ya existe.');
        }
        $this->redirigir('whatsapp/plantillas');
    }

    /** Encola los saludos de hoy y envía la cola ahora mismo (sin esperar al cron). */
    public function procesar(): void
    {
        $this->accion('whatsapp');
        $db = Database::conexion();
        $encolados = wa_generar_del_dia($db, new \DateTimeImmutable('today'));
        $r = wa_procesar_cola($db, 60);
        $msg = 'Saludos de hoy encolados: ' . array_sum($encolados) . '. Enviados: ' . $r['enviados']
             . ', con error: ' . $r['errores'] . ', para reintentar: ' . $r['reintentos'] . '.';
        Session::flash($r['detenido'] ? 'error' : 'ok', $msg . ($r['detenido'] ? ' ' . $r['detenido'] : ''));
        Auditoria::registrar('wa_envio_manual', $msg);
        $this->redirigir('whatsapp');
    }

    /** Envía una plantilla de prueba a un número (con datos de ejemplo). */
    public function prueba(): void
    {
        $wa = $this->accion('whatsapp/plantillas');
        $tel = normalizar_celular((string)($_POST['telefono'] ?? ''));
        $p = $wa->plantilla((int)($_POST['plantilla_id'] ?? 0));
        if (!preg_match('/^3\d{9}$/', $tel) || !$p) { Session::flash('error', 'Escribe un celular válido (10 dígitos) y elige la plantilla.'); $this->redirigir('whatsapp/plantillas'); }
        if (!wa_configurado()) { Session::flash('error', 'Faltan las credenciales de WhatsApp en config.php.'); $this->redirigir('whatsapp/plantillas'); }

        $ejemplo = [
            'primer_nombre' => 'María', 'nombre' => 'María Pérez', 'profesion' => 'Docente', 'zona' => 'Centro',
            'lider' => 'Carlos', 'enlace_panel' => LANDING_URL . '/promotor.php', 'enlace_invitacion' => LANDING_URL,
            'invitado' => 'Ana P.', 'nivel_promotor' => 'Súper Promotor',
        ];
        $valores = array_map(fn($c) => $ejemplo[$c] ?? '-', array_filter(explode(',', (string)$p['variables'])));
        $r = wa_enviar_plantilla($tel, $p['nombre'], $p['idioma'], array_pad($valores, (int)$p['num_variables'], '-'));
        Auditoria::registrar('wa_prueba', $p['nombre'] . ' a ' . enmascarar($tel) . ' · ' . ($r['ok'] ? 'ok' : $r['codigo']));
        Session::flash($r['ok'] ? 'ok' : 'error', $r['ok']
            ? 'Prueba enviada a ' . enmascarar($tel) . '. Revisa ese WhatsApp.'
            : 'Meta rechazó la prueba (' . $r['codigo'] . '): ' . $r['detalle']);
        $this->redirigir('whatsapp/plantillas');
    }

    /** Consulta el número en Meta para comprobar las credenciales. */
    public function conexion(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('whatsapp/configuracion');
        $this->validarCsrf();
        if (!wa_configurado()) { Session::flash('error', 'Faltan WA_PHONE_NUMBER_ID y WA_TOKEN en config.php.'); $this->redirigir('whatsapp/configuracion'); }
        [$http, $j] = wa_api('GET', wa_cfg('WA_PHONE_NUMBER_ID') . '?fields=display_phone_number,verified_name,quality_rating,messaging_limit_tier,name_status');
        if ($http === 200) {
            Session::set('wa_conexion', $j);
            Session::flash('ok', 'Conexión correcta con WhatsApp.');
        } else {
            Session::flash('error', 'Meta respondió con error: ' . ($j['error']['message'] ?? "HTTP $http"));
        }
        $this->redirigir('whatsapp/configuracion');
    }
}
