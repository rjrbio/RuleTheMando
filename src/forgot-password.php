<?php
require_once 'config.php';
require_once 'includes/auth-reset.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Por favor, introduce un email válido.';
    } elseif (rate_limit_blocked('forgot', $email, 3, 60)) {
        $message = 'Demasiados intentos. Espera unos minutos antes de volver a probar.';
    } else {
        rate_limit_record('forgot', $email, true);
        // Anti-enumeracion: NO revelamos si el email existe o no.
        // password_reset_create devuelve false si el email no esta en BD,
        // pero el mensaje al usuario es siempre el mismo.
        password_reset_create($email);
        $success = true;
        $message = 'Si existe una cuenta asociada a ese email, te hemos enviado instrucciones para restablecer la contraseña. Revisa tu bandeja (y la carpeta de spam).';
    }
}

$pageTitle = 'Recuperar contraseña';
include 'includes/head.php';
?>
<body class="bg-light d-flex align-items-center min-vh-100">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow">
          <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Recuperar contraseña</h5>
          </div>
          <div class="card-body">
            <?php if ($message): ?>
              <div class="alert alert-<?= $success ? 'success' : 'info' ?>" role="alert">
                <i class="fas fa-<?= $success ? 'check-circle' : 'info-circle' ?> me-2" aria-hidden="true"></i>
                <?= e($message) ?>
              </div>
            <?php endif; ?>

            <?php if (!$success): ?>
              <p class="text-muted small mb-3">
                Introduce el email asociado a tu cuenta y te enviaremos un enlace para restablecer la contraseña.
              </p>
              <form method="post">
                <?= csrf_field() ?>
                <div class="form-floating mb-3">
                  <input type="email" class="form-control" id="email" name="email" placeholder="tu@email.com" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                  <label for="email"><i class="fas fa-envelope me-2" aria-hidden="true"></i>Email</label>
                </div>
                <button class="btn btn-primary" type="submit">
                  <i class="fas fa-paper-plane me-2" aria-hidden="true"></i>Enviar enlace
                </button>
                <a class="btn btn-outline-secondary ms-2" href="login.php">Volver al login</a>
              </form>
            <?php else: ?>
              <a class="btn btn-outline-secondary" href="login.php">
                <i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Volver al login
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>