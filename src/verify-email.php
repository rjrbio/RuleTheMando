<?php
require_once 'config.php';

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = trim((string)$_GET['token']);

    // Buscar el token en la base de datos
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE verification_token = ? AND verification_expires > NOW() AND email_verified = FALSE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Token válido, verificar email
        $stmt = $pdo->prepare("UPDATE usuarios SET email_verified = TRUE, verification_token = NULL, verification_expires = NULL WHERE id = ?");

        if ($stmt->execute([$user['id']])) {
            $success = true;
            $message = '¡Email verificado con éxito! Ya puedes iniciar sesión en tu cuenta.';

            // Opcional: iniciar sesión automáticamente
            /*
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            */
        } else {
            $message = 'Error al verificar el email. Por favor, inténtalo de nuevo.';
        }
    } else {
        // Token inválido o expirado
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE verification_token = ?");
        $stmt->execute([$token]);
        $userExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userExists) {
            if ($userExists['email_verified']) {
                $message = 'Este email ya ha sido verificado. Puedes iniciar sesión normalmente.';
            } else {
                $message = 'El enlace de verificación ha expirado. <a href="resend-verification.php?email=' . urlencode($userExists['email']) . '" class="text-decoration-none">Solicitar nuevo enlace</a>';
            }
        } else {
            $message = 'Enlace de verificación inválido.';
        }
    }
} else {
    $message = 'No se proporcionó token de verificación.';
}

$pageTitle = 'Verificación de Email';
ob_start();
?>
<style>
    /* Solo layout: el resto (colores, glow, gradientes) vive en styles.css. */
    .verify-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .verify-card {
        padding: 3rem 2rem;
        text-align: center;
        max-width: 500px;
        width: 100%;
    }
    .verify-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
    }
    .verify-icon.success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
    .verify-icon.error   { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
    .verify-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 1rem; }
    .verify-message { font-size: 1.05rem; line-height: 1.6; margin-bottom: 2rem; }
    .btn-verify { padding: 12px 30px; }
</style>
<?php
$extraHead = ob_get_clean();
include 'includes/head.php';
?>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div
                class="verify-icon <?php echo $success ? 'success' : 'error'; ?> <?php echo $success ? 'animate-bounce' : ''; ?>">
                <i class="fas <?php echo $success ? 'fa-check' : 'fa-times'; ?>"></i>
            </div>

            <h1 class="verify-title">
                <?php echo $success ? '¡Verificación Exitosa!' : 'Error de Verificación'; ?>
            </h1>

            <div class="verify-message">
                <?php echo $message; ?>
            </div>

            <div class="d-flex gap-3 justify-content-center">
                <?php if ($success): ?>
                    <a href="login.php" class="btn btn-primary btn-verify">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </a>
                <?php endif; ?>

                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Ir al Inicio
                </a>
            </div>

            <?php if (!$success): ?>
                <div class="mt-4">
                    <small class="text-muted">
                        ¿Problemas con la verificación?
                        <a href="mailto:support@rulethemando.com" class="text-decoration-none">Contacta soporte</a>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    ob_start();
    if ($success):
    ?>
        <script>
            // Confetti animation para celebrar la verificación exitosa
            function createConfetti() {
                const colors = ['#6366f1', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444'];

                for (let i = 0; i < 50; i++) {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.animationDelay = Math.random() * 3 + 's';
                    confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.width = Math.random() * 10 + 5 + 'px';
                    confetti.style.height = Math.random() * 10 + 5 + 'px';
                    confetti.style.zIndex = '9999';
                    confetti.style.animation = 'fall linear forwards';

                    document.body.appendChild(confetti);

                    setTimeout(() => {
                        confetti.remove();
                    }, 5000);
                }
            }

            // CSS animation for falling confetti
            const style = document.createElement('style');
            style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
            document.head.appendChild(style);

            // Crear confetti cuando la página carga
            setTimeout(createConfetti, 500);
        </script>
    <?php
    endif;
    $extraScriptsHtml = ob_get_clean();
    include 'includes/footer.php';
    ?>