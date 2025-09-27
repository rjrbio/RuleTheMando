<?php
// Configuración de la base de datos
define('DB_HOST', 'mysql-rjrbio.alwaysdata.net');
define('DB_USER', 'rjrbio'); // Cambia por tu usuario de MySQL
define('DB_PASS', '***REMOVED***'); // Cambia por tu contraseña de MySQL
define('DB_NAME', 'rjrbio_rule');

// Configuración de la aplicación
define('SITE_NAME', 'Rule the Mando');
define('SITE_URL', 'http://localhost:8080');
define('UPLOAD_PATH', 'uploads/');

// Configuración de sesión
session_start();

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Funciones útiles
function sanitize($data)
{
    return htmlspecialchars(trim($data));
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

// Helpers SEO y esquema
function slugify($text)
{
    $text = trim($text);
    if ($text === '') return '';
    // transliteración básica
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function hasColumn(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([DB_NAME, $table, $column]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}
?>