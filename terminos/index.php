<?php
/**
 * /terminos/ — Condiciones del servicio del sitio, el panel, la app móvil y el WhatsApp de la campaña.
 * Es la "URL de las Condiciones del servicio" de la app en Meta.
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/legal_pagina.php';

$L = legal_datos(legal_db());
legal_abrir('terminos', 'Condiciones de uso del sitio, el panel del simpatizante, la app móvil y el WhatsApp de la campaña de Diana Lucía Montes.');
?>
<h1>Condiciones del servicio</h1>
<p class="vigencia">Vigentes desde el <?= LEGAL_VIGENCIA ?></p>

<p>Estas condiciones regulan el uso de los siguientes servicios de <b><?= le($L['legal_responsable']) ?></b> (en adelante, "la campaña"):</p>
<ul>
  <li>el sitio web <b>dianamontes.com</b>;</li>
  <li>el panel del simpatizante;</li>
  <li>la aplicación móvil <b>Diana Montes</b>;</li>
  <li>el WhatsApp de la campaña;</li>
  <li>el panel del equipo.</li>
</ul>
<p>Al registrarte o usar cualquiera de ellos aceptas estas condiciones y la <a href="/privacidad/">Política de privacidad</a>.</p>

<h2>1. Qué es el servicio</h2>
<p>Es la plataforma de participación ciudadana de la campaña de Diana Lucía Montes a la Alcaldía de Garzón 2027. Te permite:</p>
<ul>
  <li>sumarte a la red de simpatizantes;</li>
  <li>invitar a otras personas con tu enlace personal;</li>
  <li>recibir información y tareas de la campaña (reuniones, convocatorias, eventos);</li>
  <li>ver tu red y tus puntos.</li>
</ul>
<p>El equipo de la campaña usa la misma plataforma para organizar el trabajo territorial.</p>

<h2>2. Quién puede usarlo</h2>
<ul>
  <li>Personas de <b><?= EDAD_MINIMA ?> años o más</b>.</li>
  <li>Debes registrarte con <b>tus propios datos, verdaderos</b>. Está prohibido registrar a otra persona sin su autorización.</li>
  <li>Los líderes y el equipo que registran simpatizantes declaran contar con la autorización de cada persona para el tratamiento de sus datos (Ley 1581 de 2012).</li>
</ul>

<h2>3. Tu cuenta y tu seguridad</h2>
<ul>
  <li><b>Formas de ingresar:</b>
    <ul>
      <li>al panel web, con tu documento y tu clave;</li>
      <li>a la app, con el código que te llega por WhatsApp;</li>
      <li>el equipo, con su correo y contraseña o con su cuenta de Google.</li>
    </ul>
  </li>
  <li><b>Tus claves y códigos son personales.</b> No los compartas con nadie. La campaña nunca te los pedirá por llamada ni por mensaje.</li>
  <li><b>Sesión guardada:</b> en la app la sesión queda abierta en tu teléfono hasta que la cierres. Si pierdes el teléfono o crees que alguien entró a tu cuenta, avísanos para cerrarla.</li>
  <li><b>Lo que pasa con tu cuenta es tu responsabilidad</b> mientras no nos avises de un uso indebido.</li>
</ul>

<h2>4. Participación voluntaria, puntos y niveles</h2>
<ul>
  <li>Tu participación es <b>libre, voluntaria y gratuita</b>. Puedes dejar de participar cuando quieras.</li>
  <li>Los <b>puntos y niveles</b> (Simpatizante, Promotor, Súper Promotor y Embajador) son solo un reconocimiento simbólico dentro de la red. No representan dinero, pagos, premios, empleos, contratos ni ningún otro beneficio.</li>
  <li><b>La campaña no ofrece ni entrega dinero, dádivas o beneficios a cambio del voto, de un registro o de una invitación.</b> Hacerlo es un delito (artículo 390 del Código Penal). Si alguien te ofrece algo así en nombre de la campaña, denúncialo.</li>
</ul>

<h2>5. Uso permitido</h2>
<p>Te comprometes a usar el servicio de buena fe. Está prohibido:</p>
<ul>
  <li>Registrar datos falsos o de otras personas sin su autorización, o crear registros duplicados.</li>
  <li>Usar la red para enviar mensajes masivos no deseados (spam), cadenas, publicidad o contenido ajeno a la campaña.</li>
  <li>Usar los datos de otras personas que veas en tu panel para un fin distinto a las actividades de la campaña, o compartirlos con terceros.</li>
  <li>Publicar o enviar contenido ilegal, ofensivo, discriminatorio o que incite a la violencia.</li>
  <li>Hacerse pasar por la candidata, por el equipo o por otra persona.</li>
  <li>Intentar entrar a cuentas o datos ajenos, saltarse los controles de seguridad o automatizar el uso de la plataforma.</li>
</ul>

<h2>6. Mensajes de WhatsApp</h2>
<ul>
  <li>Solo te escribimos si diste tu autorización. Los mensajes se envían con la plataforma de WhatsApp Business de Meta y también se rigen por las condiciones de WhatsApp.</li>
  <li>Puedes responder en cualquier momento:
    <ul>
      <li><b>SALIR</b>, para dejar de recibir mensajes;</li>
      <li><b>VOLVER</b>, para recibirlos de nuevo;</li>
      <li><b>ELIMINAR MIS DATOS</b>, para pedir que borremos tu información.</li>
    </ul>
  </li>
</ul>

<h2>7. Obligaciones del equipo de la campaña</h2>
<p>Quien tiene acceso al panel del equipo:</p>
<ul>
  <li>Usa los datos solo para las labores de la campaña y según su rol.</li>
  <li>Guarda la confidencialidad de los datos, aun después de terminar su labor.</li>
  <li>No descarga, copia ni entrega bases de datos a terceros.</li>
  <li>Avisa de inmediato cualquier pérdida de acceso o uso indebido.</li>
</ul>
<p>Las acciones sensibles quedan registradas.</p>

<h2>8. Suspensión de cuentas</h2>
<p>La campaña puede suspender o eliminar cuentas y registros cuando:</p>
<ul>
  <li>se incumplan estas condiciones;</li>
  <li>haya datos falsos o duplicados;</li>
  <li>se use la plataforma de forma abusiva.</li>
</ul>

<h2>9. Contenido y marca</h2>
<p>Los textos, imágenes, videos, logotipos y el software de la plataforma pertenecen a la campaña o a sus autores. Puedes compartir los contenidos públicos de la campaña sin alterarlos. No puedes usar la imagen ni el nombre de la candidata para fines distintos ni hacerte pasar por la campaña.</p>

<h2>10. Disponibilidad y responsabilidad</h2>
<ul>
  <li>Hacemos lo posible para que el servicio funcione bien, pero puede tener interrupciones, fallas o cambios.</li>
  <li>El servicio se ofrece "tal como está", sin garantías de disponibilidad continua.</li>
  <li>La campaña no responde por daños que vengan del mal uso de la plataforma por parte de los usuarios, ni por fallas de terceros, como operadores de internet, WhatsApp o tiendas de aplicaciones.</li>
  <li>El servicio funciona durante la campaña electoral. Al terminar, puede cerrarse. Los datos se tratarán como dice la <a href="/privacidad/#tiempo">Política de privacidad</a>.</li>
</ul>

<h2>11. Cambios a estas condiciones</h2>
<p>Podemos actualizar estas condiciones. La versión vigente siempre estará en esta página con su fecha. Si sigues usando el servicio después de un cambio, aceptas la nueva versión.</p>

<h2>12. Ley aplicable y contacto</h2>
<p>Estas condiciones se rigen por las leyes de la República de Colombia.</p>
<p>Para dudas o reclamos escríbenos por <?= legal_contacto($L) ?>. Domicilio: <?= le($L['legal_direccion']) ?>.</p>
<?php legal_cerrar();
