<?php
require_once 'config.php';
require_once 'supabase-config.php';

$message = '';
$success = false;

// Supabase puede enviar diferentes formatos de URL
// Formato 1: ?token=...&type=signup (nuestro formato personalizado)
// Formato 2: ?token_hash=...&type=signup (ConfirmationURL de Supabase)
// Formato 3: Otros parámetros que Supabase pueda usar

if (isset($_GET['token']) || isset($_GET['token_hash'])) {
    // Determinar qué token usar (no aplicar htmlspecialchars: se pasa a la API
    // de Supabase via JSON; el escape es responsabilidad de json_encode).
    $token = trim((string)($_GET['token'] ?? $_GET['token_hash'] ?? ''));
    $type = trim((string)($_GET['type'] ?? 'signup'));

    // Verificar con Supabase
    $response = verifySupabaseEmail($token, $type);

    if (!empty($response['success'])) {
        // Obtener email del usuario desde la respuesta
        $email = null;
        if (isset($response['data']['user']['email'])) {
            $email = $response['data']['user']['email'];
        } elseif (isset($response['data']['email'])) {
            $email = $response['data']['email'];
        } elseif (isset($response['data']) && is_array($response['data']) && isset($response['data']['user']) && is_array($response['data']['user']) && isset($response['data']['user']['email'])) {
            $email = $response['data']['user']['email'];
        }

        if ($email) {
            // Actualizar el usuario local
            $stmt = $pdo->prepare("UPDATE usuarios SET email_verified = TRUE, verification_token = NULL, verification_expires = NULL WHERE email = ?");
            if ($stmt->execute([$email])) {
                $success = true;
                $message = '¡Email verificado con éxito usando Supabase! Ya puedes iniciar sesión con tu cuenta.';

                // Iniciar sesión automáticamente si existe el usuario local
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $message .= ' Has sido conectado automáticamente.';
                    header('Refresh: 3; url=index.php');
                }
            } else {
                $message = 'Error al actualizar el estado de verificación en la base de datos local.';
            }
        } else {
            $message = 'Verificación exitosa en Supabase, pero no se pudo obtener el email del usuario.';
        }
    } else {
        // No filtramos detalles internos de Supabase al usuario.
        // El detalle queda en el log de servidor para debugging.
        error_log('Supabase verify failed: ' . json_encode($response));
        $message = 'Token de verificación inválido o expirado.';

        // Intentar ofrecer reenvío si se proporcionó email
        if (!empty($_GET['email'])) {
            $message .= ' <a href="resend-verification.php?email=' . urlencode((string)$_GET['email']) . '">Solicitar nuevo enlace</a>';
        }
    }
} elseif (isset($_GET['error'])) {
    // El error viene de un parametro publico: escape obligatorio al concatenar
    // con HTML porque $message se renderiza sin escape posterior.
    $message = 'Error en la verificación: ' . e($_GET['error']);
} else {
    $message = 'Token de verificación no proporcionado.';
}


$pageTitle = 'Verificación de Email';
include 'includes/head.php';
?>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header <?php echo $success ? 'bg-success' : 'bg-danger'; ?> text-white text-center">
                        <h4>
                            <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            Verificación de Email
                        </h4>
                        <small>Powered by Supabase</small>
                    </div>
                    <div class="card-body text-center">
                        <div class="<?php echo $success ? 'text-success' : 'text-danger'; ?> mb-4">
                            <?php echo $message; ?>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <?php if (isLoggedIn()): ?>
                                    Serás redirigido al inicio en unos segundos...
                                <?php else: ?>
                                    Ya puedes iniciar sesión con tu cuenta.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <?php if ($success && !isLoggedIn()): ?>
                                <a href="login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                                </a>
                            <?php endif; ?>
                            
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Ir al Inicio
                            </a>
                            
                            <?php if (!$success): ?>
                                <a href="resend-verification.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-envelope"></i> Solicitar Nuevo Enlace
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>