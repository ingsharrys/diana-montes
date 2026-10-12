<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\Organizacion;
use Models\Configuracion;
use Models\Auditoria;

/**
 * Organización de la campaña: colaboradores pagos y voluntarios, publicidad
 * impresa, testigos electorales y gastos. Ver y registrar: dirección y
 * coordinación. Lo que tiene que ver con dinero (valores, pagos, costos,
 * gastos y tope) solo lo ve y lo edita la dirección.
 */
class OrganizacionController extends Controller
{
    private function base(string $pestana, string $titulo): array
    {
        Auth::requerirRol('direccion', 'coordinador');
        $org = new Organizacion();
        return [
            'titulo' => $titulo, 'pestana' => $pestana, 'org' => $org,
            'disponible' => $org->disponible(),
            'verDinero' => Auth::tieneRol('direccion'),
        ];
    }

    /** Valida POST + CSRF y que existan las tablas. */
    private function accion(string $volver, bool $soloDireccion = false): Organizacion
    {
        $soloDireccion ? Auth::requerirRol('direccion') : Auth::requerirRol('direccion', 'coordinador');
        if (!$this->esPost()) $this->redirigir($volver);
        $this->validarCsrf();
        $org = new Organizacion();
        if (!$org->disponible()) { Session::flash('error', 'Primero pulsa "Actualizar plataforma" en la Vista rápida.'); $this->redirigir($volver); }
        return $org;
    }

    private function irA(string $ruta, array $q): void
    {
        header('Location: ' . url_con($ruta, $q));
        exit;
    }

