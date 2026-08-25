<?php
/**
 * layout/head.php — apertura del documento.
 *
 * Cómo se usa una página:
 *
 *   <?php
 *   $titulo      = 'Catálogo';                  // opcional
 *   $descripcion = 'Discos, barras y racks.';   // opcional
 *   $clase_body  = 'pagina-catalogo';           // opcional
 *   require RASTRO_VIEWS . '/layout/head.php';
 *   ?>
 *   <main id="contenido"> … </main>
 *   <?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
 *
 * Este archivo abre <body> y deja puestos el marquee y la cabecera.
 * El pie y el cierre del documento los pone footer.php.
 */

declare(strict_types=1);

$settings = repo_settings();

$titulo_pagina = isset($titulo) && $titulo !== ''
    ? $titulo . ' · Rastro Fitness'
    : 'Rastro Fitness · Equipamiento profesional de gimnasio';

$descripcion_pagina = $descripcion
    ?? 'Discos, barras, mancuernas, kettlebells y racks para gimnasios, clubes y entrenamiento en casa. '
     . descuento_global($settings)
     . '% de descuento pagando por transferencia o efectivo.';
?>
<!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?></title>
    <meta name="description" content="<?= e($descripcion_pagina) ?>">
    <meta name="theme-color" content="#000000">
    <meta name="color-scheme" content="dark">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Rastro Fitness">
    <meta property="og:title" content="<?= e($titulo_pagina) ?>">
    <meta property="og:description" content="<?= e($descripcion_pagina) ?>">
    <meta property="og:locale" content="es_AR">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="<?= e(asset('img/favicon/favicon-32x32.png')) ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= e(asset('img/favicon/favicon-192x192.png')) ?>" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('img/favicon/favicon-180x180.png')) ?>">

    <?php /* Las dos familias que aparecen arriba de todo: el titular y el texto. */ ?>
    <link rel="preload" href="<?= e(asset('fonts/michroma-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('fonts/saira-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>

    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
</head>
<body<?= isset($clase_body) && $clase_body !== '' ? ' class="' . e($clase_body) . '"' : '' ?>>

<a class="saltar t-mono-label" href="#contenido">Saltar al contenido</a>

<?php
require RASTRO_VIEWS . '/layout/marquee.php';
require RASTRO_VIEWS . '/layout/header.php';
