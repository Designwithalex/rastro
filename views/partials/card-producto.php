<?php
/**
 * partials/card-producto.php — la card del catálogo.
 *
 * La usan la home (destacados), el catálogo (grilla) y la ficha
 * (relacionados). Una sola card para las tres.
 *
 * CÓMO SE USA
 *
 *   $card_producto = $producto;   // obligatorio, tal como sale del repository
 *   $card_titulo   = 'h3';        // opcional: h2 | h3. Por defecto h3.
 *   require RASTRO_VIEWS . '/partials/card-producto.php';
 *
 * UN SOLO ENLACE, NO TRES
 *
 * La foto, el nombre y el precio llevan a la ficha, pero el enlace es uno
 * solo: el del nombre, estirado con ::after sobre toda la card. Tres <a>
 * al mismo destino le hacen leer tres veces lo mismo a un lector de
 * pantalla y suman tres paradas de tabulación por producto — con 12
 * productos en la grilla, 36 paradas para llegar al pie.
 *
 * El botón "Agregar al carrito" queda por encima de ese enlace estirado
 * con position: relative, así el clic sobre el botón no navega a la ficha.
 * Por eso el botón NO puede ir adentro del <a>: un <button> dentro de un
 * <a> es marcado inválido y cada navegador lo resuelve como quiere.
 *
 * SIN JAVASCRIPT
 *
 * El botón es un <button> y sin JS no hace nada, igual que el resto del
 * carrito, que vive entero en localStorage (PENDIENTES #54). El enlace a
 * la ficha sí funciona siempre, y desde la ficha se puede seguir mirando.
 */

declare(strict_types=1);

$card_producto = $card_producto ?? [];
$card_titulo   = $card_titulo ?? 'h3';
$card_titulo   = in_array($card_titulo, ['h2', 'h3'], true) ? $card_titulo : 'h3';

$card_slug   = (string) ($card_producto['slug'] ?? '');
$card_imagen = (string) ($card_producto['imagen'] ?? '');
$card_webp   = $card_imagen !== '' ? imagen_webp($card_imagen) : null;
$card_medida = imagen_medidas($card_imagen);
$card_stock  = ((int) ($card_producto['stock'] ?? 0)) > 0;
?>
<article class="card<?= $card_stock ? '' : ' card--agotado' ?>">

    <div class="card__foto">
        <?php if ($card_imagen !== ''): ?>
            <picture>
                <?php if ($card_webp !== null): ?>
                    <source srcset="<?= e($card_webp) ?>" type="image/webp">
                <?php endif; ?>
                <img
                    class="card__imagen"
                    src="<?= e(asset($card_imagen)) ?>"
                    alt="<?= e($card_producto['nombre'] ?? '') ?>"
                    loading="lazy"
                    decoding="async"
                    <?php if ($card_medida !== null): ?>
                        width="<?= e((string) $card_medida['ancho']) ?>"
                        height="<?= e((string) $card_medida['alto']) ?>"
                    <?php endif; ?>
                >
            </picture>
        <?php endif; ?>

        <?php /* Las cuatro miras de encuadre. Decorativas: son el lenguaje
                 de "ficha técnica" de la v2, no información. */ ?>
        <span class="card__miras" aria-hidden="true"></span>

        <span class="card__codigo t-mono-label-sm"><?= e($card_producto['sku'] ?? '') ?></span>
    </div>

    <div class="card__cuerpo">

        <p class="card__meta">
            <?php if (!empty($card_producto['marca_nombre'])): ?>
                <span class="card__marca t-mono-label-sm"><?= e($card_producto['marca_nombre']) ?></span>
            <?php endif; ?>
            <span class="card__estado t-mono-label-sm"><?= e(estado_stock($card_producto)) ?></span>
        </p>

        <<?= $card_titulo ?> class="card__nombre t-display-s">
            <a class="card__enlace" href="<?= e(url('/producto/' . $card_slug)) ?>">
                <?= e($card_producto['nombre'] ?? '') ?>
            </a>
        </<?= $card_titulo ?>>

        <?php
        $precio_producto = $card_producto;
        $precio_tamano   = 'md';
        require RASTRO_VIEWS . '/partials/bloque-precio.php';
        ?>

        <?php if ($card_stock): ?>
            <button class="boton boton--fantasma card__cta"
                    type="button"
                    data-agregar="<?= e((string) ($card_producto['id'] ?? 0)) ?>">
                <span class="t-mono-label">Agregar al carrito</span>
                <span class="boton__flecha" aria-hidden="true">→</span>
            </button>
        <?php else: ?>
            <?php /* Sin stock no se ofrece el botón: un botón que no puede
                     cumplir es peor que no tenerlo. La card sigue llevando a
                     la ficha, donde se explica y se puede consultar. */ ?>
            <p class="card__sin-stock t-mono-label">Sin stock · consultar</p>
        <?php endif; ?>

    </div>

</article>
