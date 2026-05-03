<?php
require_once 'config.php';
require_once 'includes/db.php';

if (!isLoggedIn()) { redirect('login.php'); }
$uid = (int)$_SESSION['user_id'];

ensure_auxiliary_tables();

// Save new order (nuevo: por posiciones), mantiene compatibilidad con 'order[]'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
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

// Paginacion: 15 favoritos por pagina. Las posiciones que se renderizan
// son las reales (campo posicion), no el indice del array — asi mover
// un elemento entre paginas no cambia su numero visual.
$favPerPage = 15;
$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM favoritos WHERE user_id = ?');
$cntStmt->execute([$uid]);
$totalFavs = (int)$cntStmt->fetchColumn();
$favPages = max(1, (int)ceil($totalFavs / $favPerPage));
$favPage = isset($_GET['page']) && is_numeric($_GET['page'])
    ? max(1, min($favPages, (int)$_GET['page']))
    : 1;
$favOffset = ($favPage - 1) * $favPerPage;

$stmt = $pdo->prepare(
    'SELECT f.game_id, f.posicion, v.titulo, v.imagen, v.genero, v.plataforma' . $selectSlug .
    ' FROM favoritos f JOIN videojuegos v ON v.id=f.game_id
      WHERE f.user_id=? ORDER BY f.posicion ASC
      LIMIT ' . (int)$favPerPage . ' OFFSET ' . (int)$favOffset
);
$stmt->execute([$uid]);
$favs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Mis Favoritos';
// .podium-1/2/3 viven en styles.css; aqui solo cosmetica local.
$extraHead = <<<HTML
<style>
  .fav-item { cursor: grab; }
</style>
HTML;
include 'includes/head.php';
?>
<body class="bg-light">
<?php
$activePage = 'favorites';
include 'includes/navbar.php';

$headerTitle = 'Mi Top de Favoritos';
$headerSubtitle = 'Tu ranking personal: ordena tus juegos preferidos como quieras';
$headerIcon = 'fas fa-trophy';
include 'includes/page-header.php';
include 'includes/flash.php';
?>

<main class="container my-4">

  <?php if (empty($favs)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
      <div class="card-body">
        <div class="mb-3">
          <i class="fas fa-trophy text-muted" style="font-size: 3rem;" aria-hidden="true"></i>
        </div>
        <h2 class="h5 fw-bold mb-2">Tu Top está vacío</h2>
        <p class="text-muted mb-4 mx-auto" style="max-width: 480px;">
          Añade juegos a tus favoritos desde la ficha de cada uno y luego ord&eacute;nalos aquí como tu ranking personal.
        </p>
        <a href="games.php" class="btn btn-primary">
          <i class="fas fa-list me-2" aria-hidden="true"></i>Explorar el catálogo
        </a>
      </div>
    </div>
  <?php else: ?>
    <form method="post" id="orderForm">
      <?= csrf_field() ?>
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
              <?php foreach ($favs as $f): $pos=(int)$f['posicion']; $slug = isset($f['slug']) ? $f['slug'] : ''; $href='game.php?'.($slug?('slug='.urlencode($slug)):('id='.(int)$f['game_id'])); ?>
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
                      <button name="remove_id" value="<?php echo (int)$f['game_id']; ?>" class="btn btn-sm btn-outline-danger" type="submit" data-confirm="¿Quitar de favoritos?"><i class="fas fa-trash"></i></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <small class="text-muted">
              Mostrando <?= count($favs) ?> de <?= (int)$totalFavs ?>
              <?= $totalFavs === 1 ? 'favorito' : 'favoritos' ?>
            </small>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i> Guardar cambios</button>
          </div>
<?php if ($favPages > 1): ?>
          <nav aria-label="Paginación de favoritos" class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
              <li class="page-item <?= $favPage === 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $favPage - 1 ?>" aria-label="Anterior">&laquo;</a>
              </li>
<?php for ($p = 1; $p <= $favPages; $p++): ?>
              <li class="page-item <?= $p === $favPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
              </li>
<?php endfor; ?>
              <li class="page-item <?= $favPage === $favPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $favPage + 1 ?>" aria-label="Siguiente">&raquo;</a>
              </li>
            </ul>
          </nav>
<?php endif; ?>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>

<?php include 'includes/site-footer.php'; ?>
<?php include 'includes/footer.php'; ?>
