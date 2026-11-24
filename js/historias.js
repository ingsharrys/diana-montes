(function () {
  "use strict";

  function initStoryForm() {
    const form = document.getElementById("story-form");
    if (!form) return;

    const estado = document.getElementById("form-status");
    const boton = form.querySelector('button[type="submit"]');
    const nombre = form.elements.namedItem("nombre");
    const mensaje = form.elements.namedItem("mensaje");
    const datos = form.elements.namedItem("datos");

    if (!estado || !boton || !nombre || !mensaje || !datos) return;

    function errorCampo(campo, idError, valido) {
      const aviso = document.getElementById(idError);
      if (aviso) aviso.hidden = valido;
      campo.setAttribute("aria-invalid", String(!valido));
      return valido;
    }

    function estadoFormulario(texto, tipo) {
      estado.textContent = texto;
      estado.classList.remove("is-success", "is-error");
      if (tipo) estado.classList.add(tipo);
    }

    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (boton.disabled) return;

      const nombreOk = errorCampo(
        nombre, "e-nombre",
        nombre.value.trim().length >= 3 && nombre.value.trim().length <= 120
      );
      const mensajeOk = errorCampo(
        mensaje, "e-mensaje",
        mensaje.value.trim().length >= 10 && mensaje.value.trim().length <= 5000
      );
      const datosOk = errorCampo(datos, "e-datos", datos.checked);

      if (!nombreOk || !mensajeOk || !datosOk) {
        estadoFormulario("Revisa los campos señalados antes de continuar.", "is-error");
        ( !nombreOk ? nombre : !mensajeOk ? mensaje : datos ).focus();
        return;
      }

      const textoOriginal = boton.innerHTML;
      boton.disabled = true;
      boton.textContent = "Enviando historia...";
      estadoFormulario("", "");

      try {
        const respuesta = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          credentials: "same-origin",
          headers: { Accept: "application/json" }
        });

        let resultado;
        try {
          resultado = await respuesta.json();
        } catch (_) {
          throw new Error("El servidor no devolvió una respuesta válida.");
        }

        if (!respuesta.ok || !resultado.ok) {
          throw new Error(resultado.message || "No fue posible enviar tu historia.");
        }

        form.reset();
        [nombre, mensaje, datos].forEach(function (campo) {
          campo.removeAttribute("aria-invalid");
        });
        ["e-nombre", "e-mensaje", "e-datos", "e-barrio"].forEach(function (id) {
          const aviso = document.getElementById(id);
          if (aviso) aviso.hidden = true;
        });

        // Actualizar el control visible de Select2 tras resetear el <select> nativo.
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
          window.jQuery(form).find(".select2-custom").val(null).trigger("change");
        }

        estadoFormulario(
          resultado.message || "¡Gracias! Hemos recibido tu historia.",
          "is-success"
        );
      } catch (error) {
        estadoFormulario(
          error.message || "No fue posible enviar tu historia.",
          "is-error"
        );
      } finally {
        boton.disabled = false;
        boton.innerHTML = textoOriginal;
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStoryForm, { once: true });
  } else {
    initStoryForm();
  }
})();
