<?php
require_once 'config.php';

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    // Buscar el token en la base de datos (válido y no expirado)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE verification_token = ? AND verification_expires > NOW() AND email_verified = FALSE");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Token válido, verificar email
        $stmt = $pdo->prepare("UPDATE usuarios SET email_verified = TRUE, verification_token = NULL, verification_expires = NULL WHERE id = ?");
        
        if ($stmt->execute([$user['id']])) {
            $success = true;
            $message = '¡Email verificado con éxito! Ya puedes iniciar sesión en tu cuenta.';
            
            // Opcional: iniciar sesión automáticamente (descomentarlo si lo deseas)
            /*
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            */
        } else {
            $message = 'Error al verificar el email. Por favor, inténtalo de nuevo más tarde.';
        }
    } else {
        // Token inválido o expirado: verificar si existe usuario con ese token para diferenciar mensajes
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE verification_token = ?");
        $stmt->execute([$token]);
        $userExists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userExists) {
            if ($userExists['email_verified']) {
                $message = 'Este email ya ha sido verificado. Puedes iniciar sesión normalmente.';
            } else {
                // Construir enlace para solicitar nuevo token (asegúrate de tener resend-verification.php)
                $emailParam = urlencode($userExists['email']);
                $message = 'El enlace de verificación ha expirado. <a href="resend-verification.php?email=' . htmlspecialchars($emailParam, ENT_QUOTES, 'UTF-8') . '" class="text-decoration-none">Solicitar nuevo enlace</a>';
            }
        } else {
            $message = 'Enlace de verificación inválido.';
        }
    }
} else {
    $message = 'No se proporcionó token de verificación.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .verify-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verify-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
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
        
        .verify-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .verify-icon.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .verify-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .verify-message {
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            word-wrap: break-word;
        }
        
        .btn-verify {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }
        
        .animate-bounce {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0,-30px,0);
            }
            70% {
                transform: translate3d(0,-15px,0);
            }
            90% {
                transform: translate3d(0,-4px,0);
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-icon <?php echo $success ? 'success' : 'error'; ?> <?php echo $success ? 'animate-bounce' : ''; ?>">
                <i class="fas <?php echo $success ? 'fa-check' : 'fa-times'; ?>"></i>
            </div>
            
            <h1 class="verify-title">
                <?php echo $success ? '¡Verificación Exitosa!' : 'Error de Verificación'; ?>
            </h1>
            
            <div class="verify-message">
                <?php
                // $message puede contener HTML (enlace para reenvío); se asume que fue construido de forma segura arriba
                echo $message;
                ?>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <?php if ($success): ?>
    <script>
        // Confetti animation para celebrar la verificación exitosa.
        // Se crea un conjunto de piezas y se animan hacia abajo. Luego redirige al login.
        (function() {
            const colors = ['#6366f1', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444'];

            // Añadir estilos dinámicos para confetti
            const style = document.createElement('style');
            style.innerHTML = `
                .confetti-piece {
                    position: fixed;
                    top: -10vh;
                    width: 10px;
                    height: 16px;
                    opacity: 0.95;
                    transform-origin: center;
                    will-change: transform, top;
                    z-index: 9999;
                    border-radius: 2px;
                }
                @keyframes confetti-fall {
                    0% { transform: translateY(-20vh) rotate(0deg); opacity: 1; }
                    100% { transform: translateY(110vh) rotate(720deg); opacity: 0.9; }
                }
            `;
            document.head.appendChild(style);

            function createConfettiPiece() {
                const el = document.createElement('div');
                el.className = 'confetti-piece';
                el.style.left = Math.random() * 100 + 'vw';
                el.style.background = colors[Math.floor(Math.random() * colors.length)];
                el.style.width = (6 + Math.random() * 12) + 'px';
                el.style.height = (8 + Math.random() * 18) + 'px';
                const duration = 3 + Math.random() * 3; // 3-6s
                const delay = Math.random() * 1.2;
                el.style.animation = `confetti-fall ${duration}s linear ${delay}s forwards`;

                const drift = (Math.random() - 0.5) * 200; // px horizontal drift

                document.body.appendChild(el);

                // Animación de desplazamiento horizontal y rotación complementaria
                let start = null;
                function animate(ts) {
                    if (!start) start = ts;
                    const elapsed = (ts - start) / 1000; // segundos
                    const progress = Math.min(elapsed / duration, 1);
                    const x = drift * progress;
                    const rot = 720 * progress;
                    el.style.transform = `translateX(${x}px) rotate(${rot}deg)`;
                    if (progress < 1) requestAnimationFrame(animate);
                }
                requestAnimationFrame(animate);

                // Eliminar tras terminar la animación
                setTimeout(() => {
                    if (el && el.remove) el.remove();
                }, (duration + delay) * 1000 + 500);
            }

            // Crear un "burst" de confetti con ligero escalonado
            for (let i = 0; i < 80; i++) {
                setTimeout(createConfettiPiece, i * 12);
            }

            // Redirigir al login después de 6 segundos (el usuario puede hacer click antes)
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 6000);
        })();
    </script>
    <?php endif; ?>
</body>
</html>