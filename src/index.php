<?php
require_once 'config.php';

// Asegurar tabla de valoraciones para poder calcular notas (no falla si ya existe)
try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS valoraciones (
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, game_id), INDEX (game_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// Obtener los últimos juegos añadidos (excluyendo futuros lanzamientos)
$stmt = $pdo->prepare("SELECT v.*, COALESCE(r.avg_rating, 0) AS avg_rating, COALESCE(r.votes, 0) AS votes
                                             FROM videojuegos v
                                             LEFT JOIN (
                                                 SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS votes
                                                 FROM valoraciones
                                                 GROUP BY game_id
                                             ) r ON r.game_id = v.id
                                             WHERE v.es_futuro_lanzamiento = FALSE
                                             ORDER BY v.created_at DESC
                                             LIMIT 6");
$stmt->execute();
$ultimosJuegos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener un juego de futuro lanzamiento
$stmt = $pdo->prepare("SELECT * FROM videojuegos WHERE es_futuro_lanzamiento = TRUE ORDER BY fecha_lanzamiento ASC LIMIT 1");
$stmt->execute();
$futuroJuego = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar búsqueda
$resultadosBusqueda = [];
if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $termino = '%' . sanitize($_GET['buscar']) . '%';
        $stmt = $pdo->prepare("SELECT v.*, COALESCE(r.avg_rating, 0) AS avg_rating, COALESCE(r.votes, 0) AS votes
                                                     FROM videojuegos v
                                                     LEFT JOIN (
                                                         SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS votes
                                                         FROM valoraciones
                                                         GROUP BY game_id
                                                     ) r ON r.game_id = v.id
                                                     WHERE v.titulo LIKE ? OR v.descripcion LIKE ?
                                                     ORDER BY v.created_at DESC");
    $stmt->execute([$termino, $termino]);
    $resultadosBusqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Tu guía definitiva de videojuegos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <!-- Banner Principal -->
    <header class="hero-banner">
        <img src="media/rulethemando.png" alt="<?php echo SITE_NAME; ?>" class="hero-image" loading="eager">
        <div class="hero-overlay">
            <div class="container text-center">
                <h1 class="visually-hidden"><?php echo SITE_NAME; ?></h1>
                <p class="lead text-white-50 mt-4">Tu lista de videojuegos</p>
            </div>
        </div>
    </header>

    <!-- Barra de Navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-gamepad me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Buscador -->
                <form class="d-flex me-auto ms-3" method="GET">
                    <div class="input-group">
                        <input class="form-control" type="search" name="buscar" placeholder="Buscar juegos..." 
                               value="<?php echo isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : ''; ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Menú de usuario -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="games.php"><i class="fas fa-list"></i> Juegos</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="favorites.php"><i class="fas fa-trophy"></i> Mis Favoritos</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo sanitize($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (isAdmin()): ?>
                                    <li><a class="dropdown-item" href="admin.php"><i class="fas fa-cog"></i> Administrar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-5">
        <!-- Resultados de búsqueda -->
        <?php if (!empty($resultadosBusqueda)): ?>
            <section class="mb-5">
                <h2 class="mb-4">Resultados de búsqueda para "<?php echo htmlspecialchars($_GET['buscar']); ?>"</h2>
                <div class="row">
                    <?php foreach ($resultadosBusqueda as $juego): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card game-card h-100">
                                <?php $slug = isset($juego['slug']) ? $juego['slug'] : ''; $href = 'game.php?' . ($slug ? ('slug=' . urlencode($slug)) : ('id=' . (int)$juego['id'])); ?>
                                <a href="<?php echo $href; ?>">
                                    <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/300x200?text=Sin+Imagen'; ?>" 
                                         class="card-img-top" alt="<?php echo sanitize($juego['titulo']); ?>">
                                </a>
                                <div class="card-body d-flex flex-column">
                                                                        <h5 class="card-title d-flex align-items-center justify-content-between">
                                                                            <a href="<?php echo $href; ?>" class="text-decoration-none"><?php echo sanitize($juego['titulo']); ?></a>
                                                                            <span class="small text-muted ms-2">
                                                                                <i class="fas fa-star text-warning"></i>
                                                                                <?php echo isset($juego['avg_rating']) && $juego['avg_rating'] ? number_format((float)$juego['avg_rating'], 1) : '-'; ?>
                                                                            </span>
                                                                        </h5>
                                    <p class="card-text flex-grow-1"><?php echo sanitize(substr($juego['descripcion'], 0, 100)) . '...'; ?></p>
                                    <div class="mt-auto">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-gamepad"></i> <?php echo sanitize($juego['plataforma']); ?>
                                        </small>
                                            <div class="mt-2 d-flex gap-2">
                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo $href; ?>"><i class="fas fa-info-circle me-1"></i> Ver ficha</a>
                                                <?php if (isAdmin()): ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="admin.php?edit=<?php echo (int)$juego['id']; ?>#manage-games"><i class="fas fa-edit me-1"></i> Editar</a>
                                                <?php endif; ?>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif (isset($_GET['buscar'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No se encontraron resultados para "<?php echo htmlspecialchars($_GET['buscar']); ?>"
            </div>
        <?php endif; ?>

        <!-- Últimos Juegos Añadidos -->
        <?php if (empty($_GET['buscar'])): ?>
            <section class="mb-5">
                <h2 class="mb-4"><i class="fas fa-star text-warning"></i> Últimos Juegos Añadidos</h2>
                <div class="row">
                    <?php foreach ($ultimosJuegos as $juego): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card game-card h-100">
                                <?php $slug = isset($juego['slug']) ? $juego['slug'] : ''; $href = 'game.php?' . ($slug ? ('slug=' . urlencode($slug)) : ('id=' . (int)$juego['id'])); ?>
                                <a href="<?php echo $href; ?>">
                                    <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/300x200?text=Sin+Imagen'; ?>" 
                                         class="card-img-top" alt="<?php echo sanitize($juego['titulo']); ?>">
                                </a>
                                <div class="card-body d-flex flex-column">
                                                                        <h5 class="card-title d-flex align-items-center justify-content-between">
                                                                            <a href="<?php echo $href; ?>" class="text-decoration-none"><?php echo sanitize($juego['titulo']); ?></a>
                                                                            <span class="small text-muted ms-2">
                                                                                <i class="fas fa-star text-warning"></i>
                                                                                <?php echo isset($juego['avg_rating']) && $juego['avg_rating'] ? number_format((float)$juego['avg_rating'], 1) : '-'; ?>
                                                                            </span>
                                                                        </h5>
                                    <p class="card-text flex-grow-1"><?php echo sanitize(substr($juego['descripcion'], 0, 100)) . '...'; ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo sanitize($juego['genero']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-gamepad"></i> <?php echo sanitize($juego['plataforma']); ?>
                                        </small>
                                        <div class="mt-2 d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $href; ?>"><i class="fas fa-info-circle me-1"></i> Ver ficha</a>
                                            <?php if (isAdmin()): ?>
                                                <a class="btn btn-sm btn-outline-secondary" href="admin.php?edit=<?php echo (int)$juego['id']; ?>#manage-games"><i class="fas fa-edit me-1"></i> Editar</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Futuro Lanzamiento -->
            <?php if ($futuroJuego): ?>
                <?php 
                    // Preparar URL del futuro lanzamiento (slug si existe)
                    $slugF = isset($futuroJuego['slug']) ? $futuroJuego['slug'] : ''; 
                    $hrefF = 'game.php?' . ($slugF ? ('slug=' . urlencode($slugF)) : ('id=' . (int)$futuroJuego['id'])); 
                ?>
                <section class="mb-5">
                    <h2 class="mb-4"><i class="fas fa-rocket text-info"></i> Próximo Lanzamiento</h2>
                    <div class="card future-release-card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <a href="<?php echo $hrefF; ?>">
                                    <img src="<?php echo $futuroJuego['imagen'] ? UPLOAD_PATH . $futuroJuego['imagen'] : 'https://via.placeholder.com/400x300?text=Próximamente'; ?>" 
                                         class="img-fluid object-cover" alt="<?php echo sanitize($futuroJuego['titulo']); ?>">
                                </a>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body h-100 d-flex flex-column">
                                    <h3 class="card-title"><a href="<?php echo $hrefF; ?>" class="text-decoration-none"><?php echo sanitize($futuroJuego['titulo']); ?></a></h3>
                                    <p class="card-text flex-grow-1"><?php echo sanitize($futuroJuego['descripcion']); ?></p>
                                    
                                    <!-- Contador regresivo -->
                                    <div class="countdown-container mb-3">
                                        <h5>Lanzamiento en:</h5>
                                        <div id="countdown" class="countdown-display" 
                                             data-target="<?php echo date('c', strtotime($futuroJuego['fecha_lanzamiento'])); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="game-details">
                                        <p class="mb-2"><strong>Desarrollador:</strong> <?php echo sanitize($futuroJuego['desarrollador']); ?></p>
                                        <p class="mb-2"><strong>Plataforma:</strong> <?php echo sanitize($futuroJuego['plataforma']); ?></p>
                                        <p class="mb-2"><strong>Género:</strong> <?php echo sanitize($futuroJuego['genero']); ?></p>
                                        
                                        <div class="mt-3 d-flex gap-2">
                                            <a class="btn btn-primary" href="<?php echo $hrefF; ?>"><i class="fas fa-info-circle me-1"></i> Ver ficha</a>
                                            <?php if (isAdmin()): ?>
                                                <a class="btn btn-outline-secondary" href="admin.php?edit=<?php echo (int)$futuroJuego['id']; ?>#manage-games"><i class="fas fa-edit me-1"></i> Editar</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">Tu destino definitivo para descubrir, explorar y seguir los mejores videojuegos.</p>
                </div>
                <div class="col-md-6 mb-4">
                    <h5>Síguenos</h5>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-youtube fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-discord fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2024 <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-muted me-3">Contacto</a>
                    <a href="#" class="text-muted me-3">Política de Privacidad</a>
                    <a href="#" class="text-muted">Términos de Uso</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Contador regresivo
        function updateCountdown() {
            const countdownElement = document.getElementById('countdown');
            if (!countdownElement) return;
            
            const targetDate = new Date(countdownElement.dataset.target);
            const now = new Date();
            const difference = targetDate - now;
            
            if (difference > 0) {
                const days = Math.floor(difference / (1000 * 60 * 60 * 24));
                const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((difference % (1000 * 60)) / 1000);
                
                countdownElement.innerHTML = `
                    <div class="countdown-item">
                        <span class="countdown-number">${days}</span>
                        <span class="countdown-label">días</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${hours}</span>
                        <span class="countdown-label">horas</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${minutes}</span>
                        <span class="countdown-label">min</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${seconds}</span>
                        <span class="countdown-label">seg</span>
                    </div>
                `;
            } else {
                countdownElement.innerHTML = '<span class="text-success fw-bold">¡Ya disponible!</span>';
            }
        }
        
        // Actualizar contador cada segundo
        if (document.getElementById('countdown')) {
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
        
        // Efectos de hover en las tarjetas
        document.querySelectorAll('.game-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
                this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            });
        });

                        // Parallax suave: texto (overlay) hacia arriba y la imagen hacia abajo
                (function(){
                    const hero = document.querySelector('.hero-banner');
                            const overlay = hero ? hero.querySelector('.hero-overlay') : null;
                            const img = hero ? hero.querySelector('.hero-image') : null;
                    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                            if (!hero || !overlay || !img || reduceMotion) return;

                    let ticking = false;
                    function updateParallax(){
                        const start = hero.offsetTop;
                        const h = hero.offsetHeight;
                        const y = Math.min(Math.max(window.scrollY - start, 0), h);
                                // Texto sube (negativo), imagen baja (positivo)
                                overlay.style.transform = `translateY(${-y * 0.25}px)`;
                                img.style.transform = `translateY(${y * 0.15}px) scale(1.05)`;
                        ticking = false;
                    }
                    function onScroll(){
                        if (!ticking) {
                            ticking = true;
                            requestAnimationFrame(updateParallax);
                        }
                    }
                    window.addEventListener('scroll', onScroll, { passive: true });
                    window.addEventListener('resize', onScroll);
                    // Inicial
                    updateParallax();
                })();
    </script>
</body>
</html>