    private static function texto(string $k, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST[$k] ?? ''))), 0, $max);
    }
    private static function entero(string $k): ?int
    {
        $v = preg_replace('/\D/', '', (string)($_POST[$k] ?? ''));
        return $v === '' ? null : (int)$v;
    }
    private static function fecha(string $k): ?string
    {
        $v = (string)($_POST[$k] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
    }
    private static function celular(string $k): ?string
    {
        $v = preg_replace('/\D/', '', (string)($_POST[$k] ?? ''));
        if (strlen($v) === 12 && str_starts_with($v, '57')) $v = substr($v, 2);
        return $v === '' ? null : $v;
    }
    private function zonasValidas(): array
    {
        return array_map('intval', Database::conexion()->query('SELECT id FROM zonas')->fetchAll(\PDO::FETCH_COLUMN));
    }

    /* ======================= Resumen ======================= */

    public function index(): void
    {
        $d = $this->base('resumen', 'Organización');
        if ($d['disponible']) {
            $d['r'] = $d['org']->resumen();
            $d['tope'] = (int)(new Configuracion())->obtener('tope_gastos', '0');
        }
        $this->vista('organizacion/index', $d);
    }

    /* ======================= Colaboradores ======================= */

    public function colaboradores(): void
    {
        $d = $this->base('colaboradores', 'Organización · Colaboradores');
        if ($d['disponible']) {
            $f = ['tipo' => in_array($_GET['tipo'] ?? '', ['pago', 'voluntario'], true) ? $_GET['tipo'] : '',
                  'rol' => isset(Organizacion::ROLES[$_GET['rol'] ?? '']) ? $_GET['rol'] : '',
                  'activo' => in_array($_GET['activo'] ?? '1', ['1', '0', ''], true) ? ($_GET['activo'] ?? '1') : '1'];
            $d += ['filtro' => $f, 'lista' => $d['org']->colaboradores($f),
                   'editar' => isset($_GET['editar']) ? $d['org']->colaborador((int)$_GET['editar']) : null,
                   'pagos' => $d['verDinero'] ? $d['org']->pagos(null, 15) : [],
                   'zonas' => zonas_listar(Database::conexion()),
                   'errores' => Session::get('org_errores') ?? [], 'v' => Session::get('org_valores') ?? []];
            Session::set('org_errores', null); Session::set('org_valores', null);
        }
        $this->vista('organizacion/colaboradores', $d);
    }

    public function guardarcolaborador(string $id = '0'): void
    {
        $org = $this->accion('organizacion/colaboradores');
        $id = (int)$id ?: null;
        $previo = $id ? $org->colaborador($id) : null;
        if ($id && !$previo) $this->redirigir('organizacion/colaboradores');
        $dinero = Auth::tieneRol('direccion');

        $d = [
            'nombre' => self::texto('nombre', 120), 'documento' => self::entero('documento') !== null ? (string)self::entero('documento') : null,
            'telefono' => self::celular('telefono'), 'rol' => (string)($_POST['rol'] ?? ''),
            'tipo' => ($_POST['tipo'] ?? '') === 'pago' ? 'pago' : 'voluntario',
            'zona_id' => self::entero('zona_id'), 'inicio' => self::fecha('inicio'), 'fin' => self::fecha('fin'),
            'activo' => isset($_POST['activo']) ? 1 : 0, 'notas' => self::texto('notas', 255) ?: null,
        ];
        if ($dinero) {
            $d['valor'] = $d['tipo'] === 'pago' ? self::entero('valor') : null;
            $d['periodicidad'] = $d['tipo'] === 'pago' && isset(Organizacion::PERIODOS[$_POST['periodicidad'] ?? '']) ? $_POST['periodicidad'] : null;
        } elseif (!$id) {
            $d['valor'] = null; $d['periodicidad'] = null;     // la dirección pone el valor después
        }

        $e = [];
        if (mb_strlen($d['nombre']) < 3) $e['nombre'] = 'Escribe el nombre completo.';
        if (!isset(Organizacion::ROLES[$d['rol']])) $e['rol'] = 'Elige el rol.';
        if ($d['documento'] !== null && (strlen($d['documento']) < 5 || strlen($d['documento']) > 12)) $e['documento'] = 'Documento de 5 a 12 dígitos.';
        if ($d['telefono'] !== null && !preg_match('/^3\d{9}$/', $d['telefono'])) $e['telefono'] = 'Celular de 10 dígitos que empiece por 3.';
        if ($d['zona_id'] !== null && !in_array($d['zona_id'], $this->zonasValidas(), true)) $d['zona_id'] = null;
        if ($d['inicio'] && $d['fin'] && $d['fin'] < $d['inicio']) $e['fin'] = 'La fecha final no puede ser antes del inicio.';
        if ($dinero && $d['tipo'] === 'pago' && !$d['valor']) $e['valor'] = 'Escribe cuánto se le paga.';
        if ($dinero && $d['tipo'] === 'pago' && !$d['periodicidad']) $e['periodicidad'] = 'Elige cada cuánto se le paga.';
        if ($e) {
            Session::set('org_errores', $e); Session::set('org_valores', $_POST);
            Session::flash('error', 'Revisa los datos del colaborador.');
            $this->irA('organizacion/colaboradores', ['editar' => $id]);
        }
        $nuevo = $org->guardarColaborador($id, $d, (int)Auth::id());
        Auditoria::registrar($id ? 'colaborador_editado' : 'colaborador_creado', $d['nombre'] . ' · ' . Organizacion::ROLES[$d['rol']] . ' · ' . $d['tipo']);
        Session::flash('ok', ($id ? 'Colaborador actualizado: ' : 'Colaborador agregado: ') . $d['nombre'] . '.');
        $this->redirigir('organizacion/colaboradores');
    }

    /** Registra un pago a un colaborador (solo dirección). */
    public function pago(string $id = '0'): void
    {
        $org = $this->accion('organizacion/colaboradores', true);
        $c = $org->colaborador((int)$id);
        $valor = self::entero('valor');
        $fecha = self::fecha('fecha') ?? date('Y-m-d');
        if (!$c) Session::flash('error', 'No se encontró el colaborador.');
        elseif (!$valor) Session::flash('error', 'Escribe el valor del pago.');
        elseif ($fecha > date('Y-m-d')) Session::flash('error', 'La fecha del pago no puede ser futura.');
        else {
            $org->registrarPago((int)$id, $fecha, $valor, self::texto('concepto', 150), self::texto('medio', 30), (int)Auth::id());
            Auditoria::registrar('pago_colaborador', $c['nombre'] . ' · ' . pesos($valor) . ' · ' . $fecha);
            Session::flash('ok', 'Pago de ' . pesos($valor) . ' a ' . $c['nombre'] . ' registrado.');
        }
        $this->redirigir('organizacion/colaboradores');
    }

    /* ======================= Publicidad impresa ======================= */

    public function publicidad(): void
    {
        $d = $this->base('publicidad', 'Organización · Publicidad impresa');
        if ($d['disponible']) {
            $d += ['piezas' => $d['org']->publicidad(), 'entregas' => $d['org']->entregas(20), 'porZona' => $d['org']->entregadoPorZona(),
                   'editar' => isset($_GET['editar']) ? $d['org']->pieza((int)$_GET['editar']) : null,
                   'zonas' => zonas_listar(Database::conexion())];
        }
        $this->vista('organizacion/publicidad', $d);
    }

    public function guardarpieza(string $id = '0'): void
    {
        $org = $this->accion('organizacion/publicidad');
        $id = (int)$id ?: null;
        if ($id && !$org->pieza($id)) $this->redirigir('organizacion/publicidad');
        $d = [
            'tipo' => (string)($_POST['tipo'] ?? ''), 'descripcion' => self::texto('descripcion', 150) ?: null,
            'cantidad' => self::entero('cantidad'), 'proveedor' => self::texto('proveedor', 120) ?: null,
            'estado' => isset(Organizacion::PUB_ESTADOS[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'pedido',
            'fecha_pedido' => self::fecha('fecha_pedido'), 'fecha_entrega' => self::fecha('fecha_entrega'),
        ];
        if (Auth::tieneRol('direccion')) $d['costo'] = self::entero('costo');
        elseif (!$id) $d['costo'] = null;
        if (!isset(Organizacion::PUBLICIDAD[$d['tipo']])) $error = 'Elige el tipo de pieza.';
        elseif (!$d['cantidad'] || $d['cantidad'] > 10000000) $error = 'Escribe la cantidad.';
        elseif ($id && $d['cantidad'] < (int)$org->pieza($id)['entregado']) $error = 'La cantidad no puede ser menor que lo ya entregado.';
        if (isset($error)) { Session::flash('error', $error); $this->irA('organizacion/publicidad', ['editar' => $id]); }
        $org->guardarPieza($id, $d, (int)Auth::id());
        Auditoria::registrar($id ? 'publicidad_editada' : 'publicidad_creada', Organizacion::PUBLICIDAD[$d['tipo']] . ' × ' . $d['cantidad'] . ' (' . $d['estado'] . ')');
        Session::flash('ok', Organizacion::PUBLICIDAD[$d['tipo']] . ': ' . num($d['cantidad']) . ' ' . ($id ? 'actualizados.' : 'registrados.'));
        $this->redirigir('organizacion/publicidad');
    }

    /** Entrega de piezas a una zona o responsable (sale de la bodega). */
    public function entrega(string $id = '0'): void
    {
        $org = $this->accion('organizacion/publicidad');
        $p = $org->pieza((int)$id);
        $cantidad = self::entero('cantidad');
        $zona = self::entero('zona_id');
        if ($zona !== null && !in_array($zona, $this->zonasValidas(), true)) $zona = null;
        if (!$p) Session::flash('error', 'No se encontró la pieza.');
        elseif ($p['estado'] !== 'recibido') Session::flash('error', 'Solo se entrega lo que ya se recibió del proveedor.');
        elseif (!$cantidad) Session::flash('error', 'Escribe cuántas piezas se entregan.');
        elseif ($cantidad > (int)$p['cantidad'] - (int)$p['entregado']) Session::flash('error', 'Solo quedan ' . num((int)$p['cantidad'] - (int)$p['entregado']) . ' en bodega.');
        elseif (!$zona && self::texto('responsable', 120) === '') Session::flash('error', 'Indica la zona o quién la recibe.');
        else {
            $org->registrarEntrega((int)$id, $cantidad, $zona, self::texto('responsable', 120), self::fecha('fecha') ?? date('Y-m-d'), (int)Auth::id());
            Auditoria::registrar('publicidad_entregada', Organizacion::PUBLICIDAD[$p['tipo']] . ' × ' . $cantidad);
            Session::flash('ok', num($cantidad) . ' ' . mb_strtolower(Organizacion::PUBLICIDAD[$p['tipo']]) . ' entregados.');
        }
        $this->redirigir('organizacion/publicidad');
    }

    /* ======================= Testigos electorales ======================= */

    public function testigos(): void
    {
        $d = $this->base('testigos', 'Organización · Testigos electorales');
        if ($d['disponible']) {
            $puesto = (int)($_GET['puesto'] ?? 0) ?: null;
            $prefill = null;
            if (isset($_GET['sugerido'])) {
                foreach ($d['org']->testigosSugeridos(500) as $s) if ((int)$s['id'] === (int)$_GET['sugerido']) $prefill = $s;
            }
            $d += ['cobertura' => $d['org']->coberturaPuestos(), 'lista' => $d['org']->testigos($puesto), 'puesto' => $puesto,
                   'editar' => isset($_GET['editar']) ? $d['org']->testigo((int)$_GET['editar']) : null, 'prefill' => $prefill,
                   'sugeridos' => $d['org']->testigosSugeridos(8), 'nSugeridos' => $d['org']->contarSugeridos()];
        }
        $this->vista('organizacion/testigos', $d);
    }

    public function guardartestigo(string $id = '0'): void
    {
        $org = $this->accion('organizacion/testigos');
        $id = (int)$id ?: null;
        if ($id && !$org->testigo($id)) $this->redirigir('organizacion/testigos');
        $d = [
            'nombre' => self::texto('nombre', 120), 'documento' => self::entero('documento') !== null ? (string)self::entero('documento') : null,
            'telefono' => self::celular('telefono'), 'puesto_id' => self::entero('puesto_id'), 'mesa' => self::entero('mesa'),
            'estado' => isset(Organizacion::TESTIGO_ESTADOS[$_POST['estado'] ?? '']) ? $_POST['estado'] : 'propuesto',
            'notas' => self::texto('notas', 255) ?: null,
        ];
        $puesto = $d['puesto_id'] ? Database::conexion()->prepare('SELECT * FROM puestos_votacion WHERE id = :id') : null;
        if ($puesto) { $puesto->execute(['id' => $d['puesto_id']]); $puesto = $puesto->fetch(); }
        $volverQ = ['editar' => $id];
        if (mb_strlen($d['nombre']) < 3) $error = 'Escribe el nombre del testigo.';
        elseif ($d['documento'] === null || strlen($d['documento']) < 5 || strlen($d['documento']) > 12) $error = 'El testigo necesita su número de documento (para la credencial).';
        elseif ($d['telefono'] !== null && !preg_match('/^3\d{9}$/', $d['telefono'])) $error = 'Celular de 10 dígitos que empiece por 3.';
        elseif (!$puesto) $error = 'Elige el puesto de votación.';
        elseif ($d['mesa'] !== null && !empty($puesto['mesas']) && ($d['mesa'] < 1 || $d['mesa'] > (int)$puesto['mesas'])) $error = 'Ese puesto tiene ' . (int)$puesto['mesas'] . ' mesas.';
        elseif ($d['mesa'] !== null && ($otro = $org->mesaOcupada((int)$d['puesto_id'], (int)$d['mesa'], $id))) $error = 'La mesa ' . $d['mesa'] . ' ya tiene testigo: ' . $otro . '.';
        if (isset($error)) { Session::flash('error', $error); $this->irA('organizacion/testigos', $volverQ); }
        $org->guardarTestigo($id, $d, (int)Auth::id());
        Auditoria::registrar($id ? 'testigo_editado' : 'testigo_creado', $d['nombre'] . ' · ' . $puesto['nombre'] . ($d['mesa'] ? ' mesa ' . $d['mesa'] : ''));
        Session::flash('ok', 'Testigo ' . ($id ? 'actualizado' : 'asignado') . ': ' . $d['nombre'] . ' · ' . $puesto['nombre'] . ($d['mesa'] ? ', mesa ' . $d['mesa'] : '') . '.');
        $this->redirigir('organizacion/testigos');
    }

    /* ======================= Gastos (solo dirección) ======================= */

    public function gastos(): void
    {
        Auth::requerirRol('direccion');
        $d = $this->base('gastos', 'Organización · Gastos');
        if ($d['disponible']) {
            $d += ['r' => $d['org']->resumen(), 'lista' => $d['org']->gastos(), 'pagos' => $d['org']->pagos(null, 300),
                   'editar' => isset($_GET['editar']) ? $d['org']->gasto((int)$_GET['editar']) : null,
                   'tope' => (int)(new Configuracion())->obtener('tope_gastos', '0')];
        }
        $this->vista('organizacion/gastos', $d);
    }

    public function guardargasto(string $id = '0'): void
    {
        $org = $this->accion('organizacion/gastos', true);
        $id = (int)$id ?: null;
        if ($id && !$org->gasto($id)) $this->redirigir('organizacion/gastos');
        $d = ['fecha' => self::fecha('fecha'), 'categoria' => (string)($_POST['categoria'] ?? ''), 'concepto' => self::texto('concepto', 150),
              'valor' => self::entero('valor'), 'proveedor' => self::texto('proveedor', 120) ?: null];
        if (!$d['fecha'] || $d['fecha'] > date('Y-m-d')) $error = 'Escribe la fecha del gasto (hoy o antes).';
        elseif (!isset(Organizacion::GASTOS[$d['categoria']])) $error = 'Elige la categoría.';
        elseif (mb_strlen($d['concepto']) < 3) $error = 'Escribe el concepto.';
        elseif (!$d['valor']) $error = 'Escribe el valor.';
        if (isset($error)) { Session::flash('error', $error); $this->irA('organizacion/gastos', ['editar' => $id]); }
        $org->guardarGasto($id, $d, (int)Auth::id());
        Auditoria::registrar($id ? 'gasto_editado' : 'gasto_creado', $d['concepto'] . ' · ' . pesos($d['valor']));
        Session::flash('ok', 'Gasto ' . ($id ? 'actualizado' : 'registrado') . ': ' . $d['concepto'] . ' · ' . pesos($d['valor']) . '.');
        $this->redirigir('organizacion/gastos');
    }

    /** Tope de gastos de la campaña (lo fija el CNE para cada municipio). */
    public function tope(): void
    {
        $this->accion('organizacion/gastos', true);
        $tope = self::entero('tope') ?? 0;
        (new Configuracion())->guardar('tope_gastos', (string)$tope);
        Auditoria::registrar('tope_gastos', pesos($tope));
        Session::flash('ok', $tope ? 'Tope de gastos: ' . pesos($tope) . '.' : 'Se quitó el tope de gastos.');
        $this->redirigir('organizacion/gastos');
    }

    /* ======================= Borrar ======================= */

    public function borrar(string $tipo = '', string $id = '0'): void
    {
        $dinero = in_array($tipo, ['pago', 'gasto', 'colaborador', 'pieza'], true);
        $volver = ['colaborador' => 'colaboradores', 'pago' => 'colaboradores', 'pieza' => 'publicidad', 'entrega' => 'publicidad',
                   'testigo' => 'testigos', 'gasto' => 'gastos'][$tipo] ?? '';
        $org = $this->accion('organizacion/' . $volver, $dinero);
        if ($org->borrar($tipo, (int)$id)) {
            Auditoria::registrar('organizacion_borrado', "$tipo #" . (int)$id);
            Session::flash('ok', 'Registro eliminado.');
        }
        $this->redirigir('organizacion/' . $volver);
    }
}
