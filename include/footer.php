  <!-- 9. FOOTER -->
  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <img src="./assets/logo/logo_diana_blanco.png" alt="Diana Montes" width="100%" height="auto" loading="lazy">
        <p class="footer-place light">Garzón, Huila · Colombia</p>
      </div>
      <nav aria-label="Navegación del pie de página">
        <ul class="footer-nav">
          <li><a href="#inicio">Inicio</a></li>
          <li><a href="#conozca">Conoce a Diana</a></li>
          <li><a href="#por-que">¿Por qué hago las cosas?</a></li>
          <li><a href="#usted">¿Y tú, por qué haces lo que haces?</a></li>
          <li><a href="#garzon">Hablemos de Garzón</a></li>
          <li><a href="#conversemos">Conversemos</a></li>
          <li><a href="#sumate">Súmate a la red</a></li>
        </ul>
      </nav>
      <div class="footer-legal" id="politica">
        <p><a href="./politicas/privacidad.php">Política de privacidad</a></p>
        <p><a href="./politicas/terminos.php">Condiciones del servicio</a></p>
        <p><a href="./politicas/eliminar-datos.php">Eliminar mis datos</a></p>
      </div>
    </div>
    <div class="container footer-bottom">
      <p>© <?php echo date('Y'); ?> Campaña Diana Montes · Garzón, Huila</p>
      <a href="#top" class="link-top">Volver arriba <span aria-hidden="true"><i class="fa-solid fa-circle-arrow-up"></i></span></a>
    </div>
  </footer>

  <div class="login-float" id="login-float">
    <div class="login-menu" id="login-menu" role="menu" aria-labelledby="login-toggle" hidden>
      <p class="login-menu-title">¿Cómo quieres ingresar?</p>
      <a class="login-option" role="menuitem" href="https://dianamontes.com/mi" target="_blank" rel="noopener">
        <span class="login-option-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 20c1.5-3.8 4.4-5.5 8-5.5s6.5 1.7 8 5.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="login-option-text">
          <strong>Soy simpatizante</strong>
          <span>Mira tu red, tus puntos, tus tareas y tu enlace para invitar.</span>
        </span>
        <span class="login-option-arrow" aria-hidden="true">→</span>
      </a>
      <a class="login-option" role="menuitem" href="https://dianamontes.com/admin/auth/login" target="_blank" rel="noopener">
        <span class="login-option-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20"><circle cx="9" cy="8.5" r="3.2" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="9.5" r="2.4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 19.5c1.1-3.2 3.3-4.7 6-4.7s4.9 1.5 6 4.7M15.5 14.6c2.6-.3 4.6 1.2 5.5 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </span>
        <span class="login-option-text">
          <strong>Equipo de Diana</strong>
          <span>Dirección, coordinadores, líderes y digitación.</span>
        </span>
        <span class="login-option-arrow" aria-hidden="true">→</span>
      </a>
    </div>
    <button class="login-toggle" id="login-toggle" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="login-menu" title="Iniciar Sesión">
      <i class="fa-solid fa-user-lock"></i> Iniciar Sesión
    </button>
  </div>
    
    <!-- pagina principal -->
    <script src="./js/script.js?cache=<?php echo rand(10,100); ?>" defer></script>
    <!-- historias -->
    <script src="./js/historias.js?cache=<?php echo rand(10,100); ?>" defer></script>
    <!-- simpatizante -->
    <script src="./js/simpatizante.js?cache=<?php echo rand(10,100); ?>" defer></script>
    <!-- eventos -->
    <script src="./js/eventos.js?cache=<?php echo rand(10,100); ?>" defer></script>
    <!-- asistencia -->
    <script src="./js/asistencias.js?cache=<?php echo rand(10,100); ?>" defer></script>
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <!-- Idioma español -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/es.min.js"></script>
</body>
</html>
