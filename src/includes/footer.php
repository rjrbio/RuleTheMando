<?php
// Pie comun: cierra body/html y carga scripts compartidos.
//
// Variables opcionales:
//   $extraScripts      string|string[]   rutas a scripts cargados despues de bootstrap
//   $extraScriptsHtml  string            HTML libre que se inyecta al final
//                                        (util para bloques <script> inline; tipico
//                                        uso con ob_start / ob_get_clean en la pagina).
$extraScripts = $extraScripts ?? [];
if (is_string($extraScripts)) { $extraScripts = [$extraScripts]; }
$extraScriptsHtml = $extraScriptsHtml ?? '';
?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<?php foreach ($extraScripts as $script): ?>
  <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
<?= $extraScriptsHtml ?>
</body>
</html>
