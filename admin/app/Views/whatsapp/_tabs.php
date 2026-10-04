<?php
/** Pestañas del módulo WhatsApp + avisos de estado. Espera $pestana, $disponible, $configurado, $esDireccion. */
$tabsWa = ['whatsapp' => ['resumen', 'Resumen'], 'whatsapp/ocasiones' => ['ocasiones', 'Ocasiones'], 'whatsapp/plantillas' => ['plantillas', 'Plantillas']];
if ($esDireccion) $tabsWa['whatsapp/configuracion'] = ['configuracion', 'Configuración'];
?>
<nav class="cola-tabs wa-tabs" aria-label="Secciones de WhatsApp">
  <?php foreach ($tabsWa as $ruta => [$clave, $etiqueta]): ?>
    <a class="cola-tab <?= $pestana === $clave ? 'activo' : '' ?>" href="<?= url($ruta) ?>"><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$disponible): ?>
  <div class="activar-red" style="margin-top:14px"><span class="ar-ico">💬</span><span class="ar-txt"><b>WhatsApp aún no está activo</b>
    La dirección debe pulsar "Actualizar plataforma" en la Vista rápida para crear la cola de mensajes, las ocasiones y las plantillas.</span></div>
<?php elseif (!$configurado && $pestana !== 'configuracion'): ?>
  <div class="activar-red" style="margin-top:14px"><span class="ar-ico">🔑</span><span class="ar-txt"><b>Faltan las credenciales de WhatsApp</b>
    Los mensajes se encolan pero no salen hasta configurar la API de WhatsApp<?= $esDireccion ? ' (pestaña Configuración)' : ' (lo hace la dirección)' ?>.</span></div>
<?php endif; ?>
