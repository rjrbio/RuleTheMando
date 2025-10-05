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
    // Determinar qué token usar
    $token = isset($_GET['token']) ? sanitize($_GET['token']) : sanitize($_GET['token_hash']);
    $type = isset($_GET['type']) ? sanitize($_GET['type']) : 'signup';

    // Log para debugging
    SupabaseClient::logResponse([
        'token_length' => strlen($token),
        'token_preview' => substr($token, 0, 20) . '...',
        'type' => $type,
        'full_url' => $_SERVER['REQUEST_URI'],
        'all_params' => $_GET
    ], 'VERIFY_URL_RECEIVED');

    // Verificar con Supabase
    $response = verifySupabaseEmail($token, $type);

    // Log detallado de la respuesta
    SupabaseClient::logResponse($response, 'VERIFY_RESPONSE_DETAIL');

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
            $message = 'Verificación exitosa en Supabase, pero no se pudo obtener el email del usuario. Respuesta: ' . json_encode($response['data']);
        }
    } else {
        // Log del error para debugging
        SupabaseClient::logResponse($response, 'VERIFY_ERROR');

        $errorMsg = 'Token de verificación inválido o expirado.';
        if (isset($response['data']['error'])) {
            $errorMsg .= ' Error: ' . $response['data']['error'];
        }
        if (isset($response['data']['error_description'])) {
            $errorMsg .= ' (' . $response['data']['error_description'] . ')';
        }
        $message = $errorMsg;

        // Intentar ofrecer reenvío si se proporcionó email
        if (!empty($_GET['email'])) {
            $emailParam = sanitize($_GET['email']);
            $message .= ' <a href="resend-verification.php?email=' . urlencode($emailParam) . '">Solicitar nuevo enlace</a>';
        }
    }
} elseif (isset($_GET['error'])) {
    $error = sanitize($_GET['error']);
    $message = 'Error en la verificación: ' . $error;
} else {
    $message = 'Token de verificación no proporcionado.';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
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
                
                <!-- Debug info para desarrollo -->
                <?php if (strpos(SITE_URL, 'localhost') !== false): ?>
                    <div class="card mt-3">
                        <div class="card-header bg-warning text-dark">
                            <small><i class="fas fa-bug"></i> Debug Info (solo en desarrollo)</small>
                        </div>
                        <div class="card-body">
                            <small>
                                <strong>Token:</strong> <?php echo isset($_GET['token']) ? htmlspecialchars($_GET['token']) : 'No proporcionado'; ?><br>
                                <strong>Type:</strong> <?php echo isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'No proporcionado'; ?><br>
                                <strong>Supabase Response:</strong> <?php echo isset($response) ? json_encode($response, JSON_PRETTY_PRINT) : 'N/A'; ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>