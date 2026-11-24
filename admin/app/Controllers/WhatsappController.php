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
        $datos['borrador'] = Session::get('wa_borrador');
        Session::set('wa_borrador', null);
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

    /**
     * Crea una plantilla en Meta desde la plataforma (queda "En revisión").
     * Con una sugerida, además la deja asignada a sus ocasiones.
     */
    public function crearenmeta(): void
    {
        $this->accion('whatsapp/plantillas');
        $db = Database::conexion();
        $nombre    = strtolower(trim((string)($_POST['nombre'] ?? '')));
        $categoria = (string)($_POST['categoria'] ?? '');
        $idioma    = (string)($_POST['idioma'] ?? 'es');
        $cuerpo    = (string)($_POST['cuerpo'] ?? '');
        $campos    = array_values(array_filter(array_map('strval', (array)($_POST['campos'] ?? [])), fn($c) => $c !== ''));
        $sugerida  = WA_PLANTILLAS_SUGERIDAS[$nombre] ?? null;
        $botones = [];
        foreach ((array)($_POST['boton_tipo'] ?? []) as $i => $tipo) {
            $botones[] = ['tipo' => (string)$tipo, 'texto' => (string)($_POST['boton_texto'][$i] ?? ''), 'valor' => (string)($_POST['boton_valor'][$i] ?? '')];
        }
        $extras = ['encabezado' => (string)($_POST['encabezado'] ?? ''), 'pie' => (string)($_POST['pie'] ?? ''), 'botones' => $botones];

        // Las sugeridas pueden traer un botón con enlace personal ({{1}}), que solo usan los envíos automáticos
        $r = wa_crear_plantilla($db, $nombre, $idioma, $categoria, $cuerpo, $campos, $extras, (bool)$sugerida);
        if (!$r['ok']) {
            Session::flash('error', $r['msg']);
            Session::set('wa_borrador', ['nombre' => $nombre, 'categoria' => $categoria, 'idioma' => $idioma, 'cuerpo' => $cuerpo, 'campos' => $campos] + $extras);
            $this->redirigir('whatsapp/plantillas');
        }
        $vinculadas = $sugerida ? wa_vincular_ocasiones($db, $nombre, $idioma, $sugerida[1]) : 0;
        Auditoria::registrar('wa_plantilla_creada', "$nombre ($idioma) · pedida " . $categoria . ' · Meta: ' . $r['categoria'] . ' ' . $r['estado']);
        $cat = WA_CATEGORIAS[$r['categoria']] ?? $r['categoria'];
        Session::flash('ok', 'Plantilla "' . $nombre . '" enviada a Meta: quedó ' . (['APPROVED' => 'APROBADA', 'PENDING' => 'EN REVISIÓN', 'REJECTED' => 'RECHAZADA'][$r['estado']] ?? $r['estado'])
            . ' como ' . strtoupper($cat) . ($r['categoria'] !== $categoria ? ' (Meta la cambió de ' . (WA_CATEGORIAS[$categoria] ?? $categoria) . ')' : '') . '.'
            . ($vinculadas ? " Ya quedó asignada a $vinculadas ocasión" . ($vinculadas === 1 ? '' : 'es') . '; actívala cuando esté aprobada.' : '')
            . ' Pulsa "Traer plantillas de Meta" en unos minutos para ver si la aprobaron.');
        $this->redirigir('whatsapp/plantillas');
    }

    /** Crea en Meta, de una vez, todas las plantillas que faltan (incluida la del código de la app). */
    public function creartodas(): void
    {
        $this->accion('whatsapp/plantillas');
        $r = wa_crear_faltantes(Database::conexion());
        if (!$r) { Session::flash('ok', 'No falta ninguna: todas las plantillas ya están creadas en Meta.'); $this->redirigir('whatsapp/plantillas'); }
        $ok = array_filter($r, fn($x) => $x[1]);
        $mal = array_filter($r, fn($x) => !$x[1]);
        Auditoria::registrar('wa_plantillas_todas', count($ok) . ' enviadas, ' . count($mal) . ' con error');
        if ($ok) Session::flash('ok', count($ok) . ' plantilla' . (count($ok) === 1 ? '' : 's') . ' enviada' . (count($ok) === 1 ? '' : 's')
            . ' a Meta (' . implode(', ', array_map(fn($x) => $x[0], $ok)) . '). Quedan en revisión: pulsa "Traer plantillas de Meta" en unos minutos.');
        if ($mal) Session::flash('error', implode(' · ', array_map(fn($x) => $x[0] . ': ' . $x[2], $mal)));
        $this->redirigir('whatsapp/plantillas');
    }

    /** Edita en Meta las plantillas que aún dicen "Diana Lucía Montes" para que digan "Diana Montes". */
    public function actualizarmarca(): void
    {
        $this->accion('whatsapp/plantillas');
        $r = wa_actualizar_marca(Database::conexion());
        if (!$r) { Session::flash('ok', 'Todas las plantillas ya dicen "' . WA_MARCA . '".'); $this->redirigir('whatsapp/plantillas'); }
        $ok = array_filter($r, fn($x) => $x[1]);
        $mal = array_filter($r, fn($x) => !$x[1]);
        Auditoria::registrar('wa_plantillas_marca', count($ok) . ' actualizadas, ' . count($mal) . ' con error');
        if ($ok) Session::flash('ok', count($ok) . ' plantilla' . (count($ok) === 1 ? '' : 's') . ' actualizada' . (count($ok) === 1 ? '' : 's')
            . ' a "' . WA_MARCA . '". Meta las vuelve a revisar: pulsa "Traer plantillas de Meta" en unos minutos.');
        if ($mal) Session::flash('error', implode(' · ', array_map(fn($x) => ($x[0] !== '' ? $x[0] . ': ' : '') . $x[2], $mal)));
        $this->redirigir('whatsapp/plantillas');
    }

    /** Crea en Meta la plantilla de autenticación con la que llegan los códigos de acceso de la app móvil. */
    public function crearotp(): void
    {
        $this->accion('whatsapp/plantillas');
        $r = wa_crear_plantilla_otp(Database::conexion());
        Auditoria::registrar('wa_plantilla_otp', $r['ok'] ? ($r['estado'] ?? '') : $r['msg']);
        Session::flash($r['ok'] ? 'ok' : 'error', $r['ok']
            ? 'Plantilla del código de acceso enviada a Meta (categoría Autenticación). Cuando la aprueben, la app podrá enviar los códigos.'
            : $r['msg']);
        $this->redirigir('whatsapp/plantillas');
    }

    /** Suscribe la app a la cuenta de WhatsApp (para que lleguen entregado/leído/respuestas al webhook). */
    public function suscribir(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('whatsapp/configuracion');
        $this->validarCsrf();
        [$ok, $error] = wa_suscribir_app();
        Auditoria::registrar('wa_app_suscrita', $ok ? 'ok' : $error);
        Session::flash($ok ? 'ok' : 'error', $ok ? 'Listo: la cuenta de WhatsApp quedó conectada al webhook de la plataforma.' : $error);
        $this->redirigir('whatsapp/configuracion');
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

        $ejemplo = WA_EJEMPLOS;
        $valores = array_map(fn($c) => $ejemplo[$c] ?? '-', array_filter(explode(',', (string)$p['variables'])));
        $r = wa_enviar_plantilla($tel, $p['nombre'], $p['idioma'], array_pad($valores, (int)$p['num_variables'], '-'));
        Auditoria::registrar('wa_prueba', $p['nombre'] . ' a ' . enmascarar($tel) . ' · ' . ($r['ok'] ? 'ok' : $r['codigo']));
        Session::flash($r['ok'] ? 'ok' : 'error', $r['ok']
            ? 'Prueba enviada a ' . enmascarar($tel) . '. Revisa ese WhatsApp.'
            : 'Meta rechazó la prueba (' . $r['codigo'] . '): ' . $r['detalle']);
        $this->redirigir('whatsapp/plantillas');
    }

    /* ================= Envíos masivos ================= */

    /** Lista de envíos masivos con su avance. */
    public function campanas(): void
    {
        Auth::requerirRol('direccion', 'coordinador');
        $wa = new WhatsApp();
        $datos = $this->comun('campanas', 'WhatsApp · Mensajes masivos');
        $datos['campanasListas'] = $datos['disponible'] && $wa->campanasDisponible();
        if ($datos['campanasListas']) {
            $datos += ['campanas' => $wa->campanas(), 'respuestas' => $wa->respuestasPorCampana(), 'cupo' => $wa->cupoHoy(),
                       'conversemos' => $wa->conversemosPorCampana(), 'conversaciones' => $wa->conversemosRecientes()];
        }
        $this->vista('whatsapp/campanas', $datos);
    }

    /** Formulario: a quién (filtros con conteo en vivo), qué plantilla y cuándo. */
    public function nuevacampana(): void
    {
        Auth::requerirRol('direccion');
        $wa = new WhatsApp();
        $db = Database::conexion();
        $datos = $this->comun('campanas', 'WhatsApp · Nuevo envío masivo');
        $datos['campanasListas'] = $datos['disponible'] && $wa->campanasDisponible();
        if ($datos['campanasListas']) {
            $cat = new \Models\Catalogo();
            $datos += [
                'plantillas'  => wa_plantillas_para_envio($db),
                'zonas'       => $cat->zonas(),
                'lideres'     => $cat->lideres(),
                'puestos'     => $cat->puestos(),
                'profesiones' => $cat->profesiones(),
                'promotores'  => $db->query(
                    "SELECT s.id, s.nombre, COUNT(i.id) AS invitados FROM simpatizantes s JOIN simpatizantes i ON i.referido_por = s.id
                     GROUP BY s.id, s.nombre ORDER BY invitados DESC, s.nombre LIMIT 300")->fetchAll(\PDO::FETCH_ASSOC),
                'cupo'        => $wa->cupoHoy(),
                'previo'      => Session::get('wa_campana_borrador') ?? [],
            ];
            Session::set('wa_campana_borrador', null);
        }
        $this->vista('whatsapp/nuevacampana', $datos);
    }

    /** Conteo en vivo de la audiencia (JSON) para el formulario. */
    public function audiencia(): void
    {
        Auth::requerirRol('direccion');
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::conexion();
        if (!wa_campanas_disponible($db)) { echo json_encode(['error' => 'Falta actualizar la plataforma']); exit; }
        $f = wa_campana_filtros($_POST + $_GET);
        echo json_encode(wa_campana_contar($db, $f) + ['resumen' => wa_campana_resumen($db, $f)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Crea el envío: sale ya (al ritmo del tope diario) o queda programado. */
    public function crearcampana(): void
    {
        $wa = $this->accion('whatsapp/nuevacampana');
        $db = Database::conexion();
        if (!$wa->campanasDisponible()) { Session::flash('error', 'Primero pulsa "Actualizar plataforma" en la Vista rápida.'); $this->redirigir('whatsapp/campanas'); }

        $f = wa_campana_filtros($_POST);
        $nombre = trim(preg_replace('/\s+/', ' ', (string)($_POST['nombre'] ?? '')));
        $plantillaId = (int)($_POST['plantilla_id'] ?? 0);
        $texto = trim(preg_replace('/\s+/', ' ', (string)($_POST['texto'] ?? '')));
        $cuando = (string)($_POST['cuando'] ?? 'ahora');
        $fecha = (string)($_POST['programada_para'] ?? '');
        $volver = function (string $error) use ($f, $nombre, $plantillaId, $texto, $cuando, $fecha) {
            Session::flash('error', $error);
            Session::set('wa_campana_borrador', ['filtros' => $f, 'nombre' => $nombre, 'plantilla_id' => $plantillaId, 'texto' => $texto, 'cuando' => $cuando, 'programada_para' => $fecha]);
            $this->redirigir('whatsapp/nuevacampana');
        };

        $plantilla = null;
        foreach (wa_plantillas_para_envio($db) as $p) if ((int)$p['id'] === $plantillaId) $plantilla = $p;
        if (mb_strlen($nombre) < 3) $volver('Ponle un nombre al envío (ej. "Invitación cierre de campaña").');
        if (!$plantilla) $volver('Elige una plantilla aprobada por Meta.');
        $usaTexto = in_array('texto_campana', explode(',', (string)$plantilla['variables']), true);
        if ($usaTexto && mb_strlen($texto) < 5) $volver('Escribe el mensaje que va dentro de la plantilla.');
        if ($usaTexto && mb_strlen($texto) > 700) $volver('El mensaje no puede pasar de 700 caracteres.');
        $programada = null;
        if ($cuando === 'programar') {
            $ts = strtotime($fecha);
            if (!$ts || $ts < time() + 60) $volver('Elige una fecha y hora futuras para programar el envío.');
            $programada = date('Y-m-d H:i:00', $ts);
        }
        $conteo = wa_campana_contar($db, $f);
        if ($conteo['recibiran'] === 0) $volver('Con esos filtros no hay nadie que pueda recibir el mensaje.');
        if ((int)($_POST['confirmar_total'] ?? -1) !== $conteo['recibiran'] && wa_campana_es_todos($f)) {
            $volver('Confirma el número de personas antes de enviar a todos los registros.');
        }

        $id = $wa->crearCampana([
            'nombre' => mb_substr($nombre, 0, 120), 'plantilla_id' => $plantillaId, 'texto' => $usaTexto ? $texto : null,
            'filtros' => $f, 'resumen' => wa_campana_resumen($db, $f), 'audiencia' => $conteo['recibiran'],
            'programada_para' => $programada, 'creado_por' => Auth::id(),
        ]);
        $encolados = $programada ? 0 : wa_campana_lanzar($db, $id);
        Auditoria::registrar('wa_campana_creada', "#$id $nombre · " . ($programada ? "programada $programada" : "$encolados en cola") . ' · ' . wa_campana_resumen($db, $f));
        $dias = (int)ceil(max(1, $conteo['recibiran']) / max(1, (int)wa_cfg('WA_LIMITE_DIARIO', 250)));
        Session::flash('ok', $programada
            ? 'Envío programado para el ' . date('d/m/Y \a \l\a\s g:i a', strtotime($programada)) . ' (' . number_format($conteo['recibiran'], 0, ',', '.') . ' personas).'
            : number_format($encolados, 0, ',', '.') . ' mensajes en cola. Salen en los próximos minutos'
              . ($dias > 1 ? " y, por el tope diario, tardan unos $dias días en salir todos" : '') . '.');
        $this->redirigir('whatsapp/campanas');
    }

    /** Cancela un envío: lo que aún no salió, ya no sale. */
    public function cancelarcampana(string $id = '0'): void
    {
        $this->accion('whatsapp/campanas');
        $n = wa_campana_cancelar(Database::conexion(), (int)$id);
        Auditoria::registrar('wa_campana_cancelada', "#$id · $n mensajes frenados");
        Session::flash('ok', "Envío cancelado. $n mensaje" . ($n === 1 ? '' : 's') . ' que no habían salido ya no se enviarán.');
        $this->redirigir('whatsapp/campanas');
    }

    /** Borra una plantilla en Meta y aquí (p. ej. una rechazada, para crearla de nuevo con el mismo nombre). */
    public function eliminarplantilla(): void
    {
        $this->accion('whatsapp/plantillas');
        $nombre = strtolower(trim((string)($_POST['nombre'] ?? '')));
        if (!preg_match('/^[a-z0-9_]{1,512}$/', $nombre)) $this->redirigir('whatsapp/plantillas');
        [$ok, $error] = wa_eliminar_plantilla(Database::conexion(), $nombre);
        Auditoria::registrar('wa_plantilla_eliminada', $nombre . ($ok ? '' : ' · ' . $error));
        Session::flash($ok ? 'ok' : 'error', $ok ? "Plantilla \"$nombre\" eliminada en Meta y en la plataforma. Meta no deja reutilizar el nombre durante unas semanas: si la creas de nuevo, usa otro nombre." : $error);
        $this->redirigir('whatsapp/plantillas');
    }

    /**
     * Comprueba la conexión con Meta: la cuenta (números con su identificador y
     * si la app está suscrita) y el número configurado (calidad y límite).
     */
    public function conexion(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('whatsapp/configuracion');
        $this->validarCsrf();
        if (!wa_cfg('WA_TOKEN') || (!wa_cfg('WA_WABA_ID') && !wa_cfg('WA_PHONE_NUMBER_ID'))) {
            Session::flash('error', 'Pon al menos WA_TOKEN y WA_WABA_ID en config.php.');
            $this->redirigir('whatsapp/configuracion');
        }
        $info = ['cuenta' => null, 'numero' => null];
        $errores = [];
        if (wa_cfg('WA_WABA_ID')) {
            $info['cuenta'] = wa_info_cuenta();
            if ($info['cuenta']['error']) $errores[] = $info['cuenta']['error'];
        }
        if (wa_cfg('WA_PHONE_NUMBER_ID')) {
            [$http, $j] = wa_api('GET', wa_cfg('WA_PHONE_NUMBER_ID') . '?fields=display_phone_number,verified_name,quality_rating,messaging_limit_tier,name_status');
            if ($http === 200) $info['numero'] = $j;
            else $errores[] = 'El número configurado: ' . ($j['error']['message'] ?? "HTTP $http");
        }
        Session::set('wa_conexion', $info);
        Session::flash($errores ? 'error' : 'ok', $errores ? 'Meta respondió con error: ' . implode(' · ', $errores) : 'Conexión correcta con Meta.');
        $this->redirigir('whatsapp/configuracion');
    }
}
