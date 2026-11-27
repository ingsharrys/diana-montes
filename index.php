<?php
/**
 * index.php — Landing oficial dianamontes.com
 * Conectada a la base de datos de la Plataforma Electoral:
 * los selects de zona/profesión se cargan del catálogo real y el
 * formulario guarda vía registrar.php (fetch, sin recargar la página).
 */
require __DIR__ . '/config/config.php';
require __DIR__ . '/config/inc/promotores.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Catálogos desde la BD (con respaldo por si la BD no responde) */
try {
    $zonas = zonas_listar(db());   // agrupadas por zona urbana y corregimiento
    $profesiones = db()->query("SELECT id, nombre FROM profesiones ORDER BY (nombre = 'Otra'), nombre")->fetchAll();
    $pideGenero = esquema_tiene(db(), 'simpatizantes', 'genero');
    $pideClave  = esquema_tiene(db(), 'simpatizantes', 'clave_hash'); // acceso al panel /mi/
} catch (Throwable $e) {
    $zonas = []; $profesiones = []; $pideGenero = false; $pideClave = false;
}
$fechaMaxima = date('Y-m-d', strtotime('-' . EDAD_MINIMA . ' years'));

/* Fase de la página: 'expectativa' presenta a Diana sin hablar aún de propuestas;
   'campana' muestra además Retos y Propuestas. Se cambia en config.php con
   define('LANDING_FASE', 'campana'); */
$expectativa = (defined('LANDING_FASE') ? LANDING_FASE : 'expectativa') !== 'campana';
$fechaMinima = date('Y-m-d', strtotime('-110 years'));

/* Invitación (?ref=) de un líder o de un promotor ciudadano:
   se resuelve en el servidor para pintar el chip "Te invita…" */
$refCodigo = '';
$refLider  = '';
if (!empty($_GET['ref']) && preg_match('/^[a-zA-Z0-9\-_]{2,30}$/', $_GET['ref'])) {
    $refCodigo = $_GET['ref'];
    try {
        $refLider = promotor_resolver_ref(db(), $refCodigo)['nombre'] ?? '';
    } catch (Throwable $e) { /* silencioso */ }
}

