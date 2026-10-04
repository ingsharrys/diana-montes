<?php
/** Centro de mando › Vista rápida */
$pct = $meta > 0 ? $total * 100 / $meta : null;
$pendienteTxt = $soloLider ? 'Disponible cuando la dirección actualice la plataforma.' : 'Disponible al actualizar la plataforma (aviso de arriba).';
?>

<?php if ($pendientes): ?>
<div class="activar-red">
  <span class="ar-ico">🚀</span>
  <span class="ar-txt">
    <b>Actualiza la plataforma para completar el centro de mando</b>
    Falta: <?= e(implode(', ', $pendientes)) ?>. Se hace una sola vez, con un clic, y no borra ningún dato.
  </span>
  <form method="post" action="<?= url('dashboard/actualizar') ?>">
    <?= \Core\Csrf::campo() ?>
    <button class="btn btn-gold" type="submit">Actualizar plataforma</button>
  </form>
</div>
<?php endif; ?>

<!-- ============ Fila 1: meta · niveles · promotores ============ -->
<div class="grid mando-1">
  <section class="card">
    <h3><?= $soloLider ? 'Mi meta' : 'Progreso hacia la meta' ?></h3>
    <?php if ($pct !== null): ?>
      <?= grafica_medidor($pct) ?>
      <p class="meta-cifra"><b><?= num($total) ?></b> <span>/ <?= num($meta) ?></span></p>
      <p class="meta-txt"><?= porc($pct, $pct < 10 ? 2 : 1) ?> de la meta de inscripción de simpatizantes</p>
    <?php else: ?>
      <p class="hero-num"><?= num($total) ?></p>
      <p class="meta-txt"><?= $soloLider ? 'simpatizantes en tu red. La dirección aún no te asignó una meta.' : 'simpatizantes inscritos. Aún no hay meta de inscripción.' ?></p>
    <?php endif; ?>

    <?php if ($avance && !$soloLider): ?>
      <p class="meta-extra">Tu red: <b><?= num($avance['vinculados']) ?></b><?= $avance['meta'] ? ' de ' . num($avance['meta']) : '' ?> · puesto #<?= $avance['puesto'] ?> de <?= $avance['equipo'] ?></p>
    <?php elseif ($soloLider && $avance): ?>
      <p class="meta-extra">Puesto <b>#<?= $avance['puesto'] ?></b> de <?= $avance['equipo'] ?> en el equipo</p>
    <?php endif; ?>

    <?php if ($puedeEditarMeta): ?>
    <details class="editar-meta" <?= $meta ? '' : 'open' ?>>
      <summary><?= $meta ? 'Editar meta' : 'Definir la meta de la campaña' ?></summary>
      <form method="post" action="<?= url('dashboard/meta') ?>">
        <?= \Core\Csrf::campo() ?>
        <input name="meta" inputmode="numeric" placeholder="Ej: 45000" value="<?= $meta ?: '' ?>" aria-label="Meta de simpatizantes" required>
        <button class="btn btn-primary btn-mini" type="submit">Guardar</button>
      </form>
    </details>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Conteo por nivel en la red <span class="tag tag-blue">Total <?= num($total) ?></span></h3>
    <?php if ($porNivel === null): ?>
      <p class="muted vacio"><?= $pendienteTxt ?></p>
    <?php else:
      $filas = [];
      foreach ($porNivel as $n => $c) {
          $nombre = $n === 5 ? 'Nivel 5+' : "Nivel $n";
          $quien  = $n === 1 ? 'entraron directo' : 'invitados por alguien de nivel ' . ($n - 1) . ($n === 5 ? ' o más' : '');
          $filas[] = [$nombre, $c, RAMPA_NIVEL[$n - 1], "$nombre ($quien): " . num($c) . ($total ? ' · ' . porc($c * 100 / $total) : '')];
      } ?>
      <?= grafica_barras($filas) ?>
      <p class="muted small nota">Nivel 1: entraron directo (por la candidata o un líder). Nivel 2: invitados por alguien de nivel 1, y así sucesivamente.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Promotores</h3>
    <?php if ($promotores === null): ?>
      <p class="muted vacio"><?= $pendienteTxt ?></p>
    <?php else: ?>
      <div class="stat">
        <div><span class="stat-lbl">Promotores</span><b class="stat-num"><?= num($promotores['promotores']) ?></b></div>
        <span class="stat-ico verde"><?= icono('trofeo') ?></span>
      </div>
      <div class="stat violeta">
        <div><span class="stat-lbl">Súper promotores</span><b class="stat-num"><?= num($promotores['super']) ?></b></div>
        <span class="stat-ico"><?= icono('megafono') ?></span>
      </div>
      <p class="muted small nota"><?= num($promotores['activos']) ?> ya invitaron al menos a una persona.
        Promotor: <?= PROMOTOR_NIVELES[1][0] ?>+ invitados · Súper: <?= PROMOTOR_NIVELES[2][0] ?>+.</p>
    <?php endif; ?>
  </section>
