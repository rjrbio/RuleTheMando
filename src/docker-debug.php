<?php
require_once 'config.php';

echo "<h1>🐳 Debug Docker Configuration</h1>";

echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>📋 Configuración Actual:</h3>";
echo "<p><strong>SITE_URL:</strong> <code>" . SITE_URL . "</code></p>";
echo "<p><strong>SITE_NAME:</strong> <code>" . SITE_NAME . "</code></p>";
echo "<p><strong>Request URL:</strong> <code>http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</code></p>";
echo "</div>";

echo "<div style='background: #fef3c7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>⚠️ Para Supabase Dashboard:</h3>";
echo "<p><strong>Site URL:</strong> <code>" . SITE_URL . "</code></p>";
echo "<p><strong>Redirect URLs:</strong> <code>" . SITE_URL . "/verify-supabase.php</code></p>";
echo "</div>";

echo "<div style='background: #dcfce7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
echo "<h3>✅ URLs Correctas para Docker:</h3>";
echo "<ul>";
echo "<li><strong>Login:</strong> <a href='" . SITE_URL . "/login.php'>" . SITE_URL . "/login.php</a></li>";
echo "<li><strong>Verify:</strong> <a href='" . SITE_URL . "/verify-supabase.php'>" . SITE_URL . "/verify-supabase.php</a></li>";
echo "<li><strong>Debug:</strong> <a href='" . SITE_URL . "/debug-verify-url.php'>" . SITE_URL . "/debug-verify-url.php</a></li>";
echo "</ul>";
echo "</div>";

// Test de conexión base
echo "<div style='background: #e0f2fe; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔗 Test Básico:</h3>";

if (strpos($_SERVER['HTTP_HOST'], '8080') !== false) {
    echo "<p style='color: green;'>✅ Detectado puerto 8080 - Docker configurado correctamente</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Puerto no es 8080 - Verificar configuración Docker</p>";
}

if (SITE_URL === 'http://localhost:8080') {
    echo "<p style='color: green;'>✅ SITE_URL configurado para Docker</p>";
} else {
    echo "<p style='color: red;'>❌ SITE_URL no coincide con Docker</p>";
}

echo "</div>";

echo "<hr style='margin: 30px 0;'>";
echo "<h3>🧪 Para probar:</h3>";
echo "<ol>";
echo "<li><strong>Actualiza Supabase Dashboard</strong> con las URLs de arriba</li>";
echo "<li><strong>Registra nuevo usuario</strong> en <a href='" . SITE_URL . "/login.php'>login.php</a></li>";
echo "<li><strong>Revisa el email</strong> - debería tener URL con :8080</li>";
echo "<li><strong>Haz clic en verificar</strong> - debería funcionar</li>";
echo "</ol>";

echo "<p><a href='" . SITE_URL . "/login.php' style='background: #4f46e5; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Ir a Login</a></p>";
?>