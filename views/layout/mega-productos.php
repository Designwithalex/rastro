<?php
/**
 * layout/mega-productos.php — el panel del mega-menú de PRODUCTOS.
 *
 * Se requiere desde header.php, adentro del <li> del disparador: así el
 * panel es descendiente del elemento que lo abre —hover, foco y teclado caen
 * naturalmente en el mismo subárbol— y sale a sangre porque se posiciona
 * contra la cabecera, que es el único ancestro posicionado.
 *
 * Geometría (dirección UX v3 §2): panel a sangre, alto fijo, tres columnas
 * de 322 · 668 · 322 con gutter de 24 sobre el contenedor de 1360. El alto
 * es fijo a propósito: si cambiara al pasar de una categoría a otra, el
 * contenido de abajo saltaría con cada movimiento del mouse.
 *
 * A · categorías  B · ocho productos de la categoría  C · puerta mayorista
 *
 * La columna B NO muestra precios. Ocho bloques de precio convierten el menú
 * en una segunda grilla de catálogo, que es exactamente lo que el menú tiene
 * que evitar.
 *
 * Variables que trae de header.php:
 *   $layout_mega        array  [['categoria'=>…, 'productos'=>[…]], …]
 *   $layout_categorias  array  repo_categories()
 */

declare(strict_types=1);

$mega_settings = repo_settings();
$mega_whatsapp = whatsapp_link(
    $mega_settings,
    'Hola Rastro, estoy equipando un espacio y quiero una cotización mayorista.'
);
?>
<div class="mega" id="mega-productos" data-mega-panel hidden>
    <div class="mega__interior contenedor">

        <div class="mega__grilla">

            <?php /* --- A · Categorías ---------------------------------- */ ?>
            <div class="mega__columna mega__columna--a">
                <p class="mega__rotulo t-mono-label">Categorías</p>

                <ul class="mega__categorias reticula reticula--superficie">
                    <?php foreach ($layout_mega as $mega_i => $mega_bloque): ?>
                        <?php $mega_cat = $mega_bloque['categoria']; ?>
                        <li>
                            <?php /* Es un enlace de verdad: hacer clic lleva a la
                                     categoría. Pasar el mouse o el foco por encima
                                     cambia la columna B, y eso lo hace nav.js. */ ?>
                            <a class="mega__categoria"
                               href="<?= e(url('/catalogo/' . $mega_cat['slug'])) ?>"
                               id="mega-cat-<?= e($mega_cat['slug']) ?>"
                               aria-controls="mega-grupo-<?= e($mega_cat['slug']) ?>"
                               data-mega-categoria="<?= e($mega_cat['slug']) ?>"
                               <?= $mega_i === 0 ? 'data-activa="true"' : '' ?>>
                                <span class="mega__categoria-nombre t-body-sm"><?= e($mega_cat['nombre']) ?></span>
                                <span class="mega__categoria-cantidad t-mono-texto-sm">
                                    <?= e(str_pad((string) ($mega_cat['productos_count'] ?? 0), 2, '0', STR_PAD_LEFT)) ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="mega__ver-todo t-mono-label" href="<?= e(url('/catalogo')) ?>">
                    Ver todo el catálogo <span class="mega__flecha" aria-hidden="true">→</span>
                </a>
            </div>

            <?php /* --- B · Productos de la categoría --------------------- */ ?>
            <div class="mega__columna mega__columna--b">
                <?php foreach ($layout_mega as $mega_i => $mega_bloque): ?>
                    <?php $mega_cat = $mega_bloque['categoria']; ?>
                    <div class="mega__grupo"
                         id="mega-grupo-<?= e($mega_cat['slug']) ?>"
                         role="group"
                         aria-label="<?= e('Productos de ' . $mega_cat['nombre']) ?>"
                         data-mega-grupo="<?= e($mega_cat['slug']) ?>"
                         <?= $mega_i === 0 ? '' : 'hidden' ?>>

                        <ul class="mega__productos">
                            <?php foreach ($mega_bloque['productos'] as $mega_producto): ?>
                                <?php $mega_miniatura = imagen_miniatura((string) $mega_producto['imagen']); ?>
                                <li>
                                    <a class="mega__producto" href="<?= e(url('/producto/' . $mega_producto['slug'])) ?>">
                                        <span class="mega__miniatura">
                                            <picture>
                                                <?php if ($mega_miniatura !== null): ?>
                                                    <source srcset="<?= e($mega_miniatura) ?>" type="image/webp">
                                                <?php endif; ?>
                                                <?php /* alt vacío a propósito: el nombre del producto está
                                                         al lado, dentro del mismo enlace. Repetirlo hace que
                                                         un lector de pantalla lo diga dos veces. */ ?>
                                                <img src="<?= e(asset($mega_producto['imagen'])) ?>" alt=""
                                                     width="48" height="48" loading="lazy" decoding="async">
                                            </picture>
                                        </span>
                                        <span class="mega__producto-cuerpo">
                                            <span class="mega__producto-nombre t-body-sm"><?= e($mega_producto['nombre']) ?></span>
                                            <span class="mega__producto-dato t-mono-texto-sm">
                                                <?= e($mega_producto['sku']) ?>
                                                · <?= e(medida_producto($mega_producto)) ?>
                                                · <?= e(estado_stock($mega_producto)) ?>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <a class="mega__ver-categoria t-mono-label"
                           href="<?= e(url('/catalogo/' . $mega_cat['slug'])) ?>">
                            Ver los <?= e((string) ($mega_cat['productos_count'] ?? 0)) ?>
                            de <?= e($mega_cat['nombre']) ?>
                            <span class="mega__flecha" aria-hidden="true">→</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php /* --- C · Mayoristas ------------------------------------
                     La única puerta permanente al canal que no muestra
                     precios. No cambia con la categoría: es un módulo fijo. */ ?>
            <div class="mega__columna mega__columna--c">
                <div class="mega__mayoristas">
                    <p class="mega__alerta t-mono-label indice">!</p>
                    <p class="mega__mayoristas-titulo t-display-s">Mayoristas</p>
                    <p class="mega__mayoristas-texto t-body-sm">
                        ¿Equipás una sala completa? Cotizamos el proyecto entero por WhatsApp,
                        con precios que no están publicados.
                    </p>

                    <?php if ($mega_whatsapp !== null): ?>
                        <a class="mega__cta t-mono-label" href="<?= e($mega_whatsapp) ?>"
                           rel="noopener" target="_blank">
                            Escribinos por WhatsApp
                            <span class="visualmente-oculto">(abre WhatsApp en una pestaña nueva)</span>
                        </a>
                    <?php endif; ?>

                    <a class="mega__mayoristas-enlace t-mono-label-sm" href="<?= e(url('/mayoristas')) ?>">
                        Ver el canal mayorista <span class="mega__flecha" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

        </div>

        <?php /* --- Pie del panel: la regla de precio ------------------- */ ?>
        <div class="mega__pie">
            <?php require RASTRO_VIEWS . '/partials/regla-precio.php'; ?>
        </div>

    </div>
</div>
