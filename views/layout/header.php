<?php
/**
 * layout/header.php — logo, navegación numerada, buscador y carrito.
 *
 * El menú es fijo y de cuatro puertas:
 *
 *   01 PRODUCTOS ▾ · 02 MAYORISTAS · 03 NOSOTROS · 04 CONTACTO
 *
 * "Catálogo" y "Productos" eran dos entradas al mismo lugar y se fusionaron
 * en una sola, que abre el mega-menú. CONTACTO no es una página: ancla a la
 * sección de contacto de Nosotros.
 *
 * PRODUCTOS es un <button aria-expanded aria-controls>, no un enlace, y eso
 * no es un detalle: en un menú desplegable disparado por un <a>, el primer
 * toque en una pantalla táctil navega en vez de abrir el panel, y el usuario
 * nunca llega a ver las categorías. Un botón no tiene destino, así que el
 * primer toque solo puede hacer una cosa: abrir. La puerta al catálogo
 * completo está adentro del panel ("Ver todo el catálogo") y en el pie.
 *
 * ORDEN DE FOCO = ORDEN VISUAL, en los dos anchos. Los elementos están en el
 * marcado en el mismo orden en el que se ven, y lo que no corresponde a un
 * ancho se apaga con display:none, que además lo saca del árbol de
 * accesibilidad. Ninguna regla de CSS reordena la cabecera.
 *
 *   celular   [hamburguesa] [logo] [buscar] [carrito]
 *   escritorio [logo] [nav] [buscador] [cuenta] [carrito]
 *
 * Las variables de este archivo van con prefijo `layout_` porque se requiere
 * dentro del scope de la vista y no le puede pisar un nombre.
 */

declare(strict_types=1);

require_once RASTRO_VIEWS . '/partials/iconos.php';

$layout_categorias = repo_categories();

/* El menú principal. `mega` es el disparador del panel; `ancla` es un enlace
   a una sección de otra página, que nunca lleva aria-current. */
$layout_nav = [
    ['tipo' => 'mega',   'texto' => 'Productos',  'ruta' => '/catalogo'],
    ['tipo' => 'enlace', 'texto' => 'Mayoristas', 'ruta' => '/mayoristas'],
    ['tipo' => 'enlace', 'texto' => 'Nosotros',   'ruta' => '/nosotros'],
    ['tipo' => 'ancla',  'texto' => 'Contacto',   'ruta' => '/nosotros#contacto'],
];

/* Los ocho productos de cada categoría que muestra la columna B del panel.
   Salen ordenados por relevancia, que pone primero los `destacado`: marcar
   un producto como destacado en el panel es lo que lo sube al menú.

   TODO(backend): son seis consultas por página. Con la base real conviene
   una sola —los N primeros de cada categoría en una sola pasada— o cachear
   el bloque, porque este menú se dibuja en todas las páginas del sitio. */
$layout_mega = [];
foreach ($layout_categorias as $layout_categoria) {
    $layout_mega[] = [
        'categoria' => $layout_categoria,
        'productos' => repo_products(['categoria' => $layout_categoria['slug']], 1, 8)['items'],
    ];
}

// param() y no $_GET: ?q[]=a llega como array y el cast tira un warning
// arriba de todo el HTML.
$layout_busqueda = param('q');

