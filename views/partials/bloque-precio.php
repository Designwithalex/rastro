<?php
/**
 * partials/bloque-precio.php — el componente más repetido del sitio.
 *
 * Card del catálogo, ficha de producto, fila del carrito y relacionados
 * usan EXACTAMENTE este archivo. Es la contracara en código de la decisión
 * de Figma de tener un solo componente "Bloque de precio" con 9 variantes:
 * si mañana el negocio suma cuotas (PENDIENTES #21), se agrega una línea
 * acá y aparece en las cuatro pantallas de una.
 *
 * CÓMO SE USA
 *
 *   $precio_producto = $producto;      // obligatorio
 *   $precio_tamano   = 'md';           // lg | md | sm   (por defecto md)
 *   require RASTRO_VIEWS . '/partials/bloque-precio.php';
 *
 * NO calcula nada: el cálculo vive en precio_con_descuento() de
 * helpers.php y en ningún otro lado (CLAUDE.md §4.3).
 *
 * POR QUÉ LAS ETIQUETAS OCULTAS
 *
 * En pantalla alcanza con "MP" y "transferencia o efectivo" porque el
 * contexto lo explica: son dos números uno abajo del otro y el segundo es
 * más chico. Leído en voz alta eso es "ciento ochenta y nueve mil
 * cuatrocientos, eme pe", que no dice nada. Por eso cada línea lleva su
 * etiqueta completa en .visualmente-oculto. Es el mismo dato, dicho de la
 * forma que necesita cada quien.
 */

declare(strict_types=1);

$precio_producto = $precio_producto ?? [];
$precio_tamano   = $precio_tamano ?? 'md';
$precio_tamano   = in_array($precio_tamano, ['lg', 'md', 'sm'], true) ? $precio_tamano : 'md';

$precio = precio_con_descuento($precio_producto, $settings);

/* La escala del monto publicado cambia con el tamaño; el resto del bloque
   no. Es lo mismo que hacen las 9 variantes del componente en Figma. */
$precio_clase_monto = [
    'lg' => 't-precio-lg',
    'md' => 't-precio-md',
    'sm' => 't-precio-sm',
][$precio_tamano];
?>
<div class="precio precio--<?= e($precio_tamano) ?>">

    <p class="precio__publicado">
        <span class="visualmente-oculto">Precio pagando con Mercado Pago:</span>
        <span class="precio__monto plata <?= e($precio_clase_monto) ?>"><?= e(moneda($precio['publicado'])) ?></span>
        <span class="precio__medio t-mono-label-sm" aria-hidden="true">MP</span>
    </p>

    <?php if ($precio['tiene_descuento']): ?>
        <p class="precio__transferencia">
            <span class="visualmente-oculto">Precio por transferencia o efectivo:</span>
            <span class="precio__transferencia-monto t-mono-dato"><?= e(moneda($precio['con_descuento'])) ?></span>
            <span class="badge badge--descuento t-mono-label-sm">
                <?= e(porcentaje($precio['porcentaje'])) ?>% OFF
            </span>
        </p>

        <?php /* aria-hidden: la línea de arriba ya dijo "por transferencia o
                 efectivo" en su etiqueta oculta. Sin esto se repite dos veces. */ ?>
        <p class="precio__nota t-mono-texto-sm" aria-hidden="true">transferencia o efectivo</p>
    <?php endif; ?>

</div>
