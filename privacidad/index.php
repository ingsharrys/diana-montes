<?php
/**
 * /privacidad/ — Política de privacidad y tratamiento de datos personales (Ley 1581 de 2012).
 * Es la "URL de la Política de privacidad" de la app en Meta.
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../inc/legal_pagina.php';

$L = legal_datos(legal_db());
legal_abrir('privacidad', 'Cómo la campaña de Diana Lucía Montes recoge, usa y protege tus datos personales, y cómo ejercer tus derechos.');
?>
<h1>Política de privacidad y tratamiento de datos personales</h1>
<p class="vigencia">Vigente desde el <?= LEGAL_VIGENCIA ?> · Ley 1581 de 2012 y Decreto 1074 de 2015</p>

<div class="resumen">
  <b>En pocas palabras</b>
  <ul>
    <li>Pedimos tus datos solo para contarte de la campaña, invitarte a actividades y organizar el trabajo en tu barrio o vereda.</li>
    <li>No vendemos, no alquilamos y no regalamos tus datos a nadie.</li>
    <li>Darnos tus datos es voluntario. Puedes pedir que los corrijamos o los borremos cuando quieras.</li>
    <li>Para no recibir más mensajes, responde <b>SALIR</b> a cualquier WhatsApp de la campaña. Para borrar tus datos, escribe <b>ELIMINAR MIS DATOS</b> o <a href="/eliminar-datos/">llena este formulario</a>.</li>
  </ul>
</div>

<h2 id="responsable">1. Quién es el responsable de tus datos</h2>
<p><b><?= le($L['legal_responsable']) ?></b><?= $L['legal_documento'] !== '' ? ' · ' . le($L['legal_documento']) : '' ?>.<br>
Domicilio: <?= le($L['legal_direccion']) ?>.<br>
Contacto para temas de datos personales: <?= legal_contacto($L) ?>.</p>

<h2 id="alcance">2. A qué aplica esta política</h2>
<p>Aplica a todos los datos que la campaña recoge por estos medios:</p>
<ul>
  <li>El sitio web <b>dianamontes.com</b> y su formulario de registro.</li>
  <li>El panel del simpatizante (<b>dianamontes.com/mi</b>).</li>
  <li>La aplicación móvil <b>Diana Montes</b> para Android y iPhone.</li>
  <li>El número de WhatsApp de la campaña, que usa la plataforma de WhatsApp Business de Meta.</li>
  <li>El panel de administración que usa el equipo de la campaña.</li>
</ul>

<h2 id="datos">3. Qué datos recogemos</h2>
<div class="tabla">
<table>
  <tr><th>De quién</th><th>Qué datos</th></tr>
  <tr><td><b>Simpatizantes</b><br><span style="color:var(--gris)">(te registras tú o un líder te registra con tu permiso)</span></td>
      <td>
        <ul>
          <li>Datos personales: nombre, número de documento, celular y WhatsApp, fecha de nacimiento, género, barrio o vereda y profesión u oficio.</li>
          <li>Si los das: tu puesto y mesa de votación.</li>
          <li>Datos de tu participación: tu nivel de compromiso con la campaña, quién te invitó, a quién invitaste, tus puntos y las tareas o eventos en los que participas.</li>
          <li>Solo si lo autorizas: tu ubicación aproximada, redondeada a unos 100 metros.</li>
          <li>Acceso a tu panel: la clave, que guardamos cifrada y nadie puede leer.</li>
        </ul>
      </td></tr>
  <tr><td><b>Mensajes de WhatsApp</b></td>
      <td>Tu número, los mensajes que nos escribes y el estado de entrega de los mensajes que te enviamos (enviado, entregado, leído).</td></tr>
  <tr><td><b>App móvil</b></td>
      <td>
        <ul>
          <li>Tu celular, para enviarte el código de acceso por WhatsApp.</li>
          <li>Una llave de sesión que se guarda en tu teléfono, para que no tengas que ingresar cada vez.</li>
        </ul>
        La app <b>no</b> accede a tus contactos, fotos, micrófono, cámara ni ubicación del teléfono.
      </td></tr>
  <tr><td><b>Equipo de la campaña</b></td>
      <td>
        <ul>
          <li>Nombre, correo, celular, rol y zona a cargo.</li>
          <li>Si ingresa con su cuenta de Google, recibimos solo su nombre, correo y foto de perfil.</li>
        </ul>
      </td></tr>
  <tr><td><b>Todos</b></td>
      <td>
        <ul>
          <li>Dirección IP, fecha y hora de ingreso y registros de seguridad, que sirven para prevenir abusos.</li>
          <li>Una cookie de sesión, necesaria para mantenerte dentro del panel.</li>
        </ul>
        No usamos cookies de publicidad ni de seguimiento.
      </td></tr>
</table>
</div>

<h2 id="sensibles">4. Datos sensibles y menores de edad</h2>
<p>Registrarte en la red de una campaña puede revelar tu preferencia política. La ley considera este dato <b>sensible</b> (artículo 5 de la Ley 1581 de 2012). Por eso:</p>
<ul>
  <li>No estás obligado a darnos tus datos ni a responder preguntas sobre datos sensibles.</li>
  <li>Te pedimos una autorización explícita, aparte y por escrito (la casilla del formulario).</li>
  <li>Ningún servicio, beneficio ni trato depende de que te registres.</li>
</ul>
<p>La red es solo para personas de <b><?= EDAD_MINIMA ?> años o más</b>. No recogemos a sabiendas datos de menores de edad. Si encontramos uno, lo borramos.</p>

<h2 id="finalidades">5. Para qué usamos tus datos</h2>
<ol>
  <li>Contactarte e informarte sobre la candidatura, las propuestas y las actividades de la campaña.</li>
  <li>Enviarte por WhatsApp:
    <ul>
      <li>la bienvenida;</li>
      <li>tu enlace personal y tu acceso al panel;</li>
      <li>avisos de tareas y eventos;</li>
      <li>saludos en fechas especiales, como tu cumpleaños o el día de tu profesión.</li>
    </ul>
  </li>
  <li>Enviarte el código para ingresar a la app.</li>
  <li>Organizar el trabajo territorial: barrios y veredas, puestos de votación, reuniones y voluntariado.</li>
  <li>Llevar la red de invitaciones, los puntos y los niveles: Simpatizante, Promotor, Súper Promotor y Embajador.</li>
  <li>Verificar por llamada que los datos son correctos y evitar registros duplicados o falsos.</li>
  <li>Hacer estadísticas internas de la campaña, sin publicar datos de personas.</li>
  <li>Proteger la plataforma, atender tus solicitudes y cumplir la ley.</li>
</ol>
<p><b>No usamos tus datos para nada distinto</b> sin pedirte una nueva autorización.</p>

<h2 id="compartir">6. Quién puede ver tus datos</h2>
<ul>
  <li><b>El equipo de la campaña</b>, según su rol:
    <ul>
      <li>un líder solo ve a las personas de su red;</li>
      <li>el personal de digitación ve el documento y el celular parcialmente ocultos.</li>
    </ul>
  </li>
  <li><b>Tu red:</b>
    <ul>
      <li>quien te invitó ve tu nombre y tu barrio en su panel;</li>
      <li>si es Promotor o tiene un nivel más alto, también puede escribirte por WhatsApp para actividades de la campaña;</li>
      <li>tú ves igual a las personas que invites.</li>
    </ul>
  </li>
  <li><b>Proveedores que nos prestan servicios</b> (encargados del tratamiento). Solo pueden usar los datos para prestarnos el servicio:
    <ul>
      <li>el proveedor de alojamiento web del sitio;</li>
      <li><b>Meta Platforms</b> (WhatsApp Business Platform), para enviar y recibir los mensajes de WhatsApp;</li>
      <li><b>Google</b>, para el inicio de sesión del equipo.</li>
    </ul>
  </li>
  <li><b>Autoridades</b>, solo cuando una ley o una orden judicial lo exija.</li>
</ul>
<p>Meta y Google pueden tratar datos en servidores fuera de Colombia. Al aceptar esta política autorizas esa transferencia, que se hace con proveedores que ofrecen niveles adecuados de protección.</p>
<p><b>Nunca</b> vendemos, alquilamos ni compartimos tus datos con terceros para publicidad.</p>

<h2 id="whatsapp">7. WhatsApp y la app móvil</h2>
<ul>
  <li>Solo escribimos por WhatsApp a quien dio su autorización. Usamos plantillas aprobadas por Meta.</li>
  <li>Respuestas que puedes enviar a cualquier mensaje de la campaña:
    <ul>
      <li><b>SALIR</b>: no te enviamos más mensajes automáticos.</li>
      <li><b>VOLVER</b>: vuelves a recibirlos.</li>
      <li><b>ELIMINAR MIS DATOS</b>: inicias la solicitud para borrar tus datos.</li>
    </ul>
  </li>
  <li>El código de acceso a la app es de un solo uso, vence en 10 minutos y nunca te lo pediremos por otro medio.</li>
  <li>La sesión de la app queda guardada en tu teléfono hasta que la cierres. Si pierdes el teléfono, avísanos y la cerramos.</li>
</ul>

<h2 id="seguridad">8. Cómo protegemos tus datos</h2>
<ul>
  <li>Conexión cifrada (HTTPS).</li>
  <li>Claves y llaves de sesión guardadas cifradas, nunca en texto plano.</li>
  <li>Acceso por roles: cada persona del equipo ve solo lo que necesita.</li>
  <li>Bloqueo por intentos fallidos y límites en el envío de códigos.</li>
  <li>Registro de auditoría de las acciones sensibles.</li>
</ul>
<p>Ningún sistema es infalible. Si ocurre un incidente que afecte tus datos, lo informaremos a la Superintendencia de Industria y Comercio y a las personas afectadas, como manda la ley.</p>

<h2 id="tiempo">9. Cuánto tiempo guardamos tus datos</h2>
<p>Los guardamos mientras dure la campaña y hasta <b>seis (6) meses después de las elecciones territoriales de 2027</b>. Después los borramos o los dejamos anónimos, salvo que una ley nos obligue a conservarlos. Si pides que los borremos antes, lo hacemos (ver la sección 11).</p>

<h2 id="derechos">10. Tus derechos</h2>
<p>Como titular de los datos tienes derecho a:</p>
<ul>
  <li>Conocer, actualizar y corregir tus datos.</li>
  <li>Pedir prueba de la autorización que nos diste.</li>
  <li>Saber cómo hemos usado tus datos.</li>
  <li>Revocar tu autorización y pedir que borremos tus datos, cuando no exista un deber legal de conservarlos.</li>
  <li>Consultar tus datos gratis.</li>
  <li>Presentar quejas ante la <b>Superintendencia de Industria y Comercio</b> (<a href="https://www.sic.gov.co" rel="noopener">www.sic.gov.co</a>), después de haber hecho tu solicitud ante nosotros.</li>
</ul>

<h2 id="ejercer">11. Cómo ejercer tus derechos</h2>
<p>Escríbenos por <?= legal_contacto($L) ?>. Para proteger tus datos, verificamos que la solicitud venga del titular: comparamos tu documento y tu celular con los del registro.</p>
<ul>
  <li><b>Consultas</b> (saber qué datos tenemos): respondemos en máximo <b>10 días hábiles</b>.</li>
  <li><b>Reclamos</b> (corregir, borrar o revocar): respondemos en máximo <b>15 días hábiles</b>.</li>
</ul>
<p>Si no alcanzamos a responder en esos plazos, te avisamos el motivo; la ley permite extenderlos 5 y 8 días hábiles más, respectivamente.</p>
<p>Las instrucciones para borrar tus datos están en <a href="/eliminar-datos/">dianamontes.com/eliminar-datos</a>.</p>

<h2 id="cambios">12. Cambios a esta política</h2>
<p>Si cambiamos esta política, publicaremos la nueva versión en esta página con su fecha. Si el cambio afecta las finalidades, te avisaremos y te pediremos una nueva autorización.</p>
<?php legal_cerrar();
