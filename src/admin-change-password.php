<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 6) {
        $message = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($new !== $confirm) {
        $message = 'Las contraseñas no coinciden.';
    } else {
        // Cargar usuario actual
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'Usuario no encontrado.';
        } else {
            $passwordOk = false;
            if (password_verify($current, $user['password'])) {
                $passwordOk = true;
            } else {
                $looksHashed = preg_match('/^\$2[ayb]\$/', (string)$user['password']) || preg_match('/^\$argon2(id|i|d)\$/', (string)$user['password']);
                if (!$looksHashed && hash_equals((string)$user['password'], (string)$current)) {
                    $passwordOk = true; // aceptar texto plano como válida
                }
            }

            if (!$passwordOk) {
                $message = 'La contraseña actual no es correcta.';
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
                if ($upd->execute([$newHash, $user['id']])) {
                    $success = true;
                    $message = 'Contraseña actualizada correctamente.';
                } else {
                    $message = 'No se pudo actualizar la contraseña.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cambiar contraseña - <?php echo SITE_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow">
          <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Cambiar contraseña</h5>
          </div>
          <div class="card-body">
            <?php if ($message): ?>
              <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Actual" required>
                <label for="current_password">Contraseña actual</label>
                <div class="invalid-feedback">Introduce tu contraseña actual.</div>
              </div>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Nueva" minlength="6" required>
                <label for="new_password">Nueva contraseña</label>
                <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
              </div>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirmar" required>
                <label for="confirm_password">Confirmar nueva contraseña</label>
                <div class="invalid-feedback">Confirma tu nueva contraseña.</div>
              </div>

              <button type="submit" class="btn btn-primary">Guardar cambios</button>
              <a href="admin.php" class="btn btn-outline-secondary ms-2">Volver al panel</a>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      'use strict';
      window.addEventListener('load', function () {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.forEach.call(forms, function (form) {
          form.addEventListener('submit', function (event) {
            if (form.checkValidity() === false) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
          }, false);
        });
      }, false);
    })();
  </script>
</body>
</html>