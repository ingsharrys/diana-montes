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

/** URL de archivos estáticos (css/js/img): siempre ruta directa, sin router. */
function asset(string $ruta): string
{
    return APP_URL . '/' . ltrim($ruta, '/');
}
