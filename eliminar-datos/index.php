<?php
/**
 * /eliminar-datos/ — Instrucciones y formulario para que el titular pida borrar
 * (o corregir, consultar, revocar) sus datos. Es la "URL de las instrucciones
 * para la eliminación de datos" de la app en Meta.
 *
 * Las solicitudes llegan a Admin › Datos personales. La respuesta es la misma
 * esté o no registrada la persona, para no revelar quién está en la base.
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/legal_pagina.php';

session_start();
header('Cache-Control: no-store');

$db = legal_db();
$L  = legal_datos($db);
$listo = $db && solicitudes_listas($db);
if (empty($_SESSION['sd_csrf'])) $_SESSION['sd_csrf'] = bin2hex(random_bytes(16));

$v = ['tipo' => 'eliminar', 'nombre' => '', 'documento' => '', 'telefono' => '', 'correo' => '', 'detalle' => ''];
$errores = [];
$radicado = null;
$aviso = null;
$consulta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listo) {
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['nombre']    = normalizar_nombre($v['nombre']);
    $v['documento'] = normalizar_documento($v['documento']);
    $v['telefono']  = normalizar_celular($v['telefono']);
    $v['detalle']   = mb_substr($v['detalle'], 0, 1000);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!hash_equals($_SESSION['sd_csrf'], (string)($_POST['csrf'] ?? '')) || ($_POST['sitio'] ?? '') !== '') {
        $aviso = 'La página estuvo abierta mucho tiempo. Vuelve a enviar la solicitud.';
    } elseif (solicitudes_recientes_ip($db, $ip) >= 5) {
        $aviso = 'Recibimos varias solicitudes desde esta conexión. Intenta de nuevo mañana o escríbenos por los otros canales.';
    } else {
        if (!isset(SOLICITUD_TIPOS[$v['tipo']])) $errores['tipo'] = 'Elige qué quieres hacer con tus datos.';
        if ($m = error_nombre_persona($v['nombre'])) $errores['nombre'] = $m;
        if ($m = error_documento($v['documento'])) $errores['documento'] = $m;
        if ($m = error_celular($v['telefono'])) $errores['telefono'] = $m;
        if ($v['correo'] !== '' && ($m = error_correo($v['correo']))) $errores['correo'] = $m;
        if (empty($_POST['titular'])) $errores['titular'] = 'Confirma que eres el titular de los datos o su representante.';
        if (!$errores) {
            try {
                $radicado = solicitud_crear($db, $v + ['canal' => 'web', 'ip' => $ip]);
                $_SESSION['sd_csrf'] = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $aviso = 'No pudimos guardar la solicitud. Intenta de nuevo en unos minutos o escríbenos por los otros canales.';
            }
        }
    }
}
if ($listo && is_string($_GET['radicado'] ?? null) && preg_match('/^SD-[A-Z0-9]{6}$/i', trim($_GET['radicado']))) {
    $consulta = solicitud_por_radicado($db, $_GET['radicado']) ?: 'no';
}

$campo = function (string $k) use ($errores): string {
    return isset($errores[$k]) ? ' con-error' : '';
};
$error = fn(string $k) => isset($errores[$k]) ? '<em>' . le($errores[$k]) . '</em>' : '';

legal_abrir('eliminar-datos', 'Cómo pedir que la campaña de Diana Lucía Montes elimine tus datos personales: formulario, WhatsApp y plazos de respuesta.');
?>
<h1>Eliminación de datos de usuario</h1>
<p class="vigencia">Instrucciones para borrar tus datos de la red de Diana Lucía Montes · Vigentes desde el <?= LEGAL_VIGENCIA ?></p>

<p>Puedes pedir en cualquier momento que borremos los datos que guardamos de ti en cualquiera de estos servicios:</p>
<ul>
  <li>el sitio web y su formulario de registro;</li>
  <li>el panel del simpatizante;</li>
  <li>la app móvil <b>Diana Montes</b>;</li>
  <li>el WhatsApp de la campaña.</li>
</ul>
<p>Es gratis. Lo puedes hacer de cualquiera de estas formas:</p>

<ol class="pasos">
  <li><b>Con el formulario de esta página</b> (abajo). Al enviarlo recibes un número de radicado para consultar el estado de tu solicitud.</li>
  <li><b>Por WhatsApp:</b> desde el celular con el que te registraste, escribe <b>ELIMINAR MIS DATOS</b> al número de la campaña<?= $L['legal_telefono'] !== '' ? ' (' . le($L['legal_telefono']) . ')' : '' ?> o como respuesta a cualquiera de nuestros mensajes. De inmediato dejamos de enviarte mensajes y te respondemos con tu radicado.</li>
  <?php if ($L['legal_email'] !== ''): ?>
  <li><b>Por correo</b> a <a href="mailto:<?= le($L['legal_email']) ?>?subject=Eliminar%20mis%20datos"><?= le($L['legal_email']) ?></a>. Escribe en el mensaje tu nombre completo, tu número de documento y el celular con el que te registraste.</li>
  <?php endif; ?>
</ol>

<div class="aviso"><b>¿Solo quieres dejar de recibir mensajes?</b> Responde <b>SALIR</b> a cualquier WhatsApp de la campaña. Tus datos se conservan y puedes volver con <b>VOLVER</b>.</div>

<h2>Qué borramos</h2>
<ul>
  <li>Tu registro: nombre, documento, celular, fecha de nacimiento, género, barrio o vereda, profesión, puesto y mesa, y ubicación si la diste.</li>
  <li>Tu acceso: la clave del panel, las sesiones de la app y los códigos de acceso.</li>
  <li>Tus mensajes de WhatsApp con la campaña y el historial de envíos.</li>
  <li>Tus tareas, tus puntos y tu lugar en la red. Las personas que invitaste no se borran: quedan en la red de quien te invitó a ti.</li>
</ul>
<h3>Qué conservamos</h3>
<p>Solo el número de radicado, la fecha y el estado de tu solicitud, con tu documento y celular ocultos (por ejemplo, 310···344). Esto sirve para demostrar que atendimos tu solicitud, como exige la ley. También conservamos los datos que una ley u orden judicial nos obligue a guardar.</p>

<h2>Plazos</h2>
<ul>
  <li>Verificamos que la solicitud venga del titular: comparamos el documento y el celular con los del registro.</li>
  <li>Respondemos en máximo <b>15 días hábiles</b> (artículo 15 de la Ley 1581 de 2012). Normalmente lo hacemos mucho antes.</li>
  <li>Si los datos no coinciden con ningún registro, cerramos la solicitud e indicamos que no encontramos datos tuyos.</li>
</ul>

<h2 id="solicitud">Formulario de solicitud</h2>
<?php if (!$listo): ?>
  <div class="aviso">El formulario no está disponible en este momento. Escríbenos por WhatsApp<?= $L['legal_email'] !== '' ? ' o por correo' : '' ?> como se indica arriba.</div>
<?php elseif ($radicado): ?>
  <div class="ok" role="status">
    <b>✔ Recibimos tu solicitud.</b> Tu radicado es <b style="font-size:18px"><?= le($radicado) ?></b>. Guárdalo.<br>
    Si tus datos están en nuestra base, los tramitaremos en máximo 15 días hábiles. Puedes consultar el estado aquí abajo con tu radicado.
  </div>
<?php else: ?>
  <?php if ($aviso): ?><p class="error" role="alert"><?= le($aviso) ?></p><?php endif; ?>
  <?php if ($errores): ?><p class="error" role="alert">Revisa los datos marcados.</p><?php endif; ?>
  <form method="post" action="#solicitud" novalidate>
    <input type="hidden" name="csrf" value="<?= le($_SESSION['sd_csrf']) ?>">
    <label class="oculto" aria-hidden="true">No llenar <input type="text" name="sitio" tabindex="-1" autocomplete="off"></label>

    <label class="campo<?= $campo('tipo') ?>">
      <span>¿Qué quieres hacer? *</span>
      <select name="tipo">
        <?php foreach (SOLICITUD_TIPOS as $k => $t): ?>
          <option value="<?= $k ?>" <?= $v['tipo'] === $k ? 'selected' : '' ?>><?= le($t) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $error('tipo') ?>
    </label>

    <label class="campo<?= $campo('nombre') ?>">
      <span>Nombre completo *</span>
      <input name="nombre" maxlength="120" autocomplete="name" value="<?= le($v['nombre']) ?>" placeholder="Como aparece en tu documento">
      <?= $error('nombre') ?>
    </label>

    <div class="doble">
      <label class="campo<?= $campo('documento') ?>">
        <span>Número de documento *</span>
        <input name="documento" inputmode="numeric" maxlength="12" value="<?= le($v['documento']) ?>" placeholder="Solo números">
        <?= $error('documento') ?>
      </label>
      <label class="campo<?= $campo('telefono') ?>">
        <span>Celular con el que te registraste *</span>
        <input name="telefono" type="tel" inputmode="tel" maxlength="16" value="<?= le($v['telefono']) ?>" placeholder="3XX XXX XXXX">
        <?= $error('telefono') ?>
      </label>
    </div>

    <label class="campo<?= $campo('correo') ?>">
      <span>Correo para responderte (opcional)</span>
      <input name="correo" type="email" maxlength="150" autocomplete="email" value="<?= le($v['correo']) ?>" placeholder="nombre@correo.com">
      <?= $error('correo') ?>
    </label>

    <label class="campo">
      <span>Detalle (opcional)</span>
      <textarea name="detalle" rows="3" maxlength="1000" placeholder="Por ejemplo: qué dato quieres corregir"><?= le($v['detalle']) ?></textarea>
    </label>

    <label class="check<?= $campo('titular') ?>">
      <input type="checkbox" name="titular" value="1" <?= !empty($_POST['titular']) ? 'checked' : '' ?>>
      <span>Declaro que soy el titular de estos datos o su representante autorizado.</span>
    </label>
    <?= isset($errores['titular']) ? '<p class="campo"><em>' . le($errores['titular']) . '</em></p>' : '' ?>

    <button class="btn" type="submit">Enviar solicitud</button>
  </form>
<?php endif; ?>

<?php if ($listo): ?>
<h2 id="estado">Consultar el estado de tu solicitud</h2>
<form method="get" action="#estado" class="doble" style="align-items:end">
  <label class="campo">
    <span>Número de radicado</span>
    <input name="radicado" maxlength="9" placeholder="SD-XXXXXX" value="<?= le(is_string($_GET['radicado'] ?? null) ? $_GET['radicado'] : '') ?>" style="text-transform:uppercase">
  </label>
  <button class="btn btn-sec" type="submit">Consultar</button>
</form>
<?php if ($consulta === 'no'): ?>
  <p class="error">No encontramos ese radicado. Revísalo e intenta de nuevo.</p>
<?php elseif (is_array($consulta)): [$estTxt, $estColor] = SOLICITUD_ESTADOS[$consulta['estado']]; ?>
  <div class="resumen" role="status">
    <p><b><?= le($consulta['radicado']) ?></b> · <?= le(SOLICITUD_TIPOS[$consulta['tipo']]) ?></p>
    <p>Recibida el <?= date('d/m/Y', strtotime($consulta['created_at'])) ?> · Estado: <span class="etq <?= $estColor ?>"><?= le($estTxt) ?></span>
      <?= $consulta['atendida_at'] ? ' el ' . date('d/m/Y', strtotime($consulta['atendida_at'])) : '' ?></p>
    <?php if ($consulta['respuesta']): ?><p>Respuesta: <?= le($consulta['respuesta']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php endif; ?>

<p style="margin-top:26px">Más información en la <a href="/privacidad/">Política de privacidad</a>.</p>
<?php legal_cerrar();
