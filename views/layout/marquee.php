<?php
/**
 * layout/marquee.php — la franja bordeaux de arriba de todo.
 *
 * Es puro tono de marca: una consigna repetida y separada por barras.
 * Va con aria-hidden porque para un lector de pantalla es la misma frase
 * doce veces seguidas; el mensaje real está en el hero y en el pie.
 * La animación se apaga sola con prefers-reduced-motion (ver layout.css).
 */

declare(strict_types=1);

$consigna = 'Equipamiento profesional';

/**
 * La animación desplaza cada pista un 100% de su propio ancho, así que para
 * que el bucle no deje un hueco cada pista tiene que ser MÁS ANCHA QUE LA
 * VENTANA. Con 8 repeticiones la pista medía 2240 px: en un monitor de 2560
 * aparecía una franja bordeaux vacía de 320 px en cada vuelta.
 *
 * Cada repetición mide unos 280 px con la escala actual (t-display-xs).
 * 14 × 280 = 3920 px, que cubre 4K a 3840 con margen. Son 28 elementos de
 * texto: no llegan a 2 KB de HTML.
 *
 * Si algún día cambia el tamaño del marquee o la consigna, se recalcula:
 * repeticiones = techo(ancho_máximo_soportado / ancho_de_una_repetición).
 */
$repeticiones = 14;
?>
<div class="marquee" aria-hidden="true">
    <?php for ($pista = 0; $pista < 2; $pista++): ?>
        <div class="marquee__pista">
            <?php for ($i = 0; $i < $repeticiones; $i++): ?>
                <span class="marquee__item t-display-xs"><?= e($consigna) ?></span>
                <span class="marquee__barra t-mono-texto-sm">/</span>
            <?php endfor; ?>
        </div>
    <?php endfor; ?>
</div>
