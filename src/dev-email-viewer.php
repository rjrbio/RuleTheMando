<?php
require_once 'config.php';

// Solo permitir en desarrollo local
if (strpos(SITE_URL, 'localhost') === false && strpos(SITE_URL, '127.0.0.1') === false) {
    die('Esta herramienta solo está disponible en desarrollo local.');
}

// Verificar que solo admins puedan acceder
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

// Leer el archivo de log de emails
$emailLog = '';
if (file_exists('email_log.txt')) {
    $emailLog = file_get_contents('email_log.txt');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Emails de Desarrollo - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-envelope text-primary"></i> Visor de Emails de Desarrollo</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Inicio
            </a>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Herramienta de Desarrollo:</strong> Aquí puedes ver todos los enlaces de verificación generados. 
            En producción, estos enlaces se enviarían por email.
        </div>

        <?php if (empty($emailLog)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                No hay emails de verificación generados aún.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Log de Emails Generados</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Parsear el log y mostrar de forma más organizada
                    $entries = explode('---', $emailLog);
                    $entries = array_filter($entries); // Eliminar entradas vacías
                    $entries = array_reverse($entries); // Mostrar los más recientes primero
                    
                    foreach ($entries as $entry) {
                        $lines = explode("\n", trim($entry));
                        if (count($lines) >= 2) {
                            $datetime = '';
                            $email = '';
                            $link = '';
                            $username = '';
                            
                            foreach ($lines as $line) {
                                if (strpos($line, ' - Email to: ') !== false) {
                                    $parts = explode(' - Email to: ', $line);
                                    $datetime = $parts[0];
                                    $email = $parts[1];
                                } elseif (strpos($line, 'Verification Link: ') !== false) {
                                    $link = str_replace('Verification Link: ', '', $line);
                                } elseif (strpos($line, 'Username: ') !== false) {
                                    $username = str_replace('Username: ', '', $line);
                                }
                            }
                            
                            if (!empty($link)) {
                                echo '<div class="border-bottom pb-3 mb-3">';
                                echo '<div class="row">';
                                echo '<div class="col-md-3"><strong>Fecha:</strong><br>' . htmlspecialchars($datetime) . '</div>';
                                echo '<div class="col-md-3"><strong>Email:</strong><br>' . htmlspecialchars($email) . '</div>';
                                if (!empty($username)) {
                                    echo '<div class="col-md-2"><strong>Usuario:</strong><br>' . htmlspecialchars($username) . '</div>';
                                }
                                echo '<div class="col-md-4"><strong>Acción:</strong><br>';
                                echo '<a href="' . htmlspecialchars($link) . '" class="btn btn-success btn-sm" target="_blank">';
                                echo '<i class="fas fa-check"></i> Verificar Cuenta';
                                echo '</a></div>';
                                echo '</div>';
                                echo '<div class="row mt-2">';
                                echo '<div class="col-12">';
                                echo '<small class="text-muted">Enlace: <code>' . htmlspecialchars($link) . '</code></small>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="mt-3">
                <a href="?clear_log=1" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres limpiar el log?')">
                    <i class="fas fa-trash"></i> Limpiar Log
                </a>
            </div>

            <?php
            // Funcionalidad para limpiar el log
            if (isset($_GET['clear_log']) && $_GET['clear_log'] == '1') {
                file_put_contents('email_log.txt', '');
                echo '<script>window.location.href = "dev-email-viewer.php";</script>';
            }
            ?>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>