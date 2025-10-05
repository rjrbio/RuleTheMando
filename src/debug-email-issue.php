<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Solo desarrollo local
if (strpos(SITE_URL, 'localhost') === false) {
    die('Solo en desarrollo');
}

// Leer logs recientes
function getRecentLogs($file, $lines = 10) {
    if (!file_exists($file)) return 'No logs';
    
    $log = file_get_contents($file);
    $entries = explode("\n", trim($log));
    return implode("\n", array_slice($entries, -$lines));
}

// Verificar último registro
$stmt = $pdo->prepare("SELECT * FROM usuarios ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$lastUser = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Email Issue - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-search"></i> Debug Email Issue</h1>
            <a href="index.php" class="btn btn-secondary">Volver</a>
        </div>

        <!-- Explicación del problema -->
        <div class="alert alert-info">
            <h5><i class="fas fa-lightbulb"></i> Problema identificado:</h5>
            <p><strong>Supabase envía automáticamente el email cuando registras un usuario.</strong> Llamar a `sendVerificationEmail` después causa un error de límite de velocidad (429).</p>
        </div>

        <!-- Estado del último usuario -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-user"></i> Último Usuario Registrado</h5>
            </div>
            <div class="card-body">
                <?php if ($lastUser): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($lastUser['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($lastUser['email']); ?></p>
                            <p><strong>Creado:</strong> <?php echo $lastUser['created_at']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email Verificado:</strong> 
                                <span class="badge <?php echo $lastUser['email_verified'] ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $lastUser['email_verified'] ? 'SÍ' : 'NO'; ?>
                                </span>
                            </p>
                            <p><strong>Token Local:</strong> <?php echo $lastUser['verification_token'] ? 'Presente' : 'Ausente'; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No hay usuarios registrados</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logs recientes -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-file-alt"></i> Logs Recientes de Supabase</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: scroll; font-size: 0.8em;"><code><?php echo htmlspecialchars(getRecentLogs('supabase_log.txt', 20)); ?></code></pre>
            </div>
        </div>

        <!-- Análisis del flujo -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-check-circle"></i> Flujo Corregido</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>❌ Flujo Anterior (Incorrecto):</h6>
                        <ol>
                            <li>Crear usuario local</li>
                            <li>Registrar en Supabase → <span class="text-success">Email enviado automáticamente</span></li>
                            <li>Llamar sendVerificationEmail → <span class="text-danger">Error 429</span></li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>✅ Flujo Nuevo (Correcto):</h6>
                        <ol>
                            <li>Crear usuario local</li>
                            <li>Registrar en Supabase → <span class="text-success">Email enviado automáticamente</span></li>
                            <li>Verificar <code>confirmation_sent_at</code> → <span class="text-success">¡Listo!</span></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test manual -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-play-circle"></i> Para Probar</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-block">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Probar Nuevo Registro
                    </a>
                    <a href="debug-supabase.php" class="btn btn-info">
                        <i class="fas fa-cog"></i> Debug General
                    </a>
                    <button class="btn btn-secondary" onclick="location.reload()">
                        <i class="fas fa-refresh"></i> Actualizar Logs
                    </button>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Nota:</strong> El email se envía automáticamente cuando Supabase registra al usuario. 
                        Solo necesitas revisar tu bandeja de entrada y carpeta de spam.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>