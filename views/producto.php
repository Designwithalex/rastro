<?php
/**
 * producto.php — la ficha.
 *
 * Sigue el frame "Producto · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=60-3
 *
 * ORDEN: galería y panel de compra arriba, especificaciones y descripción
 * abajo, relacionados al final. El que ya sabe qué quiere compra sin
 * scrollear; el que está decidiendo tiene la tabla técnica a un scroll.
 *
 * LOS TRES DATOS DE LA FICHA CHICA
 *
 * Envío sale de settings, así que si el cliente cambia el umbral cambia
 * acá también. Retiro y garantía todavía son texto inventado por el
 * diseño (PENDIENTES #32 y #33): están juntos, en un array arriba, para
 * que se vean y no queden repartidos por el marcado.
 */

declare(strict_types=1);

$producto = repo_product((string) ($params['slug'] ?? ''));

if ($producto === null) {
    router_404();
}

$categoria    = repo_category((string) ($producto['categoria'] ?? ''));
$relacionados = repo_related_products((string) $producto['slug'], 4);

$hay_stock = ((int) ($producto['stock'] ?? 0)) > 0;

$titulo      = (string) $producto['nombre'];
$descripcion = recortar((string) ($producto['descripcion_corta'] ?? $producto['descripcion'] ?? ''), 155);
$clase_body  = 'pagina-producto';
$estilos     = ['componentes', 'catalogo', 'producto'];

require RASTRO_VIEWS . '/layout/head.php';

$imagenes = array_values(array_filter(
    (array) ($producto['imagenes'] ?? []),
    static fn ($i): bool => is_string($i) && $i !== ''
));

if ($imagenes === [] && !empty($producto['imagen'])) {
    $imagenes = [(string) $producto['imagen']];
}

$whatsapp_producto = whatsapp_link(
    $settings,
    'Hola Rastro, quiero consultar por cantidad de: ' . $producto['nombre'] . ' (' . ($producto['sku'] ?? '') . ').'
);

/* Los tres datos de la ficha chica. El primero es dato vivo; los otros dos
   son copy provisorio y están marcados como tal. */
$datos_ficha = [
    [
        'rotulo' => 'Envío',
        'valor'  => 'A todo el país. Gratis desde ' . moneda($settings['envio_gratis_desde'] ?? 0),
        'provisorio' => false,
    ],
    [
        'rotulo' => 'Retiro',
        'valor'  => 'En depósito, sin cargo',
        'provisorio' => true,
    ],
    [
        'rotulo' => 'Garantía',
        'valor'  => '12 meses por defecto de fabricación',
        'provisorio' => true,
    ],
];
?>

