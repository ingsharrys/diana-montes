<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use DomainException;
use Models\Auditoria;
use Models\Video;

/** CRUD de videos editoriales; replica la estructura de HistoriasController. */
class VideosController extends Controller
{
    /** Archivos nuevos de este POST: se borran si el guardado falla. */
    private array $archivosSubidos = [];

    private function limpiarArchivosSubidos(): void
    {
        foreach ($this->archivosSubidos as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
        $this->archivosSubidos = [];
    }

    public function index(): void
    {
        Auth::requerirRol('direccion');
        $m = new Video();
        if (!$m->disponible()) {
            $this->vista('videos/index', ['titulo' => 'Videos editoriales', 'listo' => false]);
            return;
        }

        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
        $estado = isset(Video::ESTADOS[$_GET['estado'] ?? '']) ? (string) $_GET['estado'] : '';
        $tema = isset(Video::TEMAS[$_GET['tema'] ?? '']) ? (string) $_GET['tema'] : '';
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $editarId = max(0, (int) ($_GET['editar'] ?? 0));

        $this->vista('videos/index', [
            'titulo' => 'Videos editoriales',
            'listo' => true,
            'q' => $q,
            'estado' => $estado,
            'tema' => $tema,
            'listado' => $m->listar($q, $estado, $tema, $pagina),
            'conteo' => $m->conteo(),
            'editar' => $editarId ? $m->obtener($editarId) : null,
            'nuevo' => ($_GET['nuevo'] ?? '') === '1',
            'borrador' => Session::get('video_borrador') ?: [],
        ]);
        Session::set('video_borrador', null);
    }

    /** POST videos/guardar — crea o edita. */
    public function guardar(): void
    {
        $this->post();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        try {
            $m = new Video();
            $anterior = $id > 0 ? $m->obtener($id) : null;
            if ($id > 0 && !$anterior) {
                throw new DomainException('El video que deseas editar no existe.');
            }
            $datos = $this->datos($anterior);

            if ($id === 0) {
                $id = $m->crear($datos);
                Auditoria::registrar('video_creado', 'Video #' . $id . ': ' . $datos['titulo']);
                Session::flash('ok', 'Video #' . $id . ' guardado como borrador.');
            } else {
                $m->actualizar($id, $datos);
                Auditoria::registrar('video_editado', 'Video #' . $id . ': ' . $datos['titulo']);
                Session::flash('ok', 'Video #' . $id . ' actualizado.');
            }
            // La base de datos confirmó el guardado: conservar los archivos.
            $this->archivosSubidos = [];
            $this->redirigir('videos');
        } catch (DomainException $e) {
            $this->limpiarArchivosSubidos();
            Session::flash('error', $e->getMessage());
            Session::set('video_borrador', $_POST);
            header('Location: ' . url_con('videos', $id ? ['editar' => $id] : ['nuevo' => 1]) . '#formulario');
            exit;
        } catch (\Throwable $e) {
            $this->limpiarArchivosSubidos();
            throw $e;
        }
    }

    /** POST videos/estado — publicar o regresar a borrador. */
    public function estado(): void
    {
        $this->post();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $estado = $this->valor('estado');
        try {
            (new Video())->cambiarEstado($id, $estado);
            Auditoria::registrar('video_estado', 'Video #' . $id . ' → ' . $estado);
            Session::flash('ok', 'Video #' . $id . ': ' . (Video::ESTADOS[$estado] ?? $estado) . '.');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->volver();
    }

    /** POST videos/eliminar — elimina el registro, no borra imágenes físicas. */
    public function eliminar(): void
    {
        $this->post();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ((new Video())->eliminar($id)) {
            Auditoria::registrar('video_eliminado', 'Video #' . $id);
            Session::flash('ok', 'Video #' . $id . ' eliminado.');
        } else {
            Session::flash('error', 'El video ya no existe.');
        }
        $this->volver();
    }

    private function post(): void
    {
        Auth::requerirRol('direccion');
        if (!$this->esPost()) {
            $this->redirigir('videos');
        }
        $this->validarCsrf();
    }

    private function volver(): void
    {
        $parametros = [];
        $q = $this->valor('q');
        if ($q !== '') $parametros['q'] = mb_substr($q, 0, 100);
        $estado = $this->valor('filtro_estado');
        if (isset(Video::ESTADOS[$estado])) $parametros['estado'] = $estado;
        $tema = $this->valor('filtro_tema');
        if (isset(Video::TEMAS[$tema])) $parametros['tema'] = $tema;
        $parametros['pagina'] = max(1, (int) ($_POST['pagina'] ?? 1));
        header('Location: ' . url_con('videos', $parametros));
        exit;
    }

    private function valor(string $campo): string
    {
        $valor = $_POST[$campo] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }

    private function datos(?array $anterior): array
    {
        $titulo = preg_replace('/\s+/u', ' ', $this->valor('titulo'));
        $tema = $this->valor('tema');
        $plataforma = $this->valor('plataforma');
        $fuente = $this->valor('fuente');
        $formato = $this->valor('formato');
        $ordenValor = $this->valor('orden');
        $estado = $anterior ? $this->valor('estado') : 'borrador';

        if ($titulo === null || mb_strlen($titulo) < 4 || mb_strlen($titulo) > 180) {
            throw new DomainException('El título debe tener entre 4 y 180 caracteres.');
        }
        if (!isset(Video::TEMAS[$tema])) {
            throw new DomainException('Selecciona un tema válido.');
        }
        if (!isset(Video::PLATAFORMAS[$plataforma])) {
            throw new DomainException('Selecciona una plataforma válida.');
        }
        if (!isset(Video::FORMATOS[$formato])) {
            throw new DomainException('Selecciona un formato válido.');
        }
        if (!isset(Video::ESTADOS[$estado])) {
            throw new DomainException('Selecciona un estado válido.');
        }
        if ($ordenValor === '' || !preg_match('/\A\d{1,6}\z/', $ordenValor)) {
            throw new DomainException('El orden debe ser un número entre 0 y 999999.');
        }
        $orden = (int) $ordenValor;

        if ($plataforma === 'mp4') {
            // Los MP4 nuevos deben subirse. Al editar se puede conservar el existente.
            $subido = $this->subirVideoMp4();
            if ($subido !== null) {
                $fuente = $subido;
            } elseif ($anterior && $anterior['plataforma'] === 'mp4') {
                $fuente = (string) $anterior['fuente'];
            } else {
                throw new DomainException('Selecciona un archivo MP4 de máximo 50 MB.');
            }
            $fuente = $this->normalizarFuente('mp4', $fuente);
        } else {
            // No aceptar un MP4 si la plataforma elegida no es «Archivo MP4».
            $archivoNoMp4 = $_FILES['video_archivo'] ?? null;
            if ($archivoNoMp4 !== null && (
                !is_array($archivoNoMp4) || is_array($archivoNoMp4['error'] ?? null)
                || (int) ($archivoNoMp4['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            )) {
                throw new DomainException('Solo puedes subir un archivo cuando la plataforma es Archivo MP4.');
            }
            $fuente = $this->normalizarFuente($plataforma, $fuente);
        }

        $poster = $this->subirPortada();
        if ($poster === null) {
            $poster = (string) ($anterior['poster'] ?? './assets/videos/poster/foto-pendiente.jpg');
        }

        return compact('titulo', 'tema', 'plataforma', 'fuente', 'formato', 'orden', 'estado', 'poster');
    }

    /**
     * MP4: ruta de assets/videos o URL HTTPS acabada en .mp4.
     * Facebook: URL HTTPS pública; YouTube y Vimeo: solo el ID final.
     */
    private function normalizarFuente(string $plataforma, string $fuente): string
    {
        if ($fuente === '' || mb_strlen($fuente) > 700) {
            throw new DomainException('Indica la fuente del video (máximo 700 caracteres).');
        }

        if ($plataforma === 'mp4') {
            $local = preg_match(
                '~\A(?:\./)?assets/videos/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.mp4\z~i',
                $fuente
            );
            $remoto = filter_var($fuente, FILTER_VALIDATE_URL)
                && strtolower((string) parse_url($fuente, PHP_URL_SCHEME)) === 'https'
                && preg_match('~\.mp4\z~i', (string) parse_url($fuente, PHP_URL_PATH));
            if (!$local && !$remoto) {
                throw new DomainException('Para MP4 usa ./assets/videos/archivo.mp4 o una URL HTTPS que termine en .mp4.');
            }
            return $fuente;
        }

        if ($plataforma === 'facebook') {
            if (!filter_var($fuente, FILTER_VALIDATE_URL)
                || strtolower((string) parse_url($fuente, PHP_URL_SCHEME)) !== 'https') {
                throw new DomainException('Indica un enlace HTTPS público de Facebook.');
            }
            $host = strtolower((string) parse_url($fuente, PHP_URL_HOST));
            if ($host !== 'facebook.com' && !str_ends_with($host, '.facebook.com')) {
                throw new DomainException('El enlace debe pertenecer a facebook.com.');
            }
            return $fuente;
        }

        if ($plataforma === 'youtube') {
            if (filter_var($fuente, FILTER_VALIDATE_URL)) {
                $url = parse_url($fuente);
                $host = strtolower((string) ($url['host'] ?? ''));
                $path = trim((string) ($url['path'] ?? ''), '/');
                if (($url['scheme'] ?? '') !== 'https') {
                    throw new DomainException('Utiliza un enlace HTTPS de YouTube.');
                }
                if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
                    $fuente = explode('/', $path)[0];
                } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
                    if ($path === 'watch') {
                        parse_str((string) ($url['query'] ?? ''), $query);
                        $fuente = is_string($query['v'] ?? null) ? $query['v'] : '';
                    } elseif (preg_match('~\A(?:shorts|embed|live)/([a-zA-Z0-9_-]{11})\z~', $path, $m)) {
                        $fuente = $m[1];
                    } else {
                        $fuente = '';
                    }
                } else {
                    $fuente = '';
                }
            }
            if (!preg_match('/\A[a-zA-Z0-9_-]{11}\z/', $fuente)) {
                throw new DomainException('YouTube requiere un ID válido de 11 caracteres o su URL pública.');
            }
            return $fuente;
        }

        if ($plataforma === 'vimeo') {
            if (filter_var($fuente, FILTER_VALIDATE_URL)) {
                $url = parse_url($fuente);
                $host = strtolower((string) ($url['host'] ?? ''));
                $path = trim((string) ($url['path'] ?? ''), '/');
                if (($url['scheme'] ?? '') !== 'https'
                    || !in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
                    throw new DomainException('Usa una URL pública HTTPS de Vimeo.');
                }
                if (preg_match('~\A(?:video/)?(\d{1,14})\z~', $path, $m)) {
                    $fuente = $m[1];
                } else {
                    $fuente = '';
                }
            }
            if (!preg_match('/\A\d{1,14}\z/', $fuente)) {
                throw new DomainException('Vimeo requiere un ID numérico público o su URL.');
            }
            return $fuente;
        }

        throw new DomainException('Plataforma no admitida.');
    }

    /**
     * Sube un MP4 (máximo 50 MiB) a assets/videos/ bajo la raíz del proyecto.
     * Devuelve la ruta pública compatible con data-video-src, o null si no hay archivo.
     */
    private function subirVideoMp4(): ?string
    {
        $archivo = $_FILES['video_archivo'] ?? null;
        if ($archivo === null) return null;
        if (!is_array($archivo) || is_array($archivo['error'] ?? null)) {
            throw new DomainException('La subida del video no es válida.');
        }
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new DomainException('El video supera el límite permitido por el servidor. Revisa los ajustes de PHP (máximo 50 MB en el formulario).');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException('No se pudo subir el MP4 (código ' . $error . ').');
        }

        $temporal = (string) ($archivo['tmp_name'] ?? '');
        $tamano = (int) ($archivo['size'] ?? 0);
        $maximo = 50 * 1024 * 1024;
        if ($tamano < 1 || $tamano > $maximo || !is_uploaded_file($temporal)) {
            throw new DomainException('El video debe ser un MP4 válido de máximo 50 MB.');
        }
        $tamanoReal = filesize($temporal);
        if ($tamanoReal === false || $tamanoReal < 1 || $tamanoReal > $maximo) {
            throw new DomainException('El tamaño real del video supera el máximo de 50 MB o no se pudo verificar.');
        }

        $nombreOriginal = (string) ($archivo['name'] ?? '');
        if (strtolower((string) pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'mp4') {
            throw new DomainException('Solo se permiten archivos con extensión .mp4.');
        }
        if (!class_exists(\finfo::class)) {
            throw new DomainException('PHP necesita la extensión fileinfo para validar videos.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporal);
        if (!in_array($mime, ['video/mp4', 'application/mp4'], true)) {
            throw new DomainException('El archivo seleccionado no se identifica como un video MP4.');
        }

        // Verifica la marca de contenedor ISO BMFF; no confiar solo en el nombre/MIME.
        $handle = @fopen($temporal, 'rb');
        $cabecera = $handle ? fread($handle, 12) : false;
        if ($handle) fclose($handle);
        if (!is_string($cabecera) || strlen($cabecera) < 12 || substr($cabecera, 4, 4) !== 'ftyp') {
            throw new DomainException('El archivo no tiene la cabecera de un MP4 válido.');
        }

        // Desde admin/app/Controllers subimos a la raíz del proyecto.
        $directorio = dirname(__DIR__, 3) . '/assets/videos';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new DomainException('No se pudo crear la carpeta assets/videos.');
        }
        if (!is_writable($directorio)) {
            throw new DomainException('La carpeta assets/videos no tiene permisos de escritura.');
        }

        // Nombre aleatorio, sin reutilizar el que envía el navegador.
        $nombreNuevo = 'video-' . bin2hex(random_bytes(16)) . '.mp4';
        $destino = $directorio . '/' . $nombreNuevo;
        if (!move_uploaded_file($temporal, $destino)) {
            throw new DomainException('No fue posible guardar el MP4 en assets/videos.');
        }
        $this->archivosSubidos[] = $destino;
        return './assets/videos/' . $nombreNuevo;
    }

    /** Devuelve ruta web del archivo o null si no se subió ninguno. */
    private function subirPortada(): ?string
    {
        $archivo = $_FILES['poster_archivo'] ?? null;
        if ($archivo === null) return null;
        if (!is_array($archivo) || is_array($archivo['error'] ?? null)) {
            throw new DomainException('Archivo de portada no válido.');
        }
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException('No fue posible subir la portada (código ' . $error . ').');
        }
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        $tamano = (int) ($archivo['size'] ?? 0);
        if ($tamano < 1 || $tamano > 4 * 1024 * 1024 || !is_uploaded_file($tmp)) {
            throw new DomainException('La portada debe ser una imagen válida de máximo 4 MB.');
        }
        if (!class_exists(\finfo::class)) {
            throw new DomainException('El servidor necesita la extensión PHP fileinfo para validar imágenes.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensiones[$mime])) {
            throw new DomainException('La portada debe ser JPG, PNG o WEBP (no SVG).');
        }
        $dimensiones = @getimagesize($tmp);
        if (!$dimensiones || $dimensiones[0] < 50 || $dimensiones[1] < 50
            || $dimensiones[0] > 6000 || $dimensiones[1] > 6000) {
            throw new DomainException('Las dimensiones de la portada no son válidas.');
        }

        // Desde admin/app/Controllers subimos hasta la raíz de diana-montes.
        $directorio = dirname(__DIR__, 3) . '/assets/videos/poster';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new DomainException('No se pudo crear la carpeta de portadas.');
        }
        if (!is_writable($directorio)) {
            throw new DomainException('La carpeta assets/videos/poster no tiene permisos de escritura.');
        }
        $nombre = 'video-' . bin2hex(random_bytes(12)) . '.' . $extensiones[$mime];
        $destino = $directorio . '/' . $nombre;
        if (!move_uploaded_file($tmp, $destino)) {
            throw new DomainException('No fue posible guardar la portada en el servidor.');
        }
        $this->archivosSubidos[] = $destino;
        return './assets/videos/poster/' . $nombre;
    }
}
