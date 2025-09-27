<?php
require_once 'config.php';
if (!isLoggedIn()) { redirect('login.php'); }
$uid = (int)$_SESSION['user_id'];

// Ensure tables exist (safe if already)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS favoritos (
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    posicion INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id),
    UNIQUE KEY uniq_user_pos (user_id, posicion),
    INDEX (user_id), INDEX (game_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// Save new order (nuevo: por posiciones), mantiene compatibilidad con 'order[]'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Quitar un favorito
  if (isset($_POST['remove_id']) && is_numeric($_POST['remove_id'])) {
    $gid = (int)$_POST['remove_id'];
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
      $_SESSION['flash_message'] = 'Juego eliminado de favoritos';
      $_SESSION['flash_success'] = true;
      redirect('favorites.php');
    } catch (Exception $e) { $pdo->rollBack(); }
  }
  if (isset($_POST['pos']) && is_array($_POST['pos'])) {
    // Reubicar cada juego en la posición elegida insertándolo y desplazando el resto
    $pdo->beginTransaction();
    try {
      // Posiciones actuales
      $map = [];
      $stmt = $pdo->prepare('SELECT game_id, posicion FROM favoritos WHERE user_id=? ORDER BY posicion ASC');
      $stmt->execute([$uid]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $total = count($rows);
      foreach ($rows as $r) { $map[(int)$r['game_id']] = (int)$r['posicion']; }

      foreach ($_POST['pos'] as $gidStr => $desiredStr) {
        if (!is_numeric($gidStr) || !isset($map[(int)$gidStr])) continue;
        $gid = (int)$gidStr;
        $currPos = (int)$map[$gid];
        $desired = (int)$desiredStr;
        if ($desired < 1) $desired = 1;
        if ($desired > $total) $desired = $total;
        if ($desired === $currPos) continue;

    // Liberar la posición actual del juego para evitar conflictos con el índice único
    $pdo->prepare('UPDATE favoritos SET posicion = 0 WHERE user_id=? AND game_id=?')
      ->execute([$uid, $gid]);

    if ($desired < $currPos) {
          // Mover hacia arriba: desplazar [desired, currPos-1] hacia abajo
          $pdo->prepare('UPDATE favoritos SET posicion = posicion + 1 WHERE user_id=? AND posicion >= ? AND posicion < ?')
        ->execute([$uid, $desired, $currPos]);
        } else {
          // Mover hacia abajo: desplazar (currPos, desired] hacia arriba
          $pdo->prepare('UPDATE favoritos SET posicion = posicion - 1 WHERE user_id=? AND posicion <= ? AND posicion > ?')
        ->execute([$uid, $desired, $currPos]);
        }
        // Colocar el juego en la posición deseada ya liberada
        $pdo->prepare('UPDATE favoritos SET posicion = ? WHERE user_id=? AND game_id=?')
            ->execute([$desired, $uid, $gid]);

        // Recalcular mapa para siguientes operaciones dentro de la misma petición
        $map = [];
        $stmt = $pdo->prepare('SELECT game_id, posicion FROM favoritos WHERE user_id=? ORDER BY posicion ASC');
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $map[(int)$r['game_id']] = (int)$r['posicion']; }
      }

      $pdo->commit();
      $_SESSION['flash_message'] = 'Orden actualizado';
      $_SESSION['flash_success'] = true;
      redirect('favorites.php');
    } catch (Exception $e) { $pdo->rollBack(); }
  } elseif (isset($_POST['order']) && is_array($_POST['order'])) {
    // Compatibilidad con el método anterior (por array de ids)
    $order = array_map('intval', $_POST['order']);
    $pdo->beginTransaction();
    try {
      $pos = 1; $seen = [];
      foreach ($order as $gid) {
        if ($gid <= 0 || isset($seen[$gid])) continue; $seen[$gid]=1;
        $pdo->prepare('REPLACE INTO favoritos (user_id, game_id, posicion) VALUES (?, ?, ?)')->execute([$uid, $gid, $pos++]);
      }
      $pdo->commit();
      $_SESSION['flash_message'] = 'Favoritos actualizados';
      $_SESSION['flash_success'] = true;
      redirect('favorites.php');
    } catch (Exception $e) { $pdo->rollBack(); }
  }
}

