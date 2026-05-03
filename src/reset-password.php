<?php
require_once 'config.php';
require_once 'includes/auth-reset.php';

$message  = '';
$success  = false;
$showForm = false;
$token    = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));

if ($token === '') {
    $message = 'Enlace de restablecimiento no proporcionado.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $newPass     = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPass) < 8) {
        $message  = 'La nueva contraseña debe tener al menos 8 caracteres.';
        $showForm = true;
    } elseif ($newPass !== $confirmPass) {
        $message  = 'Las contraseñas no coinciden.';
        $showForm = true;
    } elseif (password_reset_consume($token, $newPass)) {
        $success = true;
        $message = '¡Contraseña restablecida! Ya puedes iniciar sesión con tu nueva contraseña. Por seguridad hemos cerrado todas las sesiones recordadas del navegador.';
    } else {
        $message = 'El enlace ha expirado o no es válido. Solicita uno nuevo desde "¿Olvidaste tu contraseña?".';
    }
} else {
    // GET con token: solo verificamos que sea valido para mostrar el form
    $row = password_reset_lookup($token);
    if ($row) {
        $showForm = true;
    } else {
        $message = 'El enlace ha expirado o no es válido. Solicita uno nuevo desde "¿Olvidaste tu contraseña?".';
    }
}

$pageTitle = 'Restablecer contraseña';
include 'includes/head.php';
?>
<body class="bg-light d-flex align-items-center min-vh-100">
  <main class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-key me-2" aria-hidden="true"></i>Restablecer contraseña</h5>
          </div>
          <div class="card-body">
            <?php if ($message !== ''): ?>
              <div class="alert alert-<?= $success ? 'success' : ($showForm ? 'warning' : 'danger') ?>" role="alert">
                <?= e($message) ?>
              </div>
            <?php endif; ?>

            <?php if ($success): ?>
              <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>Iniciar sesión
              </a>
            <?php elseif ($showForm): ?>
              <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Nueva" minlength="8" required>
                  <label for="new_password">Nueva contraseña</label>
                  <div class="invalid-feedback">Mínimo 8 caracteres.</div>
                </div>
                <div class="form-floating mb-3">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirmar" required>
                  <label for="confirm_password">Confirmar contraseña</label>
                  <div class="invalid-feedback">Confirma la nueva contraseña.</div>
                </div>
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-2" aria-hidden="true"></i>Guardar nueva contraseña
                </button>
                <a href="login.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
              </form>
            <?php else: ?>
              <a href="forgot-password.php" class="btn btn-primary">
                <i class="fas fa-redo me-2" aria-hidden="true"></i>Solicitar nuevo enlace
              </a>
              <a href="login.php" class="btn btn-outline-secondary ms-2">Volver al login</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include 'includes/footer.php'; ?>
