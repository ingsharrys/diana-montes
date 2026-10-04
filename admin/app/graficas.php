<?php
/**
 * Gráficas del centro de mando, dibujadas en el servidor (HTML/SVG, sin
 * librerías ni JavaScript). Cada marca lleva su valor visible y un globo
 * de ayuda (data-tip) accesible también con el teclado.
 *
 * Colores validados contra daltonismo (protan/deután) y contraste sobre
 * blanco con el validador de la guía de visualización:
 *   - PALETA: identidad (un color por miembro del equipo / categoría), orden fijo.
 *   - RAMPA_NIVEL: niveles ordenados de la red (un solo tono, claro → oscuro).
 */

const PALETA = ['#7C3AED', '#E0186C', '#eb6834', '#2a78d6', '#1baf7a', '#008300', '#eda100', '#e34948'];
const RAMPA_NIVEL = ['#aa8fff', '#9363ff', '#7f22fd', '#6400cf', '#480099'];
const COLOR_GENERO = ['mujer' => '#E0186C', 'hombre' => '#7C3AED', 'otro' => '#eb6834', 'no_dice' => '#ADA6C2', 'sin_dato' => '#DAD6E6'];
const GRIS_SIN_GRUPO = '#B9B3CC';

/** 75436 -> "75.436" */
function num(int|float $n): string
{
    return number_format((float)$n, 0, ',', '.');
}

/** 14.4 -> "14,4 %" */
function porc(float $p, int $decimales = 1): string
{
    return number_format($p, $decimales, ',', '.') . ' %';
}

/** Texto blanco o tinta según la luminancia del fondo (para etiquetas dentro de una marca). */
function tinta_sobre(string $hex): string
{
    [$r, $g, $b] = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    return $l > 0.36 ? '#1C1630' : '#FFFFFF';
}

/** Medidor semicircular: progreso hacia una meta. La pista es un paso claro del mismo violeta. */
function grafica_medidor(float $pct): string
{
    $lleno = max(0, min(100, $pct));
    $arco  = 'M20 100 A80 80 0 0 1 180 100';
    return '<svg class="medidor" viewBox="0 0 200 112" role="img" aria-label="Avance: ' . porc($pct) . '">'
         . '<path class="m-pista" d="' . $arco . '" pathLength="100"/>'
         . ($lleno > 0 ? '<path class="m-valor" d="' . $arco . '" pathLength="100" stroke-dasharray="' . round($lleno, 2) . ' 100"/>' : '')
         . '<text class="m-num" x="100" y="94" text-anchor="middle">' . number_format($pct, $pct < 10 ? 2 : 1, ',', '.')
         . '<tspan class="m-pct" dx="2">%</tspan></text></svg>';
}

/**
 * Barras horizontales (magnitudes comparables). $filas: [etiqueta, valor, color, texto del globo].
 * Valor al final de cada barra; extremo redondeado de 4px, recto en la línea base.
 */
function grafica_barras(array $filas): string
{
    $max = max([1, ...array_map(fn($f) => (int)$f[1], $filas)]);
    $html = '<div class="barras">';
    foreach ($filas as [$etiqueta, $valor, $color, $tip]) {
        // La barra más larga deja espacio para su cifra: nunca se sale de la tarjeta
        $fraccion = $valor > 0 ? max(0.015, $valor / $max) : 0;
        $html .= '<div class="b-fila"><span class="b-et">' . e($etiqueta) . '</span>'
               . '<span class="b-pista">'
               . ($valor > 0 ? '<i tabindex="0" data-tip="' . e($tip) . '" style="width:calc((100% - 56px) * ' . round($fraccion, 4) . ');background:' . $color . '"></i>' : '')
               . '<b class="b-val">' . num($valor) . '</b></span></div>';
    }
    return $html . '</div>';
}

/**
 * Columnas verticales (una sola serie, un solo color). $cols: [etiqueta, subetiqueta, valor, destacar].
 * La columna destacada (p. ej. "hoy") va en el tono pleno; las demás en un paso más claro.
 */
function grafica_columnas(array $cols, string $color, string $claro, string $unidad): string
{
    $max = max([1, ...array_map(fn($c) => (int)$c[2], $cols)]);
    $html = '<div class="columnas" style="grid-template-columns:repeat(' . count($cols) . ',1fr)">';
    foreach ($cols as [$etiqueta, $sub, $valor, $destacar]) {
        $alto = $valor > 0 ? max(2, $valor * 100 / $max) : 0;
        $html .= '<div class="col">'
               . '<div class="col-zona"><span class="c-val">' . num($valor) . '</span>'
               . ($valor > 0 ? '<i tabindex="0" data-tip="' . e(trim("$etiqueta $sub") . ': ' . num($valor) . " $unidad") . '" style="height:'
                   . round($alto, 2) . '%;background:' . ($destacar ? $color : $claro) . '"></i>' : '')
               . '</div><span class="c-et">' . e($etiqueta) . ($sub !== '' ? '<small>' . e($sub) . '</small>' : '') . '</span></div>';
    }
    return $html . '</div>';
}

/**
 * Parte de un todo como barra apilada al 100 % (más legible que una torta
 * cuando los valores son parecidos). $partes: [etiqueta, valor, color].
 * Separación de 2px entre segmentos; leyenda siempre visible con % y cantidad.
 */
function grafica_proporcion(array $partes, string $unidad): string
{
    $total = array_sum(array_map(fn($p) => (int)$p[1], $partes));
    if ($total === 0) return '<p class="muted vacio">Aún no hay datos.</p>';

    $barra = '<div class="prop">';
    $leyenda = '<ul class="prop-ley">';
    foreach ($partes as [$etiqueta, $valor, $color]) {
        if ($valor <= 0) continue;
        $p = $valor * 100 / $total;
        $texto = "$etiqueta: " . porc($p) . ' · ' . num($valor) . " $unidad";
        $barra .= '<i tabindex="0" data-tip="' . e($texto) . '" style="flex-basis:' . round($p, 3) . '%;background:' . $color
                . ';color:' . tinta_sobre($color) . '">' . ($p >= 14 ? porc($p, 0) : '') . '</i>';
        $leyenda .= '<li><span class="sw" style="background:' . $color . '"></span>' . e($etiqueta)
                  . ' <b>' . porc($p) . '</b> <span class="muted">' . num($valor) . '</span></li>';
    }
    return $barra . '</div>' . $leyenda . '</ul>';
}