</div>

<!-- ============ Base de votos: compromiso y calidad ============ -->
<div class="grid g2 mando-3">
  <section class="card">
    <h3>Embudo de compromiso <a class="h3-link" href="<?= url('simpatizantes/verificar') ?>">Verificar →</a></h3>
    <?php
      $filas = [];
      foreach ($compromiso as $valor => $c) {
          $etq = compromiso_etiqueta($valor);
          $filas[] = [$etq, $c, RAMPA_NIVEL[compromiso_indice($valor)], "$etq: " . num($c) . ($total ? ' · ' . porc($c * 100 / $total) : '')];
      } ?>
    <div class="embudo"><?= grafica_barras($filas) ?></div>
    <p class="muted small nota">El equipo sube a cada persona en la escala al llamarla y confirmar su intención de voto.</p>
  </section>

  <section class="card">
    <h3>Calidad de la base</h3>
    <?php $pctPuesto = $calidad['total'] ? $calidad['con_puesto'] * 100 / $calidad['total'] : 0; ?>
    <div class="stat violeta">
      <div><span class="stat-lbl">Votos seguros<?= $metaVotos ? ' (meta ' . num($metaVotos) . ')' : '' ?></span>
        <b class="stat-num"><?= num($calidad['seguros']) ?></b>
        <?php if ($metaVotos): ?><div class="progreso mini" style="width:180px"><i style="width:<?= min(100, round($calidad['seguros'] * 100 / $metaVotos, 1)) ?>%"></i></div><?php endif; ?></div>
      <span class="stat-ico"><?= icono('verificar') ?></span>
    </div>
    <div class="calidad-fila">
      <span>Con puesto y mesa</span>
      <div class="progreso mini"><i style="width:<?= round($pctPuesto, 1) ?>%"></i></div>
      <b><?= porc($pctPuesto, 0) ?></b>
    </div>
    <?php if ($calidad['verificados'] !== null): $pctVer = $calidad['total'] ? $calidad['verificados'] * 100 / $calidad['total'] : 0; ?>
    <div class="calidad-fila">
      <span>Verificados por llamada</span>
      <div class="progreso mini"><i style="width:<?= round($pctVer, 1) ?>%"></i></div>
      <b><?= porc($pctVer, 0) ?></b>
    </div>
    <?php endif; ?>
    <p class="muted small nota">Sin puesto y mesa no se puede movilizar el día de la elección. <a href="<?= url('territorio') ?>">Ver por puesto →</a></p>
  </section>
</div>

