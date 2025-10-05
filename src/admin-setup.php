<?php
require_once 'config.php';

// Solo en desarrollo local
if (strpos(SITE_URL, 'localhost') === false && strpos(SITE_URL, '127.0.0.1') === false) {
    die('Solo disponible en desarrollo local.');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email inválido';
    } elseif (strlen($password) < 6) {
        $message = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        try {
            // Buscar usuario admin
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE username = ? AND role = ? LIMIT 1');
            $stmt->execute(['admin', 'admin']);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                $message = 'No existe usuario admin. Crea uno manualmente en la BD.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE usuarios SET email = ?, email_verified = 1, password = ?, verification_token = NULL, verification_expires = NULL WHERE id = ?');
                $ok = $stmt->execute([$email, $hashed, $admin['id']]);
                if ($ok) {
                    $success = true;
                    $message = 'Admin actualizado: email asignado y verificado, contraseña reiniciada.';
                } else {
                    $message = 'No se pudo actualizar el admin.';
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Setup - <?php echo SITE_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Configurar Admin (solo desarrollo)</h5>
          </div>
          <div class="card-body">
            <?php if ($message): ?>
              <div class="alert <?php echo $success ? 'alert-success' : 'alert-warning'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="post">
              <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="admin@ejemplo.com" required>
                <label for="email">Email para admin</label>
              </div>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Nueva contraseña" minlength="6" required>
                <label for="password">Nueva contraseña</label>
              </div>
              <button type="submit" class="btn btn-primary">Actualizar Admin</button>
              <a href="login.php" class="btn btn-outline-secondary ms-2">Ir a Login</a>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>