// Load favorites with game info (slug solo si existe la columna)
$hasSlug = function_exists('hasColumn') ? hasColumn($pdo, 'videojuegos', 'slug') : false;
$selectSlug = $hasSlug ? ', v.slug' : '';
$stmt = $pdo->prepare('SELECT f.game_id, f.posicion, v.titulo, v.imagen, v.genero, v.plataforma' . $selectSlug . ' FROM favoritos f JOIN videojuegos v ON v.id=f.game_id WHERE f.user_id=? ORDER BY f.posicion ASC');
$stmt->execute([$uid]);
$favs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Favoritos - <?php echo SITE_NAME; ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
  <style>
    .fav-item{cursor:grab}
    .podium-1{border:2px solid #f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.2)}
    .podium-2{border:2px solid #9ca3af; box-shadow:0 0 0 3px rgba(156,163,175,.2)}
    .podium-3{border:2px solid #b45309; box-shadow:0 0 0 3px rgba(180,83,9,.2)}
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-gamepad me-2"></i><?php echo SITE_NAME; ?></a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="games.php">Juegos</a></li>
        <li class="nav-item"><a class="nav-link active" href="favorites.php">Mis Favoritos</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Salir</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="fas fa-trophy text-warning me-2"></i>Mi Top de Favoritos</h1>
  </div>

  <?php if (empty($favs)): ?>
    <div class="alert alert-info">Aún no tienes favoritos. Añade juegos desde su ficha con el botón “Añadir a favoritos”.</div>
  <?php else: ?>
    <form method="post" id="orderForm">
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table align-middle table-hover">
              <thead>
                <tr>
                  <th style="width:80px">#</th>
                  <th>Juego</th>
                  <th style="width:220px">Plataforma / Género</th>
                  <th style="width:160px">Mover a</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($favs as $i => $f): $pos=$i+1; $slug = isset($f['slug']) ? $f['slug'] : ''; $href='game.php?'.($slug?('slug='.urlencode($slug)):('id='.(int)$f['game_id'])); ?>
                <tr class="<?php echo $pos===1 ? 'podium-1' : ($pos===2 ? 'podium-2' : ($pos===3 ? 'podium-3' : '')); ?>">
                  <td>
                    <?php if ($pos===1): ?><i class="fas fa-medal text-warning me-1"></i><?php endif; ?>
                    <?php if ($pos===2): ?><i class="fas fa-medal text-secondary me-1"></i><?php endif; ?>
                    <?php if ($pos===3): ?><i class="fas fa-medal" style="color:#b45309"></i><?php endif; ?>
                    <strong>#<?php echo $pos; ?></strong>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <img src="<?php echo $f['imagen']?UPLOAD_PATH.$f['imagen']:'https://via.placeholder.com/80x80?text=Sin+Imagen'; ?>" width="80" height="80" style="object-fit:cover;border-radius:8px;" alt="<?php echo htmlspecialchars($f['titulo']); ?>">
                      <div>
                        <a href="<?php echo $href; ?>" class="fw-semibold text-decoration-none"><?php echo htmlspecialchars($f['titulo']); ?></a>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($f['plataforma']); ?></span>
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($f['genero']); ?></span>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="input-group input-group-sm" style="width: 100px;">
                        <input type="number" class="form-control" min="1" name="pos[<?php echo (int)$f['game_id']; ?>]" value="<?php echo (int)$pos; ?>" aria-label="Mover a posición">
                      </div>
                      <button name="remove_id" value="<?php echo (int)$f['game_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Quitar de favoritos?');"><i class="fas fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar cambios</button>
          </div>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>

<script>
// Nada que hacer: el reorden se hace con inputs de número
</script>
</body>
</html>
