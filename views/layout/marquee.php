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
$repeticiones = 8;
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
