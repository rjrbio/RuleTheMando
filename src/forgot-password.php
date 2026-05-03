<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Placeholder sencillo para evitar enlace roto
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = sanitize($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Por favor, introduce un email válido.';
    } else {
        // De momento, solo mostramos un aviso. Aquí podrías integrar Supabase reset_password.
        $message = 'Funcionalidad en construcción. Prueba a contactar con soporte o vuelve más tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña - <?php echo SITE_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
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
              <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <p class="text-muted">Esta funcionalidad está en construcción. Mientras tanto, puedes:</p>
            <ul>
              <li>Revisar si tu email está verificado. Si no lo está, usa <a href="resend-verification.php">reenviar verificación</a>.</li>
              <li>Contactar con soporte: <a href="mailto:support@rulethemando.com">support@rulethemando.com</a></li>
            </ul>
            <form method="post" class="mt-3">
              <?= csrf_field() ?>
              <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="tu@email.com" required>
                <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
              </div>
              <button class="btn btn-secondary" type="submit">Enviar</button>
              <a class="btn btn-outline-primary ms-2" href="login.php">Volver al login</a>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>