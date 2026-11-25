
function initAgendaCarousel() {

    const carousel = document.getElementById("agenda-carousel");

    if (!carousel || carousel.dataset.initializado === "1") {
        return;
    }

    carousel.dataset.initializado = "1";

    const track = document.getElementById("agenda-carousel-track");
    const slides = Array.from(
        track.querySelectorAll(".agenda-carousel-slide")
    );

    const prev = document.getElementById("agenda-prev");
    const next = document.getElementById("agenda-next");
    const counter = document.getElementById("agenda-carousel-counter");
    const dotsContainer = document.getElementById("agenda-carousel-dots");

    if (!slides.length) return;

    let actual = 0;
    let animacionScroll = null;

    const reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );

    // Crear indicadores
    slides.forEach(function (slide, index) {

        const dot = document.createElement("button");

        dot.type = "button";
        dot.className = "agenda-carousel-dot";

        dot.setAttribute(
            "aria-label",
            "Ir al encuentro " + (index + 1)
        );

        dot.addEventListener("click", function () {
            irA(index);
        });

        dotsContainer.appendChild(dot);

    });

    const dots = Array.from(dotsContainer.children);

    // Posición horizontal de cada diapositiva
    function posicion(index) {
        return slides[index].offsetLeft - slides[0].offsetLeft;
    }

    // Actualizar controles
    function actualizar(index) {

        actual = Math.max(0, Math.min(index, slides.length - 1));

        counter.textContent =
            (actual + 1) + " de " + slides.length;

        prev.disabled = actual === 0;
        next.disabled = actual === slides.length - 1;

        dots.forEach(function (dot, i) {

            const activo = i === actual;

            dot.classList.toggle("is-active", activo);

            if (activo) {
                dot.setAttribute("aria-current", "true");
            } else {
                dot.removeAttribute("aria-current");
            }

        });
    }

    // Cambiar de encuentro
    function irA(index) {

        const destino = Math.max(
            0,
            Math.min(index, slides.length - 1)
        );

        actualizar(destino);

        track.scrollTo({
            left: posicion(destino),
            behavior: reduceMotion.matches ? "instant" : "smooth"
        });

    }

    // Detectar diapositiva visible
    function detectarActual() {

        let indice = 0;
        let distanciaMenor = Infinity;

        slides.forEach(function (slide, i) {

            const distancia = Math.abs(
                posicion(i) - track.scrollLeft
            );

            if (distancia < distanciaMenor) {
                distanciaMenor = distancia;
                indice = i;
            }

        });

        actualizar(indice);
    }

    // Flechas
    prev.addEventListener("click", function () {
        irA(actual - 1);
    });

    next.addEventListener("click", function () {
        irA(actual + 1);
    });

    // Deslizamiento táctil y trackpad
    track.addEventListener("scroll", function () {

        if (animacionScroll !== null) {
            cancelAnimationFrame(animacionScroll);
        }

        animacionScroll = requestAnimationFrame(function () {
            detectarActual();
            animacionScroll = null;
        });

    }, { passive: true });

    // Navegación con teclado cuando el track tiene foco
    track.addEventListener("keydown", function (event) {

        if (event.target !== track) return;

        if (event.key === "ArrowRight") {
            event.preventDefault();
            irA(actual + 1);
        }

        if (event.key === "ArrowLeft") {
            event.preventDefault();
            irA(actual - 1);
        }

    });

    // Mantener la diapositiva al cambiar tamaño de pantalla
    window.addEventListener("resize", function () {

        track.scrollTo({
            left: posicion(actual),
            behavior: "instant"
        });

    });

    // Estado inicial
    actualizar(0);
}

// Inicializar
if (document.readyState === "loading") {
    document.addEventListener(
        "DOMContentLoaded",
        initAgendaCarousel
    );
} else {
    initAgendaCarousel();
}
