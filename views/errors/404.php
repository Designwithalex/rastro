<?php
/**
 * errors/404.php — la página que no está.
 *
 * Sigue el frame "404 · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=73-604
 *
 * Llega por dos caminos: el router, cuando ninguna ruta coincide, y
 * `router_404()`, cuando una vista pidió algo al repository y no existe
 * —un producto dado de baja, una categoría inventada—. Los dos casos
 * comparten el mismo texto porque para quien está del otro lado son lo
 * mismo: el link no lleva a ningún lado.
 *
 * El código de estado ya lo puso quien la incluyó: index.php con
 * `http_response_code($ruta['estado'])` o `router_404()`. Esta vista no
 * lo toca —si lo hiciera, dos lugares decidirían lo mismo— pero sí lo
 * muestra, que es lo que le sirve a alguien que está depurando.
 */

declare(strict_types=1);

$titulo      = 'Página no encontrada';
$descripcion = 'La página que buscabas no existe. Probá desde el catálogo.';
$clase_body  = 'pagina-404';
$estilos     = ['componentes', 'catalogo', 'legales'];

require RASTRO_VIEWS . '/layout/head.php';

$whatsapp = whatsapp_link($settings, 'Hola Rastro, estoy buscando un producto y no lo encuentro en la web.');
?>

<main class="error404" id="contenido" tabindex="-1">
    <div class="contenedor error404__caja">

        <p class="error404__indice indice t-mono-label">Error 404</p>

        <?php /* La cifra es decorativa: el titular de abajo ya dice qué pasó.
                 Si el lector de pantalla leyera "cuatrocientos cuatro" antes
                 del titular, la información importante llega segunda. */ ?>
        <p class="error404__cifra plata" aria-hidden="true">404</p>

        <h1 class="error404__titulo t-display-l">Esta página no existe</h1>

        <p class="error404__texto t-body-md">
            Puede que el producto ya no esté publicado o que el link esté mal escrito.
            Probá desde el catálogo, o escribinos y lo buscamos.
        </p>

        <div class="error404__acciones">
            <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                <span class="t-mono-label">Ver el catálogo</span>
                <span class="boton__flecha" aria-hidden="true">→</span>
            </a>
            <?php if ($whatsapp !== null): ?>
                <a class="boton boton--fantasma" href="<?= e($whatsapp) ?>" rel="noopener" target="_blank">
                    <span class="t-mono-label">Escribinos por WhatsApp</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
        </div>

        <p class="error404__ruta t-mono-texto-sm">
            <?= e((string) http_response_code()) ?> · <?= e(ruta_actual()) ?>
        </p>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
