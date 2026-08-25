<?php
/**
 * partials/cifras.php — las cuatro cifras de Nosotros.
 *
 * AÑOS EN EL RUBRO · GIMNASIOS EQUIPADOS · PRODUCTOS EN CATÁLOGO ·
 * PROVINCIAS CON ENVÍO. Aparecen en dos lugares con la misma pieza: la
 * barra de la página /nosotros y el bloque 2×2 de la franja de la home.
 *
 * Tres de las cuatro todavía no existen y por eso se dibuja `[ DATO ]` a la
 * vista en vez de esconder la celda. Un hueco visible es lo que hace que en
 * la revisión con el cliente alguien pregunte el número; una celda que no
 * está no la reclama nadie. La cuarta —productos en catálogo— la resuelve
 * el repository contra el catálogo real.
 *
 * Es una lista y no un <dl>: el diseño pone la cifra arriba y el rótulo
 * abajo, y en un <dl> el <dt> tiene que ir primero. Invertirlo con CSS
 * dejaría el orden de lectura al revés del orden visual.
 *
 * Variables:
 *   $cifras        array   las cifras tal como salen de repo_nosotros()
 *   $cifras_clase  string  modificador de la grilla; '' o 'cifras--franja'
 */

declare(strict_types=1);

$cifras = $cifras ?? [];
$cifras_clase = $cifras_clase ?? '';
?>
<ul class="<?= e(trim('cifras reticula ' . $cifras_clase)) ?>">
    <?php foreach ($cifras as $cifra): ?>
        <li class="cifras__celda">
            <?php if (($cifra['valor'] ?? null) === null || $cifra['valor'] === ''): ?>
                <p class="cifras__pendiente t-mono-label indice">Dato</p>
            <?php else: ?>
                <p class="cifras__valor t-precio-lg plata"><?= e(moneda_sin_simbolo($cifra['valor'])) ?></p>
            <?php endif; ?>
            <p class="cifras__rotulo t-mono-label-sm"><?= e($cifra['rotulo'] ?? '') ?></p>
        </li>
    <?php endforeach; ?>
</ul>