<!-- ============ Fila 2: red de contactos · ubicación ============ -->
<div class="grid mando-2">
  <section class="card">
    <h3>Red de contactos <a class="h3-link" href="<?= url('red') ?>">Ver completa →</a></h3>
    <?php if (count($grafo['nodos']) <= 1): ?>
      <p class="muted vacio">Aún no hay simpatizantes para dibujar la red.</p>
    <?php else: ?>
      <div class="red-lienzo" id="redMini"></div>
      <p class="muted small nota">Cada punto es una persona; las líneas muestran quién la trajo.
        <?= $grafo['mostrados'] < $grafo['total'] ? 'Se muestran los ' . num($grafo['mostrados']) . ' registros más recientes de ' . num($grafo['total']) . '.' : '' ?></p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Ubicación de miembros <a class="h3-link" href="<?= url('mapa') ?>">Ver mapa →</a></h3>
    <?php if (!$redActiva): ?>
      <p class="muted vacio"><?= $pendienteTxt ?></p>
    <?php else: ?>
      <div class="mapa-mini" id="mapaMini"></div>
      <p class="muted small nota"><?= num(count($puntos)) ?> simpatizantes compartieron su ubicación aproximada.</p>
    <?php endif; ?>
  </section>
</div>

<!-- ============ Fila 3: género · edad · últimos 7 días ============ -->
<div class="grid g3 mando-3">
  <section class="card">
    <h3>Género</h3>
    <?php if ($genero === null): ?>
      <p class="muted vacio"><?= $pendienteTxt ?></p>
    <?php else:
      $partes = [];
      foreach (GENEROS as $clave => $etiqueta) $partes[] = [$etiqueta, $genero[$clave], COLOR_GENERO[$clave]];
      $partes[] = ['Sin dato (registros anteriores)', $genero['sin_dato'], COLOR_GENERO['sin_dato']]; ?>
      <?= grafica_proporcion($partes, 'simpatizantes') ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Distribución por edad</h3>
    <?php
      $bandas = ['18-24' => '18–24', '25-34' => '25–34', '35-44' => '35–44', '45-54' => '45–54', '55-64' => '55–64', '65+' => '65+'];
      $cols = [];
      foreach ($bandas as $clave => $etiqueta) $cols[] = [$etiqueta, '', $edad[$clave], true];
      $sinEdad = $edad['sin_dato'] + $edad['menor']; ?>
    <?= grafica_columnas($cols, '#7C3AED', '#7C3AED', 'simpatizantes (años)') ?>
    <?php if ($sinEdad): ?><p class="muted small nota"><?= num($sinEdad) ?> registros anteriores sin fecha de nacimiento válida.</p><?php endif; ?>
  </section>

  <section class="card">
    <?php $semanaTotal = array_sum(array_column($semana, 'total')); ?>
    <h3>Registros en los últimos 7 días <span class="tag tag-gold">+<?= num($semanaTotal) ?></span></h3>
    <?php
      $cols = array_map(fn($d) => [$d['etiqueta'], $d['fecha'], $d['total'], $d['hoy']], $semana); ?>
    <?= grafica_columnas($cols, '#7C3AED', '#C4B2FF', 'registros') ?>
    <p class="muted small nota">La columna más oscura es hoy.</p>
  </section>
</div>

