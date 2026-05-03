<?php
// Renderiza el mensaje flash de sesion como toast Bootstrap.
// Llamar dentro del <body>, despues del navbar (necesita el contenedor
// fixed top-end). El toast se muestra y se autodescarta solo.
//
// Convencion de sesion (compatible con el codigo previo):
//   $_SESSION['flash_message']  string con el contenido (puede llevar HTML)
//   $_SESSION['flash_success']  bool true si es exito, false/ausente si es error
//
// El mensaje se consume (se borra de sesion) tras renderizarse, asi solo
// aparece una vez aunque se recargue la pagina.

$flashMessage = $_SESSION['flash_message'] ?? null;
$flashSuccess = !empty($_SESSION['flash_success']);
unset($_SESSION['flash_message'], $_SESSION['flash_success']);

if ($flashMessage !== null && $flashMessage !== ''):
    $variant = $flashSuccess ? 'success' : 'danger';
    $icon    = $flashSuccess ? 'check-circle' : 'exclamation-circle';
?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="appFlashToast" class="toast align-items-center text-bg-<?= e($variant) ?> border-0"
       role="alert" aria-live="assertive" aria-atomic="true"
       data-bs-autohide="true" data-bs-delay="5000">
    <div class="d-flex">
      <div class="toast-body">
        <i class="fas fa-<?= e($icon) ?> me-2" aria-hidden="true"></i>
        <?= $flashMessage ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('appFlashToast');
    if (el && window.bootstrap && bootstrap.Toast) {
      bootstrap.Toast.getOrCreateInstance(el).show();
    }
  });
</script>
<?php endif; ?>
