<?php
/**
 * partials/encabezado-seccion.php — el encabezado numerado.
 *
 *   [ 05 ]  CATEGORÍAS  ─────────────────────  183 productos en 6 categorías
 *
 * Es la regla 7 del lenguaje visual: las secciones van numeradas. Se repite
 * en Categorías, Destacados, Confían en nosotros y Vendedores oficiales, y
 * también en el catálogo. Un solo archivo para todas.
 *
 * CÓMO SE USA
 *
 *   $seccion_indice = '05';
 *   $seccion_titulo = 'Categorías';
 *   $seccion_id     = 'categorias-titulo';        // para el aria-labelledby
 *   $seccion_nota   = '183 productos';            // opcional, a la derecha
 *   $seccion_enlace = ['texto' => 'Ver todo', 'href' => url('/catalogo')];  // opcional
 *   $seccion_nivel  = 'h2';                       // opcional
 *   require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
 *
 * La nota y el enlace son excluyentes: a la derecha va uno o el otro.
 * Si vienen los dos, gana el enlace, que es la acción.
 *
 * La línea del medio es decorativa y se dibuja con un pseudoelemento en
 * CSS, no con un <div> vacío como en Figma: un elemento sin contenido en
 * el marcado es una parada más en el árbol de accesibilidad a cambio de
 * nada.
 */

declare(strict_types=1);

$seccion_indice = $seccion_indice ?? '';
$seccion_titulo = $seccion_titulo ?? '';
$seccion_id     = $seccion_id ?? null;
$seccion_nota   = $seccion_nota ?? null;
$seccion_enlace = $seccion_enlace ?? null;
$seccion_nivel  = $seccion_nivel ?? 'h2';
$seccion_nivel  = in_array($seccion_nivel, ['h1', 'h2'], true) ? $seccion_nivel : 'h2';
?>
<div class="encabezado-seccion">

    <p class="encabezado-seccion__indice indice-seccion t-mono-label">
        <span class="indice"><?= e($seccion_indice) ?></span>
    </p>

    <<?= $seccion_nivel ?> class="encabezado-seccion__titulo t-display-l"
        <?php if ($seccion_id !== null): ?>id="<?= e($seccion_id) ?>"<?php endif; ?>>
        <?= e($seccion_titulo) ?>
    </<?= $seccion_nivel ?>>

    <?php if ($seccion_enlace !== null): ?>
        <a class="encabezado-seccion__enlace t-mono-label" href="<?= e($seccion_enlace['href']) ?>">
            <?= e($seccion_enlace['texto']) ?>
            <span aria-hidden="true">→</span>
        </a>
    <?php elseif ($seccion_nota !== null && $seccion_nota !== ''): ?>
        <p class="encabezado-seccion__nota t-mono-texto"><?= e($seccion_nota) ?></p>
    <?php endif; ?>

</div>
<?php
/* Se limpian para que la siguiente sección no herede la nota o el enlace
   de la anterior: los partials comparten scope con la vista. */
$seccion_nota   = null;
$seccion_enlace = null;
$seccion_id     = null;
