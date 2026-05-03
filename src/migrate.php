<?php
// Endpoint admin para gestionar migraciones SQL.
// GET: muestra el estado (aplicadas + pendientes).
// POST con boton "Aplicar pendientes": ejecuta migrations_run().

require_once 'config.php';
require_once 'includes/migration-runner.php';
requireAdmin();

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    csrf_check();
    $results = migrations_run();
}

$applied = migrations_applied_versions();
$pending = migrations_pending();

$pageTitle = 'Migraciones';
include 'includes/head.php';
?>
<body class="bg-light">
<?php
$activePage = 'admin';
include 'includes/navbar.php';

$headerTitle    = 'Migraciones de base de datos';
$headerSubtitle = 'Estado de los cambios de schema aplicados a esta BD';
$headerIcon     = 'fas fa-database';
include 'includes/page-header.php';
include 'includes/flash.php';
?>
<main class="container my-4">
  <div class="row g-4">

    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-clock me-2" aria-hidden="true"></i>Pendientes</strong>
          <span class="badge bg-warning text-dark"><?= count($pending) ?></span>
        </div>
        <div class="card-body">
          <?php if (empty($pending)): ?>
            <p class="text-muted mb-0">No hay migraciones pendientes. La BD está al día.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush mb-3">
              <?php foreach ($pending as $m): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <code><?= e($m['version']) ?></code>
                </li>
              <?php endforeach; ?>
            </ul>
            <form method="post" data-confirm="¿Aplicar <?= count($pending) ?> migración(es) ahora? El cambio en la BD es irreversible salvo backup.">
              <?= csrf_field() ?>
              <button type="submit" name="run" value="1" class="btn btn-primary">
                <i class="fas fa-play me-2" aria-hidden="true"></i>Aplicar pendientes
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-success-subtle d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-check-circle me-2" aria-hidden="true"></i>Aplicadas</strong>
          <span class="badge bg-success"><?= count($applied) ?></span>
        </div>
        <div class="card-body">
          <?php if (empty($applied)): ?>
            <p class="text-muted mb-0">Aún no se ha aplicado ninguna migración.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush mb-0">
              <?php foreach ($applied as $r): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <code><?= e($r['version']) ?></code>
                  <small class="text-muted"><?= e($r['applied_at']) ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($results !== null): ?>
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <strong><i class="fas fa-flag-checkered me-2" aria-hidden="true"></i>Resultado de la última ejecución</strong>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush mb-0">
            <?php foreach ($results as $r): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                  <code><?= e($r['version']) ?></code>
                  <?php if (!empty($r['ok'])): ?>
                    <span class="badge bg-success">
                      <i class="fas fa-check me-1" aria-hidden="true"></i>
                      <?= e($r['note'] ?? 'OK') ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-danger">
                      <i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>Falló
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($r['error'])): ?>
                  <pre class="small text-danger mb-0 mt-2" style="white-space: pre-wrap;"><?= e($r['error']) ?></pre>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-12">
      <div class="alert alert-info mb-0">
        <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
        Las migraciones viven en <code>src/migrations/</code> con formato
        <code>YYYY-MM-DD-descripcion.sql</code>. Para añadir una nueva, crea
        el archivo y aplícalo desde aquí. La tabla <code>schema_migrations</code>
        marca cuáles ya se han ejecutado para que no se repitan.
      </div>
    </div>

  </div>
</main>
<?php include 'includes/site-footer.php'; ?>
<?php include 'includes/footer.php'; ?>
