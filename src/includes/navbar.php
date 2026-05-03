<?php
// Navbar Bootstrap compartida.
//
// Variables esperadas (todas opcionales):
//   $activePage  string  — 'games' | 'favorites' | 'admin' | 'home' | ''
//                          marca el item activo en la barra
//   $showSearch  bool    — si true, muestra el buscador a la izquierda
//                          (envia GET ?buscar= a index.php)
$activePage = $activePage ?? '';
$showSearch = $showSearch ?? false;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="fas fa-gamepad me-2"></i><?= e(SITE_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
<?php if ($showSearch): ?>
      <form class="d-flex me-auto ms-3" method="GET" action="index.php" role="search">
        <div class="input-group">
          <input class="form-control" type="search" name="buscar" placeholder="Buscar juegos…"
                 value="<?= e($_GET['buscar'] ?? '') ?>" aria-label="Buscar juegos">
          <button class="btn btn-outline-light" type="submit" aria-label="Buscar">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
<?php else: ?>
      <ul class="navbar-nav me-auto"></ul>
<?php endif; ?>
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link<?= $activePage === 'games' ? ' active' : '' ?>" href="games.php">
            <i class="fas fa-list"></i> Juegos
          </a>
        </li>
<?php if (isLoggedIn()): ?>
        <li class="nav-item">
          <a class="nav-link<?= $activePage === 'favorites' ? ' active' : '' ?>" href="favorites.php">
            <i class="fas fa-trophy"></i> Mis Favoritos
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user"></i> <?= e($_SESSION['username'] ?? '') ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
<?php if (isAdmin()): ?>
            <li>
              <a class="dropdown-item<?= $activePage === 'admin' ? ' active' : '' ?>" href="admin.php">
                <i class="fas fa-cog"></i> Administrar
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
<?php endif; ?>
            <li>
              <a class="dropdown-item" href="admin-change-password.php">
                <i class="fas fa-key"></i> Cambiar contraseña
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
              </a>
            </li>
          </ul>
        </li>
<?php else: ?>
        <li class="nav-item">
          <a class="nav-link" href="login.php">
            <i class="fas fa-sign-in-alt"></i> Iniciar sesión
          </a>
        </li>
<?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
