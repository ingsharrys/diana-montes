<section class="card">
  <h3>Red de contactos
    <span class="tag tag-blue"><?= num($grafo['mostrados']) ?> personas</span>
    <span class="red-controles" style="margin-left:auto">
      <button type="button" class="btn btn-ghost btn-mini" id="rAcercar" aria-label="Acercar">＋</button>
      <button type="button" class="btn btn-ghost btn-mini" id="rAlejar" aria-label="Alejar">－</button>
      <button type="button" class="btn btn-ghost btn-mini" id="rCentrar">Centrar</button>
    </span>
  </h3>

  <?php if (count($grafo['nodos']) <= 1): ?>
    <p class="muted vacio">Aún no hay simpatizantes para dibujar la red. Comparte tu enlace de invitación desde Simpatizantes.</p>
  <?php else: ?>
    <div class="red-lienzo red-grande" id="redGrande"></div>

    <?php if ($grafo['leyenda']): ?>
    <ul class="red-ley" aria-label="Equipo y tamaño de su red">
      <li><span class="sw" style="background:#1C1630;border-radius:50%"></span>Candidata (raíz)</li>
      <?php foreach ($grafo['leyenda'] as $l): ?>
        <li><span class="sw" style="background:<?= $l['g'] >= 0 ? PALETA[$l['g']] : GRIS_SIN_GRUPO ?>;border-radius:50%"></span>
          <?= e($l['nombre']) ?> <span class="muted"><?= num($l['total']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p class="muted small nota">
      Cada punto es una persona y cada línea une a alguien con quien lo trajo. Los puntos más grandes son
      promotores que ya invitaron gente. Rueda del mouse para acercar, arrastra para moverte, pasa el cursor para ver el nombre.
      <?= $grafo['mostrados'] < $grafo['total'] ? 'Se muestran los ' . num($grafo['mostrados']) . ' registros más recientes de ' . num($grafo['total']) . '.' : '' ?>
      Los nombres van abreviados por privacidad.
    </p>

    <script src="<?= asset('../assets/vendor/d3-force.min.js') ?>"></script>
    <script src="<?= asset('assets/js/red.js') ?>"></script>
    <script>
    const red = pintarRed(document.getElementById('redGrande'),
      <?= json_encode(['nodos' => $grafo['nodos'], 'enlaces' => $grafo['enlaces']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
      { paleta: <?= json_encode(PALETA) ?>, interactivo: true, etiquetas: true });
    document.getElementById('rAcercar').onclick = red.acercar;
    document.getElementById('rAlejar').onclick = red.alejar;
    document.getElementById('rCentrar').onclick = red.centrar;
    </script>
  <?php endif; ?>
</section>
