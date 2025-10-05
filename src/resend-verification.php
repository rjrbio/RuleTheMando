<?php
require_once 'config.php';
require_once 'supabase-config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['email'])) {
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : sanitize($_GET['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Por favor, ingresa un email válido.';
    } else {
        // Buscar usuario por email
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'No se encontró una cuenta asociada a este email.';
        } elseif ($user['email_verified']) {
            $message = 'Este email ya ha sido verificado. Puedes iniciar sesión normalmente.';
        } else {
            // Generar nuevo token de verificación
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare("UPDATE usuarios SET verification_token = ?, verification_expires = ? WHERE id = ?");

            if ($stmt->execute([$verification_token, $verification_expires, $user['id']])) {
                // Intentar enviar con Supabase primero
                $supabaseResponse = sendSupabaseVerificationEmail($email, SITE_URL . '/verify-supabase.php');
                
                if ($supabaseResponse['success']) {
                    $success = true;
                    $message = 'Se ha enviado un nuevo email de verificación usando Supabase. Revisa tu bandeja de entrada y spam.';
                } else {
                    // Fallback al sistema local
                    if (sendVerificationEmailLocal($email, $user['username'], $verification_token)) {
                        $success = true;
                        $message = 'Se ha enviado un nuevo email de verificación usando el sistema local.';
                    } else {
                        $message = 'Error al enviar el email. Por favor, inténtalo de nuevo más tarde.';
                    }
                }
            } else {
                $message = 'Error interno. Por favor, inténtalo de nuevo.';
            }
        }
    }
}

// Función local para enviar email de verificación (fallback)
function sendVerificationEmailLocal($email, $username, $token)
{
    $verification_link = SITE_URL . "/verify-email.php?token=" . $token;
    $subject = "Verifica tu cuenta en " . SITE_NAME;
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . SITE_NAME . "</h1>
                <p>Verificación de email</p>
            </div>
            <div class='content'>
                <h2>Hola $username,</h2>
                <p>Has solicitado un nuevo enlace de verificación para tu cuenta en " . SITE_NAME . ". Haz clic en el botón de abajo para verificar tu email:</p>
                <p style='text-align: center;'>
                    <a href='$verification_link' class='button'>Verificar Email</a>
                </p>
                <p>O copia y pega este enlace en tu navegador:</p>
                <p style='word-break: break-all; background: #e2e8f0; padding: 10px; border-radius: 5px;'>$verification_link</p>
                <p><strong>Este enlace expira en 24 horas.</strong></p>
                <p>Si no solicitaste este enlace, puedes ignorar este email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2024 " . SITE_NAME . ". Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@rulethemando.com>" . "\r\n";

    // Log para testing
    $log_entry = date('Y-m-d H:i:s') . " - RESEND Email to: $email\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Verification Link: $verification_link\n";
    $log_entry .= "---\n\n";
    file_put_contents('email_log.txt', $log_entry, FILE_APPEND);

    return mail($email, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar Verificación - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .resend-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .resend-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 3rem 2rem;
            max-width: 500px;
            width: 100%;
        }

        .resend-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .btn-resend {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            padding: 12px 0;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-resend:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>

<body>
    <div class="resend-container">
        <div class="resend-card">
            <div class="text-center mb-4">
                <div class="resend-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h2 class="fw-bold mb-3">Reenviar Verificación</h2>
                <p class="text-muted">Ingresa tu email para recibir un nuevo enlace de verificación</p>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> mb-4">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form method="POST" class="needs-validation" novalidate>
                    <div class="form-floating mb-4">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required
                            value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                        <div class="invalid-feedback">
                            Por favor, ingresa un email válido.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-resend w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i>Reenviar Verificación
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center">
                <a href="login.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Login
                </a>

                <?php if ($success): ?>
                    <a href="login.php" class="btn btn-primary btn-resend">
                        <i class="fas fa-sign-in-alt me-2"></i>Ir al Login
                    </a>
                <?php endif; ?>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    ¿No recibes el email? Revisa tu carpeta de spam o
                    <a href="mailto:support@rulethemando.com" class="text-decoration-none">contacta soporte</a>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de formulario
        (function () {
            'use strict';
            window.addEventListener('load', function () {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function (form) {
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