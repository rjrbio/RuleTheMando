<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Cambia por tu usuario de MySQL
define('DB_PASS', ''); // Cambia por tu contraseña de MySQL
define('DB_NAME', 'rule_the_mando');

// Configuración de la aplicación
define('SITE_NAME', 'Rule the Mando');
define('SITE_URL', 'http://localhost/rule_the_mando');
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
?>