<?php
/**
 * Validación y normalización de datos personales, compartida por la landing
 * (registrar.php) y el admin. Las mismas reglas viven en el navegador en
 * assets/js/validar.js: si cambias una, cambia la otra.
 */

const DOCUMENTO_MIN = 5;
const DOCUMENTO_MAX = 10;

/** Palabras que se dejan en minúscula dentro de un nombre ("María de los Ángeles"). */
const NOMBRE_PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'van', 'von'];

/** Quita espacios sobrantes y pone mayúscula inicial: "maría  DE la cruz" → "María de la Cruz". */
function normalizar_nombre(string $nombre): string
{
    $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
    $palabras = explode(' ', mb_strtolower($nombre, 'UTF-8'));
    foreach ($palabras as $i => $p) {
        if ($i > 0 && in_array($p, NOMBRE_PARTICULAS, true)) continue;
        $palabras[$i] = mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8');
    }
    return implode(' ', $palabras);
}

/**
 * Nombre de una persona: solo letras (con tildes y ñ) y espacios,
 * mínimo nombre y apellido. Devuelve el mensaje de error o null.
 */
function error_nombre_persona(string $nombre, string $quien = 'tu'): ?string
{
    if ($nombre === '') return $quien === 'tu' ? 'Escribe tu nombre completo.' : 'Escribe el nombre completo.';
    if (!preg_match('/^[\p{L}\p{M} ]+$/u', $nombre)) return 'El nombre solo puede tener letras y espacios (sin números ni símbolos).';
    if (mb_strlen($nombre) > 120) return 'El nombre es demasiado largo.';
    $palabras = array_filter(explode(' ', $nombre), fn($p) => mb_strlen($p) >= 2);
    if (count($palabras) < 2) return 'Escribe nombre y apellido.';
    return null;
}

/** Documento: solo dígitos. */
function normalizar_documento(string $doc): string
{
    return preg_replace('/\D/', '', $doc);
}

function error_documento(string $doc): ?string
{
    if ($doc === '') return 'Escribe el número de documento.';
    if (!preg_match('/^\d{' . DOCUMENTO_MIN . ',' . DOCUMENTO_MAX . '}$/', $doc) || $doc[0] === '0') {
        return 'El documento debe tener entre ' . DOCUMENTO_MIN . ' y ' . DOCUMENTO_MAX . ' dígitos, sin puntos ni letras.';
    }
    return null;
}

/** Celular colombiano: deja 10 dígitos; quita el indicativo +57 si lo escribieron. */
function normalizar_celular(string $tel): string
{
    $tel = preg_replace('/\D/', '', $tel);
    if (strlen($tel) === 12 && str_starts_with($tel, '57')) $tel = substr($tel, 2);
    return $tel;
}

function error_celular(string $tel): ?string
{
    if ($tel === '') return 'Escribe el número de celular.';
    if (!preg_match('/^3\d{9}$/', $tel)) return 'El celular debe tener 10 dígitos y empezar por 3.';
    return null;
}

function error_correo(string $correo): ?string
{
    if ($correo === '') return 'Escribe el correo electrónico.';
    if (mb_strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)
        || !preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $correo)) {
        return 'Escribe un correo válido, por ejemplo nombre@gmail.com.';
    }
    return null;
}

/**
 * Nombre de un catálogo (barrio, vereda, puesto, profesión, ocasión):
 * letras, números, espacios y . , ( ) - / º °. Devuelve el texto limpio o null si no sirve.
 */
function limpiar_texto_catalogo(string $texto, int $max = 120): ?string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    if (mb_strlen($texto) < 2 || mb_strlen($texto) > $max) return null;
    if (!preg_match('/^[\p{L}\p{M}\p{N} .,()\-\/#º°]+$/u', $texto)) return null;
    return $texto;
}
