<?php
/**
 * partials/franja-logos.php — la grilla de logos.
 *
 * La usan las dos franjas de prueba social de la home, que son el mismo
 * componente con distinta cantidad de columnas y distinto significado:
 *
 *   [ 08 ] Confían en nosotros   → clientes a los que ya se les vendió
 *   [ 09 ] Vendedores oficiales  → marcas de terceros que Rastro representa
 *
 * CÓMO SE USA
 *
 *   $logos_items    = repo_clients();   // o repo_brands()
 *   $logos_columnas = 4;                // 4 | 3
 *   require RASTRO_VIEWS . '/partials/franja-logos.php';
 *
 * POR QUÉ .reticula--celdas Y NO .reticula
 *
 * La cantidad de celdas la decide el cliente cargando logos desde el panel,
 * no el diseño. Con cinco marcas en cuatro columnas, la retícula pintada
 * dejaría tres celdas de fondo #1F1F1F sin tapar. Acá la línea la dibuja
 * cada celda, así una fila incompleta se lee como lo que es (base.css).
 *
 * MIENTRAS NO HAYA LOGOS
 *
 * `logo` viene vacío en los mocks y va a seguir vacío hasta que el cliente
 * mande los SVG (PENDIENTES #25 y #6). En ese caso se escribe el NOMBRE, no
 * un "Logo 01": un nombre es información real y se puede revisar; un número
 * de orden no le dice nada a nadie.
 */

declare(strict_types=1);

$logos_items    = array_values((array) ($logos_items ?? []));
$logos_columnas = $logos_columnas ?? 4;
$logos_columnas = in_array($logos_columnas, [3, 4], true) ? $logos_columnas : 4;
?>
<?php if ($logos_items !== []): ?>
    <ul class="logos reticula reticula--celdas logos--<?= e((string) $logos_columnas) ?>">
        <?php foreach ($logos_items as $logo): ?>
            <?php
            $logo_archivo = (string) ($logo['logo'] ?? '');
            $logo_nombre  = (string) ($logo['nombre'] ?? '');
            $logo_medida  = $logo_archivo !== '' ? imagen_medidas($logo_archivo) : null;
            ?>
            <li class="logos__celda">
                <?php if ($logo_archivo !== ''): ?>
                    <img
                        class="logos__imagen"
                        src="<?= e(asset($logo_archivo)) ?>"
                        alt="<?= e($logo_nombre) ?>"
                        loading="lazy"
                        decoding="async"
                        <?php if ($logo_medida !== null): ?>
                            width="<?= e((string) $logo_medida['ancho']) ?>"
                            height="<?= e((string) $logo_medida['alto']) ?>"
                        <?php endif; ?>
                    >
                <?php else: ?>
                    <span class="logos__nombre t-mono-label"><?= e($logo_nombre) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
