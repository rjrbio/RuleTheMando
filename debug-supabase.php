<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Solo desarrollo local
if (strpos(SITE_URL, 'localhost') === false) {
    die('Solo en desarrollo');
}

$test_email = 'test@example.com';
$test_password = 'test123456';
$results = [];

// Test 1: Configuración
$results['config'] = [
    'supabase_url' => SUPABASE_URL,
    'site_url' => SITE_URL,
    'configured' => isSupabaseConfigured()
];

// Test 2: Conectividad
$results['connection'] = testSupabaseConnection();

// Test 3: Simulación de registro (NO real)
if (isset($_POST['test_register']) && isSupabaseConfigured()) {
    $results['register_test'] = registerSupabaseUser($test_email, $test_password, ['test' => true]);
}

// Test 4: Revisión de logs
$supabase_log = file_exists('supabase_log.txt') ? file_get_contents('supabase_log.txt') : 'No logs yet';
$email_log = file_exists('email_log.txt') ? file_get_contents('email_log.txt') : 'No logs yet';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Supabase - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-4">
        <h1><i class="fas fa-bug"></i> Debug Supabase Integration</h1>
        
        <!-- Estado de configuración -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5>📋 Estado de Configuración</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Supabase URL:</strong> <code><?php echo htmlspecialchars($results['config']['supabase_url']); ?></code></p>
                        <p><strong>Site URL:</strong> <code><?php echo htmlspecialchars($results['config']['site_url']); ?></code></p>
                        <p><strong>Configurado:</strong> 
                            <span class="badge <?php echo $results['config']['configured'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $results['config']['configured'] ? 'SÍ' : 'NO'; ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Conexión:</strong> 
                            <span class="badge <?php echo ($results['connection']['success'] ?? false) ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ($results['connection']['success'] ?? false) ? 'EXITOSA' : 'FALLO'; ?>
                            </span>
                        </p>
                        <?php if (isset($results['connection']['status_code'])): ?>
                            <p><strong>Status Code:</strong> <?php echo $results['connection']['status_code']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test de registro -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5>🧪 Test de Registro</h5>
            </div>
            <div class="card-body">
                <?php if (!$results['config']['configured']): ?>
                    <div class="alert alert-danger">Supabase no está configurado. Ve a CONFIGURACION_CRITICA.md</div>
                <?php else: ?>
                    <form method="post">
                        <button type="submit" name="test_register" class="btn btn-warning">
                            <i class="fas fa-flask"></i> Probar Registro (Test)
                        </button>
                        <small class="text-muted">Esto intentará registrar test@example.com en Supabase</small>
                    </form>

                    <?php if (isset($results['register_test'])): ?>
                        <div class="mt-3">
                            <h6>Resultado del test:</h6>
                            <pre class="bg-light p-3 rounded"><code><?php echo json_encode($results['register_test'], JSON_PRETTY_PRINT); ?></code></pre>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logs -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5>📝 Logs Recientes</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#supabase-logs">Supabase</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#email-logs">Email Local</a>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div id="supabase-logs" class="tab-pane fade show active">
                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: scroll;"><code><?php echo htmlspecialchars($supabase_log); ?></code></pre>
                    </div>
                    <div id="email-logs" class="tab-pane fade">
                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: scroll;"><code><?php echo htmlspecialchars($email_log); ?></code></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enlaces útiles -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5>🔗 Enlaces Útiles</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-block">
                    <a href="login.php" class="btn btn-primary">Ir a Login</a>
                    <a href="test-supabase.php" class="btn btn-info">Test Completo</a>
                    <a href="dev-email-viewer.php" class="btn btn-secondary">Ver Emails</a>
                    <a href="CONFIGURACION_CRITICA.md" class="btn btn-warning">Configuración</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>