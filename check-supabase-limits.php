<?php
require_once 'config.php';
require_once 'supabase-config.php';

echo "<h2>🔍 Verificación de límites y estado de Supabase</h2>";

// Crear cliente
$supabase = new SupabaseClient();

echo "<h3>📧 Información sobre límites de email:</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Supabase Free Tier límites típicos:</strong><br>";
echo "• Emails por hora: ~30-50<br>";
echo "• Emails por día: ~200-300<br>";
echo "• Reset: Cada 24 horas<br>";
echo "</div>";

echo "<h3>👥 Usuarios registrados en Supabase:</h3>";

// Intentar obtener usuarios (esto puede fallar si no tenemos permisos admin)
$users_response = $supabase->makeRequest(SUPABASE_URL . '/auth/v1/admin/users', 'GET', null, [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY
]);

if ($users_response && isset($users_response['data']['users'])) {
    echo "<strong>Total usuarios: " . count($users_response['data']['users']) . "</strong><br><br>";
    
    foreach ($users_response['data']['users'] as $user) {
        $created = date('Y-m-d H:i:s', strtotime($user['created_at']));
        $confirmed = $user['email_confirmed_at'] ? '✅ Verificado' : '❌ Sin verificar';
        echo "• {$user['email']} - Creado: {$created} - {$confirmed}<br>";
    }
} else {
    echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px;'>";
    echo "⚠️ No se pueden obtener usuarios (necesitamos service role key para admin)";
    echo "</div>";
}

echo "<h3>🗂️ Usuarios en base de datos local:</h3>";

try {
    $stmt = $pdo->prepare("SELECT username, email, email_verified, created_at FROM usuarios ORDER BY created_at DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Username</th><th>Email</th><th>Verificado</th><th>Creado</th></tr>";

    foreach ($rows as $row) {
        $verified = !empty($row['email_verified']) ? '✅ Sí' : '❌ No';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>{$verified}</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
    echo "❌ Error al consultar la base de datos: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<h3>💡 Opciones disponibles:</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<strong>1. Limpiar usuarios de Supabase:</strong><br>";
echo "• Ve a tu dashboard de Supabase<br>";
echo "• Authentication → Users<br>";
echo "• Borra los usuarios de prueba<br><br>";

echo "<strong>2. Limpiar base de datos local:</strong><br>";
echo "• <a href='clean-local-db.php'>🗑️ Limpiar usuarios locales</a><br><br>";

echo "<strong>3. Usar email temporal:</strong><br>";
echo "• Prueba con emails de 10minutemail.com<br>";
echo "• O crea nuevas cuentas de Gmail<br><br>";

echo "<strong>4. Esperar reset (24h):</strong><br>";
echo "• Los límites se resetean cada 24 horas<br>";
echo "</div>";

echo "<h3>📊 Logs recientes:</h3>";
if (file_exists('supabase_log.txt')) {
    $logs = file_get_contents('supabase_log.txt');
    $recent_logs = array_slice(explode("\n", $logs), -10);
    
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;'>";
    echo implode("\n", $recent_logs);
    echo "</pre>";
}
?>