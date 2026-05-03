<?php
// Footer comun a todas las paginas con navegacion publica.
// No usar en pages auth (login, verify-*, forgot, resend) ni en admin
// porque el layout es distinto (auth-container, sidebar fija).
?>
<footer class="site-footer bg-dark text-light py-5 mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-6 mb-4">
        <h5 class="fw-bold"><i class="fas fa-gamepad me-2" aria-hidden="true"></i><?= e(SITE_NAME) ?></h5>
        <p class="text-muted small mb-0">Tu destino para descubrir, valorar y seguir tus videojuegos favoritos.</p>
      </div>
      <div class="col-md-6 mb-4">
        <h5 class="fw-bold">Síguenos</h5>
        <div class="social-links">
          <a href="#" class="text-light me-3" aria-label="Twitter"><i class="fab fa-twitter fa-lg" aria-hidden="true"></i></a>
          <a href="#" class="text-light me-3" aria-label="Facebook"><i class="fab fa-facebook fa-lg" aria-hidden="true"></i></a>
          <a href="#" class="text-light me-3" aria-label="Instagram"><i class="fab fa-instagram fa-lg" aria-hidden="true"></i></a>
          <a href="#" class="text-light me-3" aria-label="YouTube"><i class="fab fa-youtube fa-lg" aria-hidden="true"></i></a>
          <a href="#" class="text-light" aria-label="Discord"><i class="fab fa-discord fa-lg" aria-hidden="true"></i></a>
        </div>
      </div>
    </div>
    <hr class="my-4 border-secondary">
    <div class="row align-items-center small">
      <div class="col-md-6">
        <p class="mb-0 text-muted">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Todos los derechos reservados.</p>
      </div>
      <div class="col-md-6 text-md-end mt-2 mt-md-0">
        <a href="#" class="text-muted text-decoration-none me-3">Contacto</a>
        <a href="#" class="text-muted text-decoration-none me-3">Privacidad</a>
        <a href="#" class="text-muted text-decoration-none">Términos</a>
      </div>
    </div>
  </div>
</footer>
