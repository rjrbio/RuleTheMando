<?php
require_once 'config.php';
require_once 'supabase-config.php';
require_once 'includes/db.php';

// Verificar que el usuario sea admin (re-valida rol contra BD)
requireAdmin();

ensure_auxiliary_tables();

$message = '';
$success = false;
$openAddTab = false; // Para abrir el tab de alta cuando haya errores
$oldAdd = []; // Para repoblar el formulario en caso de error
// Los mensajes flash via $_SESSION['flash_*'] los pinta includes/flash.php
// como toast. La variable $message local solo se usa para errores de
// validacion sin redireccion (PRG falla en validacion -> render con error).

// Crear directorio de uploads si no existe
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

require_once 'admin/handlers.php';
require_once 'admin/data.php';


$pageTitle = 'Panel de Administración';
ob_start();
?>
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
<?php
$extraHead = ob_get_clean();
include 'includes/head.php';
?>
<body>
<?php include 'includes/flash.php'; ?>
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-gamepad me-2"></i><?php echo SITE_NAME; ?>
        </a>

        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="#dashboard" class="sidebar-nav-link active">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#add-game" class="sidebar-nav-link">
                    <i class="fas fa-plus me-2"></i>Añadir Juego
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#manage-games" class="sidebar-nav-link">
                    <i class="fas fa-list me-2"></i>Gestionar Juegos
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#manage-users" class="sidebar-nav-link">
                    <i class="fas fa-users me-2"></i>Gestionar Usuarios
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="#manage-reviews" class="sidebar-nav-link">
                    <i class="fas fa-comments me-2"></i>Gestionar Críticas
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
                    <strong><?php echo e($_SESSION['username']); ?></strong>
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
                                                            alt="<?php echo e($juego['titulo']); ?>"
                                                            class="game-image">
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <strong><?php echo e($juego['titulo']); ?></strong>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-warning text-dark" title="Modificar" data-bs-toggle="modal" data-bs-target="#editModal"
                                                                data-id="<?php echo (int)$juego['id']; ?>"
                                                                data-titulo="<?php echo htmlspecialchars($juego['titulo'], ENT_QUOTES); ?>"
                                                                data-descripcion="<?php echo htmlspecialchars($juego['descripcion'], ENT_QUOTES); ?>"
                                                                data-fecha="<?php echo htmlspecialchars($juego['fecha_lanzamiento']); ?>"
                                                                data-plataforma="<?php echo htmlspecialchars($juego['plataforma'], ENT_QUOTES); ?>"
                                                                data-genero="<?php echo htmlspecialchars($juego['genero'], ENT_QUOTES); ?>"
                                                                data-desarrollador="<?php echo htmlspecialchars($juego['desarrollador'], ENT_QUOTES); ?>"
                                                                
                                                                data-futuro="<?php echo !empty($juego['es_futuro_lanzamiento']) ? '1' : '0'; ?>"
                                                                data-imagen="<?php echo htmlspecialchars($juego['imagen']); ?>"
                                                                data-slug="<?php echo htmlspecialchars($juego['slug'] ?? ''); ?>">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            <a class="btn btn-outline-secondary" target="_blank" title="Ver vista pública"
                                                               href="game.php?<?php echo !empty($juego['slug']) ? ('slug=' . urlencode($juego['slug'])) : ('id=' . (int)$juego['id']); ?>">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo e($juego['plataforma']); ?></td>
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
                                    <form method="POST" enctype="multipart/form-data" id="addGameForm" class="needs-validation <?php echo $openAddTab ? 'was-validated' : ''; ?>"
                                        novalidate>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="add_game" value="1">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control" id="titulo" name="titulo"
                                                        placeholder="Título" required value="<?php echo htmlspecialchars($oldAdd['titulo'] ?? ''); ?>">
                                                    <label for="titulo">Título del Juego</label>
                                                    <div class="invalid-feedback">Por favor, ingresa el título del
                                                        juego.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating mb-3">
                                                    <input type="text" class="form-control" id="desarrollador"
                                                        name="desarrollador" placeholder="Desarrollador" required value="<?php echo htmlspecialchars($oldAdd['desarrollador'] ?? ''); ?>">
                                                    <label for="desarrollador">Desarrollador</label>
                                                    <div class="invalid-feedback">Por favor, ingresa el desarrollador.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" id="descripcion" name="descripcion"
                                                style="height: 100px" placeholder="Descripción" required><?php echo htmlspecialchars($oldAdd['descripcion'] ?? ''); ?></textarea>
                                            <label for="descripcion">Descripción</label>
                                            <div class="invalid-feedback">Por favor, ingresa una descripción.</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <input type="date" class="form-control" id="fecha_lanzamiento"
                                                        name="fecha_lanzamiento" required value="<?php echo htmlspecialchars($oldAdd['fecha_lanzamiento'] ?? ''); ?>">
                                                    <label for="fecha_lanzamiento">Fecha de Lanzamiento</label>
                                                    <div class="invalid-feedback">Por favor, selecciona la fecha de
                                                        lanzamiento.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <input list="plataformasList" class="form-control" id="plataforma" name="plataforma" placeholder="Plataforma" required value="<?php echo htmlspecialchars($oldAdd['plataforma'] ?? ''); ?>">
                                                    <label for="plataforma">Plataforma</label>
                                                    <datalist id="plataformasList">
                                                        <?php
                                                        $pls = $pdo->query("SELECT DISTINCT plataforma FROM videojuegos WHERE plataforma IS NOT NULL AND plataforma <> '' ORDER BY plataforma ASC")->fetchAll(PDO::FETCH_COLUMN);
                                                        foreach ($pls as $p) echo '<option value="' . htmlspecialchars($p) . '"></option>';
                                                        ?>
                                                    </datalist>
                                                    <div class="invalid-feedback">Por favor, especifica una plataforma.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-floating mb-3">
                                                    <input list="generosList" class="form-control" id="genero" name="genero" placeholder="Género" required value="<?php echo htmlspecialchars($oldAdd['genero'] ?? ''); ?>">
                                                    <label for="genero">Género</label>
                                                    <datalist id="generosList">
                                                        <?php
                                                        $gens = $pdo->query("SELECT DISTINCT genero FROM videojuegos WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero ASC")->fetchAll(PDO::FETCH_COLUMN);
                                                        foreach ($gens as $g) echo '<option value="' . htmlspecialchars($g) . '"></option>';
                                                        ?>
                                                    </datalist>
                                                    <div class="invalid-feedback">Por favor, especifica un género.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            
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
                                                name="es_futuro_lanzamiento" <?php echo !empty($oldAdd['es_futuro_lanzamiento']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="es_futuro_lanzamiento">
                                                <strong>Es un futuro lanzamiento</strong>
                                                <small class="text-muted d-block">Marcar si el juego aún no se ha
                                                    lanzado</small>
                                            </label>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" name="add_game" value="1" class="btn btn-primary btn-lg">
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
                                        
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($juegos as $juego): ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/50x50'; ?>"
                                                    alt="<?php echo e($juego['titulo']); ?>" class="game-image">
                                            </td>
                                            <td>
                                                <strong><?php echo e($juego['titulo']); ?></strong>
                                                <br><small
                                                    class="text-muted"><?php echo e(substr($juego['descripcion'], 0, 50)) . '...'; ?></small>
                                            </td>
                                            <td><?php echo e($juego['desarrollador']); ?></td>
                                            <td><?php echo e($juego['plataforma']); ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary"><?php echo e($juego['genero']); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?></td>
                                            
                                            <td>
                                                <?php if ($juego['es_futuro_lanzamiento']): ?>
                                                    <span class="badge bg-warning">Próximamente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Disponible</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#editModal"
                                                        data-id="<?php echo (int)$juego['id']; ?>"
                                                        data-titulo="<?php echo htmlspecialchars($juego['titulo'], ENT_QUOTES); ?>"
                                                        data-descripcion="<?php echo htmlspecialchars($juego['descripcion'], ENT_QUOTES); ?>"
                                                        data-fecha="<?php echo htmlspecialchars($juego['fecha_lanzamiento']); ?>"
                                                        data-plataforma="<?php echo htmlspecialchars($juego['plataforma'], ENT_QUOTES); ?>"
                                                        data-genero="<?php echo htmlspecialchars($juego['genero'], ENT_QUOTES); ?>"
                                                        data-desarrollador="<?php echo htmlspecialchars($juego['desarrollador'], ENT_QUOTES); ?>"
                                                        
                                                        data-futuro="<?php echo !empty($juego['es_futuro_lanzamiento']) ? '1' : '0'; ?>"
                                                        data-imagen="<?php echo htmlspecialchars($juego['imagen']); ?>"
                                                        data-slug="<?php echo htmlspecialchars($juego['slug'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" title="Ver vista pública"
                                                       href="game.php?<?php echo !empty($juego['slug']) ? ('slug=' . urlencode($juego['slug'])) : ('id=' . (int)$juego['id']); ?>">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete(<?php echo (int)$juego['id']; ?>, <?php echo json_encode($juego['titulo'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)">
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
                                            <td><strong><?php echo e($u['username']); ?></strong></td>
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
                                                    <?= csrf_field() ?>
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
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-success" name="user_action" value="verify_user" type="submit" title="Marcar como verificado">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="post" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-secondary" name="user_action" value="resend_verification" type="submit" title="Reenviar verificación">
                                                                <i class="fas fa-envelope"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button class="btn btn-sm btn-warning" name="user_action" value="unverify_user" type="submit" title="Marcar como no verificado">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" class="d-inline">
                                                        <?= csrf_field() ?>
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

                <!-- Manage Reviews Tab -->
                <div class="tab-pane fade" id="manage-reviews">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Gestionar Críticas</h4>
                        <div class="input-group" style="max-width: 360px;">
                            <input type="text" class="form-control" id="searchReviews" placeholder="Buscar por usuario o juego" value="<?php echo htmlspecialchars($reviewQ); ?>">
                            <button class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="game-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="reviewsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Juego</th>
                                        <th>Nota</th>
                                        <th>Likes</th>
                                        <th>Actualizada</th>
                                        <th>Crítica</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $rv): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($rv['username']); ?></strong><br><span class="text-muted">ID: <?php echo (int)$rv['user_id']; ?></span></td>
                                            <td><?php echo htmlspecialchars($rv['titulo']); ?><br><span class="text-muted">ID: <?php echo (int)$rv['game_id']; ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo $rv['rating'] ? (int)$rv['rating'] : '-'; ?></span></td>
                                            <td><span class="badge bg-success"><i class="fas fa-thumbs-up me-1"></i><?php echo (int)($rv['likes_count'] ?? 0); ?></span></td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($rv['updated_at']))); ?></td>
                                            <td style="max-width: 420px;">
                                                <div class="small" style="white-space: pre-wrap;" id="rv_view_<?php echo (int)$rv['user_id'] . '_' . (int)$rv['game_id']; ?>"><?php echo nl2br(htmlspecialchars($rv['contenido'])); ?></div>
                                                <form method="post" id="rv_edit_<?php echo (int)$rv['user_id'] . '_' . (int)$rv['game_id']; ?>" style="display:none;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="review_action" value="update">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$rv['user_id']; ?>">
                                                    <input type="hidden" name="game_id" value="<?php echo (int)$rv['game_id']; ?>">
                                                    <textarea name="contenido" class="form-control" rows="3"><?php echo htmlspecialchars($rv['contenido']); ?></textarea>
                                                    <div class="mt-2 d-flex gap-2">
                                                        <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-save me-1"></i> Guardar</button>
                                                        <button class="btn btn-sm btn-secondary" type="button" onclick="toggleAdminReview('<?php echo (int)$rv['user_id'] . '_' . (int)$rv['game_id']; ?>')">Cancelar</button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-primary" type="button" onclick="toggleAdminReview('<?php echo (int)$rv['user_id'] . '_' . (int)$rv['game_id']; ?>')"><i class="fas fa-edit"></i></button>
                                                    <form method="post" data-confirm="¿Eliminar esta crítica?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="review_action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$rv['user_id']; ?>">
                                                        <input type="hidden" name="game_id" value="<?php echo (int)$rv['game_id']; ?>">
                                                        <button class="btn btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
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

        <!-- Modal de Edición de Juego -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Juego</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="update_game" value="1">
                            <input type="hidden" name="game_id" id="edit_game_id">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Título</label>
                                    <input type="text" class="form-control" id="edit_titulo" name="titulo" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Desarrollador</label>
                                    <input type="text" class="form-control" id="edit_desarrollador" name="desarrollador" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="4" required></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fecha de lanzamiento</label>
                                    <input type="date" class="form-control" id="edit_fecha" name="fecha_lanzamiento" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Plataforma</label>
                                    <input type="text" class="form-control" id="edit_plataforma" name="plataforma" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Género</label>
                                    <input type="text" class="form-control" id="edit_genero" name="genero" required>
                                </div>
                                
                                <div class="col-md-8">
                                    <label class="form-label">Imagen (opcional, reemplaza la actual)</label>
                                    <input type="file" class="form-control" id="edit_imagen" name="imagen" accept="image/*">
                                    <div class="form-text">Formatos: JPG, PNG, GIF, WebP</div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_futuro" name="es_futuro_lanzamiento">
                                        <label class="form-check-label" for="edit_futuro">Es un futuro lanzamiento</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <img id="edit_preview" src="" alt="Preview" style="max-width:200px; border-radius:8px; display:none;">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                        <a id="viewPublicBtn" class="btn btn-outline-secondary" href="#" target="_blank"><i class="fas fa-external-link-alt me-1"></i> Ver vista pública</a>
                                        <a id="viewListBtn" class="btn btn-outline-primary" href="#" target="_blank"><i class="fas fa-list me-1"></i> Ver en listado</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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

        // Manejo manual de tabs del sidebar (evita errores del plugin de Bootstrap en esta estructura personalizada)
        function showTabByHash(hash) {
            var target = document.querySelector(hash);
            if (!target) return;
            // desactivar panes
            document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('show','active'); });
            // activar objetivo
            target.classList.add('active','show');
            // actualizar estado de enlaces
            document.querySelectorAll('.sidebar-nav-link').forEach(function (link) {
                link.classList.toggle('active', link.getAttribute('href') === hash);
            });
        }

        document.querySelectorAll('.sidebar-nav-link[href^="#"]').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                var hash = this.getAttribute('href');
                showTabByHash(hash);
                if (history.replaceState) {
                    history.replaceState(null, '', hash);
                } else {
                    location.hash = hash;
                }
            });
        });

                // Activar tab por hash en la URL al cargar
                (function(){
                    var hash = window.location.hash;
                    if (hash) {
                        showTabByHash(hash);
                    }
                })();

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

        // Búsqueda de críticas
        var searchReviews = document.getElementById('searchReviews');
        if (searchReviews) {
            searchReviews.addEventListener('keyup', function(){
                var term = this.value.toLowerCase();
                document.querySelectorAll('#reviewsTable tbody tr').forEach(function(tr){
                    tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }

        // Toggle edición de crítica en admin
        window.toggleAdminReview = function(key){
            var view = document.getElementById('rv_view_' + key);
            var edit = document.getElementById('rv_edit_' + key);
            if (!view || !edit) return;
            var showEdit = edit.style.display === 'none' || edit.style.display === '';
            edit.style.display = showEdit ? 'block' : 'none';
            view.style.display = showEdit ? 'none' : 'block';
        };

        // Función para confirmar eliminación
        function confirmDelete(gameId, gameTitle) {
            document.getElementById('gameTitle').textContent = gameTitle;
            document.getElementById('confirmDeleteBtn').href = '?delete=' + gameId;

            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

                // Cargar datos en el modal de edición
                var editModal = document.getElementById('editModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        var button = event.relatedTarget;
                        var id = button.getAttribute('data-id');
                                var slug = button.getAttribute('data-slug') || '';
                        var titulo = button.getAttribute('data-titulo');
                        var descripcion = button.getAttribute('data-descripcion');
                        var fecha = button.getAttribute('data-fecha');
                        var plataforma = button.getAttribute('data-plataforma');
                        var genero = button.getAttribute('data-genero');
                        var desarrollador = button.getAttribute('data-desarrollador');
                        
                        var futuro = button.getAttribute('data-futuro') === '1';
                        var imagen = button.getAttribute('data-imagen');

                        document.getElementById('edit_game_id').value = id;
                        document.getElementById('edit_titulo').value = titulo || '';
                        document.getElementById('edit_descripcion').value = descripcion || '';
                        document.getElementById('edit_fecha').value = fecha || '';
                        document.getElementById('edit_plataforma').value = plataforma || '';
                        document.getElementById('edit_genero').value = genero || '';
                        document.getElementById('edit_desarrollador').value = desarrollador || '';
                        
                        document.getElementById('edit_futuro').checked = futuro;

                        var preview = document.getElementById('edit_preview');
                        if (imagen) {
                            preview.src = '<?php echo UPLOAD_PATH; ?>' + imagen;
                            preview.style.display = 'block';
                        } else {
                            preview.src = '';
                            preview.style.display = 'none';
                        }

                                    // Actualiza enlace a vista pública
                                    var viewBtn = document.getElementById('viewPublicBtn');
                                    if (viewBtn) {
                                        var href = 'game.php?' + (slug ? ('slug=' + encodeURIComponent(slug)) : ('id=' + id));
                                        viewBtn.href = href;
                                    }

                                                // Enlace al listado con filtros
                                                var listBtn = document.getElementById('viewListBtn');
                                                if (listBtn) {
                                                    var params = [];
                                                    if (plataforma) params.push('plataforma=' + encodeURIComponent(plataforma));
                                                    if (genero) params.push('genero=' + encodeURIComponent(genero));
                                                    var listHref = 'games.php' + (params.length ? ('?' + params.join('&')) : '');
                                                    listBtn.href = listHref;
                                                }
                    });
                }

                // Preview de imagen al seleccionar archivo en alta
                var imagenInput = document.getElementById('imagen');
                if (imagenInput) imagenInput.addEventListener('change', function (e) {
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

                // Preview de imagen en modal de edición
                var editImagenInput = document.getElementById('edit_imagen');
                if (editImagenInput) editImagenInput.addEventListener('change', function (e) {
                    var file = e.target.files[0];
                    if (file) {
                        var reader = new FileReader();
                        reader.onload = function (ev) {
                            var preview = document.getElementById('edit_preview');
                            preview.src = ev.target.result;
                            preview.style.display = 'block';
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
    <?php if ($openAddTab): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            // Mostrar pestaña de alta
            (window.showTabByHash || function(){})();
            var hash = '#add-game';
            var target = document.querySelector(hash);
            if (target) {
                document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('show','active'); });
                target.classList.add('active','show');
                document.querySelectorAll('.sidebar-nav-link').forEach(function (link) { link.classList.toggle('active', link.getAttribute('href') === hash); });
            }
            // scroll al formulario por si hay mensajes
            var form = document.getElementById('addGameForm');
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    </script>
    <?php endif; ?>
        <?php if (isset($_GET['edit']) && is_numeric($_GET['edit'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function(){
                // Mostrar pestaña gestionar juegos manualmente
                var hash = '#manage-games';
                var target = document.querySelector(hash);
                if (target) {
                    document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('show','active'); });
                    target.classList.add('active','show');
                    document.querySelectorAll('.sidebar-nav-link').forEach(function (link) { link.classList.toggle('active', link.getAttribute('href') === hash); });
                }
                var id = '<?php echo (int)$_GET['edit']; ?>';
                var btn = document.querySelector('button[data-bs-target="#editModal"][data-id="' + id + '"]');
                if (btn) { btn.click(); }
            });
        </script>
        <?php endif; ?>
<?php include 'includes/footer.php'; ?>