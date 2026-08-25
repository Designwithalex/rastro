<?php
/**
 * partials/regla-precio.php — la política de precio, sin ningún monto.
 *
 * El sistema tiene DOS componentes de precio y este es el que no lleva
 * números de producto:
 *
 *   · regla-precio  — la política: "publicado = Mercado Pago, X% menos por
 *     transferencia" + el umbral de envío. Vive en el hero, en el pie del
 *     mega-menú, en el cajón de mobile y en el pie del sitio.
 *   · bloque-precio — el precio de UN producto, con montos y badge. Vive en
 *     la card, en la ficha y en el carrito.
 *
 * Si fueran un solo componente, tarde o temprano alguien termina poniendo un
 * monto en el hero.
 *
 * El porcentaje y el umbral salen de repo_settings() a través de los helpers:
 * ni este archivo ni ninguna vista escriben el número a mano.
 *
 * Uso:
 *   require RASTRO_VIEWS . '/partials/regla-precio.php';
 *
 * Se dibuja como dos celdas separadas por un hairline. Que vayan en fila o
 * apiladas lo decide el CSS del lugar donde se lo incluye, no una variante:
 * el pie del mega-menú las pone lado a lado y el cajón de mobile las apila.
 */

declare(strict_types=1);

// Variable propia y no $settings: este partial se incluye dentro del scope
// de la vista y no le puede pisar un nombre. repo_settings() cachea, así
// que llamarlo de nuevo no cuesta nada.
$regla_settings = repo_settings();
?>
<div class="regla-precio">
    <p class="regla-precio__celda">
        <span class="regla-precio__rotulo t-mono-label">Precio publicado</span>
        <span class="regla-precio__dato t-mono-texto">
            Es el precio pagando con Mercado Pago.
            <span class="regla-precio__acento"><?= e(descuento_global($regla_settings)) ?>% menos</span>
            por transferencia o efectivo.
        </span>
    </p>
    <p class="regla-precio__celda">
        <span class="regla-precio__rotulo t-mono-label">Envío</span>
        <span class="regla-precio__dato t-mono-texto">
            Sin cargo desde <?= e(moneda($regla_settings['envio_gratis_desde'] ?? 0)) ?>.
        </span>
    </p>
</div>
