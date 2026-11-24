(function () {
  "use strict";

  var DESKTOP_QUERY = window.matchMedia("(min-width: 1100px)");
  var REDUCED_MOTION = window.matchMedia("(prefers-reduced-motion: reduce)");

  /* ---------- Header: menú hamburguesa ---------- */
  function initMenu() {
    var header = document.querySelector(".site-header");
    var toggle = document.querySelector(".menu-toggle");
    var nav = document.getElementById("menu-principal");
    if (!toggle || !nav) return;

    function setOpen(open) {
      toggle.setAttribute("aria-expanded", String(open));
      nav.classList.toggle("is-open", open);
    }

    toggle.addEventListener("click", function () {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    nav.addEventListener("click", function (event) {
      if (event.target.closest("a") && !DESKTOP_QUERY.matches) setOpen(false);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
        setOpen(false);
        toggle.focus();
      }
    });

    DESKTOP_QUERY.addEventListener("change", function () { setOpen(false); });

    window.addEventListener("scroll", function () {
      header.classList.toggle("is-scrolled", window.scrollY > 8);
    }, { passive: true });
  }

  /* ---------- Navegación activa según la sección visible ---------- */
  function initActiveNav() {
    var links = Array.prototype.slice.call(document.querySelectorAll(".site-nav a"));
    var sections = links
      .map(function (link) { return document.querySelector(link.getAttribute("href")); })
      .filter(Boolean);
    if (!("IntersectionObserver" in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        links.forEach(function (link) {
          var active = link.getAttribute("href") === "#" + entry.target.id;
          if (active) link.setAttribute("aria-current", "true");
          else link.removeAttribute("aria-current");
        });
      });
    }, { rootMargin: "-45% 0px -50% 0px" });

    sections.forEach(function (section) { observer.observe(section); });
  }

  /* ---------- Filtro de videos por tema (solo interfaz) ---------- */
  function initFilters() {
    var chips = document.querySelectorAll(".chip[data-filter]");
    var cards = document.querySelectorAll(".video-card[data-theme]");
    var status = document.getElementById("filter-status");

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        var filter = chip.dataset.filter;
        chips.forEach(function (c) { c.setAttribute("aria-pressed", String(c === chip)); });

        var visible = 0;
        cards.forEach(function (card) {
          var show = filter === "todos" || card.dataset.theme === filter;
          card.hidden = !show;
          if (show) visible += 1;
        });
        status.textContent = visible + (visible === 1 ? " video visible" : " videos visibles");
      });
    });
  }

  /* ---------- Botón reproducir: aviso de contenido pendiente ---------- */
  
