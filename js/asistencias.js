/* Sustituye el antiguo initModalAsistencia: NO cargues ambos. */
function initModalAsistencia() {
  const modal = document.getElementById('modal-asistencia');
  const cerrar = document.getElementById('cerrar-modal-asistencia');
  const form = document.getElementById('form-asistencia');
  const campoId = document.getElementById('asistencia-evento-id');
  const tituloEvento = document.getElementById('asistencia-evento-nombre');
  const feedback = document.getElementById('asistencia-feedback');
  const exito = document.getElementById('asistencia-exito');
  const exitoTexto = document.getElementById('asistencia-exito-texto');
  const enviar = form?.querySelector('button[type="submit"]');

  if (!modal || !cerrar || !form || !campoId || !tituloEvento ||
      !feedback || !exito || !exitoTexto || !enviar) return;
  if (modal.dataset.asistenciaInicializada === '1') return;
  modal.dataset.asistenciaInicializada = '1';

  const textoBoton = enviar.innerHTML;

  // Delegación: también sirve para botones dentro del carrusel.
  document.addEventListener('click', function (event) {
    const boton = event.target.closest('.btn-asistir');
    if (!boton || !boton.dataset.eventoId) return;

    form.reset();
    form.hidden = false;
    exito.hidden = true;
    feedback.hidden = true;
    feedback.textContent = '';
    feedback.className = 'asistencia-feedback';
    campoId.value = boton.dataset.eventoId;
    tituloEvento.textContent = boton.dataset.eventoTitulo || 'Encuentro';

    if (!modal.open) modal.showModal();
    document.getElementById('asistencia-nombre')?.focus();
  });

  cerrar.addEventListener('click', () => modal.close());

  // Permite cerrar al pulsar fuera del cuadro, además de Escape.
  modal.addEventListener('click', function (event) {
    if (event.target !== modal) return;
    const r = modal.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right ||
        event.clientY < r.top || event.clientY > r.bottom) {
      modal.close();
    }
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (enviar.disabled) return;
    if (!form.reportValidity()) return;

    feedback.hidden = true;
    enviar.disabled = true;
    enviar.textContent = 'Enviando inscripción…';

    try {
      const respuesta = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });

      let datos;
      try {
        datos = await respuesta.json();
      } catch (_error) {
        throw new Error('El servidor respondió en un formato inesperado.');
      }
      if (!respuesta.ok || !datos.ok) {
        throw new Error(datos.message || 'No se pudo registrar tu asistencia.');
      }

      exitoTexto.textContent = datos.message;
      tituloEvento.textContent = datos.evento_titulo || tituloEvento.textContent;
      form.hidden = true;
      exito.hidden = false;
      // No se limpia el formulario antes de que PHP confirme el INSERT.
    } catch (error) {
      feedback.textContent = error.message || 'No fue posible enviar la inscripción.';
      feedback.className = 'asistencia-feedback is-error';
      feedback.hidden = false;
    } finally {
      enviar.disabled = false;
      enviar.innerHTML = textoBoton;
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initModalAsistencia);
} else {
  initModalAsistencia();
}
