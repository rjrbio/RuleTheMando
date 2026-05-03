<?php
require_once 'config.php';
require_once 'includes/db.php';

ensure_auxiliary_tables();

// Filtros básicos
$buscar = isset($_GET['q']) ? trim($_GET['q']) : '';
$plataforma = isset($_GET['plataforma']) ? trim($_GET['plataforma']) : '';
$genero = isset($_GET['genero']) ? trim($_GET['genero']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : ''; // all|available|upcoming
$startsWith = isset($_GET['starts']) ? strtoupper(substr(trim($_GET['starts']), 0, 1)) : '';
// Orden
$orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'recientes';

// Paginación
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 8;
$offset = ($page - 1) * $perPage;

// Construir query
$where = [];
$params = [];
if ($buscar !== '') {
  $where[] = '(titulo LIKE ? OR descripcion LIKE ?)';
  $params[] = '%' . $buscar . '%';
  $params[] = '%' . $buscar . '%';
}
if ($plataforma !== '') {
  $where[] = 'plataforma = ?';
  $params[] = $plataforma;
}
if ($genero !== '') {
  $where[] = 'genero = ?';
  $params[] = $genero;
}
if ($estado === 'available') {
  $where[] = 'es_futuro_lanzamiento = 0';
} elseif ($estado === 'upcoming') {
  $where[] = 'es_futuro_lanzamiento = 1';
}
if ($startsWith !== '' && ctype_alpha($startsWith)) {
  $where[] = 'titulo LIKE ?';
  $params[] = $startsWith . '%';
}

// Count total
$countSql = 'SELECT COUNT(*) FROM videojuegos';
if (!empty($where)) {
  $countSql .= ' WHERE ' . implode(' AND ', $where);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

// Obtener página actual con agregados de nota
$sql = 'SELECT v.*, COALESCE(r.avg_rating, 0) AS avg_rating, COALESCE(r.votes, 0) AS votes
        FROM videojuegos v
        LEFT JOIN (
          SELECT game_id, AVG(rating) AS avg_rating, COUNT(*) AS votes
          FROM valoraciones
          GROUP BY game_id
        ) r ON r.game_id = v.id';
if (!empty($where)) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Orden dinámico
switch ($orden) {
  case 'nota_desc': $orderBy = 'avg_rating DESC, votes DESC, v.created_at DESC'; break;
  case 'nota_asc': $orderBy = 'avg_rating ASC, v.created_at DESC'; break;
  case 'titulo_asc': $orderBy = 'v.titulo ASC'; break;
  case 'titulo_desc': $orderBy = 'v.titulo DESC'; break;
  case 'fecha_asc': $orderBy = 'v.fecha_lanzamiento ASC'; break;
  case 'fecha_desc': $orderBy = 'v.fecha_lanzamiento DESC'; break;
  default: $orderBy = 'v.created_at DESC';
}
$sql .= ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$juegos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar listas para filtros
$plataformas = $pdo->query("SELECT DISTINCT plataforma FROM videojuegos WHERE plataforma IS NOT NULL AND plataforma <> '' ORDER BY plataforma ASC")->fetchAll(PDO::FETCH_COLUMN);
$generos = $pdo->query("SELECT DISTINCT genero FROM videojuegos WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Todos los juegos';
// Pequeno reset local para esta vista (estilos completos en styles.css)
$extraHead = <<<HTML
<style>
  .game-cover { width: 100%; height: 100%; object-fit: cover; }
  .game-cover-wrap { min-height: 260px; display: flex; align-items: center; justify-content: center; }
</style>
HTML;
include 'includes/head.php';
?>
<body class="bg-light">
<?php
$activePage = 'games';
include 'includes/navbar.php';

$headerTitle = 'Catálogo de juegos';
$headerSubtitle = 'Todos los videojuegos disponibles, con filtros y orden';
$headerIcon = 'fas fa-list';
include 'includes/page-header.php';
include 'includes/flash.php';
?>

  <main class="container my-4">
    <div class="row g-4">
      <!-- Filtros -->
      <div class="col-lg-3">
        <div class="card filters-card">
          <div class="card-header bg-primary text-white">
            <i class="fas fa-filter me-2"></i>Filtros
          </div>
          <div class="card-body">
            <form method="get">
              <div class="mb-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Título o descripción">
              </div>
              <div class="mb-3">
                <label class="form-label">Plataforma</label>
                <select class="form-select" name="plataforma">
                  <option value="">Todas</option>
                  <?php foreach ($plataformas as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $plataforma===$p?'selected':''; ?>><?php echo htmlspecialchars($p); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Género</label>
                <select class="form-select" name="genero">
                  <option value="">Todos</option>
                  <?php foreach ($generos as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $genero===$g?'selected':''; ?>><?php echo htmlspecialchars($g); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                  <option value="" <?php echo $estado===''?'selected':''; ?>>Todos</option>
                  <option value="available" <?php echo $estado==='available'?'selected':''; ?>>Disponible</option>
                  <option value="upcoming" <?php echo $estado==='upcoming'?'selected':''; ?>>Próximamente</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Ordenar por</label>
                <select class="form-select" name="orden">
                  <option value="recientes" <?php echo $orden==='recientes'?'selected':''; ?>>Más recientes</option>
                  <option value="nota_desc" <?php echo $orden==='nota_desc'?'selected':''; ?>>Nota comunidad (alta a baja)</option>
                  <option value="nota_asc" <?php echo $orden==='nota_asc'?'selected':''; ?>>Nota comunidad (baja a alta)</option>
                  <option value="titulo_asc" <?php echo $orden==='titulo_asc'?'selected':''; ?>>Título (A-Z)</option>
                  <option value="titulo_desc" <?php echo $orden==='titulo_desc'?'selected':''; ?>>Título (Z-A)</option>
                  <option value="fecha_desc" <?php echo $orden==='fecha_desc'?'selected':''; ?>>Fecha lanzamiento (nueva a antigua)</option>
                  <option value="fecha_asc" <?php echo $orden==='fecha_asc'?'selected':''; ?>>Fecha lanzamiento (antigua a nueva)</option>
                </select>
              </div>
              <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i> Aplicar</button>
                <a class="btn btn-outline-secondary" href="games.php">Limpiar</a>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Lista de juegos -->
      <div class="col-lg-9">
        <?php if (empty($juegos)): ?>
          <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
              <div class="mb-3">
                <i class="fas fa-search text-muted" style="font-size: 3rem;" aria-hidden="true"></i>
              </div>
              <h2 class="h5 fw-bold mb-2">Sin resultados</h2>
              <p class="text-muted mb-4 mx-auto" style="max-width: 460px;">
                No hay juegos que cumplan los filtros actuales. Prueba a quitar alguno o cambia el término de búsqueda.
              </p>
              <a href="games.php" class="btn btn-primary">
                <i class="fas fa-times-circle me-2" aria-hidden="true"></i>Limpiar filtros
              </a>
            </div>
          </div>
        <?php else: ?>
          <!-- Índice A-Z -->
          <div class="card mb-3">
            <div class="card-body py-2">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong class="me-2">Índice:</strong>
                <?php foreach (range('A','Z') as $letter): ?>
                  <?php
                    $query = $_GET;
                    $query['starts'] = $letter;
                    unset($query['page']);
                    $url = 'games.php?' . http_build_query($query);
                  ?>
                  <a class="btn btn-sm <?php echo $startsWith===$letter?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo $url; ?>"><?php echo $letter; ?></a>
                <?php endforeach; ?>
                <?php
                  $query = $_GET; unset($query['starts']); unset($query['page']);
                  $urlAll = 'games.php' . (empty($query)?'':'?' . http_build_query($query));
                ?>
                <a class="btn btn-sm btn-outline-dark ms-auto" href="<?php echo $urlAll; ?>">Todos</a>
              </div>
            </div>
          </div>
          <?php foreach ($juegos as $juego): ?>
            <section class="game-section">
              <div class="row g-0">
                <div class="col-md-5">
                  <div class="game-cover-wrap">
                    <img class="game-cover" src="<?php echo $juego['imagen'] ? UPLOAD_PATH . $juego['imagen'] : 'https://via.placeholder.com/640x360?text=Sin+Imagen'; ?>" alt="<?php echo e($juego['titulo']); ?>">
                  </div>
                </div>
                <div class="col-md-7">
                  <div class="p-4 h-100 d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <h3 class="mb-0"><?php echo e($juego['titulo']); ?></h3>
                      <?php if ($juego['es_futuro_lanzamiento']): ?>
                        <span class="badge badge-upcoming">Próximamente</span>
                      <?php else: ?>
                        <span class="badge badge-available">Disponible</span>
                      <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-3">Publicado el <?php echo date('d/m/Y', strtotime($juego['fecha_lanzamiento'])); ?></p>
                    <p class="flex-grow-1" style="white-space: pre-wrap;"><?php echo htmlspecialchars($juego['descripcion']); ?></p>
                    <div class="mt-3">
                      <div class="row g-2">
                        <div class="col-6"><small><strong>Plataforma:</strong> <?php echo e($juego['plataforma']); ?></small></div>
                        <div class="col-6"><small><strong>Género:</strong> <?php echo e($juego['genero']); ?></small></div>
                        <div class="col-6"><small><strong>Desarrollador:</strong> <?php echo e($juego['desarrollador']); ?></small></div>
                        
                      </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                      <?php $slug = isset($juego['slug']) ? $juego['slug'] : ''; ?>
                      <a class="btn btn-outline-primary btn-sm" href="game.php?<?php echo $slug ? ('slug=' . urlencode($slug)) : ('id=' . (int)$juego['id']); ?>">
                        <i class="fas fa-info-circle me-1"></i> Ver detalle
                      </a>
                      <span class="ms-auto align-self-center small text-muted">
                        <i class="fas fa-star text-warning"></i>
                        <?php echo !$juego['es_futuro_lanzamiento'] && $juego['avg_rating'] ? number_format((float)$juego['avg_rating'], 1) : '-'; ?>
                      </span>
                      <?php if (isAdmin()): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="admin.php?edit=<?php echo (int)$juego['id']; ?>#manage-games" title="Editar en panel admin">
                          <i class="fas fa-edit me-1"></i> Editar
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </section>
          <?php endforeach; ?>

          <!-- Paginador -->
          <?php if ($totalPages > 1): ?>
            <nav>
              <ul class="pagination justify-content-center">
                <?php
                  $query = $_GET;
                  $prev = max(1, $page - 1);
                  $next = min($totalPages, $page + 1);
                  $query['page'] = $prev; $prevUrl = 'games.php?' . http_build_query($query);
                  $query['page'] = $next; $nextUrl = 'games.php?' . http_build_query($query);
                ?>
                <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
                  <a class="page-link" href="<?php echo $prevUrl; ?>" tabindex="-1">&laquo;</a>
                </li>
                <?php for ($p=1; $p <= $totalPages; $p++): ?>
                  <?php $query['page'] = $p; $url = 'games.php?' . http_build_query($query); ?>
                  <li class="page-item <?php echo $page===$p?'active':''; ?>">
                    <a class="page-link" href="<?php echo $url; ?>"><?php echo $p; ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>">
                  <a class="page-link" href="<?php echo $nextUrl; ?>">&raquo;</a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php include 'includes/site-footer.php'; ?>

  <?php
  ob_start();
  ?>
  <script>
    // Búsqueda de usuarios
    const searchUsers = document.getElementById('searchUsers');
    if (searchUsers) {
      searchUsers.addEventListener('keyup', function(){
        const term = this.value.toLowerCase();
        document.querySelectorAll('#usersTable tbody tr').forEach(tr => {
          tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
      });
    }
  </script>
  <?php
  $extraScriptsHtml = ob_get_clean();
  include 'includes/footer.php';
  ?>