function initPlayButtons() {

    const modal = document.getElementById("video-modal");
    const modalTitle = document.getElementById("video-modal-title");
    const modalVideo = document.getElementById("video-modal-player");
    const modalEmbed = document.getElementById("video-modal-embed");
    const modalEmpty = document.getElementById("video-modal-empty");
    const modalOriginal = document.getElementById("video-modal-original");
    const closeButton = document.getElementById("video-modal-close");

    if (
        !modal ||
        !modalTitle ||
        !modalVideo ||
        !modalEmbed ||
        !modalEmpty ||
        !modalOriginal ||
        !closeButton
    ) {
        return;
    }

    // Validar URL de Facebook
    function validarFacebook(url) {
        try {
            const parsed = new URL(url);
            const host = parsed.hostname.toLowerCase();

            if (
                parsed.protocol === "https:" &&
                (
                    host === "facebook.com" ||
                    host.endsWith(".facebook.com")
                )
            ) {
                return parsed.href;
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    // Detener y limpiar reproductores
    function resetVideo() {

        modalVideo.pause();
        modalVideo.removeAttribute("src");
        modalVideo.load();

        // Eliminar el iframe detiene la reproducción
        modalEmbed.removeAttribute("src");

        modalVideo.hidden = true;
        modalEmbed.hidden = true;
        modalEmpty.hidden = true;
        modalOriginal.hidden = true;
        modalOriginal.removeAttribute("href");
    }

    // Mostrar enlace original
    function mostrarEnlace(url, plataforma) {
        modalOriginal.href = url;
        modalOriginal.innerHTML =
            '<i class="fas fa-arrow-up-right-from-square" ' +
            'aria-hidden="true"></i> Ver en ' + plataforma;

        modalOriginal.hidden = false;
    }

    // Configurar botones de reproducción
    document.querySelectorAll(".btn-play").forEach(function(button) {

        button.addEventListener("click", function() {

            const title = button.dataset.video || "Video";

            const videoSrc =
                (button.dataset.videoSrc || "").trim();

            const facebookUrl =
                (button.dataset.facebookUrl || "").trim();

            const youtubeId =
                (button.dataset.youtubeId || "").trim();

            const vimeoId =
                (button.dataset.vimeoId || "").trim();

            resetVideo();

            modalTitle.textContent = title;
            
            // Detectar orientación del video
            const formato = button.dataset.videoFormat || "horizontal";
            
            const esVertical = formato === "vertical";
            
            // Cambiar diseño del modal
            modal.classList.toggle("video-modal--vertical", esVertical);

            // FACEBOOK
            if (facebookUrl) {

                const urlValida = validarFacebook(facebookUrl);

                if (urlValida) {

                    const anchoFacebook = esVertical ? 350 : 800;
                    
                    modalEmbed.src =
                        "https://www.facebook.com/plugins/video.php" +
                        "?href=" + encodeURIComponent(urlValida) +
                        "&show_text=false&width=" + anchoFacebook;

                    modalEmbed.hidden = false;

                    mostrarEnlace(urlValida, "Facebook");

                } else {
                    modalEmpty.hidden = false;
                }

            }

            // YOUTUBE
            else if (/^[a-zA-Z0-9_-]{11}$/.test(youtubeId)) {

                modalEmbed.src =
                    "https://www.youtube-nocookie.com/embed/" +
                    youtubeId +
                    "?autoplay=1&rel=0";

                modalEmbed.hidden = false;

                mostrarEnlace(
                    "https://www.youtube.com/watch?v=" + youtubeId,
                    "YouTube"
                );

            }

            // VIMEO
            else if (/^\d+$/.test(vimeoId)) {

                modalEmbed.src =
                    "https://player.vimeo.com/video/" +
                    vimeoId +
                    "?autoplay=1";

                modalEmbed.hidden = false;

                mostrarEnlace(
                    "https://vimeo.com/" + vimeoId,
                    "Vimeo"
                );

            }

            // VIDEO MP4
            else if (videoSrc) {

                modalVideo.src = videoSrc;
                modalVideo.hidden = false;
                modalVideo.load();

            }

            // SIN VIDEO
            else {

                modalEmpty.hidden = false;

            }

            // Abrir modal
            if (!modal.open) {
                modal.showModal();
            }

            // Reproducir MP4 automáticamente
            if (!modalVideo.hidden) {

                modalVideo.play().catch(function() {
                    // El usuario puede iniciar con los controles.
                });

            }

        });

    });

    // Cerrar con botón
    closeButton.addEventListener("click", function() {
        modal.close();
    });

    // Cerrar al hacer clic fuera
    modal.addEventListener("click", function(event) {

        if (event.target !== modal) return;

        const rect = modal.getBoundingClientRect();

        const outside =
            event.clientX < rect.left ||
            event.clientX > rect.right ||
            event.clientY < rect.top ||
            event.clientY > rect.bottom;

        if (outside) {
            modal.close();
        }

    });

    // Cerrar con Escape y detener videos
    modal.addEventListener("close", resetVideo);

}

  /* ---------- Entrada escalonada discreta ---------- */
  function initReveal() {
    if (REDUCED_MOTION.matches || !("IntersectionObserver" in window)) return;
    var targets = document.querySelectorAll(".hero-copy > *, .hero-photo, .section-head, .chapter, .video-card, .topic, .conv-card, .story-form, .join-intro > *, .step, .join-card");

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        el.classList.add("is-visible");
        observer.unobserve(el);
        setTimeout(function () { el.style.transitionDelay = ""; }, 1200);
      });
    }, { threshold: 0.12 });

    targets.forEach(function (el) {
      var siblings = Array.prototype.indexOf.call(el.parentElement.children, el);
      el.style.transitionDelay = Math.min(siblings, 5) * 70 + "ms";
      el.classList.add("reveal");
      observer.observe(el);
    });
  }

  initMenu();
  initActiveNav();
  initFilters();
  initPlayButtons();
  initReveal();
})();

