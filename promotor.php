<?php
/**
 * promotor.php — enlace antiguo del panel del promotor (promotor.php?t=TOKEN).
 * El panel ahora vive en /mi/ (con usuario y clave); este archivo solo
 * reenvía los enlaces ya compartidos por WhatsApp para que sigan sirviendo.
 */
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$t = is_string($_GET['t'] ?? null) && preg_match('/^[a-f0-9]{32}$/', $_GET['t']) ? $_GET['t'] : null;
header('Location: mi/' . ($t ? '?t=' . $t : ''), true, 302);
