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
  // Tabla de críticas: una por usuario y juego
  $pdo->exec("CREATE TABLE IF NOT EXISTS criticas (
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    contenido TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id),
    INDEX (game_id),
    INDEX (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  // Tabla de likes de críticas (un like por usuario por crítica)
  $pdo->exec("CREATE TABLE IF NOT EXISTS critica_likes (
    review_user_id INT NOT NULL,
    game_id INT NOT NULL,
    liker_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_user_id, game_id, liker_user_id),
    INDEX (game_id),
    INDEX (review_user_id),
    INDEX (liker_user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  // Añadir FKs con ON DELETE CASCADE (ignorar si ya existen)
  try { $pdo->exec("ALTER TABLE criticas ADD CONSTRAINT fk_criticas_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE criticas ADD CONSTRAINT fk_criticas_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_review FOREIGN KEY (review_user_id, game_id) REFERENCES criticas(user_id, game_id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_liker FOREIGN KEY (liker_user_id) REFERENCES usuarios(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  // Opcional: FKs para valoraciones y favoritos
  try { $pdo->exec("ALTER TABLE valoraciones ADD CONSTRAINT fk_val_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE valoraciones ADD CONSTRAINT fk_val_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE favoritos ADD CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE"); } catch (Exception $e) {}
  try { $pdo->exec("ALTER TABLE favoritos ADD CONSTRAINT fk_fav_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE"); } catch (Exception $e) {}
} catch (Exception $e) {
  // Silenciar creación fallida para no romper la vista pública
}

// Procesar acciones de usuario (POST) para rating y favoritos
if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $uid = (int)$_SESSION['user_id'];
  // Crear/editar crítica del usuario
  if (isset($_POST['set_review'])) {
    $gid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
    if ($gid > 0) {
      // comprobar si es futuro lanzamiento
      $chk = $pdo->prepare('SELECT es_futuro_lanzamiento FROM videojuegos WHERE id=?');
      $chk->execute([$gid]);
      $isUpcoming = (bool)$chk->fetchColumn();
      // comprobar que el usuario ha valorado previamente
      $vr = $pdo->prepare('SELECT 1 FROM valoraciones WHERE user_id=? AND game_id=?');
      $vr->execute([$uid, $gid]);
      $hasRated = (bool)$vr->fetchColumn();
      if (!$isUpcoming && $hasRated) {
        if ($contenido === '') {
          // si envía vacío, interpretamos como eliminar su crítica
          $pdo->prepare('DELETE FROM criticas WHERE user_id=? AND game_id=?')->execute([$uid, $gid]);
          // borrar likes asociados a esa crítica
          $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=?')->execute([$uid, $gid]);
        } else {
          // upsert
          $stmt = $pdo->prepare('INSERT INTO criticas (user_id, game_id, contenido, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())
                                 ON DUPLICATE KEY UPDATE contenido=VALUES(contenido), updated_at=NOW()');
          $stmt->execute([$uid, $gid, $contenido]);
        }
      }
    }
    $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$gid);
    redirect($target);
  }
  // Like / Unlike a una crítica
  if (isset($_POST['review_like_action'])) {
    $gid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    $reviewUserId = isset($_POST['review_user_id']) ? (int)$_POST['review_user_id'] : 0;
    if ($gid > 0 && $reviewUserId > 0 && $reviewUserId !== $uid) {
      try {
        // verificar que la crítica existe
        $ex = $pdo->prepare('SELECT 1 FROM criticas WHERE user_id=? AND game_id=?');
        $ex->execute([$reviewUserId, $gid]);
        if ($ex->fetchColumn()) {
          if ($_POST['review_like_action'] === 'like') {
            $stmt = $pdo->prepare('INSERT IGNORE INTO critica_likes (review_user_id, game_id, liker_user_id, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$reviewUserId, $gid, $uid]);
          } elseif ($_POST['review_like_action'] === 'unlike') {
            $stmt = $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=? AND liker_user_id=?');
            $stmt->execute([$reviewUserId, $gid, $uid]);
          }
        }
      } catch (Exception $e) { /* noop */ }
    }
    $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$gid);
    redirect($target);
  }
  // Acciones admin sobre críticas
  if (isset($_POST['admin_review_action']) && isAdmin()) {
    $gid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    $targetUser = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
    if ($gid > 0 && $targetUser > 0) {
      if ($_POST['admin_review_action'] === 'delete') {
        $pdo->prepare('DELETE FROM criticas WHERE user_id=? AND game_id=?')->execute([$targetUser, $gid]);
        // borrar likes asociados
        $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=?')->execute([$targetUser, $gid]);
      } elseif ($_POST['admin_review_action'] === 'update') {
        $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
        if ($contenido !== '') {
          $stmt = $pdo->prepare('INSERT INTO criticas (user_id, game_id, contenido, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())
                                 ON DUPLICATE KEY UPDATE contenido=VALUES(contenido), updated_at=NOW()');
          $stmt->execute([$targetUser, $gid, $contenido]);
        } else {
          $pdo->prepare('DELETE FROM criticas WHERE user_id=? AND game_id=?')->execute([$targetUser, $gid]);
          $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=?')->execute([$targetUser, $gid]);
        }
      }
    }
    $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$gid);
    redirect($target);
  }
  if (isset($_POST['set_rating'])) {
    $rid = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
    $rval = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    // bloquear valoraciones si es futuro lanzamiento
    $isUpcoming = false;
    if ($rid > 0) {
      $chk = $pdo->prepare('SELECT es_futuro_lanzamiento FROM videojuegos WHERE id=?');
      $chk->execute([$rid]);
      $isUpcoming = (bool)$chk->fetchColumn();
    }
    if ($rid > 0 && !$isUpcoming && $rval >= 1 && $rval <= 10) {
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
      // comprobar si es futuro lanzamiento
      $chk = $pdo->prepare('SELECT es_futuro_lanzamiento FROM videojuegos WHERE id=?');
      $chk->execute([$gid]);
      $isUpcoming = (bool)$chk->fetchColumn();
      if ($_POST['fav_action'] === 'add') {
        if ($isUpcoming) { // no permitir añadir si es futuro lanzamiento
          $target = isset($_GET['slug']) ? ('game.php?slug=' . urlencode($_GET['slug'])) : ('game.php?id=' . (int)$gid);
          redirect($target);
        }
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
$relStmt->execute([(int)$juego['id'], $juego['genero'], $juego['plataforma']]);
$relacionados = $relStmt->fetchAll(PDO::FETCH_ASSOC);

// Nota de la comunidad y estado del usuario
$avg = null; $cnt = 0; $myRating = null; $isFav = false; $favPos = null;
try {
  if (!$juego['es_futuro_lanzamiento']) {
    $s = $pdo->prepare('SELECT ROUND(AVG(rating),1) avg_rating, COUNT(*) c FROM valoraciones WHERE game_id=?');
    $s->execute([$juego['id']]);
    $row = $s->fetch(PDO::FETCH_ASSOC); if ($row) { $avg = $row['avg_rating']; $cnt = (int)$row['c']; }
  } else {
    $avg = null; $cnt = 0;
  }
  if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    if (!$juego['es_futuro_lanzamiento']) {
      $s = $pdo->prepare('SELECT rating FROM valoraciones WHERE user_id=? AND game_id=?');
      $s->execute([$uid, $juego['id']]); $r = $s->fetch(PDO::FETCH_ASSOC); if ($r) $myRating = (int)$r['rating'];
      $s = $pdo->prepare('SELECT posicion FROM favoritos WHERE user_id=? AND game_id=?');
      $s->execute([$uid, $juego['id']]); $f = $s->fetch(PDO::FETCH_ASSOC); if ($f) { $isFav = true; $favPos = (int)$f['posicion']; }
      // Cargar mi crítica (si existe)
      $s = $pdo->prepare('SELECT contenido FROM criticas WHERE user_id=? AND game_id=?');
      $s->execute([$uid, $juego['id']]); $mine = $s->fetch(PDO::FETCH_ASSOC); if ($mine) { $myReview = $mine['contenido']; }
    }
  }
} catch (Exception $e) { /* noop */ }

// Cargar críticas públicas del juego (con username y nota del autor)
$reviews = [];
$reviewsCount = 0;
try {
  $select = 'SELECT c.user_id, u.username, c.contenido, c.updated_at, v.rating,
                      (SELECT COUNT(*) FROM critica_likes cl WHERE cl.review_user_id = c.user_id AND cl.game_id = c.game_id) AS likes_count';
  if (isLoggedIn()) {
    $select .= ', (SELECT 1 FROM critica_likes cl2 WHERE cl2.review_user_id = c.user_id AND cl2.game_id = c.game_id AND cl2.liker_user_id = ' . (int)$_SESSION['user_id'] . ' LIMIT 1) AS i_liked';
  } else {
    $select .= ', NULL AS i_liked';
  }
  $q = $pdo->prepare($select . '
                      FROM criticas c
                      JOIN usuarios u ON u.id = c.user_id
                      LEFT JOIN valoraciones v ON v.user_id = c.user_id AND v.game_id = c.game_id
                      WHERE c.game_id = ?
                      ORDER BY c.updated_at DESC');
  $q->execute([$juego['id']]);
  $reviews = $q->fetchAll(PDO::FETCH_ASSOC);
  $reviewsCount = is_array($reviews) ? count($reviews) : 0;
} catch (Exception $e) { /* noop */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($juego['titulo']); ?> - <?php echo SITE_NAME; ?></title>
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
    'datePublished' => $juego['fecha_lanzamiento'] ?: null
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
          <img class="game-hero-img" src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/800x450?text=Sin+Imagen'; ?>" alt="<?php echo e($juego['titulo']); ?>">
        </div>
        <div class="col-md-7">
          <h1 class="mb-2"><?php echo e($juego['titulo']); ?></h1>
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
                <span class="badge bg-primary ms-1"><?php echo !$juego['es_futuro_lanzamiento'] && $avg !== null ? $avg : '-'; ?></span>
                <?php if (!$juego['es_futuro_lanzamiento']): ?>
                  <small class="text-muted ms-1"><?php echo (int)$cnt; ?> votos</small>
                <?php endif; ?>
              </div>
              <?php if (isLoggedIn()): ?>
                <?php if (!$juego['es_futuro_lanzamiento']): ?>
                  <form class="d-flex align-items-center gap-2" method="post">
                    <?= csrf_field() ?>
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
                    <?= csrf_field() ?>
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
                  <small class="text-muted">Próximamente: sin puntuación ni favoritos.</small>
                <?php endif; ?>
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

        <div class="card mb-4"><div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="card-title mb-0">Críticas <?php if ($reviewsCount>0): ?><span class="badge bg-secondary align-middle"><?php echo (int)$reviewsCount; ?></span><?php endif; ?></h5>
            <?php if (isLoggedIn() && !$juego['es_futuro_lanzamiento']): ?>
              <?php if ($myRating !== null): ?>
                <small class="text-muted">Tu nota: <strong><?php echo (int)$myRating; ?></strong></small>
              <?php else: ?>
                <small class="text-muted">Vota el juego para poder escribir una crítica</small>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <?php if (isLoggedIn() && !$juego['es_futuro_lanzamiento'] && $myRating !== null): ?>
            <form method="post" class="mb-3">
              <?= csrf_field() ?>
              <input type="hidden" name="set_review" value="1">
              <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
              <label class="form-label">Tu crítica (una por juego; puedes editarla cuando quieras)</label>
              <textarea name="contenido" class="form-control" rows="4" placeholder="Escribe tu opinión..."><?php echo isset($myReview)?htmlspecialchars($myReview):''; ?></textarea>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i> Guardar crítica</button>
                <?php if (!empty($myReview)): ?>
                  <button class="btn btn-outline-danger btn-sm" name="contenido" value="" type="submit" onclick="return confirm('¿Eliminar tu crítica?');"><i class="fas fa-trash me-1"></i> Eliminar</button>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>

          <?php if (empty($reviews)): ?>
            <div class="text-muted">Aún no hay críticas.</div>
          <?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($reviews as $rv): ?>
                <li class="mb-3 p-3 border rounded">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <strong><?php echo htmlspecialchars($rv['username'] ?? ''); ?></strong>
                      <span class="ms-2 small text-muted"><i class="fas fa-star text-warning"></i> <?php echo isset($rv['rating']) && $rv['rating'] ? (int)$rv['rating'] : '-'; ?>/10</span>
                      <div class="small text-muted">Actualizado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($rv['updated_at']))); ?></div>
                    </div>
                    <div class="ms-2 d-flex gap-2 align-items-start">
                      <?php if (isLoggedIn() && (int)$rv['user_id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                        <form method="post" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
                          <input type="hidden" name="review_user_id" value="<?php echo (int)$rv['user_id']; ?>">
                          <?php $liked = !empty($rv['i_liked']); $likes = (int)($rv['likes_count'] ?? 0); ?>
                          <?php if ($liked): ?>
                            <input type="hidden" name="review_like_action" value="unlike">
                            <button class="btn btn-sm btn-success" type="submit" title="Quitar me gusta"><i class="fas fa-thumbs-up me-1"></i> <?php echo $likes; ?></button>
                          <?php else: ?>
                            <input type="hidden" name="review_like_action" value="like">
                            <button class="btn btn-sm btn-outline-success" type="submit" title="Me gusta"><i class="far fa-thumbs-up me-1"></i> <?php echo $likes; ?></button>
                          <?php endif; ?>
                        </form>
                      <?php else: ?>
                        <span class="badge bg-light text-dark"><i class="far fa-thumbs-up me-1"></i><?php echo (int)($rv['likes_count'] ?? 0); ?></span>
                      <?php endif; ?>
                      <?php if (isAdmin()): ?>
                      <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleEditReview(<?php echo (int)$rv['user_id']; ?>)"><i class="fas fa-edit"></i></button>
                        <form method="post" onsubmit="return confirm('¿Eliminar crítica de este usuario?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="admin_review_action" value="delete">
                          <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
                          <input type="hidden" name="target_user_id" value="<?php echo (int)$rv['user_id']; ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                        </form>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="mt-2" id="review_view_<?php echo (int)$rv['user_id']; ?>" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($rv['contenido'])); ?></div>
                  <?php if (isAdmin()): ?>
                    <form method="post" id="review_edit_<?php echo (int)$rv['user_id']; ?>" class="mt-2" style="display:none;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="admin_review_action" value="update">
                      <input type="hidden" name="game_id" value="<?php echo (int)$juego['id']; ?>">
                      <input type="hidden" name="target_user_id" value="<?php echo (int)$rv['user_id']; ?>">
                      <textarea name="contenido" class="form-control" rows="3"><?php echo htmlspecialchars($rv['contenido']); ?></textarea>
                      <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-save me-1"></i> Guardar</button>
                        <button class="btn btn-sm btn-secondary" type="button" onclick="toggleEditReview(<?php echo (int)$rv['user_id']; ?>)">Cancelar</button>
                      </div>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
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
                      <img src="<?php echo $r['imagen'] ? UPLOAD_PATH . $r['imagen'] : 'https://via.placeholder.com/400x225?text=Sin+Imagen'; ?>" alt="<?php echo e($r['titulo']); ?>">
                      <div class="card-body p-2">
                        <div class="fw-semibold small mb-1"><?php echo e($r['titulo']); ?></div>
                        <div class="text-muted small"><?php echo e($r['genero'] ?: $r['plataforma']); ?></div>
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
            <li><strong>Plataforma:</strong> <?php echo e($juego['plataforma'] ?: '-'); ?></li>
            <li><strong>Género:</strong> <?php echo e($juego['genero'] ?: '-'); ?></li>
            <li><strong>Desarrollador:</strong> <?php echo e($juego['desarrollador'] ?: '-'); ?></li>
            
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
  <script>
    function toggleEditReview(uid){
      var v = document.getElementById('review_view_' + uid);
      var e = document.getElementById('review_edit_' + uid);
      if (!v || !e) return;
      var isHidden = e.style.display === 'none' || e.style.display === '';
      e.style.display = isHidden ? 'block' : 'none';
      v.style.display = isHidden ? 'none' : 'block';
    }
  </script>
</body>
</html>
