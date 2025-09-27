<?php
require_once 'config.php';

// Solo desarrollo
if (strpos(SITE_URL, 'localhost') === false) {
    die('Solo en desarrollo');
}

echo "<h1>🐳 Debug Verificación URL (Docker)</h1>";

echo "<div style='background: #fef3c7; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>";
echo "<strong>⚠️ Docker Mode:</strong> SITE_URL configurado para localhost:8080";
echo "</div>";

echo "<h3>Parámetros recibidos:</h3>";
echo "<pre>";
var_dump($_GET);
echo "</pre>";

echo "<h3>Headers recibidos:</h3>";
echo "<pre>";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        echo $key . ': ' . $value . "\n";
    }
}
echo "</pre>";

echo "<h3>Información completa:</h3>";
echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "QUERY_STRING: " . $_SERVER['QUERY_STRING'] . "\n";
echo "</pre>";

// Si hay token, intentar procesar
if (isset($_GET['token'])) {
    require_once 'supabase-config.php';
    
    echo "<h3>Test de verificación con Supabase:</h3>";
    
    $token = $_GET['token'];
    $type = $_GET['type'] ?? 'signup';
    
    echo "<p><strong>Token:</strong> " . htmlspecialchars($token) . "</p>";
    echo "<p><strong>Type:</strong> " . htmlspecialchars($type) . "</p>";
    
    $client = getSupabaseClient(true);
    $response = $client->verifyEmail($token, $type);
    
    echo "<h4>Respuesta de Supabase:</h4>";
    echo "<pre>";
    echo json_encode($response, JSON_PRETTY_PRINT);
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='login.php'>Volver a Login</a></p>";
?>