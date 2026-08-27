<?php
/**
 * catalogo.php — el listado, con filtros, orden y paginación.
 *
 * Sirve dos rutas:
 *   /catalogo                  todo el catálogo
 *   /catalogo/{categoria}      una categoría, por la ruta y no por query
 *
 * Cuando la categoría viene por la ruta manda la ruta: es la URL que se
 * comparte y la que indexa un buscador. El `?categoria=` de la query se
 * usa nada más que cuando se filtra desde la barra lateral estando en
 * /catalogo, y en ese caso la vista redirige a la ruta linda.
 *
 * LOS FILTROS SON ENLACES, NO CASILLAS
 *
 * El frame dibuja casillas de verificación, que prometen selección
 * múltiple: tres categorías a la vez. `repo_products()` acepta UNA
 * categoría y UNA marca (CLAUDE.md §4.2), así que una casilla acá sería
 * un control que dice que hace algo que no hace.
 *
 * Van como enlaces: cada uno arma la URL con ese filtro puesto —o sacado,
 * si ya estaba— y conserva los demás. Se lee igual que el frame porque el
 * cuadradito del diseño no es un checkbox nativo dibujado, es un cuadrado;
 * andan sin JavaScript; y la URL queda compartible. Pasar a selección
 * múltiple es un cambio de contrato, no de vista: está anotado en
 * PENDIENTES #63.
 *
 * El precio sí es un formulario: son dos números que se escriben.
 */

declare(strict_types=1);

/* --- Entrada ------------------------------------------------------- */

$categoria_ruta = (string) ($params['categoria'] ?? '');
$categoria_slug = $categoria_ruta !== '' ? $categoria_ruta : param('categoria');

$categoria_actual = $categoria_slug !== '' ? repo_category($categoria_slug) : null;

/* Una categoría que no existe es un 404, no un listado vacío: si alguien
   escribe /catalogo/mancuernitas, el sitio tiene que decir que esa página
   no está, no devolver cero productos como si fuera una búsqueda. */
if ($categoria_slug !== '' && $categoria_actual === null) {
    router_404();
}

/* La categoría por query se normaliza a la ruta bonita antes de dibujar
   nada, así no hay dos URLs para el mismo listado. */
if ($categoria_ruta === '' && $categoria_slug !== '') {
    $resto = $_GET;
    unset($resto['categoria']);
    $destino = url('/catalogo/' . $categoria_slug) . ($resto !== [] ? '?' . http_build_query($resto) : '');

    header('Location: ' . $destino, true, 302);
    exit;
}

$orden_valido = ['relevancia', 'precio_asc', 'precio_desc', 'nombre'];

$filtros = [
    'q'          => param('q'),
    'categoria'  => $categoria_slug,
    'marca'      => param('marca'),
    'precio_min' => param_int('precio_min', null, 0),
    'precio_max' => param_int('precio_max', null, 0),
    'orden'      => param('orden', 'relevancia', $orden_valido),
    'en_stock'   => param_bool('en_stock'),
];

$pagina   = param_int('pagina', 1, 1) ?? 1;
$por_pag  = 9;
$listado  = repo_products($filtros, $pagina, $por_pag);

$categorias = repo_categories();
/* Con las propias incluidas: 19 de los 30 productos son de línea Rastro y
   sin ella el filtro de marca esconde dos tercios del catálogo. */
$marcas = repo_brands(true);

/* --- Título de la página ------------------------------------------- */

$titulo_seccion = $categoria_actual !== null
    ? (string) $categoria_actual['nombre']
    : ($filtros['q'] !== '' ? 'Búsqueda' : 'Catálogo');

$titulo      = $titulo_seccion;
/* repo_settings() y no $settings: $settings lo define layout/head.php, que
   todavía no corrió. La función está cacheada, así que no hay lectura de
   más. */
$descripcion = $categoria_actual !== null
    ? (string) ($categoria_actual['descripcion'] ?? '')
    : 'Discos, barras, mancuernas, kettlebells, racks y accesorios. '
      . descuento_global(repo_settings()) . '% de descuento por transferencia o efectivo.';
$clase_body  = 'pagina-catalogo';
$estilos     = ['componentes', 'catalogo'];

require RASTRO_VIEWS . '/layout/head.php';

/* --- Armado de URLs -------------------------------------------------
   Un solo lugar donde se decide cómo se ve una URL del catálogo, para
   que ningún enlace se olvide de conservar un filtro. */

/**
 * URL del catálogo con los filtros actuales, cambiando lo que se pase.
 * Un valor null saca ese filtro. La página siempre vuelve a 1: cambiar
 * un filtro y quedar en la página 5 de un resultado de dos páginas es
 * la forma más rápida de mostrar un listado vacío sin motivo.
 */
