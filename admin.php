<?php
require_once 'config.php';
require_once 'supabase-config.php';

// Verificar que el usuario sea admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$success = false;

// Crear directorio de uploads si no existe
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}

// Procesar formulario de añadir juego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    $titulo = sanitize($_POST['titulo']);
    $descripcion = sanitize($_POST['descripcion']);
    $fecha_lanzamiento = $_POST['fecha_lanzamiento'];
    $plataforma = sanitize($_POST['plataforma']);
    $genero = sanitize($_POST['genero']);
    $desarrollador = sanitize($_POST['desarrollador']);
    $precio = $_POST['precio'] ? floatval($_POST['precio']) : null;
    $es_futuro_lanzamiento = isset($_POST['es_futuro_lanzamiento']) ? 1 : 0;

    // Manejar subida de imagen
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['imagen']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($filetype), $allowed)) {
            $imagen = uniqid() . '.' . $filetype;
            $upload_path = UPLOAD_PATH . $imagen;

            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path)) {
                $imagen = '';
                $message = 'Error al subir la imagen.';
            }
        } else {
            $message = 'Formato de imagen no válido. Usa JPG, PNG, GIF o WebP.';
        }
    }

    if (empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO videojuegos (titulo, descripcion, fecha_lanzamiento, plataforma, genero, desarrollador, imagen, precio, es_futuro_lanzamiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([$titulo, $descripcion, $fecha_lanzamiento, $plataforma, $genero, $desarrollador, $imagen, $precio, $es_futuro_lanzamiento])) {
            $success = true;
            $message = 'Juego añadido exitosamente.';
        } else {
            $message = 'Error al añadir el juego.';
        }
    }
}

// Procesar eliminación de juego
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $game_id = intval($_GET['delete']);

    // Obtener información del juego para eliminar imagen
    $stmt = $pdo->prepare("SELECT imagen FROM videojuegos WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($game && $game['imagen'] && file_exists(UPLOAD_PATH . $game['imagen'])) {
        unlink(UPLOAD_PATH . $game['imagen']);
    }

    $stmt = $pdo->prepare("DELETE FROM videojuegos WHERE id = ?");
    if ($stmt->execute([$game_id])) {
        $success = true;
        $message = 'Juego eliminado exitosamente.';
    } else {
        $message = 'Error al eliminar el juego.';
    }
}