// La sección Productos queda marcada en todo el catálogo y en la ficha de
// cualquier producto: son el mismo lugar del sitio.
$layout_en_catalogo = es_ruta_activa('/catalogo') || es_ruta_activa('/producto');
?>
<header class="cabecera" data-cabecera>
    <div class="cabecera__barra contenedor">

        <?php /* Solo en celular. Abre el cajón, que sí es un diálogo. */ ?>
        <button class="cabecera__hamburguesa" type="button"
                aria-expanded="false" aria-controls="cajon-nav" data-cajon-abrir>
            <?= icono('menu') ?>
            <span class="visualmente-oculto">Abrir el menú</span>
        </button>

        <a class="cabecera__logo" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset('img/marca/rastro-horizontal-transparente.png')) ?>"
                 alt="Rastro Fitness" width="600" height="153" fetchpriority="high">
            <span class="visualmente-oculto">Ir al inicio</span>
        </a>

        <nav class="nav" aria-label="Principal">
            <ul class="nav__lista">
                <?php foreach ($layout_nav as $layout_i => $layout_item): ?>
                    <?php
                    $layout_indice = str_pad((string) ($layout_i + 1), 2, '0', STR_PAD_LEFT);

                    // El ancla a una sección de otra página no puede ser
                    // "la página actual": la página actual es Nosotros.
                    $layout_es_actual = $layout_item['tipo'] === 'mega'
                        ? $layout_en_catalogo
                        : ($layout_item['tipo'] === 'enlace' && es_ruta_exacta($layout_item['ruta']));
                    ?>

                    <?php if ($layout_item['tipo'] === 'mega'): ?>
                        <li class="nav__item nav__item--mega" data-mega>
                            <?php /* aria-current="true" y no "page": el disparador es un
                                     botón, no lleva a ninguna página. Lo que declara es que la
                                     sección donde estás parado —catálogo o ficha— es esta. Sin
                                     esto, la barra bordeaux marcaba la sección activa solo para
                                     quien la ve. */ ?>
                            <button class="<?= e(trim('nav__enlace nav__disparador t-mono-label ' . ($layout_es_actual ? 'es-activo es-actual' : ''))) ?>"
                                    type="button" id="nav-productos"
                                    aria-expanded="false" aria-controls="mega-productos"
                                    <?= $layout_es_actual ? 'aria-current="true"' : '' ?>
                                    data-mega-disparador>
                                <span class="nav__indice"><?= e($layout_indice) ?></span>
                                <span class="nav__texto"><?= e($layout_item['texto']) ?></span>
                                <span class="nav__chevron"><?= icono('chevron') ?></span>
                            </button>

                            <?php require RASTRO_VIEWS . '/layout/mega-productos.php'; ?>
                        </li>
                    <?php else: ?>
                        <li class="nav__item">
                            <a class="<?= e(trim('nav__enlace t-mono-label ' . ($layout_es_actual ? 'es-activo es-actual' : ''))) ?>"
                               href="<?= e(url($layout_item['ruta'])) ?>"
                               <?= $layout_es_actual ? 'aria-current="page"' : '' ?>>
                                <span class="nav__indice"><?= e($layout_indice) ?></span>
                                <span class="nav__texto"><?= e($layout_item['texto']) ?></span>
                            </a>
                        </li>
                    <?php endif; ?>

                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="cabecera__acciones">

            <?php /* La búsqueda es una sola: en escritorio está siempre
                     abierta y en celular la despliega el botón de la lupa,
                     que en ese modo se queda con la barra entera. */ ?>
            <div class="busqueda">
                <button class="busqueda__abrir" type="button"
                        aria-expanded="false" aria-controls="busqueda-formulario" data-busqueda-abrir>
                    <?= icono('buscar') ?>
                    <span class="visualmente-oculto">Buscar en el catálogo</span>
                </button>

                <?php /* Cancelar va ANTES del campo, en el marcado y en pantalla.
                         Cuando la búsqueda se queda con la barra entera, la salida
                         tiene que ser lo primero que se anuncia y lo primero que se
                         ve, en el mismo lugar donde estaba la hamburguesa. Solo se
                         dibuja en celular y con la búsqueda abierta. */ ?>
                <button class="busqueda__cerrar t-mono-label" type="button" data-busqueda-cerrar>
                    Cancelar
                </button>

                <form class="buscador" id="busqueda-formulario" role="search"
                      method="get" action="<?= e(url('/catalogo')) ?>">
                    <label class="visualmente-oculto" for="buscador-q">Buscar en el catálogo</label>
                    <input class="buscador__campo t-mono-texto" type="search" id="buscador-q" name="q"
                           value="<?= e($layout_busqueda) ?>" placeholder="Buscar por nombre o código"
                           autocomplete="off" enterkeyhint="search">
                    <button class="buscador__boton t-mono-label-sm" type="submit">Buscar</button>
                </form>
            </div>

            <?php /* Con sesión abierta, el nombre abre un menú con las tres
                     cosas que se hacen con una cuenta: pedidos, datos y salir.
                     Sin sesión, "Cuenta" lleva a /ingresar.

                     Es un disclosure —botón con aria-expanded que muestra una
                     lista de enlaces— y no un role="menu". Un menú ARIA promete
                     flechas, typeahead y un foco que se mueve solo; tres enlaces
                     no necesitan nada de eso, y prometerlo sin cumplirlo es peor
                     que no prometerlo. Lo maneja assets/js/nav.js; sin JS se abre
                     con hover y foco (sin-js.css).

                     `sesion_usuario()` resuelve una sola vez por request y no
                     arranca ninguna sesión si el visitante no trae la cookie, así
                     que esto no le cuesta nada a quien pasa por la home sin cuenta. */ ?>
            <?php $layout_usuario = sesion_usuario(); ?>
            <?php if ($layout_usuario !== null): ?>
                <div class="cuenta-menu" data-cuenta-menu>
                    <button class="<?= e(trim('cabecera__cuenta cuenta-menu__disparador t-mono-label ' . activo('/cuenta'))) ?>"
                            type="button" aria-expanded="false" aria-controls="cuenta-menu-panel"
                            data-cuenta-menu-disparador>
                        <span class="cuenta-menu__nombre"><?= e((string) ($layout_usuario['nombre'] ?? 'Mi cuenta')) ?></span>
                        <span class="cuenta-menu__chevron"><?= icono('chevron') ?></span>
                    </button>

                    <div class="cuenta-menu__panel" id="cuenta-menu-panel" data-cuenta-menu-panel hidden>
                        <p class="cuenta-menu__correo t-mono-texto-sm"><?= e((string) ($layout_usuario['email'] ?? '')) ?></p>
                        <ul class="cuenta-menu__lista">
                            <li>
                                <a class="cuenta-menu__item t-mono-label" href="<?= e(url('/cuenta') . '?seccion=pedidos') ?>">Mis pedidos</a>
                            </li>
                            <li>
                                <a class="cuenta-menu__item t-mono-label" href="<?= e(url('/cuenta') . '?seccion=datos') ?>">Mis datos</a>
                            </li>
                            <li>
                                <?php /* Salir es un POST porque un GET que cierra sesión
                                         lo dispara cualquier cosa que precargue enlaces. */ ?>
                                <form class="cuenta-menu__salir" method="post" action="<?= e(url('/salir')) ?>">
                                    <input type="hidden" name="csrf" value="<?= e(sesion_csrf()) ?>">
                                    <button class="cuenta-menu__item t-mono-label" type="submit">Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <a class="<?= e(trim('cabecera__cuenta t-mono-label ' . activo('/ingresar'))) ?>"
                   href="<?= e(url('/ingresar')) ?>"
                   <?= es_ruta_exacta('/ingresar') ? 'aria-current="page"' : '' ?>>
                    Cuenta
                </a>
            <?php endif; ?>

            <a class="<?= e(trim('boton-carrito t-mono-label ' . activo('/carrito'))) ?>"
               href="<?= e(url('/carrito')) ?>"
               <?= es_ruta_exacta('/carrito') ? 'aria-current="page"' : '' ?>>
                <?= icono('carrito') ?>
                <span class="boton-carrito__texto">Carrito</span>
                <?php /* El contador lo escribe assets/js/carrito.js desde localStorage. */ ?>
                <span class="boton-carrito__contador" data-carrito-contador>[00]</span>
            </a>
        </div>

    </div>
</header>

<?php /* El velo del mega-menú. Va afuera de <header> y con menos z-index que
         la cabecera, así oscurece la página sin tocar la barra. */ ?>
<div class="mega__velo" data-mega-velo hidden></div>

<?php require RASTRO_VIEWS . '/layout/cajon.php'; ?>
