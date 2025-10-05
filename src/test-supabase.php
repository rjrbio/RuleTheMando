<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Solo permitir en desarrollo local
if (strpos(SITE_URL, 'localhost') === false && strpos(SITE_URL, '127.0.0.1') === false) {
    die('Esta herramienta solo está disponible en desarrollo local.');
}

$testResults = [];

// Test 1: Verificar configuración de Supabase
$testResults['config'] = [
    'supabase_url' => SUPABASE_URL !== 'https://your-project-id.supabase.co',
    'anon_key' => SUPABASE_ANON_KEY !== 'your-anon-key',
    'service_key' => SUPABASE_SERVICE_ROLE_KEY !== 'your-service-role-key'
];

// Test 2: Probar conexión a Supabase
if ($testResults['config']['supabase_url'] && $testResults['config']['anon_key']) {
    $client = getSupabaseClient();
    // Test básico de conectividad
    $response = $client->makeRequest(SUPABASE_URL . '/rest/v1/', 'GET');
    $testResults['connection'] = $response['success'] || $response['status_code'] === 401;
    $testResults['connection_response'] = $response;
} else {
    $testResults['connection'] = false;
    $testResults['connection_response'] = ['error' => 'Configuración incompleta'];
}

// Test 3: Verificar usuarios locales
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM usuarios");
$stmt->execute();
$testResults['local_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Supabase Integration - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-flask text-primary"></i> Test Integración Supabase</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <!-- Resumen de cambios -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-check-circle"></i> ✅ Cambios Implementados</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user"></i> Sistema de Login</h6>
                                <ul class="list-unstyled">
                                    <li>✅ Login con <strong>username</strong> (no email)</li>
                                    <li>✅ Validaciones robustas de username</li>
                                    <li>✅ Mensajes de error específicos</li>
                                    <li>✅ Formulario actualizado</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-cloud"></i> Integración Supabase</h6>
                                <ul class="list-unstyled">
                                    <li>✅ Cliente Supabase configurado</li>
                                    <li>✅ Envío de emails vía Supabase</li>
                                    <li>✅ Verificación automática</li>
                                    <li>✅ Sistema fallback local</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tests de configuración -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-cog"></i> Estado de Configuración</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas <?php echo $testResults['config']['supabase_url'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?> fa-3x"></i>
                                    <h6 class="mt-2">URL Supabase</h6>
                                    <small class="text-muted">
                                        <?php echo $testResults['config']['supabase_url'] ? 'Configurada' : 'Pendiente configurar'; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas <?php echo $testResults['config']['anon_key'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?> fa-3x"></i>
                                    <h6 class="mt-2">Clave Anónima</h6>
                                    <small class="text-muted">
                                        <?php echo $testResults['config']['anon_key'] ? 'Configurada' : 'Pendiente configurar'; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas <?php echo $testResults['config']['service_key'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?> fa-3x"></i>
                                    <h6 class="mt-2">Clave Service Role</h6>
                                    <small class="text-muted">
                                        <?php echo $testResults['config']['service_key'] ? 'Configurada' : 'Pendiente configurar'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test de conexión -->
                <div class="card mb-4">
                    <div class="card-header <?php echo $testResults['connection'] ? 'bg-success' : 'bg-warning'; ?> text-white">
                        <h5><i class="fas fa-wifi"></i> Test de Conexión</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <i class="fas <?php echo $testResults['connection'] ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning'; ?> fa-3x"></i>
                            </div>
                            <div class="col-md-10">
                                <h6><?php echo $testResults['connection'] ? 'Conexión exitosa' : 'Conexión no establecida'; ?></h6>
                                <p class="text-muted mb-2">
                                    <?php echo $testResults['connection'] ? 'Supabase responde correctamente.' : 'Verifica tu configuración de Supabase.'; ?>
                                </p>
                                <details>
                                    <summary class="btn btn-sm btn-outline-secondary">Ver detalles técnicos</summary>
                                    <pre class="mt-2 p-2 bg-light rounded"><code><?php echo json_encode($testResults['connection_response'], JSON_PRETTY_PRINT); ?></code></pre>
                                </details>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de usuarios -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-database"></i> Base de Datos Local</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <i class="fas fa-users fa-3x text-info"></i>
                            </div>
                            <div class="col-md-10">
                                <h6><?php echo $testResults['local_users']; ?> usuarios registrados</h6>
                                <p class="text-muted">
                                    Sistema híbrido: usuarios locales + verificación vía Supabase
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Próximos pasos -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5><i class="fas fa-tasks"></i> Próximos Pasos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Para configurar Supabase:</h6>
                                <ol>
                                    <li>Crear proyecto en <a href="https://supabase.com" target="_blank">supabase.com</a></li>
                                    <li>Configurar credenciales en <code>supabase-config.php</code></li>
                                    <li>Configurar redirect URLs en Supabase Dashboard</li>
                                    <li>Personalizar templates de email</li>
                                </ol>
                                <a href="SUPABASE_SETUP.md" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-book"></i> Ver documentación completa
                                </a>
                            </div>
                            <div class="col-md-6">
                                <h6>Para probar el sistema:</h6>
                                <ol>
                                    <li>Ir a <code>login.php</code></li>
                                    <li>Registrar nuevo usuario (username único)</li>
                                    <li>Verificar email (Supabase o local)</li>
                                    <li>Hacer login con username</li>
                                </ol>
                                <div class="d-grid gap-2 mt-2">
                                    <a href="login.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-sign-in-alt"></i> Probar Login
                                    </a>
                                    <a href="dev-email-viewer.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-envelope"></i> Ver Emails (Local)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>