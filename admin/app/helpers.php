<?php
/**
 * Helpers globales disponibles en toda la aplicación (controladores y vistas).
 */

/** Escape HTML: SIEMPRE usarlo al imprimir datos de usuario en las vistas. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Enmascara documentos/teléfonos para mostrarlos en listados. */
function enmascarar(?string $valor): string
{
    $v = (string)$valor;
    if (strlen($v) <= 6) return $v;
    return substr($v, 0, 3) . '··· ' . substr($v, -3);
}

/** URL absoluta de la app (con o sin URLs bonitas según USE_REWRITE). */
function url(string $ruta = ''): string
{
    $ruta = ltrim($ruta, '/');
    if (defined('USE_REWRITE') && USE_REWRITE === false) {
        // Modo compatible: no depende del .htaccess
        return $ruta === '' ? APP_URL . '/index.php' : APP_URL . '/index.php?url=' . $ruta;
    }
    return APP_URL . '/' . $ruta;
}

/** Fecha legible en español corto: 14/07/2026 6:00 p.m. */
function fecha_co(?string $fechaSql): string
{
    if (!$fechaSql) return '—';
    $ts = strtotime($fechaSql);
    return date('d/m/Y g:i a', $ts);
}

/* ------------------------------------------------------------
   Polyfills mínimos por si el hosting no tiene ext-mbstring.
   (En cPanel suele estar activa; esto es un seguro adicional.)
   ------------------------------------------------------------ */
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s): string { return strtoupper($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $i, ?int $l = null): string { return $l === null ? substr($s, $i) : substr($s, $i, $l); }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return strlen($s); }
}

/** Ruta actual del router ("dashboard", "simpatizantes/crear"…), saneada igual que Core\App. */
function ruta_actual(): string
{
    $url = trim(preg_replace('/[^a-zA-Z0-9\/_-]/', '', $_GET['url'] ?? ''), '/');
    return strtolower($url !== '' ? $url : 'dashboard');
}

/** Íconos de línea (24×24) del menú y las tarjetas. Heredan el color del texto. */
function icono(string $nombre): string
{
    static $trazos = [
        'inicio'    => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'personas'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14.2c2.8.3 5 2.4 5 5.8"/>',
        'registrar' => '<circle cx="10" cy="8" r="3.5"/><path d="M3.5 20c0-3.6 2.9-6 6.5-6 1.4 0 2.6.3 3.6.9"/><path d="M18 14v6M15 17h6"/>',
        'red'       => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="18.5" r="2.5"/><circle cx="19" cy="18.5" r="2.5"/><circle cx="12" cy="13" r="2"/><path d="M12 7.5V11M10.4 14.3l-3.4 2.6M13.6 14.3l3.4 2.6"/>',
        'mapa'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'equipo'    => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'catalogos' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'salir'     => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
        'trofeo'    => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4"/><path d="M12 13v4M8.5 20.5h7M10 17h4"/>',
        'megafono'  => '<path d="M4 10v4a1 1 0 0 0 1 1h2.5L15 19V5L7.5 9H5a1 1 0 0 0-1 1z"/><path d="M18.5 9.5a3.5 3.5 0 0 1 0 5"/><path d="M7.5 15l1.2 4.5h2.3"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($trazos[$nombre] ?? '') . '</svg>';
}

/** URL de archivos estáticos (css/js/img): siempre ruta directa, sin router. */
function asset(string $ruta): string
{
    $ruta = ltrim($ruta, '/');
    // ?v=fecha de modificación: tras cada actualización el navegador descarga
    // la versión nueva en vez de usar la vieja guardada en caché
    $archivo = dirname(__DIR__) . '/' . $ruta;
    return APP_URL . '/' . $ruta . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}
