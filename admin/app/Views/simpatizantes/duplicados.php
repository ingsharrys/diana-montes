<section class="card">
  <h3>Registros duplicados</h3>
  <p class="muted small" style="line-height:1.6;margin-bottom:12px">
    Cada persona debe estar <b>una sola vez</b>: ni el documento ni el celular se pueden repetir.
    Aquí aparecen los registros que comparten uno de esos datos. Deja el correcto y elimina el sobrante;
    sus invitados pasan a quien lo invitó. Cuando no quede ninguno, pulsa <b>Actualizar plataforma</b> en Inicio
    para que la base de datos bloquee los duplicados para siempre.
  </p>
  <?php if (!$grupos): ?>
    <p class="alert alert-ok" style="font-weight:500">✔ No hay registros duplicados.</p>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="cola">
    <tr><th>Dato repetido</th><th>Nombre</th><th>Documento</th><th>Celular</th><th>Zona · líder</th><th style="text-align:right">Invitados</th><th>Registrado</th><th></th></tr>
    <?php foreach ($grupos as $clave => $filas): foreach ($filas as $i => $f): ?>
    <tr<?= $i === 0 ? ' style="border-top:2px solid var(--line)"' : '' ?>>
      <td class="small"><?= $i === 0 ? '<b>' . e($clave) . '</b>' : '' ?></td>
      <td><b><?= e($f['nombre']) ?></b></td>
      <td><?= e($f['documento']) ?></td>
      <td><?= e($f['telefono']) ?></td>
      <td class="small"><?= e($f['zona'] ?? '—') ?> · <?= e($f['lider'] ?? '—') ?></td>
      <td style="text-align:right"><?= (int)$f['invitados'] ?></td>
      <td class="small"><?= $f['created_at'] ? date('d/m/Y', strtotime($f['created_at'])) : '—' ?></td>
      <td>
        <form method="post" action="<?= url('simpatizantes/eliminar/' . (int)$f['id']) ?>"
              onsubmit="return confirm('¿Eliminar el registro de <?= e($f['nombre']) ?>? No se puede deshacer.')">
          <?= \Core\Csrf::campo() ?>
          <button class="btn btn-ghost btn-mini" type="submit">Eliminar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</section>
