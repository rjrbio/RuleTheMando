<?php
require_once 'config.php';
require_once 'includes/db.php';

ensure_auxiliary_tables();

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

$pageTitle = 'Tu guía definitiva de videojuegos';
$extraCss = ['advanced-animations.css'];
include 'includes/head.php';
?>
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
<?php
$activePage = 'home';
$showSearch = true;
include 'includes/navbar.php';
include 'includes/flash.php';
?>

    <main class="container my-5">
        <!-- Últimos Juegos Añadidos -->
        <section class="mb-5">
                <h2 class="mb-4 section-title"><i class="fas fa-star text-warning"></i> Últimos Juegos Añadidos</h2>
                <div class="row">
                    <?php foreach ($ultimosJuegos as $juego): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card game-card h-100">
                                <?php $slug = isset($juego['slug']) ? $juego['slug'] : ''; $href = 'game.php?' . ($slug ? ('slug=' . urlencode($slug)) : ('id=' . (int)$juego['id'])); ?>
                                <a href="<?php echo $href; ?>">
                                    <img src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/300x200?text=Sin+Imagen'; ?>" 
                                         class="card-img-top" alt="<?php echo e($juego['titulo']); ?>">
                                </a>
                                <div class="card-body d-flex flex-column">
                                                                        <h5 class="card-title d-flex align-items-center justify-content-between">
                                                                            <a href="<?php echo $href; ?>" class="text-decoration-none"><?php echo e($juego['titulo']); ?></a>
                                                                            <span class="small text-muted ms-2">
                                                                                <i class="fas fa-star text-warning"></i>
                                                                                <?php echo isset($juego['avg_rating']) && $juego['avg_rating'] ? number_format((float)$juego['avg_rating'], 1) : '-'; ?>
                                                                            </span>
                                                                        </h5>
                                    <p class="card-text flex-grow-1"><?php echo e(substr($juego['descripcion'], 0, 100)) . '...'; ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary"><?php echo e($juego['genero']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-gamepad"></i> <?php echo e($juego['plataforma']); ?>
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
                    <h2 class="mb-4 section-title"><i class="fas fa-rocket text-info"></i> Próximo Lanzamiento</h2>
                    <div class="card future-release-card pulse-effect">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <a href="<?php echo $hrefF; ?>">
                                    <img src="<?php echo $futuroJuego['imagen'] ? UPLOAD_PATH . $futuroJuego['imagen'] : 'https://via.placeholder.com/400x300?text=Próximamente'; ?>" 
                                         class="img-fluid object-cover" alt="<?php echo e($futuroJuego['titulo']); ?>">
                                </a>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body h-100 d-flex flex-column">
                                    <h3 class="card-title"><a href="<?php echo $hrefF; ?>" class="text-decoration-none"><?php echo e($futuroJuego['titulo']); ?></a></h3>
                                    <p class="card-text flex-grow-1"><?php echo e($futuroJuego['descripcion']); ?></p>
                                    
                                    <!-- Contador regresivo -->
                                    <div class="countdown-container mb-3">
                                        <h5>Lanzamiento en:</h5>
                                        <div id="countdown" class="countdown-display" 
                                             data-target="<?php echo date('c', strtotime($futuroJuego['fecha_lanzamiento'])); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="game-details">
                                        <p class="mb-2"><strong>Desarrollador:</strong> <?php echo e($futuroJuego['desarrollador']); ?></p>
                                        <p class="mb-2"><strong>Plataforma:</strong> <?php echo e($futuroJuego['plataforma']); ?></p>
                                        <p class="mb-2"><strong>Género:</strong> <?php echo e($futuroJuego['genero']); ?></p>
                                        
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
    </main>

    <?php include 'includes/site-footer.php'; ?>

    <?php
    $extraScripts = ['animations.js'];
    ob_start();
    ?>
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
    <?php
    $extraScriptsHtml = ob_get_clean();
    include 'includes/footer.php';
    ?>