// Agenda de próximos encuentros públicos (3) y arrays de meses/días para mostrar fechas
$consulta = db()->prepare(
    "SELECT id, titulo, categoria, descripcion, fecha_inicio, fecha_fin,
            lugar, direccion, mapa_url
       FROM eventos
      WHERE estado = 'publicado' AND fecha_fin >= :ahora
      ORDER BY fecha_inicio ASC, id ASC
      LIMIT 3"
);
$consulta->execute(['ahora' => (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s')]);
$agenda = $consulta->fetchAll(PDO::FETCH_ASSOC);
$meses = [1=>'ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
$dias = [0=>'DOM',1=>'LUN',2=>'MAR',3=>'MIÉ',4=>'JUE',5=>'VIE',6=>'SÁB'];
$mesesLargos = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$diasLargos = [0=>'domingo',1=>'lunes',2=>'martes',3=>'miércoles',4=>'jueves',5=>'viernes',6=>'sábado'];
$zona = new DateTimeZone('America/Bogota');
$horaEs = static function (DateTimeImmutable $fecha): string {
    return $fecha->format('g:i') . ($fecha->format('A') === 'AM' ? ' a. m.' : ' p. m.');
};

// Videos públicos de Diana Montes, con tema, título, plataforma y fuente

$temasVideoPublico = [
    'cercania' => 'Cercanía humana',
    'pertenencia' => 'Pertenencia',
    'responsabilidad' => 'Responsabilidad',
    'perseverancia' => 'Perseverancia',
    'autonomia' => 'Autonomía',
];

$videosPublicos = db()->query(
    "SELECT id, titulo, tema, plataforma, fuente, formato, poster
       FROM videos_editoriales
      WHERE estado = 'publicado'
      ORDER BY orden ASC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);


?>

<?php
include 'include/header.php';
?>

<?php
include 'include/navbar.php';
?>

  <main id="contenido">
    <!-- 2. HERO -->
    <section class="hero" id="inicio" aria-labelledby="hero-title">
      <div class="container hero-grid">
        <figure class="photo-slot hero-photo">
          <div class="photo-slot-inner" role="img" aria-label="Espacio reservado para una fotografía original de Diana Montes">
            <img src="./assets/img/img_diana_1.jpeg" alt="Diana Montes">
          </div>
        </figure>

        <div class="hero-copy">
          <p class="eyebrow">DIANA MONTES · GARZÓN, HUILA</p>
          <h1 id="hero-title" class="hero-title"><span class="accent" aria-hidden="true">¿</span><span class="sr-only">¿</span>Por qué hago<br class="br-desk" /> las cosas?</h1>
          <p class="hero-sub">Porque lo que he vivido, lo que he aprendido y las personas que me rodean le dan sentido a cada paso. Quiero contarte mi historia y escuchar la tuya</p>
          <div class="btn-row">
            <a class="btn btn-primary" href="#por-que">
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M8 5.5v13l10.5-6.5z"/></svg>
              Mira mis videos
            </a>
            <a class="btn btn-ghost" href="#conozca">Conoce mi historia</a>
          </div>
          <p class="hero-place">Garzón, Huila · Colombia</p>
        </div>
      </div>
    </section>

    <!-- 3. BANDA DE PARTICIPACIÓN -->
    <section class="band" aria-labelledby="band-title">
      <div class="container band-inner">
        <h2 id="band-title" class="band-title">¿Y tú, por qué haces lo que haces?</h2>
        <a class="btn btn-primary" href="#usted">Comparte tu historia</a>
      </div>
    </section>

    <!-- 4. CONOZCA A DIANA -->
    <section class="section" id="conozca" aria-labelledby="conozca-title">
      <div class="container">
        <header class="section-head">
          <p class="eyebrow">Conoce a Diana</p>
          <h2 id="conozca-title" class="section-title">Lo que soy está en lo que hago</h2>
          <p class="section-lead light">Mi historia está hecha de familia, campo, estudio y experiencias que han marcado mi manera de pensar y actuar. Te invito a conocer cuatro capítulos de mi vida.</p>
        </header>

        <div class="about-grid">
          <figure class="photo-slot about-photo">
            <div class="photo-slot-inner" role="img" aria-label="Espacio reservado para una fotografía original de Diana Montes en su entorno">
                <video id="heroVideo" autoplay muted loop playsinline controls preload="metadata" width="100%"
                     poster="./assets/videos/poster/foto-portada.jpg"
                     aria-label="Video de presentación de Diana Montes">
                <source src="./assets/videos/lo-que-hago.mp4" type="video/mp4">
              </video>
            </div>
          </figure>

          <ol class="chapters">
            <li class="chapter">
              <h3>Familia</h3>
              <p class="pending">La familia es el lugar donde aprendí a escuchar, a cuidar y a cumplir mi palabra. Soy madre de dos hijos y comparto mi vida con un hombre dedicado al campo y al emprendimiento. En nuestro hogar he aprendido que los compromisos se construyen con trabajo, respeto y constancia. Son valores que me acompañan en cada etapa de mi vida.</p>
            </li>
            <li class="chapter">
              <h3>Raíces campesinas</h3>
              <p class="pending">El campo es parte de nuestra vida familiar. Conocemos el esfuerzo que hay detrás de cada cosecha y el valor de quienes trabajan la tierra. Durante años he compartido experiencias con familias rurales y he aprendido que, para comprender sus realidades, lo primero es escuchar. Allí encuentro una parte importante de lo que soy y de mi relación con Garzón.</p>
            </li>
            <li class="chapter">
              <h3>Formación</h3>
              <p class="pending">Soy abogada de la Universidad Surcolombiana, especialista en Derecho Público de la Universidad Externado de Colombia y magíster en Derecho Público de la Universidad Carlos III de Madrid. Mi formación y mi trayectoria en entidades públicas y privadas me han enseñado que el conocimiento cobra sentido cuando se aplica con responsabilidad y compromiso.</p>
            </li>
            <li class="chapter">
              <h3>Experiencia en el hospital</h3>
              <p class="pending">Mi experiencia en la Subdirección Administrativa y Financiera del Hospital Departamental San Vicente de Paúl de Garzón me permitió conocer de cerca la gestión de una institución fundamental para nuestra región. Allí comprendí aún más la importancia de organizar los recursos, trabajar en equipo y recordar que detrás de los procesos administrativos hay personas que necesitan respuestas.</p>
            </li>
          </ol>
        </div>
      </div>
    </section>

    <!-- 5. ¿POR QUÉ HAGO LAS COSAS? -->
    <section class="section section-tint" id="por-que" aria-labelledby="porque-title">
      <div class="container">
        <header class="section-head section-head-split">
          <div>
            <p class="eyebrow">HISTORIAS Y REFLEXIONES</p>
            <h2 id="porque-title" class="section-title">¿Por qué hago las cosas?</h2>
            <p class="section-lead light">Hay momentos, personas y experiencias que nos enseñan lecciones para toda la vida. En esta serie comparto historias y reflexiones que ayudan a comprender mis convicciones, mis aprendizajes y las razones que orientan mis decisiones.</p>
          </div>
        </header>

        <div class="filters" role="group" aria-label="Filtrar videos por tema">
          <button type="button" class="chip" aria-pressed="true" data-filter="todos">Todos</button>
          <button type="button" class="chip" aria-pressed="false" data-filter="cercania">Cercanía humana</button>
          <button type="button" class="chip" aria-pressed="false" data-filter="pertenencia">Pertenencia</button>
          <button type="button" class="chip" aria-pressed="false" data-filter="responsabilidad">Responsabilidad</button>
          <button type="button" class="chip" aria-pressed="false" data-filter="perseverancia">Perseverancia</button>
          <button type="button" class="chip" aria-pressed="false" data-filter="autonomia">Autonomía</button>
        </div>
        <p class="sr-only" aria-live="polite" id="filter-status"></p>

        <?php if (!$videosPublicos): ?>
          <p class="form-proto" role="status">Próximamente compartiremos nuevas historias y reflexiones.</p>
        <?php else: ?>
          <ul class="video-grid">
            <?php foreach ($videosPublicos as $indice => $video):
                $tema = (string) $video['tema'];
                $titulo = (string) $video['titulo'];
                $plataforma = (string) $video['plataforma'];
                $fuente = (string) $video['fuente'];
                $formato = $video['formato'] === 'vertical' ? 'vertical' : 'horizontal';
                $poster = (string) $video['poster'];
                if (!preg_match('~\A\./assets/videos/poster/[a-zA-Z0-9_.-]+\z~', $poster)) {
                    $poster = './assets/videos/poster/foto-pendiente.jpg';
                }
            ?>
              <li class="video-card" data-theme="<?= e($tema) ?>">
                <div class="video-cover">
                  <img src="<?= e($poster) ?>" alt="Portada del video: <?= e($titulo) ?>" loading="lazy">
                  <span class="video-num" aria-hidden="true"><?= e(str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                </div>
                <div class="video-body">
                  <p class="video-theme"><?= e($temasVideoPublico[$tema] ?? $tema) ?></p>
                  <h3 class="video-title"><?= e($titulo) ?></h3>
                  <button type="button" class="btn-play"
                          data-video="<?= e($titulo) ?>"
                          data-video-format="<?= e($formato) ?>"
                          <?php if ($plataforma === 'mp4'): ?>data-video-src="<?= e($fuente) ?>"<?php endif; ?>
                          <?php if ($plataforma === 'facebook'): ?>data-facebook-url="<?= e($fuente) ?>"<?php endif; ?>
                          <?php if ($plataforma === 'youtube'): ?>data-youtube-id="<?= e($fuente) ?>"<?php endif; ?>
                          <?php if ($plataforma === 'vimeo'): ?>data-vimeo-id="<?= e($fuente) ?>"<?php endif; ?>>
                    <i class="fas fa-play" aria-hidden="true"></i>
                    Reproducir <span class="sr-only">: <?= e($titulo) ?></span>
                  </button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p class="toast" id="video-toast" role="status" aria-live="polite" hidden></p>
      </div>
    </section>
    
    <!-- MODAL DE VIDEOS -->
    <dialog id="video-modal" class="video-modal" aria-labelledby="video-modal-title">
        <div class="video-modal__header">
            <div class="video-modal__heading"><span class="video-modal__label">DIANA MONTES · HISTORIAS</span>
                <h2 id="video-modal-title">Reproduciendo video</h2>
            </div>
            <button type="button" class="video-modal__close" id="video-modal-close" aria-label="Cerrar video">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        
        <div class="video-modal__content">
            <!-- Reproductor MP4 -->
            <video id="video-modal-player" controls playsinline preload="metadata" hidden></video>
            <!-- Facebook, YouTube y Vimeo -->
            <iframe id="video-modal-embed" title="Reproductor de video" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen hidden></iframe>
            <!-- Video no disponible -->
            <p id="video-modal-empty" class="video-modal__empty" hidden><i class="fas fa-video" aria-hidden="true"></i> Este video aún no está disponible.</p>
            <!-- Enlace alternativo -->
            <a id="video-modal-original" class="video-modal__external" href="#" target="_blank" rel="noopener noreferrer" hidden><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Ver video en la plataforma</a>
        
        </div>

    </dialog>

    <!-- 6. ¿Y USTED POR QUÉ LAS HACE? -->
    <section class="section" id="usted" aria-labelledby="usted-title">
      <div class="container story-grid">
        <div class="story-intro">
          <p class="eyebrow">TU HISTORIA</p>
          <h2 id="usted-title" class="section-title">¿Y tú, por qué haces lo que haces?</h2>
          <p class="section-lead">Todos tenemos algo que nos mueve cada día. Puede ser nuestra familia, un sueño, nuestro trabajo o el deseo de ayudar a los demás. Queremos conocer las historias de las personas que hacen parte de Garzón y descubrir qué hay detrás de lo que hacen.</p>
          <aside class="disclaimer" aria-label="Aviso importante">
            <h3>Aviso de participación:</h3>
            <p>Compartir tu historia es voluntario. No implica respaldo electoral ni autoriza el envío de mensajes políticos. Tú decides si permites que publiquemos tu relato.</p>
          </aside>
        </div>

        <form class="story-form" id="story-form" action="./api/guardar_historia.php" method="POST" novalidate>
          <p class="form-proto" role="note">Tu decides si publicamos tu relato</p>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="jfield">
            <label for="f-nombre">Nombre Completo</label>
            <input id="f-nombre" name="nombre" type="text" autocomplete="name" required aria-describedby="e-nombre" minlength="10" maxlength="120"/>
            <p class="field-error" id="e-nombre" hidden>Escriba su nombre.</p>
          </div>
        
        <!-- Barrio o vereda -->
        <div class="jfield">
            <label for="f-barrio">Barrio o vereda (Opcional)</label>
            <select id="f-barrio" name="barrio_id" class="select2-custom" aria-describedby="e-barrio">
                <option value=""></option>
                <?= zonas_opciones($zonas) ?>
            </select>
            <p class="field-error" id="e-barrio" hidden>Indica tu barrio o vereda.</p>
        </div>

          <div class="field">
            <label for="f-mensaje">Tu historia</label>
            <textarea id="f-mensaje" name="mensaje" rows="5" required aria-describedby="h-mensaje e-mensaje"></textarea>
            <p class="field-hint" id="h-mensaje">¿Qué haces cada día y qué te inspira a seguir adelante? Queremos conocer tu experiencia con tus propias palabras.</p>
            <p class="field-error" id="e-mensaje" hidden>Escriba su historia.</p>
          </div>

          <div class="check">
            <input id="f-datos" name="datos" type="checkbox" value="1" required aria-describedby="e-datos" />
            <label for="f-datos">Acepto el tratamiento de mis datos personales según la <a href="https://dianamontes.com/privacidad/">política de privacidad</a>.</label>
          </div>
          <p class="field-error check-error" id="e-datos" hidden>Debe aceptar el tratamiento de datos para continuar.</p>

          <div class="check">
            <input id="f-publicar" name="publicar" value="1" type="checkbox" />
            <label for="f-publicar">Autorizo voluntariamente la publicación de mi historia y mi nombre en este sitio web. <span class="opt">(opcional)</span></label>
          </div>

          <!-- Honeypot -->
          <div class="antibot-trampa" aria-hidden="true">
              <label for="hp-historia">Sitio web</label>
              <input type="text" id="hp-historia" name="sitio_web" autocomplete="off" tabindex="-1">
          </div>

          <button class="btn btn-primary btn-block" type="submit">Compartir mi historia</button>
          <p class="form-status" id="form-status" role="status" aria-live="polite"></p>
        </form>
      </div>
      <!-- HISTORIAS CIUDADANAS APROBADAS -->
      <?php require __DIR__ . '/include/historias_publicadas.php'; ?>
    </section>

    <!-- 7. HABLEMOS DE GARZÓN -->
    <section class="section section-tint" id="garzon" aria-labelledby="garzon-title">
      <div class="container">
        
        <header class="section-head section-head-split">
          <div>
            <p class="eyebrow">Hablemos de Garzón</p>
            <h2 id="garzon-title" class="section-title">Temas de conversación</h2>
          </div>
          <p class="section-lead light">Garzón tiene realidades, desafíos y oportunidades que merecen ser escuchados. Estos son algunos de los temas que aparecen en las conversaciones sobre nuestro municipio. Queremos conocer distintas experiencias, inquietudes e ideas.</p>
        </header>

        <ul class="topics">
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Vías y movilidad</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Seguridad y convivencia</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Salud y hospital</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Obras y proyectos</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Campo y producción</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Empleo y emprendimiento</h3></li>
          <li class="topic"><span class="topic-tag">Tema para conversar</span><h3>Turismo y tradiciones</h3></li>
          <li class="topic topic-cta">
            <h3>¿Hay otro tema del que deberíamos hablar?</h3>
            <a href="#usted" class="link-arrow">Cuéntenos <span aria-hidden="true">→</span></a>
          </li>
        </ul>
      </div>
    </section>

    <!-- 8. CONVERSEMOS -->
    <section class="section" id="conversemos" aria-labelledby="conv-title">
      <div class="container">
        <header class="section-head section-head-split">
          <div>
            <p class="eyebrow">CONVERSEMOS</p>
            <h2 id="garzon-title" class="section-title">Encontrémonos para hablar</h2>
          </div>
          <p class="section-lead light">Las buenas conversaciones comienzan cuando nos damos el tiempo de escucharnos. Conoce nuestros canales de comunicación y los espacios donde podemos compartir historias, preguntas y experiencias sobre Garzón.</p>
        </header>
        
        <div class="conv-grid">
        
          <!-- CANALES OFICIALES -->
          <article class="conv-card conv-card--contacto">
        
            <div class="conv-card-header">
              <div class="conv-card-icon">
                <i class="fas fa-comments" aria-hidden="true"></i>
              </div>
              <div>
                <span class="conv-eyebrow">MANTENGAMOS EL CONTACTO</span>
                <h3>Canales oficiales</h3>
              </div>
            </div>
        
            <p class="conv-description">
              Estos son nuestros canales oficiales para consultar información sobre Diana Montes, conocer actividades y comunicarte con el equipo.
            </p>
        
            <div class="conv-contact-list">
        
              <!-- Sitio web -->
              <a class="conv-contact-item"
                 href="https://dianamontes.com/"
                 target="_blank"
                 rel="noopener noreferrer">
        
                <span class="conv-contact-icon">
                  <i class="fas fa-globe" aria-hidden="true"></i>
                </span>
        
                <span class="conv-contact-info">
                  <small>Sitio web</small>
                  <strong>dianamontes.com</strong>
                </span>
        
                <i class="fas fa-arrow-up-right-from-square conv-arrow"
                   aria-hidden="true"></i>
              </a>
        
              <!-- WhatsApp -->
              <a class="conv-contact-item"
                 href="https://api.whatsapp.com/send/?phone=573224848387"
                 target="_blank"
                 rel="noopener noreferrer">
        
                <span class="conv-contact-icon">
                  <i class="fab fa-whatsapp" aria-hidden="true"></i>
                </span>
        
                <span class="conv-contact-info">
                  <small>WhatsApp</small>
                  <strong>(+57) 322 484 83 87</strong>
                  <span class="conv-pending-label">@DianaMontes</span>
                </span>
        
                <i class="fas fa-arrow-up-right-from-square conv-arrow"
                   aria-hidden="true"></i>
              </a>
        
              <!-- Correo -->
              <a class="conv-contact-item"
                 href="mailto:candidata@dianamontes.com">
        
                <span class="conv-contact-icon">
                  <i class="fas fa-envelope" aria-hidden="true"></i>
                </span>
        
                <span class="conv-contact-info">
                  <small>Correo electrónico</small>
                  <strong>candidata@dianamontes.com</strong>
                </span>
        
                <i class="fas fa-arrow-up-right-from-square conv-arrow"
                   aria-hidden="true"></i>
              </a>
        
            </div>
        
            <!-- REDES SOCIALES -->
            <div class="conv-social-box">
              <div class="conv-social-heading">
                <strong>Síguenos en:</strong>
                <span>Redes Sociales</span>
              </div>
        
              <div class="conv-social-links">
                <a href="https://web.facebook.com/DianaLMontes" target="_blank" rel="noopener noreferrer">
                    <span class="conv-social-btn">
                      <i class="fab fa-facebook-f" aria-hidden="true"></i>
                      Facebook
                    </span>
                </a>
                <a href="https://www.instagram.com/dianamontescabrera" target="_blank" rel="noopener noreferrer">
                    <span class="conv-social-btn">
                      <i class="fab fa-instagram" aria-hidden="true"></i>
                      Instagram
                    </span>
                </a>
              </div>
            </div>
        
          </article>
        
        
          <!-- AGENDA DE ENCUENTROS -->
          <article class="conv-card conv-card--agenda">
            <div class="conv-card-header">
              <div class="conv-card-icon"><i class="fas fa-calendar-days" aria-hidden="true"></i></div>
              <div>
                <span class="conv-eyebrow">Próximos encuentros</span>
                <h3>Agenda de encuentros</h3>
              </div>
            </div>
            <p class="conv-description">Consulta los espacios programados para conversar, compartir experiencias y escuchar diferentes puntos de vista sobre nuestro municipio.</p>

            <?php if (!$agenda): ?>
              <div class="conv-event-empty">
                <i class="fas fa-calendar-xmark" aria-hidden="true"></i>
                <h4>Próximos encuentros por confirmar</h4>
                <p>Pronto compartiremos nuevas fechas y lugares de conversación.</p>
              </div>
            <?php else: ?>

              <div class="agenda-carousel" id="agenda-carousel"
                role="region"
                aria-roledescription="carrusel"
                aria-label="Próximos encuentros">

                <!-- NAVEGACIÓN -->
                <div class="agenda-carousel-nav">

                    <span class="agenda-carousel-counter"
                          id="agenda-carousel-counter"
                          aria-live="polite">
                        1 de <?= count($agenda) ?>
                    </span>

                    <div class="agenda-carousel-arrows">

                        <button type="button"
                                class="agenda-carousel-arrow"
                                id="agenda-prev"
                                aria-label="Encuentro anterior"
                                aria-controls="agenda-carousel-track">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </button>

                        <button type="button"
                                class="agenda-carousel-arrow"
                                id="agenda-next"
                                aria-label="Encuentro siguiente"
                                aria-controls="agenda-carousel-track">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>

                    </div>
                </div>

                <!-- CONTENEDOR DESLIZABLE -->
                <div class="agenda-carousel-track"
                    id="agenda-carousel-track"
                    tabindex="0"
                    aria-label="Desliza para ver los encuentros">
              <?php foreach ($agenda as $indice => $ev):
                  $inicio = new DateTimeImmutable($ev['fecha_inicio'], $zona);
                  $fin = new DateTimeImmutable($ev['fecha_fin'], $zona);
                  $diaSemana = (int) $inicio->format('w');
                  $mes = (int) $inicio->format('n');
                  $fechaCompleta = $diasLargos[$diaSemana] . ', ' . $inicio->format('j') .
                      ' de ' . $mesesLargos[$mes] . ' de ' . $inicio->format('Y');
                  $horario = $horaEs($inicio) . ' – ' . $horaEs($fin);
                  if ($inicio->format('Y-m-d') !== $fin->format('Y-m-d')) {
                      $horario = $inicio->format('d/m/Y') . ' ' . $horaEs($inicio) . ' – ' .
                          $fin->format('d/m/Y') . ' ' . $horaEs($fin);
                  }
              ?>
                <div class="conv-event agenda-carousel-slide" role="group" aria-roledescription="diapositiva" aria-label="Encuentro <?= $indice + 1 ?> de <?= count($agenda) ?>">
                  <div class="conv-event-main">
                    <div class="conv-event-date" aria-label="<?= e($fechaCompleta) ?>">
                      <span class="conv-event-month"><?= e($meses[$mes]) ?></span>
                      <strong><?= e($inicio->format('d')) ?></strong>
                      <span class="conv-event-day"><?= e($dias[$diaSemana]) ?></span>
                    </div>
                    <div class="conv-event-content">
                      <span class="conv-event-category"><?= e(mb_strtoupper($ev['categoria'], 'UTF-8')) ?></span>
                      <h4><?= e($ev['titulo']) ?></h4>
                      <p><?= nl2br(e($ev['descripcion'])) ?></p>
                    </div>
                  </div>
                  <div class="conv-event-meta">
                    <div class="conv-event-meta-item">
                      <i class="fas fa-calendar" aria-hidden="true"></i>
                      <span><time datetime="<?= e($inicio->format('Y-m-d')) ?>"><?= e(ucfirst($fechaCompleta)) ?></time></span>
                    </div>
                    <div class="conv-event-meta-item">
                      <i class="fas fa-clock" aria-hidden="true"></i><span><?= e($horario) ?></span>
                    </div>
                    <div class="conv-event-meta-item">
                      <i class="fas fa-location-dot" aria-hidden="true"></i>
                      <span><?= e($ev['lugar']) ?><?= $ev['direccion'] ? ', ' . e($ev['direccion']) : '' ?>, Garzón, Huila</span>
                      <?php if ($ev['mapa_url']): ?>
                        <a class="conv-ver-mapa" href="<?= e($ev['mapa_url']) ?>" target="_blank" rel="noopener noreferrer">Ver mapa <i class="fas fa-street-view" aria-hidden="true"></i></a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="conv-event-actions">
                    <button type="button" class="conv-event-btn btn-asistir"
                            data-evento-id="<?= (int) $ev['id'] ?>"
                            data-evento-titulo="<?= e($ev['titulo']) ?>"
                            aria-haspopup="dialog" aria-controls="modal-asistencia">
                      Asistir <i class="fas fa-calendar-check" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
                  </div> <!-- agenda-carousel-track -->

                <!-- INDICADORES -->
                <div class="agenda-carousel-dots"
                    id="agenda-carousel-dots"
                    aria-label="Seleccionar encuentro">
                </div>

            </div> <!-- agenda-carousel -->

              <p class="conv-event-disclaimer">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Inscríbete para registrar tu interés en asistir. La confirmación dependerá de la organización del evento.
              </p>
            <?php endif; ?>
          </article>

        
        </div>

      </div>
    </section>
    
    
    <!-- ==========================================
         MODAL DE ASISTENCIA A ENCUENTROS
    =========================================== -->
    
    <dialog id="modal-asistencia" class="asistencia-modal"
        aria-labelledby="asistencia-titulo"
        aria-describedby="asistencia-descripcion">
  <div class="asistencia-modal-header">
    <div class="asistencia-modal-icon">
      <i class="fas fa-calendar-check" aria-hidden="true"></i>
    </div>
    <button type="button" class="asistencia-modal-close"
            id="cerrar-modal-asistencia" aria-label="Cerrar formulario">
      <i class="fas fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <div class="asistencia-modal-heading">
    <span class="asistencia-modal-eyebrow">AGENDA DE ENCUENTROS</span>
    <h2 id="asistencia-titulo">Registra tu asistencia</h2>
    <p id="asistencia-descripcion">Completa tus datos para registrar tu interés en asistir al encuentro.</p>
  </div>

  <div class="asistencia-evento-info">
    <span>ENCUENTRO SELECCIONADO</span>
    <strong id="asistencia-evento-nombre"></strong>
  </div>

  <form id="form-asistencia" action="./api/registrar_asistencia.php" method="POST">
    <input type="hidden" name="evento_id" id="asistencia-evento-id">
    <input type="hidden" name="csrf_token"
           value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <div class="asistencia-field">
      <label for="asistencia-nombre">Nombre completo <span>*</span></label>
      <div class="asistencia-input">
        <i class="fas fa-user" aria-hidden="true"></i>
        <input type="text" id="asistencia-nombre" name="nombre"
               placeholder="Escribe tu nombre completo" autocomplete="name"
               maxlength="120" required>
      </div>
    </div>

    <div class="asistencia-field">
      <label for="asistencia-correo">Correo electrónico <span>*</span></label>
      <div class="asistencia-input">
        <i class="fas fa-envelope" aria-hidden="true"></i>
        <input type="email" id="asistencia-correo" name="correo"
               placeholder="ejemplo@correo.com" autocomplete="email"
               maxlength="254" required>
      </div>
    </div>

    <div class="asistencia-field">
      <label for="asistencia-celular">Número de celular <span>*</span></label>
      <div class="asistencia-input">
        <i class="fas fa-phone" aria-hidden="true"></i>
        <input type="tel" id="asistencia-celular" name="celular"
               placeholder="300 123 4567" autocomplete="tel"
               inputmode="tel" maxlength="20" required>
      </div>
    </div>

    <label class="asistencia-privacy">
      <input type="checkbox" name="autorizacion_datos" value="1" required>
      <span>Autorizo el tratamiento de mis datos para gestionar mi inscripción
        y las comunicaciones relacionadas únicamente con este encuentro, según la
        <a href="https://dianamontes.com/privacidad/" target="_blank" rel="noopener noreferrer">política de privacidad</a>.
      </span>
    </label>

    <!-- Honeypot -->
    <div class="antibot-trampa" aria-hidden="true">
        <label for="hp-asistencia">Sitio web</label>
        <input type="text" id="hp-asistencia" name="pagina_web" autocomplete="off" tabindex="-1">
    </div>

    <div class="asistencia-modal-actions">
      <button type="submit" class="asistencia-submit">
        <i class="fas fa-calendar-check" aria-hidden="true"></i>
        Registrar mi asistencia
      </button>
    </div>

    <p id="asistencia-feedback" class="asistencia-feedback" role="alert" hidden></p>
    <p class="asistencia-note"><i class="fas fa-lock" aria-hidden="true"></i>
      Tus datos se utilizarán para gestionar este encuentro.</p>
  </form>

 <div id="asistencia-exito" class="asistencia-exito" role="status" hidden>
  <i class="fas fa-circle-check" aria-hidden="true"></i>
  <h3>¡Inscripción recibida!</h3>
  <p id="asistencia-exito-texto"></p>
  <p class="muted">Esto registra tu interés; la organización confirmará los detalles.</p>
  <button type="button" class="asistencia-submit"
          onclick="document.getElementById('modal-asistencia').close()">Cerrar</button>
</div>
</dialog>



    <!-- 9. SÚMATE A LA RED -->
    <section class="section section-tint" id="sumate" aria-labelledby="sumate-title">
      <div class="container join-grid">
        <div class="join-intro">
          <p class="pill">Súmate a la red</p>
          <h2 id="sumate-title" class="section-title join-title">Mantengamos el contacto</h2>

          <ol class="steps">
            <li class="step">
              <span class="step-num" aria-hidden="true">1</span>
              <div>
                <h3>Registra tus datos</h3>
                <p>Completa el formulario con tu información y crea una contraseña para acceder a tu panel.</p>
              </div>
            </li>
            <li class="step">
              <span class="step-num" aria-hidden="true">2</span>
              <div>
                <h3>Recibe información</h3>
                <p>Podrás recibir comunicaciones de la campaña por los canales que hayas autorizado.</p>
              </div>
            </li>
            <li class="step">
              <span class="step-num" aria-hidden="true">3</span>
              <div>
                <h3>Accede a tu red</h3>
                <p>Ingresa a tu panel y consulta tu enlace personal para compartir la invitación con otras personas.</p>
              </div>
            </li>
          </ol>

          <aside class="join-info" aria-label="Cómo funciona la red">
            <p>
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 21s-7.5-4.6-9.4-9.2C1.3 8.6 3.4 5 6.9 5c2 0 3.6 1.1 5.1 2.9C13.5 6.1 15.1 5 17.1 5c3.5 0 5.6 3.6 4.3 6.8C19.5 16.4 12 21 12 21z"/></svg>
              <strong class="accent-text">Información sobre la red</strong>
              Al registrarte tendrás acceso a un panel personal. Si alguien te compartió su enlace de invitación, tu registro quedará asociado a esa referencia. También podrás registrarte directamente y compartir tu propio enlace con otras personas.
            </p>
          </aside>
        </div>

        <form class="join-card" id="join-form" novalidate aria-labelledby="join-form-title" action="./api/registrar_simpatizante.php" method="POST">
          <header class="join-card-head">
            <h3 id="join-form-title">Registro de simpatizantes</h3>
            <?php if ($refLider): ?>
                <div class="join-info">
                    <span>
                        Has llegado por invitación de
                        <strong class="accent-text"><?= e($refLider) ?></strong>.
                        Si decides registrarte, su invitación quedará
                        asociada a tu registro.
                    </span>
                </div>
            <?php endif; ?>
          </header>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            
            
            <input type="hidden" name="ref" value="<?= htmlspecialchars((string)($_GET['ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">


          <div class="jfield">
            <label for="j-nombre">Nombre completo</label>
            <input id="j-nombre" name="nombre" type="text" autocomplete="name" placeholder="Ej: María Fernanda Ortiz" required aria-describedby="je-nombre" />
            <p class="field-error" id="je-nombre" hidden>Escribe tu nombre completo.</p>
          </div>

          <div class="jrow">
            <div class="jfield">
              <label for="j-documento">Documento de identidad</label>
              <input id="j-documento" name="documento" type="text" inputmode="numeric" autocomplete="off" placeholder="Solo números, sin puntos" required aria-describedby="je-documento" />
              <p class="field-error" id="je-documento" hidden>Entre 6 y 10 números, sin puntos.</p>
            </div>
            <div class="jfield">
              <label for="j-celular">Celular (WhatsApp)</label>
              <input id="j-celular" name="celular" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="3XX XXX XXXX" required aria-describedby="je-celular" />
              <p class="field-error" id="je-celular" hidden>Celular de 10 dígitos que empiece por 3.</p>
            </div>
          </div>

        <div class="jrow">
            <!-- Barrio o vereda -->
            <div class="jfield">
                <label for="f-zona">Barrio o vereda</label>
                <select id="f-zona" name="zona_id" class="select2-custom" required aria-describedby="je-zona">
                    <option value=""></option>
                    <?= zonas_opciones($zonas) ?>
                </select>
                <p class="field-error" id="je-zona" hidden>Indica tu barrio o vereda.</p>
            </div>
            <!-- Ocupación -->
            <div class="jfield">
                <label for="j-prof">¿A qué te dedicas?</label>
                <select id="j-prof" name="prof" class="select2-custom" required aria-describedby="je-prof">
                    <option value=""></option>
                    <?php foreach ($profesiones as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= e($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error" id="je-prof" hidden>Selecciona tu profesión.</p>
            </div>
        </div>

          <div class="jrow">
            <div class="jfield">
              <label for="j-fecha">Fecha de nacimiento</label>
              <input id="j-fecha" name="fecha" type="date" required aria-describedby="je-fecha" />
              <p class="field-error" id="je-fecha" hidden>Debes ser mayor de 18 años.</p>
            </div>
            <div class="jfield">
              <label for="j-genero">Género</label>
              <select id="j-genero" name="genero" required aria-describedby="je-genero" placeholder="Selecciona tu género">
                <option value=""></option>
                <option>Femenino</option>
                <option>Masculino</option>
                <option>Otro</option>
                <option>Prefiero no decirlo</option>
              </select>
              <p class="field-error" id="je-genero" hidden>Selecciona tu género.</p>
            </div>
          </div>

          <div class="jfield">
            <label for="j-clave">Crea una clave para tu panel <span class="opt">(Crea una contraseña segura para ingresar a tu panel. Tu usuario será tu documento de identidad.)</span></label>
            <div class="pass-wrap">
              <input id="j-clave" name="clave" type="password" autocomplete="new-password" minlength="12" placeholder="Mínimo 12 caracteres" required aria-describedby="je-clave" />
              <button type="button" class="pass-toggle" aria-controls="j-clave" aria-pressed="false" aria-label="Mostrar clave">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
              </button>
            </div>
            <p class="field-error" id="je-clave" hidden>La clave debe tener al menos 12 caracteres.</p>
          </div>

          <div class="jcheck jcheck-tint">
            <input id="j-ubicacion" name="ubicacion" type="checkbox" aria-describedby="j-ubicacion-status" />
            <label for="j-ubicacion">
              <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
              <strong>Compartir mi ubicación aproximada</strong> <span class="opt">(opcional)</span> para invitarme a actividades cerca de mí. Se guarda redondeada a unos 100 m.
            </label>
          </div>
          <p class="jcheck-status" id="j-ubicacion-status" aria-live="polite"></p>

          <div class="jcheck">
            <input id="j-datos" name="datos" type="checkbox" required aria-describedby="je-datos" />
            <label for="j-datos">Autorizo el tratamiento de mis datos personales para gestionar mi registro y recibir información política de la campaña de Diana Montes, de acuerdo con <a href="https://dianamontes.com/privacidad/">la política de privacidad</a></label>
          </div>
          <p class="field-error" id="je-datos" hidden>Debes autorizar el tratamiento de datos para continuar.</p>

          <!-- Honeypot -->
          <div class="antibot-trampa" aria-hidden="true">
              <label for="hp-simpatizante">Sitio web</label>
              <input type="text" id="hp-simpatizante" name="web" autocomplete="off" tabindex="-1">
          </div>

          <button class="btn btn-primary btn-block join-submit" type="submit">Completar registro</button>
          <p class="form-status" id="join-status" role="status" aria-live="polite"></p>
          <p class="join-foot">
            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M17 10V7a5 5 0 0 0-10 0v3H5v12h14V10h-2zm-8 0V7a3 3 0 0 1 6 0v3H9z"/></svg>
            Consulta nuestra política de privacidad para conocer cómo utilizamos y protegemos tus datos personales.
          </p>
        </form>
        
        <!-- MODAL: insertar una sola vez en include/footer.php, antes de </body> -->
<dialog
    id="join-success-modal"
    class="join-success-modal"
    aria-labelledby="join-success-title"
    aria-describedby="join-success-description"
>
    <div class="join-success-modal__top">
        <span class="join-success-modal__icon" aria-hidden="true">
            <i class="fas fa-circle-check"></i>
            <p class="join-success-modal__eyebrow">REGISTRO CONFIRMADO</p>
        </span>
        <button type="button" class="video-modal__close" id="join-success-close" aria-label="Cerrar Confirmacion">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <h2 id="join-success-title">¡Gracias por sumarte!</h2>
    <p id="join-success-description" class="join-success-modal__description">
        Tu registro se ha completado correctamente. Gracias por tu interés en conocer y acompañar las actividades de la campaña de Diana Montes.
    </p>

    <div class="join-success-modal__details">
        <div class="join-success-modal__field">
            <span>Nombre del simpatizante</span>
            <strong id="join-success-name"></strong>
        </div>
        <div class="join-success-modal__field">
            <span>Usuario para iniciar sesión (documento de identidad)</span>
            <strong id="join-success-user"></strong>
        </div>
        <p class="join-success-modal__password-note">
            Ingresa con la contraseña que acabas de crear. No la compartas.
        </p>
    </div>

    <a class="join-success-modal__login" href="https://dianamontes.com/mi/">
        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
        Iniciar sesión en mi panel
    </a>
    <p class="join-success-modal__help">
        Guarda tus datos de acceso en un lugar seguro. Recuerda que tu contraseña es personal y no debes compartirla.
    </p>
</dialog>

        
      </div>
    </section>
  </main>
  
<?php
    include 'include/footer.php';
?>