$url_catalogo = static function (array $cambios = []) use ($filtros, $categoria_slug): string {
    $query = [
        'q'          => $filtros['q'],
        'marca'      => $filtros['marca'],
        'precio_min' => $filtros['precio_min'],
        'precio_max' => $filtros['precio_max'],
        'orden'      => $filtros['orden'] === 'relevancia' ? null : $filtros['orden'],
        'en_stock'   => $filtros['en_stock'] ? '1' : null,
    ];

    $categoria = array_key_exists('categoria', $cambios) ? $cambios['categoria'] : $categoria_slug;
    unset($cambios['categoria']);

    $query = array_merge($query, $cambios);
    $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '' && $v !== false);

    $base = ($categoria !== null && $categoria !== '')
        ? url('/catalogo/' . $categoria)
        : url('/catalogo');

    return $base . ($query !== [] ? '?' . http_build_query($query) : '');
};

/* Chips de lo que está filtrado, para poder sacarlo de a uno. */
$chips = [];

if ($filtros['q'] !== '') {
    $chips[] = ['texto' => '“' . $filtros['q'] . '”', 'href' => $url_catalogo(['q' => null])];
}

if ($categoria_actual !== null) {
    $chips[] = ['texto' => (string) $categoria_actual['nombre'], 'href' => $url_catalogo(['categoria' => null])];
}

foreach ($marcas as $marca) {
    if (($marca['slug'] ?? '') === $filtros['marca']) {
        $chips[] = ['texto' => (string) $marca['nombre'], 'href' => $url_catalogo(['marca' => null])];
    }
}

if ($filtros['en_stock']) {
    $chips[] = ['texto' => 'En stock', 'href' => $url_catalogo(['en_stock' => null])];
}

if ($filtros['precio_min'] !== null || $filtros['precio_max'] !== null) {
    $chips[] = [
        'texto' => 'Precio ' . moneda($filtros['precio_min'] ?? 0) . ' a ' . ($filtros['precio_max'] !== null ? moneda($filtros['precio_max']) : '∞'),
        'href'  => $url_catalogo(['precio_min' => null, 'precio_max' => null]),
    ];
}

$ordenes = [
    'relevancia'  => 'Relevancia',
    'precio_asc'  => 'Menor precio',
    'precio_desc' => 'Mayor precio',
    'nombre'      => 'Nombre',
];
?>

