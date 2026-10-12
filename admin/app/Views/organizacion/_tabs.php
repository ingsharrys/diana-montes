<?php
/** Pestañas del módulo Organización + aviso si faltan las tablas. Espera $pestana, $disponible, $verDinero. */
$tabsOrg = ['organizacion' => ['resumen', 'Resumen'], 'organizacion/colaboradores' => ['colaboradores', 'Colaboradores'],
            'organizacion/publicidad' => ['publicidad', 'Publicidad impresa'], 'organizacion/testigos' => ['testigos', 'Testigos electorales']];
if ($verDinero) $tabsOrg['organizacion/gastos'] = ['gastos', 'Gastos'];
?>
<nav class="cola-tabs wa-tabs" aria-label="Secciones de Organización">
  <?php foreach ($tabsOrg as $ruta => [$clave, $etiqueta]): ?>
    <a class="cola-tab <?= $pestana === $clave ? 'activo' : '' ?>" href="<?= url($ruta) ?>"><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</nav>
<?php if (!$disponible): ?>
  <div class="activar-red" style="margin-top:14px"><span class="ar-ico">🗂</span><span class="ar-txt"><b>Falta actualizar la plataforma</b>
    La dirección debe pulsar "Actualizar plataforma" en la Vista rápida para crear las tablas de colaboradores, publicidad, testigos y gastos.</span></div>
<?php endif; ?>
