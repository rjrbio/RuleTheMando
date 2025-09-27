<?php
require_once 'config.php';

// Asegurar tablas para favoritos y valoraciones
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS valoraciones (
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id),
    INDEX (game_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  $pdo->exec("CREATE TABLE IF NOT EXISTS favoritos (
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    posicion INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id),
    UNIQUE KEY uniq_user_pos (user_id, posicion),
    INDEX (user_id),
    INDEX (game_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
  // Silenciar creación fallida para no romper la vista pública
}

// Procesar acciones de usuario (POST) para rating y favoritos
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid = (int)$_SESSION['user_id'];
  if (isset($_POST['set_rating'])) {
    $rid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    $rval = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    if ($rid > 0 && $rval >= 1 && $rval <= 10) {
      $stmt = $pdo->prepare('REPLACE INTO valoraciones (user_id, game_id, rating, updated_at) VALUES (?, ?, ?, NOW())');
      $stmt->execute([$uid, $rid, $rval]);
    }
    // PRG
    $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$rid);
    redirect($target);
  }
  if (isset($_POST['fav_action'])) {
    $gid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    if ($gid > 0) {
      if ($_POST['fav_action'] === 'add') {
        // determinar siguiente posición libre
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(posicion),0)+1 FROM favoritos WHERE user_id = ?');
        $maxStmt->execute([$uid]);
        $next = (int)$maxStmt->fetchColumn();
        if (isset($_POST['posicion']) && (int)$_POST['posicion'] > 0) {
          // Intentar insertar en posición deseada corriendo hacia abajo los existentes
          $pos = (int)$_POST['posicion'];
          $pdo->beginTransaction();
          try {
            $pdo->prepare('UPDATE favoritos SET posicion = posicion + 1 WHERE user_id=? AND posicion >= ?')->execute([$uid, $pos]);
            $pdo->prepare('REPLACE INTO favoritos (user_id, game_id, posicion) VALUES (?, ?, ?)')->execute([$uid, $gid, $pos]);
            $pdo->commit();
          } catch (Exception $e) { $pdo->rollBack(); }
        } else {
          $pdo->prepare('REPLACE INTO favoritos (user_id, game_id, posicion) VALUES (?, ?, ?)')->execute([$uid, $gid, max(1,$next)]);
        }
      } elseif ($_POST['fav_action'] === 'remove') {
        // eliminar y compactar posiciones
        $pdo->beginTransaction();
        try {
          $posStmt = $pdo->prepare('SELECT posicion FROM favoritos WHERE user_id=? AND game_id=?');
          $posStmt->execute([$uid, $gid]);
          $pos = (int)$posStmt->fetchColumn();
          $pdo->prepare('DELETE FROM favoritos WHERE user_id=? AND game_id=?')->execute([$uid, $gid]);
          if ($pos > 0) {
            $pdo->prepare('UPDATE favoritos SET posicion = posicion - 1 WHERE user_id=? AND posicion > ?')->execute([$uid, $pos]);
          }
          $pdo->commit();
        } catch (Exception $e) { $pdo->rollBack(); }
      }
    }
    $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$gid);
    redirect($target);
  }
}

// Resolver por id o slug
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($slug !== '') {
  $stmt = $pdo->prepare('SELECT * FROM videojuegos WHERE slug = ?');
  $stmt->execute([$slug]);
  $juego = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
  if ($id <= 0) {
    http_response_code(400);
    echo 'Solicitud inválida';
    exit;
  }
  $stmt = $pdo->prepare('SELECT * FROM videojuegos WHERE id = ?');
  $stmt->execute([$id]);
  $juego = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$juego) {
    http_response_code(404);
    echo 'Juego no encontrado';
    exit;
}

// Juegos relacionados (mismo género o plataforma, excluyendo el actual)
$relStmt = $pdo->prepare('SELECT id, titulo, imagen, genero, plataforma FROM videojuegos WHERE id <> ? AND (genero = ? OR plataforma = ?) ORDER BY created_at DESC LIMIT 6');
$relStmt->execute([$id, $juego['genero'], $juego['plataforma']]);
$relacionados = $relStmt->fetchAll(PDO::FETCH_ASSOC);

