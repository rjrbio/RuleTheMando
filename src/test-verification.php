<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Solo desarrollo
if (strpos(SITE_URL, 'localhost') === false) {
    die('Solo en desarrollo');
}

echo "<h1>🧪 Test Verificación Token</h1>";

// Si hay token en la URL, procesarlo
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $type = $_GET['type'] ?? 'signup';
    
    echo "<div style='background: #e0f2fe; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<h3>🔍 Token Recibido:</h3>";
    echo "<p><strong>Token:</strong> <code>" . htmlspecialchars($token) . "</code></p>";
    echo "<p><strong>Longitud:</strong> " . strlen($token) . " caracteres</p>";
    echo "<p><strong>Tipo:</strong> " . htmlspecialchars($type) . "</p>";
    echo "</div>";
    
    // Probar verificación
    echo "<div style='background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<h3>🧪 Test de Verificación:</h3>";
    
    $client = getSupabaseClient(true);
    $response = $client->verifyEmail($token, $type);
    
    echo "<h4>Respuesta completa:</h4>";
    echo "<pre style='background: #f3f4f6; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
    echo json_encode($response, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    // Analizar respuesta
    if ($response['success']) {
        echo "<div style='color: green;'>";
        echo "<h4>✅ Verificación exitosa</h4>";
        
        if (isset($response['data']['user'])) {
            $user = $response['data']['user'];
            echo "<p><strong>Email del usuario:</strong> " . ($user['email'] ?? 'No disponible') . "</p>";
            echo "<p><strong>ID:</strong> " . ($user['id'] ?? 'No disponible') . "</p>";
            echo "<p><strong>Confirmado:</strong> " . ($user['email_confirmed_at'] ?? 'No') . "</p>";
        } else {
            echo "<p>⚠️ No se encontró información del usuario en la respuesta</p>";
        }
        echo "</div>";
    } else {
        echo "<div style='color: red;'>";
        echo "<h4>❌ Error en verificación</h4>";
        echo "<p><strong>Status:</strong> " . $response['status_code'] . "</p>";
        if (isset($response['data']['error'])) {
            echo "<p><strong>Error:</strong> " . $response['data']['error'] . "</p>";
        }
        if (isset($response['data']['error_description'])) {
            echo "<p><strong>Descripción:</strong> " . $response['data']['error_description'] . "</p>";
        }
        echo "</div>";
    }
    echo "</div>";
    
} else {
    echo "<div style='background: #fef2f2; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<p>No hay token para probar. Haz clic en un enlace de verificación de email para llegar aquí.</p>";
    echo "</div>";
}

// Mostrar logs recientes
echo "<div style='background: #f0fdf4; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>📝 Logs Recientes de Supabase:</h3>";

if (file_exists('supabase_log.txt')) {
    $logs = file_get_contents('supabase_log.txt');
    $entries = array_slice(explode("\n", trim($logs)), -10); // Últimas 10 líneas
    
    echo "<pre style='background: #f3f4f6; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: scroll; font-size: 0.8em;'>";
    echo htmlspecialchars(implode("\n", $entries));
    echo "</pre>";
} else {
    echo "<p>No hay logs disponibles</p>";
}
echo "</div>";

echo "<div style='background: #f3f4f6; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔗 Enlaces útiles:</h3>";
echo "<p><a href='login.php'>Registrar nuevo usuario</a></p>";
echo "<p><a href='debug-verify-url.php'>Debug verificación general</a></p>";
echo "<p><a href='supabase_log.txt' target='_blank'>Ver logs completos</a></p>";
echo "</div>";
?>