<!-- ============ Fila 4: rankings, zonas y auditoría ============ -->
<div class="grid g2 mando-4">
  <?php if ($redActiva): ?>
  <section class="card">
    <h3>🏆 Top Súper Promotores <span class="tag tag-grey">ciudadanos que más invitan</span></h3>
    <?php if (!$topPromotores): ?>
      <p class="muted">Todavía nadie ha invitado a alguien con su enlace personal. Anima a los simpatizantes a abrir su panel y compartir su QR.</p>
    <?php else: ?>
    <table>
      <tr><th>#</th><th>Promotor</th><th>Zona</th><th style="text-align:right">Invitados</th></tr>
      <?php foreach ($topPromotores as $i => $p): $nv = promotor_nivel((int)$p['invitados']); ?>
      <tr>
        <td class="muted"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></td>
        <td><b><?= e($p['nombre']) ?></b><br><span class="muted small"><?= $nv['actual'][2] . ' ' . e($nv['actual'][1]) ?> · red de <?= e($p['lider']) ?></span></td>
        <td><?= e($p['zona'] ?? '—') ?></td>
        <td style="text-align:right"><b><?= (int)$p['invitados'] ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($rankingEquipo): ?>
  <section class="card">
    <h3>👥 Ranking del equipo <span class="tag tag-grey">simpatizantes vinculados vs. meta</span></h3>
    <table>
      <tr><th>Miembro</th><th>Avance</th><th style="text-align:right">Red</th></tr>
      <?php foreach ($rankingEquipo as $m): $pctM = $m['meta'] ? min(100, (int)round($m['vinculados'] * 100 / $m['meta'])) : null; ?>
      <tr>
        <td><b><?= e($m['nombre']) ?></b> <span class="muted small"><?= e($m['rol']) ?></span></td>
        <td style="min-width:120px">
          <?php if ($pctM !== null): ?>
            <div class="progreso mini"><i style="width:<?= $pctM ?>%"></i></div>
            <span class="muted small"><?= $pctM ?>% de <?= (int)$m['meta'] ?></span>
          <?php else: ?><span class="muted small">sin meta</span><?php endif; ?>
        </td>
        <td style="text-align:right"><b><?= (int)$m['vinculados'] ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
  <?php endif; ?>

  <section class="card">
    <h3>Registros por zona</h3>
    <table>
      <tr><th>Zona</th><th>Tipo</th><th style="text-align:right">Total</th></tr>
      <?php foreach ($porZona as $z): ?>
      <tr>
        <td><?= e($z['nombre']) ?></td>
        <td><span class="tag <?= $z['tipo'] === 'rural' ? 'tag-gold' : 'tag-blue' ?>"><?= e($z['tipo']) ?></span></td>
        <td style="text-align:right"><b><?= num((int)$z['total']) ?></b></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>

  <?php if ($auditoria): ?>
  <section class="card">
    <h3>Auditoría reciente <span class="tag tag-blue">solo dirección</span></h3>
    <table>
      <tr><th>Fecha</th><th>Usuario</th><th>Acción</th></tr>
      <?php foreach ($auditoria as $a): ?>
      <tr>
        <td class="muted"><?= fecha_co($a['created_at']) ?></td>
        <td><?= e($a['usuario'] ?? 'Sistema') ?></td>
        <td><?= e($a['accion']) ?> <span class="muted"><?= e($a['detalle']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
  <?php endif; ?>
</div>

<?php if (count($grafo['nodos']) > 1): ?>
<script src="<?= asset('../assets/vendor/d3-force.min.js') ?>"></script>
<script src="<?= asset('assets/js/red.js') ?>"></script>
<script>
pintarRed(document.getElementById('redMini'),
  <?= json_encode(['nodos' => $grafo['nodos'], 'enlaces' => $grafo['enlaces']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  { paleta: <?= json_encode(PALETA) ?>, interactivo: false, etiquetas: true });
</script>
<?php endif; ?>

<?php if ($redActiva): ?>
<link rel="stylesheet" href="<?= asset('../assets/vendor/leaflet/leaflet.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.css') ?>">
<link rel="stylesheet" href="<?= asset('../assets/vendor/markercluster/MarkerCluster.Default.css') ?>">
<script src="<?= asset('../assets/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('../assets/vendor/markercluster/leaflet.markercluster.js') ?>"></script>
<script src="<?= asset('assets/js/mapa.js') ?>"></script>
<script>
pintarMapa('mapaMini', <?= json_encode(array_map(fn($p) => [
    'n' => promotor_nombre_corto($p['nombre']), 'lat' => (float)$p['lat'], 'lng' => (float)$p['lng'],
    'nv' => compromiso_etiqueta($p['nivel']), 'ci' => compromiso_indice($p['nivel']), 'z' => $p['zona'], 'l' => $p['lider'], 'inv' => (int)$p['invitados'],
], $puntos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= mapa_opciones('claro', false) ?>);
</script>
<?php endif; ?>
