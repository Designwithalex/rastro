<?php
/**
 * admin/_cabecera.php — apertura de cualquier pantalla del panel.
 *
 * Es el equivalente de layout/head.php para el panel, pero aparte a
 * propósito: el panel no lleva marquee, ni la navegación del sitio, ni el
 * pie con la marca de agua, y va en claro. Meterlo en el mismo archivo
 * habría sido un layout lleno de condicionales.
 *
 * LO PRIMERO QUE HACE ES EL GUARD. Antes de imprimir un solo byte
 * comprueba que haya sesión y que el rol sea admin, así que ninguna
 * pantalla del panel puede olvidarse de proteger: se protege por incluir
 * la cabecera. Quien está logueado pero no es admin recibe un 404 y no un
 * 403, porque un 403 confirma que la ruta existe.
 *
 * CÓMO SE USA
 *
 *   $admin_titulo  = 'Productos';
 *   $admin_seccion = 'productos';        // marca el ítem del menú
 *   $admin_accion  = ['texto' => 'Nuevo producto', 'href' => url('/admin/productos/nuevo')];
 *   require RASTRO_VIEWS . '/admin/_cabecera.php';
 *   …
 *   require RASTRO_VIEWS . '/admin/_pie.php';
 *
 * EL MISMO SISTEMA, EN CLARO
 *
 * `<html data-tema="admin">` cambia los 24 tokens de color de tokens.css a
 * su variante clara. No hay un segundo sistema de diseño que mantener: son
 * los mismos tokens, la misma escala tipográfica y los mismos componentes.
 */

declare(strict_types=1);

$admin_usuario = sesion_exigir_admin();

/* El lado de escritura del contrato. Se carga acá y no en index.php: el
   sitio público no usa ni una de esas funciones, y para cuando esta línea
   corre el guard de arriba ya dijo que quien está del otro lado es admin. */
require_once RASTRO_RAIZ . '/app/repository-admin.php';

$admin_titulo  = (string) ($admin_titulo ?? 'Panel');
$admin_seccion = (string) ($admin_seccion ?? '');
$admin_accion  = $admin_accion ?? null;

/* Las nueve secciones, en el orden del frame. La clave es la que compara
   $admin_seccion; el número lo dibuja el menú. */
$admin_secciones = [
    'dashboard'     => ['texto' => 'Dashboard',        'href' => url('/admin')],
    'productos'     => ['texto' => 'Productos',        'href' => url('/admin/productos')],
    'pedidos'       => ['texto' => 'Pedidos',          'href' => url('/admin/pedidos')],
    'categorias'    => ['texto' => 'Categorías',       'href' => url('/admin/categorias')],
    'marcas'        => ['texto' => 'Marcas oficiales', 'href' => url('/admin/marcas')],
    'clientes'      => ['texto' => 'Clientes',         'href' => url('/admin/clientes')],
    'banners'       => ['texto' => 'Banners',          'href' => url('/admin/banners')],
    'nosotros'      => ['texto' => 'Nosotros',         'href' => url('/admin/nosotros')],
    'configuracion' => ['texto' => 'Configuración',    'href' => url('/admin/configuracion')],
];

/* Mensajes de una acción anterior. Se guardan en la sesión y se leen una
   sola vez: así "Producto guardado" sobrevive al redirect que va después
   de un POST, y no vuelve a aparecer si la persona recarga. */
$admin_aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
?>
<!doctype html>
<html lang="es-AR" data-tema="admin">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($admin_titulo) ?> · Panel Rastro</title>

    <?php /* El panel no se indexa. No alcanza con que esté detrás de un
             login: si un buscador llega a una URL del panel, la lista
             igual con el título. */ ?>
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">

    <link rel="icon" href="<?= e(asset('img/favicon/favicon-32x32.png')) ?>" sizes="32x32" type="image/png">

    <link rel="preload" href="<?= e(asset('fonts/michroma-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('fonts/jetbrains-mono-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>

    <?php foreach (['tokens', 'base', 'componentes', 'admin'] as $hoja): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $hoja . '.css')) ?>">
    <?php endforeach; ?>
</head>
<body class="t-body-md pagina-admin">

<a class="saltar t-mono-label" href="#panel">Saltar al contenido</a>

<div class="admin">

    <?php /* ============================================================
             Barra lateral
             ============================================================ */ ?>
    <div class="admin__lateral">

        <div class="admin__marca">
            <a class="admin__logo" href="<?= e(url('/admin')) ?>">
                <img src="<?= e(asset('img/marca/rastro-completo-negro.png')) ?>"
                     alt="Rastro Fitness" width="120" height="32">
            </a>
            <span class="admin__insignia t-mono-label-sm">Admin</span>
        </div>

        <nav class="admin__nav" aria-label="Secciones del panel">
            <ul>
                <?php $n = 0; foreach ($admin_secciones as $clave => $seccion): $n++; ?>
                    <?php $activa = $clave === $admin_seccion; ?>
                    <li>
                        <a class="admin__item <?= $activa ? 'es-activo' : '' ?>"
                           href="<?= e($seccion['href']) ?>"
                           <?= $activa ? 'aria-current="page"' : '' ?>>
                            <span class="admin__numero t-mono-label-sm"><?= e(sprintf('%02d', $n)) ?></span>
                            <span class="t-mono-label"><?= e($seccion['texto']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="admin__usuario">
            <p class="admin__nombre t-mono-label"><?= e($admin_usuario['nombre'] . ' ' . $admin_usuario['apellido']) ?></p>
            <?php /* Cerrar sesión es un POST con token, igual que en el
                     sitio: un GET lo dispara cualquier imagen remota. */ ?>
            <form method="post" action="<?= e(url('/salir')) ?>">
                <?= csrf_campo() ?>
                <button class="admin__salir t-mono-label-sm" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </div>

    <?php /* ============================================================
             Contenido
             ============================================================ */ ?>
    <div class="admin__cuerpo">

        <header class="admin__barra">
            <h1 class="admin__titulo t-display-l"><?= e($admin_titulo) ?></h1>

            <div class="admin__acciones">
                <?php if ($admin_accion !== null): ?>
                    <a class="boton boton--acento admin__accion" href="<?= e($admin_accion['href']) ?>">
                        <span class="t-mono-label"><?= e($admin_accion['texto']) ?></span>
                        <span class="boton__flecha" aria-hidden="true">+</span>
                    </a>
                <?php endif; ?>

                <a class="admin__ver-sitio t-mono-label" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                    Ver el sitio
                    <span aria-hidden="true">↗</span>
                    <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
                </a>
            </div>
        </header>

        <main class="admin__panel" id="panel" tabindex="-1">

            <?php if ($admin_aviso !== null): ?>
                <?php /* role="status" y no "alert": es la confirmación de algo
                         que la persona acaba de hacer, no una interrupción. */ ?>
                <p class="admin-aviso admin-aviso--<?= e((string) ($admin_aviso['tipo'] ?? 'ok')) ?> t-mono-texto"
                   role="status">
                    <span class="admin-aviso__marca" aria-hidden="true">
                        <?= ($admin_aviso['tipo'] ?? 'ok') === 'error' ? '!' : '✓' ?>
                    </span>
                    <?= e((string) ($admin_aviso['texto'] ?? '')) ?>
                </p>
            <?php endif; ?>
