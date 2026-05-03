<?php
require_once 'config.php';

requireLogin();

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $message = 'La nueva contraseña debe tener al menos 8 caracteres.';
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
            $passwordOk = password_verify($current, $user['password']);

            if (!$passwordOk) {
                $message = 'La contraseña actual no es correcta.';
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
                if ($upd->execute([$newHash, $user['id']])) {
                    // Cerrar sesiones "Recuerdame" de otros dispositivos al
                    // cambiar la contrasena. Tambien limpia la cookie del
                    // navegador actual; si quiere que se le recuerde aqui,
                    // tendra que volver a marcar la casilla en el proximo
                    // login.
                    remember_me_clear_all_for_user((int)$user['id']);
                    $success = true;
                    $message = 'Contraseña actualizada correctamente.';
                } else {
                    $message = 'No se pudo actualizar la contraseña.';
                }
            }
        }
    }
}

$pageTitle = 'Cambiar contraseña';
include 'includes/head.php';
?>
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
              <?= csrf_field() ?>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Actual" required>
                <label for="current_password">Contraseña actual</label>
                <div class="invalid-feedback">Introduce tu contraseña actual.</div>
              </div>
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Nueva" minlength="8" required>
                <label for="new_password">Nueva contraseña</label>
                <div class="invalid-feedback">La contraseña debe tener al menos 8 caracteres.</div>
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
  <?php
  ob_start();
  ?>
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
  <?php
  $extraScriptsHtml = ob_get_clean();
  include 'includes/footer.php';
  ?>