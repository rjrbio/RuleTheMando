<?php
require_once 'config.php';

$error = '';
$success = '';

// Helper para mostrar mensajes permitiendo solo etiquetas básicas seguras (a, strong)
function render_message($msg) {
    // Permitir solo etiquetas <a> y <strong> (y sus atributos básicos)
    $allowed = '<a><strong>';
    // Escapar y luego permitir las etiquetas especificadas
    return strip_tags($msg, $allowed);
}

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('index.php');
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['email_verified']) {
                $error = 'Debes verificar tu email antes de iniciar sesión. <a href="resend-verification.php?email=' . urlencode($email) . '">Reenviar email de verificación</a>';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                redirect('index.php');
            }
        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = sanitize($_POST['reg_username']);
    $email = sanitize($_POST['reg_email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];

    // Validaciones
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, completa todos los campos.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido.';
    } else {
        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $error = 'El nombre de usuario o email ya está en uso.';
        } else {
            // Crear nuevo usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare("INSERT INTO usuarios (username, email, password, verification_token, verification_expires) VALUES (?, ?, ?, ?, ?)");

            if ($stmt->execute([$username, $email, $hashed_password, $verification_token, $verification_expires])) {
                // Enviar email de verificación
                if (sendVerificationEmail($email, $username, $verification_token)) {
                    $success = 'Registro exitoso. Se ha enviado un email de verificación a tu dirección de correo.';
                } else {
                    $error = 'Usuario creado, pero hubo un error al enviar el email de verificación. <a href="resend-verification.php?email=' . urlencode($email) . '">Reenviar</a>';
                }
            } else {
                $error = 'Error al crear el usuario. Inténtalo de nuevo.';
            }
        }
    }
}

// Función para enviar email de verificación
function sendVerificationEmail($email, $username, $token)
{
    // Aquí integrarías PHPMailer o tu servicio de email preferido
    // Por ahora, simularemos el envío

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
                <p>¡Bienvenido a la comunidad gamer!</p>
            </div>
            <div class='content'>
                <h2>Hola $username,</h2>
                <p>Gracias por registrarte en " . SITE_NAME . ". Para completar tu registro, por favor verifica tu dirección de email haciendo clic en el botón de abajo:</p>
                <p style='text-align: center;'>
                    <a href='$verification_link' class='button'>Verificar Email</a>
                </p>
                <p>O copia y pega este enlace en tu navegador:</p>
                <p style='word-break: break-all; background: #e2e8f0; padding: 10px; border-radius: 5px;'>$verification_link</p>
                <p><strong>Este enlace expira en 24 horas.</strong></p>
                <p>Si no te registraste en " . SITE_NAME . ", puedes ignorar este email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2024 " . SITE_NAME . ". Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Headers para HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@rulethemando.com>" . "\r\n";

    // Por ahora, guardaremos el email en un archivo de log para testing
    // En producción, reemplaza esto con PHPMailer o tu servicio de email
    $log_entry = date('Y-m-d H:i:s') . " - Email to: $email\n";
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
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 800px;
        }

        .auth-header {
            background: linear-gradient(135deg, #1f2937, #374151);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .auth-body {
            padding: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 10px;
        }

        .form-floating label {
            color: #6b7280;
        }

        .btn-auth {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            padding: 12px 0;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .back-home:hover {
            color: #f59e0b;
            transform: translateX(-5px);
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <a href="index.php" class="back-home">
            <i class="fas fa-arrow-left me-2"></i>Volver al inicio
        </a>

        <div class="auth-card">
            <div class="auth-header">
                <h2><i class="fas fa-gamepad me-3"></i><?php echo SITE_NAME; ?></h2>
                <p class="mb-0">Únete a la comunidad gamer</p>
            </div>

            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo render_message($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo render_message($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Tabs para Login/Registro -->
                <ul class="nav nav-tabs justify-content-center mb-4" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active me-2" id="login-tab" data-bs-toggle="tab" data-bs-target="#login"
                            type="button" role="tab">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link ms-2" id="register-tab" data-bs-toggle="tab" data-bs-target="#register"
                            type="button" role="tab">
                            <i class="fas fa-user-plus me-2"></i>Registrarse
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login" role="tabpanel">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email"
                                    required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                                <div class="invalid-feedback">
                                    Por favor, ingresa un email válido.
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Contraseña" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                                <div class="invalid-feedback">
                                    Por favor, ingresa tu contraseña.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Recordarme
                                    </label>
                                </div>
                                <a href="forgot-password.php" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                            </div>

                            <button type="submit" name="login" class="btn btn-primary btn-auth w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </form>
                    </div>

                    <!-- Register Tab -->
                    <div class="tab-pane fade" id="register" role="tabpanel">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="reg_username" name="reg_username"
                                            placeholder="Usuario" required>
                                        <label for="reg_username"><i class="fas fa-user me-2"></i>Usuario</label>
                                        <div class="invalid-feedback">
                                            Por favor, elige un nombre de usuario.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="reg_email" name="reg_email"
                                            placeholder="Email" required>
                                        <label for="reg_email"><i class="fas fa-envelope me-2"></i>Email</label>
                                        <div class="invalid-feedback">
                                            Por favor, ingresa un email válido.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="reg_password" name="reg_password"
                                    placeholder="Contraseña" minlength="6" required>
                                <label for="reg_password"><i class="fas fa-lock me-2"></i>Contraseña</label>
                                <div class="invalid-feedback">
                                    La contraseña debe tener al menos 6 caracteres.
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="reg_confirm_password"
                                    name="reg_confirm_password" placeholder="Confirmar Contraseña" required>
                                <label for="reg_confirm_password"><i class="fas fa-lock me-2"></i>Confirmar
                                    Contraseña</label>
                                <div class="invalid-feedback">
                                    Por favor, confirma tu contraseña.
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    Acepto los <a href="#" class="text-decoration-none">términos y condiciones</a>
                                </label>
                                <div class="invalid-feedback">
                                    Debes aceptar los términos y condiciones.
                                </div>
                            </div>

                            <button type="submit" name="register" class="btn btn-primary btn-auth w-100">
                                <i class="fas fa-user-plus me-2"></i>Crear Cuenta
                            </button>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        Al registrarte, aceptas recibir emails de verificación y notificaciones importantes.
                    </small>
                </div>
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

        // Verificar que las contraseñas coincidan
        document.getElementById('reg_confirm_password').addEventListener('input', function () {
            const password = document.getElementById('reg_password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>