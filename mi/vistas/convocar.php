<?php defined('PORTAL') or exit; ?>
<section class="card">
  <h2>📣 Convocar una reunión con tu red</h2>
  <p class="small muted" style="margin-bottom:14px">Tu red recibirá la invitación en su panel (y por WhatsApp si la campaña lo tiene activo).
    Tú sumas <b><?= TAREA_TIPOS['reunion'][2] ?> puntos</b> y cada asistente <b><?= RED_PUNTOS_ASISTENCIA ?></b> cuando el equipo valide la asistencia.</p>
  <form method="post" action="<?= e(portal_url('convocar')) ?>" data-validar>
    <?= portal_campo_csrf() ?><input type="hidden" name="accion" value="convocar">
    <div class="campo">
      <label for="cv-t">Nombre de la reunión</label>
      <input id="cv-t" name="titulo" data-tipo="texto" maxlength="150" placeholder="Ej: Café con vecinos del barrio Centro" required data-msg="Escribe el nombre de la reunión.">
    </div>
    <div class="campo">
      <label for="cv-f">Fecha y hora</label>
      <input id="cv-f" type="datetime-local" name="fecha" min="<?= date('Y-m-d\TH:i') ?>" required data-msg="Elige la fecha y la hora.">
    </div>
    <div class="campo">
      <label for="cv-l">Lugar</label>
      <input id="cv-l" name="lugar" data-tipo="texto" maxlength="200" placeholder="Ej: Mi casa, Cra 5 # 3-20" required data-msg="Escribe el lugar.">
    </div>
    <div class="campo">
      <label for="cv-d">Mensaje para tus invitados (opcional)</label>
      <textarea id="cv-d" name="descripcion" maxlength="1000" placeholder="Ej: Hablaremos de las propuestas de Diana para el barrio. ¡Trae a un amigo!"></textarea>
    </div>
    <div class="campo">
      <span>¿A quiénes invitas?</span>
      <label class="check-persona"><input type="radio" name="quienes" value="toda" checked> Toda mi red <span><?= $tamanoRed ?> personas</span></label>
      <label class="check-persona"><input type="radio" name="quienes" value="directos"> Solo mis invitados directos <span><?= $directos ?> personas</span></label>
    </div>
    <button class="btn btn-rosa btn-block" type="submit" <?= $tamanoRed ? '' : 'disabled' ?>>Enviar invitaciones</button>
    <?php if (!$tamanoRed): ?><p class="small muted" style="margin-top:8px">Aún no tienes personas en tu red para convocar.</p><?php endif; ?>
  </form>
</section>
