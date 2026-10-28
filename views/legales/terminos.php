<?php
/**
 * legales/terminos.php — términos y condiciones.
 *
 * EL TEXTO LEGAL NO LO ESCRIBE EL DISEÑO. Lo tiene que pasar el cliente,
 * idealmente revisado por quien le lleve lo legal (PENDIENTES #10). Esta
 * vista arma la estructura y deja cada bloque como hueco VISIBLE, con el
 * mismo criterio que /nosotros: un texto legal inventado que se lee como
 * definitivo es peor que un hueco, porque nadie lo vuelve a mirar y
 * termina publicado.
 *
 * Lo que sí está escrito es lo que ya sabemos del negocio y sale de
 * `settings`: el porcentaje de descuento, el umbral de envío gratis y los
 * datos fiscales. Eso no hay que pedirlo dos veces.
 */

declare(strict_types=1);

$titulo      = 'Términos y condiciones';
$descripcion = 'Condiciones de compra, envíos, cambios y garantía de Rastro Fitness.';
$clase_body  = 'pagina-legal';
$estilos     = ['componentes', 'catalogo', 'legales'];

require RASTRO_VIEWS . '/layout/head.php';

/* Los bloques se editan desde el panel (Legales) desde el 28/10. Si nunca
   se guardaron, salen los de terminos_por_defecto() en app/helpers.php. */
$bloques = repo_terminos();
?>

<main id="contenido" tabindex="-1">
    <div class="contenedor legal">

        <?php
        $miga = [['texto' => 'Términos y condiciones']];
        require RASTRO_VIEWS . '/partials/breadcrumb.php';
        ?>

        <h1 class="legal__titulo t-display-l">Términos y condiciones</h1>

        <p class="legal__bajada t-body-lg">
            Condiciones de compra de <?= e($settings['razon_social'] ?? 'Rastro Fitness') ?>.
        </p>

        <?php
        $nota_maqueta = 'Faltan varios bloques de texto legal, que los tiene que pasar el '
                      . 'cliente (PENDIENTES #10). Los huecos se ven a propósito: un texto '
                      . 'legal inventado que se lee como definitivo termina publicado.';
        require RASTRO_VIEWS . '/partials/nota-maqueta.php';
        ?>

        <div class="legal__cuerpo">
            <?php foreach ($bloques as $i => $bloque): ?>
                <section class="legal__bloque">
                    <h2 class="legal__seccion t-display-s">
                        <span class="legal__numero t-mono-label-sm"><?= e(sprintf('%02d', $i + 1)) ?></span>
                        <?= e($bloque['titulo']) ?>
                    </h2>

                    <?php if (!empty($bloque['texto'])): ?>
                        <?php foreach (preg_split('/\n\s*\n/', trim((string) $bloque['texto'])) as $parrafo): ?>
                            <p class="t-body-md"><?= e(preg_replace('/\s+/', ' ', $parrafo)) ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="marcador marcador--bloque t-mono-label">[ Falta el texto legal ]</p>
                    <?php endif; ?>

                    <?php /* Las notas de qué falta en cada bloque están escritas
                             para el equipo —nombran números de PENDIENTES— así que
                             sólo se dibujan en desarrollo, igual que la nota de
                             maqueta. El hueco `[ Falta el texto legal ]` de arriba
                             SÍ se sigue viendo en producción, y es a propósito: un
                             texto legal inventado que se lee como definitivo es peor
                             que un hueco que se ve. */ ?>
                    <?php if (!empty($bloque['pendiente']) && config('entorno') === 'local'): ?>
                        <p class="legal__pendiente t-mono-texto-sm">
                            <span aria-hidden="true">!</span> <?= e($bloque['pendiente']) ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <p class="legal__pie t-mono-texto-sm">
            Ante cualquier duda podés escribirnos a
            <?= e($settings['email'] ?? '') ?>. También podés hacer un reclamo en
            <a class="legal__enlace" href="https://autogestion.produccion.gob.ar/consumidores"
               rel="noopener" target="_blank">Defensa de las y los consumidores</a>.
        </p>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