// Acciones de gestión de usuarios (solo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $action = $_POST['user_action'];
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    try {
        if ($action === 'verify_user' && $userId > 0) {
            $stmt = $pdo->prepare('UPDATE usuarios SET email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
            $ok = $stmt->execute([$userId]);
            $success = $ok;
            $message = $ok ? 'Usuario marcado como verificado.' : 'No se pudo marcar el usuario como verificado.';
        } elseif ($action === 'unverify_user' && $userId > 0) {
            $stmt = $pdo->prepare('UPDATE usuarios SET email_verified = 0 WHERE id = ?');
            $ok = $stmt->execute([$userId]);
            $success = $ok;
            $message = $ok ? 'Usuario marcado como no verificado.' : 'No se pudo actualizar el estado de verificación.';
        } elseif ($action === 'resend_verification' && $userId > 0) {
            $stmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = ?');
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['email'])) {
                $resp = sendSupabaseVerificationEmail($u['email'], SITE_URL . '/verify-supabase.php');
                $success = !empty($resp['success']);
                $message = $success ? 'Email de verificación reenviado (vía Supabase).' : 'No se pudo reenviar el email de verificación.';
            } else {
                $success = false;
                $message = 'El usuario no tiene email asignado.';
            }
        } elseif ($action === 'reset_password' && $userId > 0) {
            // Generar contraseña temporal segura
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
            $temp = '';
            for ($i = 0; $i < 12; $i++) {
                $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
            $ok = $stmt->execute([$hash, $userId]);
            $success = $ok;
            if ($ok) {
                $message = 'Contraseña reseteada. Temporal: ' . htmlspecialchars($temp) . ' (cámbiala tras el primer login)';
            } else {
                $message = 'No se pudo resetear la contraseña.';
            }
        } elseif ($action === 'set_role' && $userId > 0 && isset($_POST['role'])) {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $stmt = $pdo->prepare('UPDATE usuarios SET role = ? WHERE id = ?');
            $ok = $stmt->execute([$role, $userId]);
            $success = $ok;
            $message = $ok ? 'Rol actualizado.' : 'No se pudo actualizar el rol.';
        }
    } catch (Exception $e) {
        $success = false;
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Obtener todos los juegos para la tabla
$stmt = $pdo->prepare("SELECT * FROM videojuegos ORDER BY created_at DESC");
$stmt->execute();
$juegos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM videojuegos");
$stmt->execute();
$totalJuegos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM videojuegos WHERE es_futuro_lanzamiento = TRUE");
$stmt->execute();
$futurosLanzamientos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios");
$stmt->execute();
$totalUsuarios = $stmt->fetchColumn();

// Obtener usuarios para gestión
$stmt = $pdo->prepare('SELECT id, username, email, email_verified, role, created_at FROM usuarios ORDER BY created_at DESC');
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            background: linear-gradient(135deg, #1f2937, #374151);
            min-height: 100vh;
            position: fixed;
            width: 250px;
            left: 0;
            top: 0;
            z-index: 1000;
            padding-top: 20px;
        }

        .admin-main {
            margin-left: 250px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }

        .sidebar-brand {
            color: white;
            text-decoration: none;
            padding: 1rem 1.5rem;
            display: block;
            font-weight: 700;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav-item {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            display: block;
            transition: all 0.3s ease;
        }

        .sidebar-nav-link:hover,
        .sidebar-nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .stat-card {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .game-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .game-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-gamepad me-2"></i><?php echo SITE_NAME; ?>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#dashboard" class="sidebar-nav-link active" data-bs-toggle="tab">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#add-game" class="sidebar-nav-link" data-bs-toggle="tab">
                    <i class="fas fa-plus me-2"></i>Añadir Juego
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#manage-games" class="sidebar-nav-link" data-bs-toggle="tab">
                    <i class="fas fa-list me-2"></i>Gestionar Juegos
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#manage-users" class="sidebar-nav-link" data-bs-toggle="tab">
                    <i class="fas fa-users me-2"></i>Gestionar Usuarios
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="admin-change-password.php" class="sidebar-nav-link">
                    <i class="fas fa-key me-2"></i>Cambiar contraseña
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php" class="sidebar-nav-link">
                    <i class="fas fa-home me-2"></i>Ver Sitio Web
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="logout.php" class="sidebar-nav-link">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Header -->
        <div class="admin-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Panel de Administración</h1>
                <div>
                    <span class="text-muted">Bienvenido, </span>
                    <strong><?php echo sanitize($_SESSION['username']); ?></strong>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="container-fluid px-4">
            <?php if ($message): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="tab-content">
                <!-- Dashboard Tab -->
                <div class="tab-pane fade show active" id="dashboard">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $totalJuegos; ?></h4>
                                        <p class="mb-0">Total de Juegos</p>
                                    </div>
                                    <i class="fas fa-gamepad stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $futurosLanzamientos; ?></h4>
                                        <p class="mb-0">Futuros Lanzamientos</p>
                                    </div>
                                    <i class="fas fa-rocket stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0"><?php echo $totalUsuarios; ?></h4>
                                        <p class="mb-0">Usuarios Registrados</p>
                                    </div>
                                    <i class="fas fa-users stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <h4 class="mb-3">Últimos Juegos Añadidos</h4>
                            <div class="game-table">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Imagen</th>
                                                <th>Título</th>
                                                <th>Plataforma</th>
                                                <th>Fecha de Lanzamiento</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($juegos, 0, 5) as $juego): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/50x50'; ?>"
                                                            alt="<?php echo sanitize($juego['titulo']); ?>"
                                                            class="game-image">
                                                    </td>
                                                    <td><strong><?php echo sanitize($juego['titulo']); ?></strong></td>
                                                    <td><?php echo sanitize($juego['plataforma']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($juego['es_futuro_lanzamiento']): ?>
                                                            <span class="badge bg-warning">Próximamente</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Disponible</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Game Tab -->
                <div class="tab-pane fade" id="add-game">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0"><i class="fas fa-plus me-2"></i>Añadir Nuevo Juego</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data" class="needs-validation"
                                        novalidate>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control" id="titulo" name="titulo"
                                                        placeholder="Título" required>
                                                    <label for="titulo">Título del Juego</label>
                                                    <div class="invalid-feedback">Por favor, ingresa el título del
                                                        juego.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control" id="desarrollador"
                                                        name="desarrollador" placeholder="Desarrollador" required>
                                                    <label for="desarrollador">Desarrollador</label>
                                                    <div class="invalid-feedback">Por favor, ingresa el desarrollador.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="descripcion" name="descripcion"
                                                style="height: 100px" placeholder="Descripción" required></textarea>
                                            <label for="descripcion">Descripción</label>
                                            <div class="invalid-feedback">Por favor, ingresa una descripción.</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <input type="date" class="form-control" id="fecha_lanzamiento"
                                                        name="fecha_lanzamiento" required>
                                                    <label for="fecha_lanzamiento">Fecha de Lanzamiento</label>
                                                    <div class="invalid-feedback">Por favor, selecciona la fecha de
                                                        lanzamiento.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select" id="plataforma" name="plataforma"
                                                        required>
                                                        <option value="">Seleccionar plataforma</option>
                                                        <option value="PC">PC</option>
                                                        <option value="PlayStation 5">PlayStation 5</option>
                                                        <option value="PlayStation 4">PlayStation 4</option>
                                                        <option value="Xbox Series X/S">Xbox Series X/S</option>
                                                        <option value="Xbox One">Xbox One</option>
                                                        <option value="Nintendo Switch">Nintendo Switch</option>
                                                        <option value="iOS">iOS</option>
                                                        <option value="Android">Android</option>
                                                        <option value="Multiplataforma">Multiplataforma</option>
                                                    </select>
                                                    <label for="plataforma">Plataforma</label>
                                                    <div class="invalid-feedback">Por favor, selecciona una plataforma.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <select class="form-select" id="genero" name="genero" required>
                                                        <option value="">Seleccionar género</option>
                                                        <option value="Acción">Acción</option>
                                                        <option value="Aventura">Aventura</option>
                                                        <option value="RPG">RPG</option>
                                                        <option value="Estrategia">Estrategia</option>
                                                        <option value="Simulación">Simulación</option>
                                                        <option value="Deportes">Deportes</option>
                                                        <option value="Carreras">Carreras</option>
                                                        <option value="Shooter">Shooter</option>
                                                        <option value="Puzzle">Puzzle</option>
                                                        <option value="Terror">Terror</option>
                                                        <option value="Plataformas">Plataformas</option>
                                                        <option value="Indie">Indie</option>
                                                    </select>
                                                    <label for="genero">Género</label>
                                                    <div class="invalid-feedback">Por favor, selecciona un género.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="number" step="0.01" class="form-control" id="precio"
                                                        name="precio" placeholder="Precio">
                                                    <label for="precio">Precio (€)</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="imagen" class="form-label">Imagen del Juego</label>
                                                    <input type="file" class="form-control" id="imagen" name="imagen"
                                                        accept="image/*">
                                                    <div class="form-text">Formatos soportados: JPG, PNG, GIF, WebP
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" id="es_futuro_lanzamiento"
                                                name="es_futuro_lanzamiento">
                                            <label class="form-check-label" for="es_futuro_lanzamiento">
                                                <strong>Es un futuro lanzamiento</strong>
                                                <small class="text-muted d-block">Marcar si el juego aún no se ha
                                                    lanzado</small>
                                            </label>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" name="add_game" class="btn btn-primary btn-lg">
                                                <i class="fas fa-plus me-2"></i>Añadir Juego
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manage Games Tab -->
                <div class="tab-pane fade" id="manage-games">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Gestionar Juegos</h4>
                        <div class="input-group" style="max-width: 300px;">
                            <input type="text" class="form-control" id="searchGames" placeholder="Buscar juegos...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div class="game-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="gamesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Imagen</th>
                                        <th>Título</th>
                                        <th>Desarrollador</th>
                                        <th>Plataforma</th>
                                        <th>Género</th>
                                        <th>Fecha</th>
                                        <th>Precio</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($juegos as $juego): ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/50x50'; ?>"
                                                    alt="<?php echo sanitize($juego['titulo']); ?>" class="game-image">
                                            </td>
                                            <td>
                                                <strong><?php echo sanitize($juego['titulo']); ?></strong>
                                                <br><small
                                                    class="text-muted"><?php echo sanitize(substr($juego['descripcion'], 0, 50)) . '...'; ?></small>
                                            </td>
                                            <td><?php echo sanitize($juego['desarrollador']); ?></td>
                                            <td><?php echo sanitize($juego['plataforma']); ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary"><?php echo sanitize($juego['genero']); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?></td>
                                            <td>
                                                <?php if ($juego['precio']): ?>
                                                    <span
                                                        class="text-success">€<?php echo number_format($juego['precio'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($juego['es_futuro_lanzamiento']): ?>
                                                    <span class="badge bg-warning">Próximamente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Disponible</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        onclick="editGame(<?php echo $juego['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete(<?php echo $juego['id']; ?>, '<?php echo addslashes($juego['titulo']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Manage Users Tab -->
                <div class="tab-pane fade" id="manage-users">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Gestionar Usuarios</h4>
                        <div class="input-group" style="max-width: 300px;">
                            <input type="text" class="form-control" id="searchUsers" placeholder="Buscar usuarios...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div class="game-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="usersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Verificado</th>
                                        <th>Rol</th>
                                        <th>Creado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td><?php echo (int)$u['id']; ?></td>
                                            <td><strong><?php echo sanitize($u['username']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                            <td>
                                                <?php if (!empty($u['email_verified'])): ?>
                                                    <span class="badge bg-success">Sí</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                    <select name="role" class="form-select form-select-sm" style="width: 120px;">
                                                        <option value="user" <?php echo $u['role']==='user'?'selected':''; ?>>user</option>
                                                        <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>admin</option>
                                                    </select>
                                                    <button class="btn btn-sm btn-outline-primary" name="user_action" value="set_role" type="submit">Aplicar</button>
                                                </form>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if (empty($u['email_verified'])): ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-success" name="user_action" value="verify_user" type="submit" title="Marcar como verificado">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-secondary" name="user_action" value="resend_verification" type="submit" title="Reenviar verificación">
                                                                <i class="fas fa-envelope"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-warning" name="user_action" value="unverify_user" type="submit" title="Marcar como no verificado">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" name="user_action" value="reset_password" type="submit" title="Resetear contraseña">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de que quieres eliminar el juego <strong id="gameTitle"></strong>?
                    <br><small class="text-muted">Esta acción no se puede deshacer.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de formulario
        (function () {
            'use strict';
            window.addEventListener('load', function () {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function (form) {
                    form.addEventListener('submit', function (event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Manejar tabs del sidebar
        document.querySelectorAll('.sidebar-nav-link[data-bs-toggle="tab"]').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();

                // Remover clase active de todos los enlaces
                document.querySelectorAll('.sidebar-nav-link').forEach(function (link) {
                    link.classList.remove('active');
                });

                // Añadir clase active al enlace clickeado
                this.classList.add('active');

                // Mostrar el tab correspondiente
                var targetTab = new bootstrap.Tab(this);
                targetTab.show();
            });
        });

        // Búsqueda de juegos
        document.getElementById('searchGames').addEventListener('keyup', function () {
            var searchTerm = this.value.toLowerCase();
            var table = document.getElementById('gamesTable');
            var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (var i = 0; i < rows.length; i++) {
                var title = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                var developer = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                var platform = rows[i].getElementsByTagName('td')[3].textContent.toLowerCase();

                if (title.includes(searchTerm) || developer.includes(searchTerm) || platform.includes(searchTerm)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });

        // Función para confirmar eliminación
        function confirmDelete(gameId, gameTitle) {
            document.getElementById('gameTitle').textContent = gameTitle;
            document.getElementById('confirmDeleteBtn').href = '?delete=' + gameId;

            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Función para editar juego (placeholder - implementar según necesidades)
        function editGame(gameId) {
            alert('Funcionalidad de edición en desarrollo. ID del juego: ' + gameId);
            // Aquí puedes implementar un modal de edición o redirigir a una página de edición
        }

        // Preview de imagen al seleccionar archivo
        document.getElementById('imagen').addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    // Crear preview si no existe
                    var preview = document.getElementById('imagePreview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.style.maxWidth = '200px';
                        preview.style.maxHeight = '200px';
                        preview.style.marginTop = '10px';
                        preview.style.borderRadius = '8px';
                        preview.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                        e.target.parentNode.appendChild(preview);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Auto-dismiss alerts después de 5 segundos
        setTimeout(function () {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>