// Nota de la comunidad y estado del usuario
$avg = null; $cnt = 0; $myRating = null; $isFav = false; $favPos = null;
try {
  $s = $pdo->prepare('SELECT ROUND(AVG(rating),1) avg_rating, COUNT(*) c FROM valoraciones WHERE game_id=?');
  $s->execute([$juego['id']]);
  $row = $s->fetch(PDO::FETCH_ASSOC); if ($row) { $avg = $row['avg_rating']; $cnt = (int)$row['c']; }
  if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    $s = $pdo->prepare('SELECT rating FROM valoraciones WHERE user_id=? AND game_id=?');
    $s->execute([$uid, $juego['id']]); $r = $s->fetch(PDO::FETCH_ASSOC); if ($r) $myRating = (int)$r['rating'];
    $s = $pdo->prepare('SELECT posicion FROM favoritos WHERE user_id=? AND game_id=?');
    $s->execute([$uid, $juego['id']]); $f = $s->fetch(PDO::FETCH_ASSOC); if ($f) { $isFav = true; $favPos = (int)$f['posicion']; }
  }
} catch (Exception $e) { /* noop */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo sanitize($juego['titulo']); ?> - <?php echo SITE_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
  <meta name="description" content="Detalles de <?php echo htmlspecialchars($juego['titulo']); ?>: fecha, plataforma, género, desarrollador y más.">
  <?php
    $hasSlug = isset($juego['slug']) && !empty($juego['slug']);
    $canonical = SITE_URL . '/game.php?' . ($hasSlug ? ('slug=' . urlencode($juego['slug'])) : ('id=' . (int)$juego['id']));
  ?>
  <link rel="canonical" href="<?php echo $canonical; ?>">
  <script type="application/ld+json">
  <?php echo json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'VideoGame',
    'name' => $juego['titulo'],
    'author' => $juego['desarrollador'] ?: null,
    'genre' => $juego['genero'] ?: null,
    'gamePlatform' => $juego['plataforma'] ?: null,
    'image' => $juego['imagen'] ? (SITE_URL . '/' . UPLOAD_PATH . $juego['imagen']) : null,
    'datePublished' => $juego['fecha_lanzamiento'] ?: null,
    'offers' => $juego['precio'] ? [
      '@type' => 'Offer',
      'price' => number_format((float)$juego['precio'], 2),
      'priceCurrency' => 'EUR'
    ] : null
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
  </script>
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-gamepad me-2"></i><?php echo SITE_NAME; ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link" href="games.php"><i class="fas fa-list"></i> Juegos</a></li>
        </ul>
        <ul class="navbar-nav">
          <?php if (isLoggedIn()): ?>
            <li class="nav-item"><a class="nav-link" href="favorites.php"><i class="fas fa-trophy"></i> Mis Favoritos</a></li>
          <?php endif; ?>
          <?php if (isAdmin()): ?>
            <li class="nav-item"><a class="nav-link" href="admin.php?edit=<?php echo (int)$juego['id']; ?>#manage-games"><i class="fas fa-edit"></i> Editar</a></li>
          <?php endif; ?>
          <?php if (isLoggedIn()): ?>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <header class="game-hero py-4">
    <div class="container">
      <div class="row g-4 align-items-center">
        <div class="col-md-5">
          <img class="game-hero-img" src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/800x450?text=Sin+Imagen'; ?>" alt="<?php echo sanitize($juego['titulo']); ?>">
        </div>
        <div class="col-md-7">
          <h1 class="mb-2"><?php echo sanitize($juego['titulo']); ?></h1>
          <p class="lead mb-3"><?php echo htmlspecialchars($juego['descripcion']); ?></p>
          <div>
            <?php if ($juego['es_futuro_lanzamiento']): ?>
              <span class="badge bg-warning text-dark">Próximamente</span>
            <?php else: ?>
              <span class="badge bg-success">Disponible</span>
            <?php endif; ?>
          </div>
          <div class="mt-3">
            <div class="d-flex align-items-center flex-wrap gap-3">
              <div>
                <span class="fw-semibold">Nota de la comunidad:</span>
                <span class="badge bg-primary ms-1"><?php echo $avg !== null ? $avg : '-'; ?></span>
                <small class="text-muted ms-1"><?php echo (int)$cnt; ?> votos</small>
              </div>
              <?php if (isLoggedIn()): ?>
              <form class="d-flex align-items-center gap-2" method="post">
                <input type="hidden" name="set_rating" value="1">
                <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
                <label for="rating" class="small text-muted mb-0">Tu nota</label>
                <select name="rating" id="rating" class="form-select form-select-sm" style="width: auto;">
                  <?php for ($i=1; $i<=10; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($myRating===$i)?'selected':''; ?>><?php echo $i; ?></option>
                  <?php endfor; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-star me-1"></i> Guardar</button>
              </form>
              <form method="post">
                <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
                <?php if ($isFav): ?>
                  <input type="hidden" name="fav_action" value="remove">
                  <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-heart-broken me-1"></i> Quitar de favoritos<?php echo $favPos? ' (#'.$favPos.')':''; ?></button>
                <?php else: ?>
                  <input type="hidden" name="fav_action" value="add">
                  <button class="btn btn-sm btn-outline-success" type="submit"><i class="fas fa-heart me-1"></i> Añadir a favoritos</button>
                <?php endif; ?>
              </form>
              <a class="btn btn-sm btn-outline-secondary" href="favorites.php"><i class="fas fa-trophy me-1"></i> Mi Top favoritos</a>
              <?php else: ?>
                <small class="text-muted">Inicia sesión para puntuar y marcar favoritos.</small>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="container my-4">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card mb-4"><div class="card-body">
          <h5 class="card-title">Descripción</h5>
          <p style="white-space: pre-wrap;" class="mb-0"><?php echo nl2br(htmlspecialchars($juego['descripcion'])); ?></p>
        </div></div>

        <?php if (!empty($relacionados)): ?>
          <div class="card"><div class="card-body">
            <h5 class="card-title">Relacionados</h5>
            <div class="row g-3">
              <?php foreach ($relacionados as $r): ?>
                <div class="col-6 col-md-4">
                  <?php $slugR = isset($r['slug']) ? $r['slug'] : ''; ?>
                  <a href="game.php?<?php echo $slugR ? ('slug=' . urlencode($slugR)) : ('id=' . (int)$r['id']); ?>" class="text-decoration-none text-dark">
                    <div class="card card-related">
                      <img src="<?php echo $r['imagen'] ? UPLOAD_PATH . $r['imagen'] : 'https://via.placeholder.com/400x225?text=Sin+Imagen'; ?>" alt="<?php echo sanitize($r['titulo']); ?>">
                      <div class="card-body p-2">
                        <div class="fw-semibold small mb-1"><?php echo sanitize($r['titulo']); ?></div>
                        <div class="text-muted small"><?php echo sanitize($r['genero'] ?: $r['plataforma']); ?></div>
                      </div>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div></div>
        <?php endif; ?>
      </div>
      <div class="col-lg-4">
        <div class="card"><div class="card-body">
          <h5 class="card-title">Detalles</h5>
          <ul class="list-unstyled meta">
            <li><strong>Fecha:</strong> <?php echo $juego['fecha_lanzamiento'] ? date('d/m/Y', strtotime($juego['fecha_lanzamiento'])) : '-'; ?></li>
            <li><strong>Plataforma:</strong> <?php echo sanitize($juego['plataforma'] ?: '-'); ?></li>
            <li><strong>Género:</strong> <?php echo sanitize($juego['genero'] ?: '-'); ?></li>
            <li><strong>Desarrollador:</strong> <?php echo sanitize($juego['desarrollador'] ?: '-'); ?></li>
            <li><strong>Precio:</strong> <?php echo $juego['precio'] ? '€' . number_format($juego['precio'], 2) : '-'; ?></li>
            <li><strong>Actualizado:</strong> <?php echo date('d/m/Y H:i', strtotime($juego['updated_at'])); ?></li>
          </ul>
          <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="games.php"><i class="fas fa-arrow-left me-1"></i> Volver al listado</a>
            <?php if (isAdmin()): ?>
              <a class="btn btn-primary" href="admin.php?edit=<?php echo (int)$juego['id']; ?>#manage-games"><i class="fas fa-edit me-1"></i> Editar</a>
            <?php endif; ?>
          </div>
        </div></div>
      </div>
    </div>
  </main>

  <footer class="bg-dark text-light py-4">
    <div class="container d-flex justify-content-between">
      <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></span>
      <a href="index.php" class="text-muted">Inicio</a>
    </div>
  </footer>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