(function () {
  var wrap = document.getElementById("login-float");
  var toggle = document.getElementById("login-toggle");
  var menu = document.getElementById("login-menu");
  if (!wrap || !toggle || !menu) return;

  function setOpen(open) {
    toggle.setAttribute("aria-expanded", String(open));
    menu.hidden = !open;
    if (open) menu.querySelector(".login-option").focus();
  }

  toggle.addEventListener("click", function () {
    setOpen(toggle.getAttribute("aria-expanded") !== "true");
  });
  document.addEventListener("click", function (event) {
    if (!wrap.contains(event.target)) setOpen(false);
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !menu.hidden) {
      setOpen(false);
      toggle.focus();
    }
  });
  menu.addEventListener("keydown", function (event) {
    if (event.key !== "ArrowDown" && event.key !== "ArrowUp") return;
    event.preventDefault();
    var items = Array.prototype.slice.call(menu.querySelectorAll(".login-option"));
    var i = items.indexOf(document.activeElement);
    var next = event.key === "ArrowDown" ? (i + 1) % items.length : (i - 1 + items.length) % items.length;
    items[next].focus();
  });
})();

$(document).ready(function () {

    // Barrio o vereda
    $('#f-zona').select2({
        placeholder: 'Escribe tu barrio o vereda...',
        allowClear: true,
        width: '100%',
        language: 'es'
    });
    
    $('#f-barrio').select2({
        placeholder: 'Escribe tu barrio o vereda...',
        allowClear: true,
        width: '100%',
        language: 'es'
    });

    // Ocupación
    $('#j-prof').select2({
        placeholder: 'Busca tu ocupación...',
        allowClear: true,
        width: '100%',
        language: 'es'
    });

});

document.addEventListener("DOMContentLoaded", function() {
    initModalAsistencia();
});


function initModalAsistencia() {

    const modal = document.getElementById("modal-asistencia");
    const cerrar = document.getElementById("cerrar-modal-asistencia");
    const formulario = document.getElementById("form-asistencia");

    const eventoNombre = document.getElementById("asistencia-evento-nombre");
    const eventoId = document.getElementById("asistencia-evento-id");

    if (!modal || !cerrar || !formulario || !eventoNombre || !eventoId) {
        return;
    }

    // Abrir desde cualquier evento
    document.querySelectorAll(".btn-asistir").forEach(function(boton) {

        boton.addEventListener("click", function() {

            const titulo = boton.dataset.eventoTitulo || "Encuentro";
            const id = boton.dataset.eventoId || "";

            // Limpiar datos anteriores
            formulario.reset();

            // Asignar evento seleccionado
            eventoNombre.textContent = titulo;
            eventoId.value = id;

            // Abrir modal
            if (!modal.open) {
                modal.showModal();
            }

            // Enfocar primer campo
            document.getElementById("asistencia-nombre").focus();

        });

    });

    // Cerrar con botón
    cerrar.addEventListener("click", function() {
        modal.close();
    });

    // Cerrar al hacer clic fuera
    modal.addEventListener("click", function(e) {

        if (e.target !== modal) return;

        const rect = modal.getBoundingClientRect();

        const fuera =
            e.clientX < rect.left ||
            e.clientX > rect.right ||
            e.clientY < rect.top ||
            e.clientY > rect.bottom;

        if (fuera) {
            modal.close();
        }

    });

    // La tecla ESC funciona automáticamente con dialog

}

