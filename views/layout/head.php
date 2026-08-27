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
 *   <main id="contenido" tabindex="-1"> … </main>
 *   <?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
 *
 * El tabindex="-1" del <main> no es opcional: es el destino del enlace
 * "Saltar al contenido". Sin él, Firefox mueve el scroll pero deja el foco
 * donde estaba, así que la siguiente vez que se aprieta Tab el recorrido
 * vuelve a empezar por la cabecera y el salto no sirvió para nada. Chrome
 * lo perdona; Firefox no, y el atributo no le molesta a ninguno de los dos.
 *
 * Este archivo abre <body> y deja puestos el marquee y la cabecera.
 * El pie y el cierre del documento los pone footer.php.
 *
 * ---------------------------------------------------------------------
 * NOMBRES RESERVADOS
 *
 * Los partials del layout se incluyen con require dentro del scope de la
 * vista: comparten variables con ella. Estas son las que ocupa el layout,
 * y ninguna vista puede usarlas para otra cosa.
 *
 * Las lee el layout (las escribe la vista, antes del require):
 *   $titulo       string  título de la pestaña, sin el sufijo de marca
 *   $descripcion  string  meta description
 *   $clase_body   string  clases extra del <body>
 *   $estilos      array   hojas de estilo propias de la página, por nombre
 *                         de archivo sin extensión: ['nosotros'] carga
 *                         assets/css/nosotros.css después de las comunes.
 *   $precargar_titular
 *                 bool    precarga Saira Condensed Black. La usa sólo el
 *                         titular del hero, así que la pone sólo la home.
 *
 * Las define el layout (la vista las puede leer, no pisar):
 *   $settings            array   repo_settings(), ya cacheado
 *   $titulo_pagina       string  título final, con sufijo
 *   $descripcion_pagina  string  descripción final
 *
 * Todo lo demás que necesitan header.php y footer.php va con prefijo
 * `layout_` justamente para no chocar con las variables de las vistas.
 * ---------------------------------------------------------------------
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

// El cuerpo toma su tipografía de la escala, igual que cualquier otro
// elemento: base.css no declara ni un tamaño de fuente.
$clases_body = trim('t-body-md ' . ($clase_body ?? ''));

/* Hojas de estilo. Las tres primeras están en todas las páginas; nav.css
   también, porque la cabecera está en todas. Lo que es de una sola página
   —la de Nosotros, mañana el catálogo— lo declara la vista en $estilos y
   no lo baja el resto del sitio. */
$estilos_comunes = ['tokens', 'base', 'layout', 'nav'];
$estilos_pagina  = array_values(array_filter(
    (array) ($estilos ?? []),
    static fn ($hoja): bool => is_string($hoja) && preg_match('/^[a-z0-9-]+$/', $hoja) === 1
));
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

    <?php /* Saira Condensed es 18 KB y sólo la usa el titular del hero, que
             está en la home y en ningún otro lado. Se precarga únicamente
             ahí: bajarla en el catálogo o en el carrito sería pagar por una
             fuente que esas páginas no dibujan. La declara $precargar_titular. */ ?>
    <?php if (!empty($precargar_titular)): ?>
        <link rel="preload" href="<?= e(asset('fonts/saira-condensed-900.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <?php endif; ?>

    <?php foreach (array_merge($estilos_comunes, $estilos_pagina) as $hoja): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $hoja . '.css')) ?>">
    <?php endforeach; ?>

    <?php /* Lo único que puede reaccionar a la falta de JavaScript sin
             ejecutar JavaScript. El CSP de la raíz no permite <style> en
             línea, pero sí un <link> del mismo origen. */ ?>
    <noscript><link rel="stylesheet" href="<?= e(asset('css/sin-js.css')) ?>"></noscript>
</head>
<body class="<?= e($clases_body) ?>">

<a class="saltar t-mono-label" href="#contenido">Saltar al contenido</a>

<?php
require RASTRO_VIEWS . '/layout/marquee.php';
require RASTRO_VIEWS . '/layout/header.php';
