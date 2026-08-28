<?php
/**
 * admin/layout/cabeza.php — apertura de cualquier pantalla del panel.
 *
 * Cómo se usa una pantalla del panel:
 *
 *   <?php
 *   declare(strict_types=1);
 *   panel_exigir_sesion();
 *   // … procesar el POST y redirigir …
 *   $titulo = 'Productos';
 *   require RASTRO_VIEWS . '/admin/layout/cabeza.php';
 *   ?>
 *   … la pantalla …
 *   <?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
 *
 * NOMBRES RESERVADOS (los escribe la pantalla, los lee el layout):
 *   $titulo        string  título de la pantalla, obligatorio
 *   $bajada        string  una línea de contexto abajo del título
 *   $accion        array   botón principal: ['texto'=>…, 'href'=>…]
 *   $volver        array   migaja de vuelta: ['texto'=>…, 'href'=>…]
 *   $panel_pelado  bool    sin barra ni menú (la pantalla de ingreso)
 *
 * ---------------------------------------------------------------------
 * POR QUÉ EL PANEL TIENE SU PROPIO LAYOUT Y NO REUSA layout/head.php
 *
 * El del sitio arrastra el marquee, la cabecera con mega-menú, el carrito
 * y cuatro hojas de estilo pensadas para vender. El panel no vende: es una
 * herramienta de trabajo, es sólo escritorio y lo abre una persona por
 * semana. Compartir el layout obligaría a llenar los dos de condicionales
 * hasta que ninguno de los dos se entienda. Lo que sí comparten es lo que
 * de verdad tienen en común: `tokens.css` —una sola paleta, una sola
 * escala tipográfica— y los helpers de escape.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

$panel_usuario_actual = panel_usuario();
$panel_pelado         = !empty($panel_pelado);
$panel_titulo_pagina  = ($titulo ?? 'Panel') . ' · Panel de Rastro Fitness';
$panel_aviso_actual   = panel_aviso();
?>
<?php /* data-tema="admin" es lo que enciende los tokens en claro que ya
         están en tokens.css y que salen de la colección Color de Figma
         (modo "Admin"). No es un segundo sistema de diseño: son los mismos
         24 tokens con otros valores, y por eso admin.css no tiene un solo
         color literal. El sitio público sigue en negro. */ ?>
<!doctype html>
<html lang="es-AR" data-tema="admin">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($panel_titulo_pagina) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <?php /* El panel va en claro (tema Admin), al revés que el sitio.
             Declararlo evita que el navegador pinte de negro los controles
             nativos —el desplegable, el selector de archivo— sobre un fondo
             blanco. */ ?>
    <meta name="theme-color" content="#f4f4f4">
    <meta name="color-scheme" content="light">

    <link rel="icon" href="<?= e(asset('img/favicon/favicon-32x32.png')) ?>" sizes="32x32" type="image/png">

    <link rel="preload" href="<?= e(asset('fonts/jetbrains-mono-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('fonts/saira-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>

    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="panel<?= $panel_pelado ? ' panel--pelado' : '' ?>">

<?php if (!$panel_pelado): ?>

    <a class="panel__saltar" href="#panel-contenido">Saltar al contenido</a>

    <header class="panel__barra">
        <a class="panel__marca" href="<?= e(url('/admin')) ?>">
            <?php /* La variante negra, no la blanca: el panel va sobre
                     fondo claro y el logo blanco ahí no se ve. */ ?>
            <img src="<?= e(asset('img/marca/rastro-completo-negro.png')) ?>"
                 alt="Rastro Fitness" width="90" height="32">
            <span class="panel__marca-rotulo">Panel</span>
        </a>

        <div class="panel__barra-derecha">
            <?php /* Abre en pestaña nueva a propósito: el cliente entra al
                     panel a cargar cosas y quiere mirar cómo quedaron sin
                     perder la pantalla en la que estaba trabajando. */ ?>
            <a class="panel__enlace-barra" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                Ver el sitio
                <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
            </a>

            <?php if ($panel_usuario_actual !== null): ?>
                <span class="panel__quien"><?= e((string) $panel_usuario_actual['nombre']) ?></span>

                <?php /* Salir es un POST y no un enlace: un GET que cierra
                         sesión lo dispara cualquier cosa que precargue
                         enlaces, y el cliente se encuentra afuera sin haber
                         tocado nada. */ ?>
                <form class="panel__salir" method="post" action="<?= e(url('/admin/salir')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                    <button class="panel__enlace-barra" type="submit">Salir</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="panel__cuerpo">

        <nav class="panel__lateral" aria-label="Secciones del panel">
            <ul class="panel__menu">
                <?php foreach (panel_secciones() as $seccion): ?>
                    <?php
                    $activa = $seccion['exacta']
                        ? RASTRO_RUTA === $seccion['ruta']
                        : str_starts_with(RASTRO_RUTA, $seccion['ruta']);
                    ?>
                    <li>
                        <a class="panel__menu-enlace<?= $activa ? ' es-activo' : '' ?>"
                           href="<?= e(url($seccion['ruta'])) ?>"
                           <?= $activa ? 'aria-current="page"' : '' ?>>
                            <?= e($seccion['titulo']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main class="panel__contenido" id="panel-contenido" tabindex="-1">

            <?php if (!empty($volver)): ?>
                <a class="panel__volver" href="<?= e($volver['href']) ?>">
                    <span aria-hidden="true">←</span> <?= e($volver['texto']) ?>
                </a>
            <?php endif; ?>

            <div class="panel__encabezado">
                <div>
                    <h1 class="panel__titulo"><?= e($titulo ?? 'Panel') ?></h1>
                    <?php if (!empty($bajada)): ?>
                        <p class="panel__bajada"><?= e($bajada) ?></p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($accion)): ?>
                    <a class="panel-boton panel-boton--acento" href="<?= e($accion['href']) ?>">
                        <?= e($accion['texto']) ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php /* El aviso va acá, arriba de todo y con role="status", para
                     que un lector de pantalla lo anuncie al cargar la página
                     que sigue al guardado. Un cartel que sólo cambia de color
                     no le avisa a nadie que no lo esté mirando. */ ?>
            <?php if ($panel_aviso_actual !== null): ?>
                <p class="panel-aviso panel-aviso--<?= e($panel_aviso_actual['tipo']) ?>" role="status">
                    <span class="panel-aviso__icono" aria-hidden="true"><?= $panel_aviso_actual['tipo'] === 'ok' ? '✓' : '!' ?></span>
                    <?= e($panel_aviso_actual['texto']) ?>
                </p>
            <?php endif; ?>

<?php endif; ?>
