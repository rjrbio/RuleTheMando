<?php
require_once 'config.php';

// Revocar el token "Recuerdame" en BD y borrar la cookie del cliente.
// Tiene que ir antes de session_destroy() porque algunas implementaciones
// pueden depender de la sesion para identificar al usuario.
remember_me_clear();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al índice con mensaje de despedida
redirect('index.php?logout=success');
?>