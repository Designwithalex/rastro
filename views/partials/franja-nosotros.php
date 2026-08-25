<?php
/**
 * partials/franja-nosotros.php — la prueba de confianza en la home.
 *
 * TODAVÍA NO ESTÁ INCLUIDA EN NINGUNA VISTA. La home se maqueta en el
 * bloque siguiente; esto queda listo para que entre ahí.
 *
 * DÓNDE VA: después de destacados y ANTES del bloque mayorista. La prueba
 * de confianza precede al pedido de contacto: primero se contesta "¿quiénes
 * son estos?" y recién después se pide que escriban.
 *
 * CÓMO SE USA, desde la vista que la incluye:
 *
 *   $estilos[] = 'nosotros';                 // ANTES de layout/head.php
 *   …
 *   $franja_indice = '06';                   // el número que le toque
 *   require RASTRO_VIEWS . '/partials/franja-nosotros.php';
 *
 * El número de sección es un parámetro porque depende de cuántos bloques
 * tenga la home arriba, y eso todavía no está cerrado. Si no se pasa, va 06.
 *
 * La franja y la página /nosotros son la misma información con dos trabajos
 * distintos: acá es para el que todavía no decidió mirar, y allá para el que
 * ya está evaluando. Por eso las dos leen del mismo repo_nosotros().
 */

declare(strict_types=1);

$franja_nosotros = repo_nosotros();
$franja_indice   = $franja_indice ?? '06';
?>
<section class="franja-nosotros" aria-labelledby="franja-nosotros-titulo">
    <div class="contenedor franja-nosotros__interior">

        <div class="franja-nosotros__texto">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($franja_indice) ?></span>
                <?= e($franja_nosotros['encabezado']['kicker'] ?? 'Quiénes somos') ?>
            </p>

            <h2 class="franja-nosotros__titulo t-display-l" id="franja-nosotros-titulo">
                <?= e($franja_nosotros['franja']['titulo'] ?? '') ?>
            </h2>

            <p class="franja-nosotros__bajada t-body-md">
                <?= e($franja_nosotros['franja']['texto'] ?? '') ?>
            </p>

            <a class="franja-nosotros__enlace t-mono-label" href="<?= e(url('/nosotros')) ?>">
                <?= e($franja_nosotros['franja']['enlace_texto'] ?? 'Quiénes somos') ?>
                <span aria-hidden="true">→</span>
            </a>
        </div>

        <div class="franja-nosotros__cifras">
            <?php
            $cifras = $franja_nosotros['cifras'];
            $cifras_clase = 'cifras--franja';
            require RASTRO_VIEWS . '/partials/cifras.php';
            ?>
        </div>

    </div>
</section>