<main id="contenido" tabindex="-1">

    <?php /* ============================================================
             Barra de página: dónde estoy, qué estoy viendo y cómo ordenarlo
             ============================================================ */ ?>
    <div class="contenedor barra-pagina">

        <div class="barra-pagina__titulo">
            <?php
            $miga = $categoria_actual !== null
                ? [['texto' => 'Catálogo', 'href' => url('/catalogo')], ['texto' => (string) $categoria_actual['nombre']]]
                : [['texto' => $titulo_seccion]];
            require RASTRO_VIEWS . '/partials/breadcrumb.php';
            ?>

            <div class="barra-pagina__linea">
                <h1 class="barra-pagina__nombre t-display-l"><?= e($titulo_seccion) ?></h1>
                <p class="barra-pagina__conteo t-mono-texto">
                    <?= e((string) $listado['total']) ?>
                    <?= $listado['total'] === 1 ? 'producto' : 'productos' ?>
                </p>
            </div>
        </div>

        <?php /* Un <form> con <select> y un botón: sin JavaScript ordena
                 igual. nav.js podría enviarlo al cambiar, pero el botón
                 tiene que existir para el que no tiene JS. */ ?>
        <form class="orden" method="get" action="<?= e($url_catalogo(['orden' => null])) ?>">
            <?php foreach (['q' => $filtros['q'], 'marca' => $filtros['marca'], 'precio_min' => $filtros['precio_min'], 'precio_max' => $filtros['precio_max'], 'en_stock' => $filtros['en_stock'] ? '1' : ''] as $nombre => $valor): ?>
                <?php if ($valor !== null && $valor !== ''): ?>
                    <input type="hidden" name="<?= e($nombre) ?>" value="<?= e((string) $valor) ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <label class="orden__etiqueta t-mono-label-sm" for="orden">Ordenar</label>
            <select class="orden__select t-mono-label" id="orden" name="orden">
                <?php foreach ($ordenes as $valor => $etiqueta): ?>
                    <option value="<?= e($valor) ?>" <?= $filtros['orden'] === $valor ? 'selected' : '' ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="orden__aplicar t-mono-label-sm" type="submit">Aplicar</button>
        </form>
    </div>

    <div class="catalogo reticula">

        <?php /* ============================================================
                 Filtros
                 ============================================================ */ ?>
        <aside class="filtros" aria-labelledby="filtros-titulo">
            <h2 class="visualmente-oculto" id="filtros-titulo">Filtros</h2>

            <section class="filtros__grupo" aria-labelledby="filtro-categoria">
                <h3 class="filtros__titulo t-mono-label" id="filtro-categoria">Categoría</h3>
                <ul class="filtros__lista">
                    <?php foreach ($categorias as $categoria): ?>
                        <?php $activa = ($categoria['slug'] ?? '') === $categoria_slug; ?>
                        <li>
                            <a class="filtro <?= $activa ? 'es-activo' : '' ?>"
                               href="<?= e($url_catalogo(['categoria' => $activa ? null : $categoria['slug']])) ?>"
                               <?= $activa ? 'aria-current="true"' : '' ?>>
                                <span class="filtro__marca" aria-hidden="true"></span>
                                <span class="filtro__nombre t-mono-texto"><?= e($categoria['nombre'] ?? '') ?></span>
                                <span class="filtro__linea" aria-hidden="true"></span>
                                <span class="filtro__conteo t-mono-texto-sm"><?= e((string) ($categoria['productos_count'] ?? 0)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <?php if ($marcas !== []): ?>
                <section class="filtros__grupo" aria-labelledby="filtro-marca">
                    <h3 class="filtros__titulo t-mono-label" id="filtro-marca">Marca</h3>
                    <ul class="filtros__lista">
                        <?php foreach ($marcas as $marca): ?>
                            <?php $activa = ($marca['slug'] ?? '') === $filtros['marca']; ?>
                            <li>
                                <a class="filtro <?= $activa ? 'es-activo' : '' ?>"
                                   href="<?= e($url_catalogo(['marca' => $activa ? null : $marca['slug']])) ?>"
                                   <?= $activa ? 'aria-current="true"' : '' ?>>
                                    <span class="filtro__marca" aria-hidden="true"></span>
                                    <span class="filtro__nombre t-mono-texto"><?= e($marca['nombre'] ?? '') ?></span>
                                    <span class="filtro__linea" aria-hidden="true"></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="filtros__grupo" aria-labelledby="filtro-stock">
                <h3 class="filtros__titulo t-mono-label" id="filtro-stock">Disponibilidad</h3>
                <ul class="filtros__lista">
                    <li>
                        <a class="filtro <?= $filtros['en_stock'] ? 'es-activo' : '' ?>"
                           href="<?= e($url_catalogo(['en_stock' => $filtros['en_stock'] ? null : '1'])) ?>"
                           <?= $filtros['en_stock'] ? 'aria-current="true"' : '' ?>>
                            <span class="filtro__marca" aria-hidden="true"></span>
                            <span class="filtro__nombre t-mono-texto">En stock</span>
                            <span class="filtro__linea" aria-hidden="true"></span>
                        </a>
                    </li>
                </ul>
                <?php /* El frame tiene además "A pedido". repo_products()
                         expone en_stock y nada más, y "a pedido" no es lo
                         mismo que "sin stock": es una decisión comercial que
                         el catálogo todavía no guarda (PENDIENTES #65). */ ?>
            </section>

            <section class="filtros__grupo" aria-labelledby="filtro-precio">
                <h3 class="filtros__titulo t-mono-label" id="filtro-precio">Precio</h3>

                <form class="filtros__precio" method="get" action="<?= e($url_catalogo(['precio_min' => null, 'precio_max' => null])) ?>">
                    <?php foreach (['q' => $filtros['q'], 'marca' => $filtros['marca'], 'orden' => $filtros['orden'] === 'relevancia' ? '' : $filtros['orden'], 'en_stock' => $filtros['en_stock'] ? '1' : ''] as $nombre => $valor): ?>
                        <?php if ($valor !== null && $valor !== ''): ?>
                            <input type="hidden" name="<?= e($nombre) ?>" value="<?= e((string) $valor) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="filtros__rango">
                        <label class="visualmente-oculto" for="precio_min">Precio mínimo</label>
                        <input class="campo t-mono-texto" type="number" inputmode="numeric" min="0" step="1000"
                               id="precio_min" name="precio_min" placeholder="Mínimo"
                               value="<?= e($filtros['precio_min'] !== null ? (string) $filtros['precio_min'] : '') ?>">

                        <label class="visualmente-oculto" for="precio_max">Precio máximo</label>
                        <input class="campo t-mono-texto" type="number" inputmode="numeric" min="0" step="1000"
                               id="precio_max" name="precio_max" placeholder="Máximo"
                               value="<?= e($filtros['precio_max'] !== null ? (string) $filtros['precio_max'] : '') ?>">
                    </div>

                    <button class="boton boton--fantasma filtros__aplicar" type="submit">
                        <span class="t-mono-label">Aplicar precio</span>
                    </button>
                </form>
            </section>
        </aside>

        <?php /* ============================================================
                 Resultados
                 ============================================================ */ ?>
        <section class="resultados" aria-label="Resultados">

            <?php if ($chips !== []): ?>
                <div class="chips">
                    <p class="chips__rotulo t-mono-label-sm">Filtrando por</p>
                    <ul class="chips__lista">
                        <?php foreach ($chips as $chip): ?>
                            <li>
                                <a class="chip t-mono-label-sm" href="<?= e($chip['href']) ?>">
                                    <?= e($chip['texto']) ?>
                                    <span class="chip__quitar" aria-hidden="true">×</span>
                                    <span class="visualmente-oculto">(quitar filtro)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="chips__limpiar t-mono-label-sm" href="<?= e(url('/catalogo')) ?>">Limpiar todo</a>
                </div>
            <?php endif; ?>

            <?php if ($listado['items'] === []): ?>
                <div class="vacio">
                    <p class="vacio__indice t-mono-label">Sin resultados</p>
                    <h2 class="t-display-m">No encontramos nada con esos filtros</h2>
                    <p class="vacio__texto t-body-md">
                        Probá sacando alguno, o escribinos: si no está publicado no quiere decir
                        que no lo consigamos.
                    </p>
                    <div class="vacio__acciones">
                        <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                            <span class="t-mono-label">Ver todo el catálogo</span>
                            <span class="boton__flecha" aria-hidden="true">→</span>
                        </a>
                        <?php $wsp = whatsapp_link($settings, 'Hola Rastro, estoy buscando un producto que no encontré en la web.'); ?>
                        <?php if ($wsp !== null): ?>
                            <a class="boton boton--fantasma" href="<?= e($wsp) ?>" rel="noopener" target="_blank">
                                <span class="t-mono-label">Consultar por WhatsApp</span>
                                <span class="boton__flecha" aria-hidden="true">→</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <ul class="grilla-productos grilla-productos--catalogo reticula">
                    <?php foreach ($listado['items'] as $producto): ?>
                        <li class="grilla-productos__celda">
                            <?php
                            $card_producto = $producto;
                            $card_titulo   = 'h2';
                            require RASTRO_VIEWS . '/partials/card-producto.php';
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="resultados__pie">
                    <p class="resultados__conteo t-mono-texto-sm">
                        Mostrando <?= e((string) count($listado['items'])) ?> de <?= e((string) $listado['total']) ?>
                    </p>

                    <?php if ($listado['paginas'] > 1): ?>
                        <nav class="paginacion" aria-label="Paginación">
                            <?php
                            /* Ventana corta alrededor de la página actual. Con 8
                               páginas entran todas; con 40 no, y una tira de 40
                               números no la usa nadie. */
                            $actual  = $listado['pagina'];
                            $ultimas = $listado['paginas'];
                            $ventana = array_unique(array_filter(
                                [1, $actual - 1, $actual, $actual + 1, $ultimas],
                                static fn (int $p): bool => $p >= 1 && $p <= $ultimas
                            ));
                            sort($ventana);
                            $previa = 0;
                            ?>

                            <?php if ($actual > 1): ?>
                                <a class="paginacion__paso t-mono-label" href="<?= e($url_catalogo(['pagina' => $actual - 1 > 1 ? $actual - 1 : null])) ?>" rel="prev">
                                    <span aria-hidden="true">←</span>
                                    <span class="visualmente-oculto">Página anterior</span>
                                </a>
                            <?php endif; ?>

                            <?php foreach ($ventana as $p): ?>
                                <?php if ($previa && $p - $previa > 1): ?>
                                    <span class="paginacion__salto t-mono-label" aria-hidden="true">…</span>
                                <?php endif; ?>

                                <?php if ($p === $actual): ?>
                                    <span class="paginacion__paso es-activo t-mono-label" aria-current="page"><?= e((string) $p) ?></span>
                                <?php else: ?>
                                    <a class="paginacion__paso t-mono-label" href="<?= e($url_catalogo(['pagina' => $p > 1 ? $p : null])) ?>">
                                        <?= e((string) $p) ?>
                                        <span class="visualmente-oculto">Página <?= e((string) $p) ?></span>
                                    </a>
                                <?php endif; ?>

                                <?php $previa = $p; ?>
                            <?php endforeach; ?>

                            <?php if ($actual < $ultimas): ?>
                                <a class="paginacion__paso t-mono-label" href="<?= e($url_catalogo(['pagina' => $actual + 1])) ?>" rel="next">
                                    <span aria-hidden="true">→</span>
                                    <span class="visualmente-oculto">Página siguiente</span>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
