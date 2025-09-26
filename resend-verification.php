<?php
require_once 'config.php';

$message = '';
$success = false;

// Función local para enviar email de verificación (similar a la de login.php)
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
                <p>Verificación de cuenta</p>
            </div>
            <div class='content'>
                <h2>Hola $username,</h2>
                <p>Solicitaste un nuevo enlace de verificación para tu cuenta en " . SITE_NAME . "</p>
                <p style='text-align: center;'>
                    <a href='$verification_link' class='button'>Verificar Email</a>
                </p>
                <p>Si no solicitaste este email, puedes ignorar este mensaje.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . ".</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@rulethemando.com>" . "\r\n";

    // Log para pruebas locales
    $log_entry = date('Y-m-d H:i:s') . " - Resend verification email to: $email\n";
    $log_entry .= "Verification Link: $verification_link\n";
    $log_entry .= "Username: $username\n";
    $log_entry .= "---\n\n";
    file_put_contents('email_log.txt', $log_entry, FILE_APPEND);

    // Para desarrollo local: simular envío exitoso
    // Verificar si estamos en localhost (desarrollo)
    $isLocalhost = (strpos(SITE_URL, 'localhost') !== false || strpos(SITE_URL, '127.0.0.1') !== false);
    
    if ($isLocalhost) {
        // En desarrollo local, simular envío exitoso
        return true;
    } else {
        // En producción, intentar enviar con mail()
        return mail($email, $subject, $message, $headers);
    }
}

// Manejo de entrada: permitir GET (enlaces existentes) o POST desde formulario
$inputEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['email'])) {
    $inputEmail = sanitize($_GET['email']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $inputEmail = sanitize($_POST['email']);
}

if ($inputEmail) {
    if (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email inválido.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email_verified FROM usuarios WHERE email = ?');
        $stmt->execute([$inputEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'No existe una cuenta con ese email.';
        } elseif ($user['email_verified']) {
            $message = 'El email ya está verificado. Puedes iniciar sesión.';
        } else {
            // Generar nuevo token y expiración
            try {
                $verification_token = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $message = 'Error generando token. Intenta de nuevo.';
            }

            if (empty($message)) {
                $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $pdo->prepare('UPDATE usuarios SET verification_token = ?, verification_expires = ? WHERE id = ?');
                if ($stmt->execute([$verification_token, $verification_expires, $user['id']])) {
                    if (sendVerificationEmailLocal($inputEmail, $user['username'], $verification_token)) {
                        $success = true;
                        $message = 'Se ha enviado un nuevo email de verificación a tu dirección.';
                    } else {
                        $message = 'No se pudo enviar el email de verificación. Inténtalo más tarde.';
                    }
                } else {
                    $message = 'Error interno al actualizar el usuario.';
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reenviar verificación - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <div class="container" style="padding: 40px 0;">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-3">Reenviar email de verificación</h3>

                        <?php if ($message): ?>
                            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$success): ?>
                            <form method="post" class="mb-3">
                                <div class="mb-3">
                                    <input type="email" name="email" class="form-control" placeholder="Tu email" required value="<?php echo htmlspecialchars($inputEmail); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Enviar enlace de verificación</button>
                            </form>
                        <?php endif; ?>

                        <a href="login.php" class="btn btn-outline-secondary mt-2">Volver al login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
