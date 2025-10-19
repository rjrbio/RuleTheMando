<?php
require_once 'config.php';

echo "<h2>🗑️ Limpiar usuarios de base de datos local</h2>";

if ($_POST['action'] ?? null) {
    try {
        if ($_POST['action'] === 'delete_all') {
            $stmt = $pdo->prepare('DELETE FROM usuarios');
            $ok = $stmt->execute();
            if ($ok) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
                echo "✅ Todos los usuarios han sido eliminados de la base de datos local";
                echo "</div>";
            }
        } elseif ($_POST['action'] === 'delete_unverified') {
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE email_verified = 0');
            $ok = $stmt->execute();
            $affected = $stmt->rowCount();
            if ($ok) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
                echo "✅ {$affected} usuarios sin verificar han sido eliminados";
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
        echo "❌ Error al eliminar usuarios: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

// Mostrar usuarios actuales
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified FROM usuarios');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)($row['total'] ?? 0);
    $verified = (int)($row['verified'] ?? 0);
    $unverified = max(0, $total - $verified);

    echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>📊 Estadísticas actuales:</strong><br>";
    echo "• Total usuarios: {$total}<br>";
    echo "• Verificados: {$verified}<br>";
    echo "• Sin verificar: {$unverified}<br>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
    echo "❌ Error al consultar la base de datos: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    
    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <strong>⚠️ Atención:</strong> Estas acciones son irreversibles. Los usuarios eliminados tendrán que registrarse nuevamente.
    </div>

    <form method="post" style="margin: 20px 0;">
        <button type="submit" name="action" value="delete_unverified" 
                style="background: #ffc107; color: #000; padding: 10px 20px; border: none; border-radius: 5px; margin: 10px;"
                onclick="return confirm('¿Eliminar solo usuarios SIN verificar?')">
            🧹 Eliminar usuarios sin verificar
        </button>
        
        <button type="submit" name="action" value="delete_all" 
                style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 10px;"
                onclick="return confirm('¿Eliminar TODOS los usuarios? Esta acción no se puede deshacer.')">
            💥 Eliminar TODOS los usuarios
        </button>
    </form>

    <div style="background: #d1ecf1; padding: 15px; border-radius: 5px;">
        <strong>💡 Recomendación:</strong><br>
        1. Primero elimina solo los usuarios sin verificar<br>
        2. Ve a tu dashboard de Supabase y elimina los usuarios de prueba<br>
        3. Prueba el registro con un email nuevo<br>
    </div>

    <p style="margin-top: 20px;">
        <a href="check-supabase-limits.php">← Volver al verificador de límites</a> | 
        <a href="index.php">Ir al inicio</a>
    </p>
</div>