<?php
/**
 * layout/header.php — logo, navegación numerada, buscador y carrito.
 *
 * La navegación se arma con las categorías reales del repository: si el
 * cliente agrega una categoría en el panel, el menú la toma sola. Por eso
 * el ancho del menú es variable y el layout no puede dar por sentado que
 * entra: ver la nota de layout.css sobre los tres tramos de la cabecera.
 *
 * Las variables de este archivo van con prefijo `layout_` porque se
 * requiere dentro del scope de la vista y no puede pisarle un nombre.
 */

declare(strict_types=1);

// El índice numerado del menú: 01 Catálogo, 02 Discos, 03 Barras…
$layout_nav = [['ruta' => '/catalogo', 'texto' => 'Catálogo']];

foreach (array_slice(repo_categories(), 0, 3) as $layout_categoria) {
    $layout_nav[] = [
        'ruta'  => '/catalogo/' . $layout_categoria['slug'],
        'texto' => $layout_categoria['nombre'],
    ];
}

$layout_nav[] = ['ruta' => '/mayoristas', 'texto' => 'Mayoristas'];

// param() y no $_GET: ?q[]=a llega como array y el cast tira un warning
// arriba de todo el HTML.
$layout_busqueda = param('q');
?>
<header class="cabecera">
    <div class="cabecera__barra contenedor">

        <a class="cabecera__logo" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset('img/marca/rastro-horizontal-transparente.png')) ?>"
                 alt="Rastro Fitness" width="600" height="153" fetchpriority="high">
            <span class="visualmente-oculto">Ir al inicio</span>
        </a>

        <nav class="nav" aria-label="Navegación principal">
            <ul class="nav__lista">
                <?php foreach ($layout_nav as $i => $layout_item): ?>
                    <?php $layout_es_actual = es_ruta_exacta($layout_item['ruta']); ?>
                    <li class="nav__item">
                        <a class="<?= e(trim('nav__enlace t-mono-label ' . activo($layout_item['ruta']) . ($layout_es_actual ? ' es-actual' : ''))) ?>"
                           href="<?= e(url($layout_item['ruta'])) ?>"
                           <?= $layout_es_actual ? 'aria-current="page"' : '' ?>>
                            <span class="nav__indice"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <span class="nav__texto"><?= e($layout_item['texto']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <form class="buscador" role="search" method="get" action="<?= e(url('/catalogo')) ?>">
            <label class="visualmente-oculto" for="buscador-q">Buscar en el catálogo</label>
            <input class="buscador__campo t-mono-texto" type="search" id="buscador-q" name="q"
                   value="<?= e($layout_busqueda) ?>" placeholder="Buscar por nombre o código"
                   autocomplete="off" enterkeyhint="search">
            <button class="buscador__boton t-mono-label-sm" type="submit">Buscar</button>
        </form>

        <div class="cabecera__acciones">
            <?php /* TODO(backend): con sesión abierta este enlace va a /cuenta
                     y muestra el nombre del usuario en vez de "Cuenta". */ ?>
            <a class="<?= e(trim('cabecera__cuenta t-mono-label ' . activo('/ingresar'))) ?>"
               href="<?= e(url('/ingresar')) ?>"
               <?= es_ruta_exacta('/ingresar') ? 'aria-current="page"' : '' ?>>
                Cuenta
            </a>

            <a class="<?= e(trim('boton-carrito t-mono-label ' . activo('/carrito'))) ?>"
               href="<?= e(url('/carrito')) ?>"
               <?= es_ruta_exacta('/carrito') ? 'aria-current="page"' : '' ?>>
                <span>Carrito</span>
                <?php /* El contador lo escribe assets/js/carrito.js desde localStorage. */ ?>
                <span class="boton-carrito__contador" data-carrito-contador>[00]</span>
            </a>
        </div>

    </div>
</header>
