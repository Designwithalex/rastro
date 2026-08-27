<?php
/**
 * partials/breadcrumb.php — la miga de pan.
 *
 * CÓMO SE USA
 *
 *   $miga = [
 *       ['texto' => 'Catálogo', 'href' => url('/catalogo')],
 *       ['texto' => 'Discos'],          // sin href: es la página actual
 *   ];
 *   require RASTRO_VIEWS . '/partials/breadcrumb.php';
 *
 * "Inicio" lo pone este archivo: está en todas las migas y repetirlo en
 * cada vista es una oportunidad más de escribirlo distinto.
 *
 * El último ítem no lleva enlace —un enlace a la página en la que ya
 * estás no hace nada— y sí lleva aria-current="page".
 */

declare(strict_types=1);

$miga = array_values((array) ($miga ?? []));

array_unshift($miga, ['texto' => 'Inicio', 'href' => url('/')]);

$miga_ultimo = count($miga) - 1;
?>
<nav class="miga" aria-label="Migas de pan">
    <ol class="miga__lista">
        <?php foreach ($miga as $i => $paso): ?>
            <li class="miga__paso">
                <?php if ($i > 0): ?>
                    <span class="miga__barra t-mono-label-sm" aria-hidden="true">/</span>
                <?php endif; ?>

                <?php if ($i === $miga_ultimo || empty($paso['href'])): ?>
                    <span class="miga__actual t-mono-label-sm" aria-current="page">
                        <?= e($paso['texto']) ?>
                    </span>
                <?php else: ?>
                    <a class="miga__enlace t-mono-label-sm" href="<?= e($paso['href']) ?>">
                        <?= e($paso['texto']) ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