<main id="contenido" tabindex="-1">

    <div class="contenedor barra-pagina barra-pagina--ficha">
        <?php
        $miga = [
            ['texto' => 'Catálogo', 'href' => url('/catalogo')],
        ];
        if ($categoria !== null) {
            $miga[] = ['texto' => (string) $categoria['nombre'], 'href' => url('/catalogo/' . $categoria['slug'])];
        }
        $miga[] = ['texto' => (string) $producto['nombre']];
        require RASTRO_VIEWS . '/partials/breadcrumb.php';
        ?>
    </div>

    <div class="ficha reticula">

        <?php /* ============================================================
                 Galería
                 ============================================================ */ ?>
        <div class="galeria">
            <?php
            $principal = $imagenes[0] ?? '';
            $principal_webp = $principal !== '' ? imagen_webp($principal) : null;
            $principal_med  = $principal !== '' ? imagen_medidas($principal) : null;
            ?>

            <figure class="galeria__marco">
                <?php if ($principal !== ''): ?>
                    <picture>
                        <?php if ($principal_webp !== null): ?>
                            <source srcset="<?= e($principal_webp) ?>" type="image/webp">
                        <?php endif; ?>
                        <img class="galeria__foto" id="galeria-foto"
                             src="<?= e(asset($principal)) ?>"
                             alt="<?= e($producto['nombre']) ?>"
                             fetchpriority="high" decoding="async"
                             <?php if ($principal_med !== null): ?>
                                 width="<?= e((string) $principal_med['ancho']) ?>"
                                 height="<?= e((string) $principal_med['alto']) ?>"
                             <?php endif; ?>>
                    </picture>
                <?php endif; ?>

                <span class="card__miras" aria-hidden="true"></span>

                <figcaption class="galeria__pie t-mono-label-sm">
                    Fig. 01 · <?= e($producto['nombre']) ?>
                </figcaption>
            </figure>

            <?php /* Las miniaturas son ENLACES a la misma página con un ancla,
                     no botones: sin JavaScript la foto grande no cambia, pero
                     cada toma sigue siendo alcanzable y se abre en su tamaño.
                     Un <button> sin JS no haría nada.
                     TODO(frontend): cuando haya JS de galería, interceptar el
                     clic y cambiar el src en vez de navegar. */ ?>
            <?php if (count($imagenes) > 1): ?>
                <ul class="galeria__tira">
                    <?php foreach ($imagenes as $i => $imagen): ?>
                        <?php
                        /* imagen_miniatura() devuelve el WebP de 96 px que deja
                           bin/optimizar-imagenes.sh. Si todavía no se generó
                           cae al original: pesa más, pero no se rompe nada. */
                        $mini      = imagen_miniatura($imagen);
                        $mini_webp = imagen_webp($imagen);
                        ?>
                        <li class="galeria__item">
                            <a class="galeria__mini <?= $i === 0 ? 'es-activa' : '' ?>"
                               href="<?= e(asset($imagen)) ?>">
                                <picture>
                                    <?php if ($mini === null && $mini_webp !== null): ?>
                                        <source srcset="<?= e($mini_webp) ?>" type="image/webp">
                                    <?php endif; ?>
                                    <?php /* Sin width/height cuando el archivo es el
                                             original: declararlo 96×96 mentiría sobre
                                             una foto de 1000 px y el navegador
                                             reservaría el espacio equivocado. El
                                             aspect-ratio del CSS ya lo resuelve. */ ?>
                                    <img src="<?= e($mini ?? asset($imagen)) ?>"
                                         alt="<?= e($producto['nombre']) ?> · toma <?= e((string) ($i + 1)) ?>"
                                         loading="lazy" decoding="async"
                                         <?= $mini !== null ? 'width="96" height="96"' : '' ?>>
                                </picture>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php /* ============================================================
                 Panel de compra
                 ============================================================ */ ?>
        <div class="panel-compra" data-compra>

            <p class="panel-compra__meta">
                <?php if (!empty($producto['marca_nombre'])): ?>
                    <span class="t-mono-label-sm"><?= e($producto['marca_nombre']) ?></span>
                <?php endif; ?>
                <span class="t-mono-label-sm"><?= e($producto['sku'] ?? '') ?></span>
            </p>

            <h1 class="panel-compra__nombre t-display-m"><?= e($producto['nombre']) ?></h1>

            <?php /* El punto es decorativo: el estado se dice con palabras,
                     nunca sólo con un color (CLAUDE.md §5.4). */ ?>
            <p class="panel-compra__estado t-mono-texto <?= $hay_stock ? '' : 'es-agotado' ?>">
                <span class="panel-compra__punto" aria-hidden="true"></span>
                <?= e(estado_stock($producto)) ?>
                <?php if ($hay_stock): ?>
                    · <?= e((string) $producto['stock']) ?> unidades disponibles
                <?php else: ?>
                    · lo conseguimos a pedido
                <?php endif; ?>
            </p>

            <?php
            $precio_producto = $producto;
            $precio_tamano   = 'lg';
            require RASTRO_VIEWS . '/partials/bloque-precio.php';
            ?>

            <?php if ($hay_stock): ?>
                <div class="compra">
                    <?php /* Sin JS el campo sigue siendo un número editable y
                             el botón agrega 1. Con JS, los dos pasos y el
                             campo trabajan juntos (assets/js/carrito.js). */ ?>
                    <div class="stepper">
                        <button class="stepper__paso" type="button" data-paso="-1" aria-label="Quitar una unidad">−</button>
                        <label class="visualmente-oculto" for="cantidad">Cantidad</label>
                        <input class="stepper__campo t-mono-label" type="number" id="cantidad" name="cantidad"
                               value="1" min="1" max="<?= e((string) $producto['stock']) ?>" step="1" inputmode="numeric">
                        <button class="stepper__paso" type="button" data-paso="1" aria-label="Agregar una unidad">+</button>
                    </div>

                    <button class="boton boton--acento compra__agregar" type="button"
                            data-agregar="<?= e((string) $producto['id']) ?>">
                        <span class="t-mono-label">Agregar al carrito</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>
                </div>
            <?php else: ?>
                <p class="panel-compra__aviso t-mono-texto">
                    Este producto no tiene stock publicado. Escribinos y te decimos
                    en cuánto lo tenemos.
                </p>
            <?php endif; ?>

            <?php if ($whatsapp_producto !== null): ?>
                <a class="boton boton--fantasma panel-compra__cantidad"
                   href="<?= e($whatsapp_producto) ?>" rel="noopener" target="_blank">
                    <span class="t-mono-label">¿Comprás por cantidad?</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>

            <dl class="ficha-datos reticula reticula--superficie">
                <?php foreach ($datos_ficha as $dato): ?>
                    <div class="ficha-datos__fila">
                        <dt class="t-mono-label-sm"><?= e($dato['rotulo']) ?></dt>
                        <dd class="t-mono-texto">
                            <?= e($dato['valor']) ?>
                            <?php if ($dato['provisorio']): ?>
                                <?php /* Marcador visible, como en /nosotros: un hueco
                                         escondido no lo reclama nadie. */ ?>
                                <span class="marcador t-mono-label-sm">[ confirmar ]</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <?php /* ============================================================
             Especificaciones y descripción
             ============================================================ */ ?>
    <div class="detalle reticula">

        <section class="detalle__bloque" aria-labelledby="especificaciones-titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">02</span></p>
            <h2 class="detalle__titulo t-display-m" id="especificaciones-titulo">Especificaciones</h2>

            <?php if (!empty($producto['especificaciones'])): ?>
                <dl class="especificaciones">
                    <?php foreach ($producto['especificaciones'] as $espec): ?>
                        <div class="especificaciones__fila">
                            <dt class="t-mono-label-sm"><?= e($espec['label'] ?? '') ?></dt>
                            <dd class="t-mono-texto"><?= e($espec['valor'] ?? '') ?></dd>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($producto['peso_kg'])): ?>
                        <div class="especificaciones__fila">
                            <dt class="t-mono-label-sm">Peso</dt>
                            <dd class="t-mono-texto"><?= e(peso_kg((float) $producto['peso_kg'])) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php else: ?>
                <?php /* Sin especificaciones la ficha queda a medias y hay que
                         verlo, no esconderlo (PENDIENTES #34). */ ?>
                <p class="marcador t-mono-label">[ Faltan las especificaciones de este producto ]</p>
            <?php endif; ?>
        </section>

        <section class="detalle__bloque detalle__bloque--descripcion" aria-labelledby="descripcion-titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">03</span></p>
            <h2 class="detalle__titulo t-display-m" id="descripcion-titulo">Descripción</h2>

            <div class="descripcion t-body-md">
                <?php foreach (preg_split('/\n{2,}/', (string) ($producto['descripcion'] ?? '')) ?: [] as $parrafo): ?>
                    <?php if (trim($parrafo) !== ''): ?>
                        <p><?= e(trim($parrafo)) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <p class="nota t-mono-texto-sm">
                El precio publicado es por unidad. Las fotos son ilustrativas: el color
                del logo puede variar según el lote.
            </p>
        </section>
    </div>

    <?php /* ============================================================
             Relacionados
             ============================================================ */ ?>
    <?php if ($relacionados !== []): ?>
        <section class="relacionados" aria-labelledby="relacionados-titulo">
            <div class="contenedor">
                <?php
                $seccion_indice = '04';
                $seccion_titulo = 'De la misma categoría';
                $seccion_id     = 'relacionados-titulo';
                $seccion_enlace = $categoria !== null
                    ? ['texto' => 'Ver ' . mb_strtolower((string) $categoria['nombre']), 'href' => url('/catalogo/' . $categoria['slug'])]
                    : null;
                require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
                ?>
            </div>

            <ul class="grilla-productos reticula">
                <?php foreach ($relacionados as $relacionado): ?>
                    <li class="grilla-productos__celda">
                        <?php
                        $card_producto = $relacionado;
                        $card_titulo   = 'h3';
                        require RASTRO_VIEWS . '/partials/card-producto.php';
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
