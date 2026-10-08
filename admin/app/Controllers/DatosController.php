<?php
namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\Auditoria;
use Models\Configuracion;

/**
 * Datos personales (Ley 1581 de 2012): solicitudes de los titulares que llegan
 * por /eliminar-datos/ y por WhatsApp ("ELIMINAR MIS DATOS"), y los datos del
 * responsable que se publican en /privacidad/, /terminos/ y /eliminar-datos/.
 * Solo el rol dirección.
 */
class DatosController extends Controller
{
    public function index(): void
    {
        Auth::requerirRol('direccion');
        $db = Database::conexion();
        $estado = in_array($_GET['estado'] ?? '', ['pendiente', 'atendida', 'cerrada', 'todas'], true) ? $_GET['estado'] : 'pendiente';
        $lista = []; $conteo = [];
        if (solicitudes_listas($db)) {
            $sql = 'SELECT d.*, s.nombre AS registro_nombre, s.documento AS registro_documento, s.telefono AS registro_telefono, u.nombre AS atendida_por_nombre
                    FROM solicitudes_datos d
                    LEFT JOIN simpatizantes s ON s.id = d.simpatizante_id
                    LEFT JOIN usuarios u ON u.id = d.atendida_por';
            $p = [];
            if ($estado !== 'todas') { $sql .= ' WHERE d.estado = :e'; $p['e'] = $estado; }
            $st = $db->prepare($sql . ' ORDER BY d.id DESC LIMIT 200');
            $st->execute($p);
            $lista = $st->fetchAll();
            $conteo = $db->query('SELECT estado, COUNT(*) FROM solicitudes_datos GROUP BY estado')->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
        $this->vista('datos/index', [
            'titulo' => 'Datos personales',
            'listo'  => solicitudes_listas($db),
            'estado' => $estado,
            'lista'  => $lista,
            'conteo' => $conteo,
            'legal'  => legal_datos($db),
        ]);
    }

    /** POST datos/eliminar/{id} — borra el registro del titular y cierra la solicitud. */
    public function eliminar(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('datos');
        $this->validarCsrf();
        $db = Database::conexion();
        $sol = $this->solicitud((int)$id);
        if (!$sol['simpatizante_id']) {
            Session::flash('error', 'Esta solicitud no corresponde a ningún registro: ciérrala indicando que no se encontraron datos.');
            $this->redirigir('datos');
        }
        try {
            $nombre = datos_eliminar_simpatizante($db, (int)$sol['simpatizante_id']);
            solicitud_cerrar($db, (int)$sol['id'], 'atendida', 'Tus datos fueron eliminados de la plataforma de la campaña.', Auth::id(), true);
            Auditoria::registrar('datos_eliminados', $sol['radicado'] . ' · registro #' . (int)$sol['simpatizante_id'] . ' borrado por solicitud del titular');
            Session::flash('ok', 'Se eliminaron los datos de ' . $nombre . ' y la solicitud ' . $sol['radicado'] . ' quedó atendida.');
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudieron eliminar los datos: ' . $e->getMessage());
        }
        $this->redirigir('datos');
    }

    /** POST datos/cerrar/{id} — marca la solicitud como atendida o cerrada con una respuesta. */
    public function cerrar(string $id = '0'): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('datos');
        $this->validarCsrf();
        $sol = $this->solicitud((int)$id);
        $estado = ($_POST['estado'] ?? '') === 'cerrada' ? 'cerrada' : 'atendida';
        $respuesta = trim(mb_substr((string)($_POST['respuesta'] ?? ''), 0, 500));
        if ($respuesta === '') {
            $respuesta = $estado === 'cerrada' ? 'No encontramos datos tuyos con el documento y el celular indicados.' : 'Tu solicitud fue atendida.';
        }
        // Al cerrar sin datos en la base no hay nada que conservar de la persona: se ocultan sus datos
        solicitud_cerrar(Database::conexion(), (int)$sol['id'], $estado, $respuesta, Auth::id(), $estado === 'cerrada');
        Auditoria::registrar('solicitud_datos_' . $estado, $sol['radicado'] . ' · ' . $respuesta);
        Session::flash('ok', 'Solicitud ' . $sol['radicado'] . ' marcada como ' . ($estado === 'cerrada' ? 'cerrada' : 'atendida') . '.');
        $this->redirigir('datos');
    }

    /** POST datos/responsable — datos que se publican en las páginas legales. */
    public function responsable(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) $this->redirigir('datos');
        $this->validarCsrf();
        $conf = new Configuracion();
        if (!$conf->disponible()) {
            Session::flash('error', 'Primero pulsa "Actualizar plataforma" en Inicio.');
            $this->redirigir('datos');
        }
        $correo = trim((string)($_POST['legal_email'] ?? ''));
        if ($correo !== '' && ($msg = error_correo($correo))) {
            Session::flash('error', $msg);
            $this->redirigir('datos');
        }
        foreach (array_keys(LEGAL_CAMPOS) as $k) {
            $conf->guardar($k, trim(mb_substr(strip_tags((string)($_POST[$k] ?? '')), 0, 200)));
        }
        Auditoria::registrar('datos_responsable', 'Actualizó los datos del responsable de las páginas legales');
        Session::flash('ok', 'Datos del responsable guardados. Ya se ven en las páginas de privacidad, condiciones y eliminación de datos.');
        $this->redirigir('datos');
    }

    private function solicitud(int $id): array
    {
        $db = Database::conexion();
        if (!solicitudes_listas($db)) $this->redirigir('datos');
        $st = $db->prepare('SELECT * FROM solicitudes_datos WHERE id = :id');
        $st->execute(['id' => $id]);
        $sol = $st->fetch();
        if (!$sol) { Session::flash('error', 'Solicitud no encontrada.'); $this->redirigir('datos'); }
        if ($sol['estado'] !== 'pendiente') { Session::flash('error', 'Esa solicitud ya fue tramitada.'); $this->redirigir('datos'); }
        return $sol;
    }
}
