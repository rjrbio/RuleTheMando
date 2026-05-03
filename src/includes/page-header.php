<?php
// Mini cabecera de pagina interna (no es el banner hero del index).
// Strip con titulo + subtitulo opcional. Pensado para games, favorites,
// y futuras paginas internas que ahora arrancan directamente con el
// <main> sin contexto visual.
//
// Variables esperadas (todas opcionales):
//   $headerTitle    string — titulo grande
//   $headerSubtitle string — descripcion bajo el titulo
//   $headerIcon     string — clase de Font Awesome (e.g. 'fas fa-list')
$headerTitle    = $headerTitle    ?? '';
$headerSubtitle = $headerSubtitle ?? '';
$headerIcon     = $headerIcon     ?? '';
?>
<header class="page-header text-white py-4 mb-4">
  <div class="container">
    <h1 class="h3 fw-bold mb-1">
<?php if ($headerIcon !== ''): ?>
      <i class="<?= e($headerIcon) ?> me-2" aria-hidden="true"></i>
<?php endif; ?>
      <?= e($headerTitle) ?>
    </h1>
<?php if ($headerSubtitle !== ''): ?>
    <p class="mb-0 text-white-50"><?= e($headerSubtitle) ?></p>
<?php endif; ?>
  </div>
</header>
