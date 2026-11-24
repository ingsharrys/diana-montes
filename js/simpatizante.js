/*
 * Sustituye la función initJoinForm() anterior por esta versión.
 * Funciona con el HTML nuevo: #join-form, name="celular", name="prof",
 * name="fecha", name="datos", etc.
 */
function initJoinForm() {
  const form = document.getElementById('join-form');
  if (!form || form.dataset.joinInicializado === '1') return;
  form.dataset.joinInicializado = '1';

  const el = (nombre) => form.elements.namedItem(nombre);
  const estado = document.getElementById('join-status');
  const ubicacionEstado = document.getElementById('j-ubicacion-status');
  const boton = form.querySelector('.join-submit');
  const cambiarClave = form.querySelector('.pass-toggle');
  if (!estado || !ubicacionEstado || !boton) return;

  // Modal de confirmación (no registra nada; solo se abre tras el 201 de PHP).
  const modalExito = document.getElementById('join-success-modal');
  const modalNombre = document.getElementById('join-success-name');
  const modalUsuario = document.getElementById('join-success-user');
  const modalCerrar = document.getElementById('join-success-close');

  if (modalExito && modalCerrar && modalNombre && modalUsuario) {
    modalCerrar.addEventListener('click', () => modalExito.close());

    modalExito.addEventListener('click', (event) => {
      if (event.target !== modalExito) return;
      const r = modalExito.getBoundingClientRect();
      const fuera = event.clientX < r.left || event.clientX > r.right ||
                    event.clientY < r.top || event.clientY > r.bottom;
      if (fuera) modalExito.close();
    });

    // No conservar el número de documento en el DOM después de cerrar el modal.
    modalExito.addEventListener('close', () => {
      modalNombre.textContent = '';
      modalUsuario.textContent = '';
    });
  }

  function mostrarRegistroExitoso(nombre, documento) {
    if (modalExito && modalNombre && modalUsuario && typeof modalExito.showModal === 'function') {
      // textContent impide interpretar los datos enviados como HTML.
      modalNombre.textContent = nombre;
      modalUsuario.textContent = documento;
      if (!modalExito.open) modalExito.showModal();
    } else {
      estado.textContent = 'Registro guardado correctamente. Puedes iniciar sesión en https://dianamontes.com/mi/';
    }
  }

  let coordenadas = null;
  let ubicacionPendiente = false;
  let solicitudUbicacion = 0;
  let enviando = false;

  function soloDigitos(input) {
    input.value = input.value.replace(/\D/g, '');
  }
  el('documento').addEventListener('input', () => soloDigitos(el('documento')));
  el('celular').addEventListener('input', () => soloDigitos(el('celular')));

  // Edad mínima: 18 años. Se calcula como fecha local, no con UTC.
  const hoy = new Date();
  const limite = new Date(hoy.getFullYear() - 18, hoy.getMonth(), hoy.getDate());
  const dos = (n) => String(n).padStart(2, '0');
  el('fecha').max = `${limite.getFullYear()}-${dos(limite.getMonth() + 1)}-${dos(limite.getDate())}`;

  if (cambiarClave) {
    cambiarClave.addEventListener('click', () => {
      const visible = el('clave').type === 'password';
      el('clave').type = visible ? 'text' : 'password';
      cambiarClave.setAttribute('aria-pressed', String(visible));
      cambiarClave.setAttribute('aria-label', visible ? 'Ocultar clave' : 'Mostrar clave');
    });
  }

  el('ubicacion').addEventListener('change', () => {
    const solicitud = ++solicitudUbicacion;
    coordenadas = null;
    ubicacionPendiente = false;

    if (!el('ubicacion').checked) {
      ubicacionEstado.textContent = '';
      return;
    }
    if (!('geolocation' in navigator)) {
      el('ubicacion').checked = false;
      ubicacionEstado.textContent = 'Tu navegador no permite compartir ubicación.';
      return;
    }
    ubicacionPendiente = true;
    ubicacionEstado.textContent = 'Solicitando permiso de ubicación…';

    navigator.geolocation.getCurrentPosition((pos) => {
      if (solicitud !== solicitudUbicacion || !el('ubicacion').checked) return;
      ubicacionPendiente = false;
      coordenadas = {
        lat: Math.round(pos.coords.latitude * 1000) / 1000,
        lng: Math.round(pos.coords.longitude * 1000) / 1000
      };
      ubicacionEstado.textContent = 'Ubicación aproximada lista.';
    }, () => {
      if (solicitud !== solicitudUbicacion) return;
      ubicacionPendiente = false;
      el('ubicacion').checked = false;
      ubicacionEstado.textContent = 'No se pudo obtener tu ubicación. Puedes continuar sin ella.';
    }, { enableHighAccuracy: false, timeout: 10000 });
  });

  const noVacio = (input) => input.value.trim() !== '';
  const reglas = [
    { nombre: 'nombre', error: 'je-nombre', valido: (input) => input.value.trim().split(/\s+/).length >= 2 && input.value.trim().length <= 120 },
    { nombre: 'documento', error: 'je-documento', valido: (input) => /^\d{6,10}$/.test(input.value) },
    { nombre: 'celular', error: 'je-celular', valido: (input) => /^3\d{9}$/.test(input.value) },
    { nombre: 'zona_id', error: 'je-zona', valido: noVacio },
    { nombre: 'prof', error: 'je-prof', valido: noVacio },
    { nombre: 'fecha', error: 'je-fecha', valido: (input) => input.value !== '' && input.value <= input.max },
    { nombre: 'genero', error: 'je-genero', valido: noVacio },
    { nombre: 'clave', error: 'je-clave', valido: (input) => {
      const bytes = new TextEncoder().encode(input.value).length;
      return bytes >= 12 && bytes <= 72;
    } },
    { nombre: 'datos', error: 'je-datos', valido: (input) => input.checked }
  ];

  function mostrarErrorCampo(nombre, mensaje) {
    const regla = reglas.find((r) => r.nombre === nombre);
    if (!regla) return false;
    const input = el(nombre);
    const error = document.getElementById(regla.error);
    input.setAttribute('aria-invalid', 'true');
    if (error) {
      if (mensaje) error.textContent = mensaje;
      error.hidden = false;
    }
    if (input.classList.contains('select2-custom') && window.jQuery && jQuery.fn.select2) {
      jQuery(input).select2('open');
    } else {
      input.focus();
    }
    return true;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (enviando) return;
    estado.textContent = '';

    let primerError = null;
    reglas.forEach((regla) => {
      const input = el(regla.nombre);
      const valido = regla.valido(input);
      input.setAttribute('aria-invalid', String(!valido));
      const error = document.getElementById(regla.error);
      if (error) error.hidden = valido;
      if (!valido && primerError === null) primerError = regla.nombre;
    });
    if (primerError !== null) {
      mostrarErrorCampo(primerError, null);
      return;
    }
    if (el('ubicacion').checked && (ubicacionPendiente || !coordenadas)) {
      ubicacionEstado.textContent = 'Espera la ubicación o desmarca esa opción para continuar.';
      return;
    }

    const datos = new FormData(form);
    if (el('ubicacion').checked && coordenadas) {
      datos.set('lat', String(coordenadas.lat));
      datos.set('lng', String(coordenadas.lng));
    }

    enviando = true;
    boton.disabled = true;
    const textoBoton = boton.textContent;
    boton.textContent = 'Guardando…';
    const controlador = new AbortController();
    const timeout = setTimeout(() => controlador.abort(), 25000);

    try {
      const respuesta = await fetch(form.action, {
        method: 'POST',
        body: datos,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controlador.signal
      });
      const crudo = await respuesta.text();
      let resultado;
      try {
        resultado = JSON.parse(crudo);
      } catch (_) {
        throw new Error('El servidor no devolvió JSON válido. Revisa el log de PHP.');
      }
      if (!respuesta.ok || !resultado.ok) {
        const mensaje = resultado.message || resultado.msg || 'No se pudo guardar el registro.';
        if (!(resultado.campo && mostrarErrorCampo(resultado.campo, mensaje))) {
          estado.textContent = mensaje;
        }
        return;
      }

      // El honeypot puede devolver HTTP 200 sin INSERT. Exigimos una señal
      // explícita que solo se envía tras guardar el registro en MySQL.
      if (resultado.registro_confirmado !== true) {
        estado.textContent = 'No pudimos confirmar que el registro se haya guardado. Consulta con soporte antes de volver a intentarlo.';
        return;
      }

      // Guardar los datos *del formulario ya validado* antes de resetearlo.
      // No hace falta duplicar nombre o documento en la respuesta del servidor.
      const nombreConfirmado = String(datos.get('nombre') || '').trim();
      const documentoConfirmado = String(datos.get('documento') || '').trim();

      form.reset();
      solicitudUbicacion++;
      coordenadas = null;
      ubicacionPendiente = false;
      ubicacionEstado.textContent = '';
      el('clave').type = 'password';
      if (cambiarClave) {
        cambiarClave.setAttribute('aria-pressed', 'false');
        cambiarClave.setAttribute('aria-label', 'Mostrar clave');
      }
      reglas.forEach((regla) => {
        el(regla.nombre).removeAttribute('aria-invalid');
        const error = document.getElementById(regla.error);
        if (error) error.hidden = true;
      });
      if (window.jQuery && jQuery.fn.select2) {
        jQuery(form).find('.select2-custom').val('').trigger('change');
      }
      mostrarRegistroExitoso(nombreConfirmado, documentoConfirmado);
    } catch (error) {
      estado.textContent = error.name === 'AbortError'
        ? 'El servidor tardó demasiado. Consulta si se guardó el registro antes de reintentar.'
        : (error.message || 'No se pudo conectar con el servidor.');
    } finally {
      clearTimeout(timeout);
      boton.disabled = false;
      boton.textContent = textoBoton;
      enviando = false;
    }
  });
}

// Funciona tanto si el script lleva defer como si se incluye al final de body.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initJoinForm);
} else {
  initJoinForm();
}
