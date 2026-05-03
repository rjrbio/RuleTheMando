<?php
// Cabecera HTML compartida.
//
// Variables esperadas (todas opcionales):
//   $pageTitle  string  — texto que precede a SITE_NAME en el <title>
//   $extraCss   string|string[]  — rutas adicionales de hojas de estilo
//   $extraHead  string  — HTML libre que se inyecta al final del <head>
//                         (meta tags, JSON-LD, <style> inline, link canonical, etc.)
$pageTitle = $pageTitle ?? '';
$extraCss  = $extraCss  ?? [];
if (is_string($extraCss)) { $extraCss = [$extraCss]; }
$extraHead = $extraHead ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle !== '' ? e($pageTitle) . ' — ' : '' ?><?= e(SITE_NAME) ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
<?php foreach ($extraCss as $css): ?>
  <link href="<?= e($css) ?>" rel="stylesheet">
<?php endforeach; ?>
<?= $extraHead ?>
</head>
