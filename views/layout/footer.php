<?php
/**
 * layout/footer.php — pie del sitio y cierre del documento.
 *
 * Cuatro columnas con letra de índice, la barra de legales con el botón de
 * arrepentimiento (obligatorio por la ley 24.240) y la palabra RASTRO
 * recortada por el borde inferior.
 */

declare(strict_types=1);

$settings = repo_settings();

$layout_whatsapp = whatsapp_link($settings);

$layout_columnas = [
    [
        'letra'   => 'A',
        'titulo'  => 'Catálogo',
        'enlaces' => array_map(
            static fn (array $c): array => [
                'texto' => $c['nombre'],
                'ruta'  => '/catalogo/' . $c['slug'],
            ],
            repo_categories()
        ),
    ],
    [
        'letra'   => 'B',
        'titulo'  => 'Rastro',
        'enlaces' => [
            ['texto' => 'Todo el catálogo',       'ruta' => '/catalogo'],
            ['texto' => 'Destacados',             'ruta' => '/catalogo?destacado=1'],
            ['texto' => 'Venta mayorista',        'ruta' => '/mayoristas'],
            ['texto' => 'Nosotros',               'ruta' => '/nosotros'],
            ['texto' => 'Términos y condiciones', 'ruta' => '/terminos'],
        ],
    ],
    [
        'letra'   => 'C',
        'titulo'  => 'Tu cuenta',
        'enlaces' => [
            ['texto' => 'Ingresar',      'ruta' => '/ingresar'],
            ['texto' => 'Crear cuenta',  'ruta' => '/registro'],
            ['texto' => 'Mis pedidos',   'ruta' => '/cuenta'],
            ['texto' => 'Carrito',       'ruta' => '/carrito'],
        ],
    ],
];
?>

<footer class="pie">

    <div class="pie__cuerpo contenedor">
        <div class="pie__columnas reticula reticula--superficie">

            <?php foreach ($layout_columnas as $layout_columna): ?>
                <section class="pie__columna">
                    <h2 class="pie__titulo t-mono-label">
                        <span class="pie__letra"><?= e($layout_columna['letra']) ?></span>
                        <?= e($layout_columna['titulo']) ?>
                    </h2>
                    <ul class="pie__lista">
                        <?php foreach ($layout_columna['enlaces'] as $layout_enlace): ?>
                            <li>
                                <a class="pie__enlace t-body-sm" href="<?= e(url($layout_enlace['ruta'])) ?>">
                                    <?= e($layout_enlace['texto']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <section class="pie__columna">
                <h2 class="pie__titulo t-mono-label">
                    <span class="pie__letra">D</span>
                    Contacto
                </h2>
                <ul class="pie__lista">
                    <?php /* Sin número cargado no se dibuja el enlace: mandar a
                             wa.me sin destinatario es peor que no ofrecerlo. */ ?>
                    <?php if ($layout_whatsapp !== null): ?>
                        <li>
                            <a class="pie__enlace t-body-sm" href="<?= e($layout_whatsapp) ?>"
                               rel="noopener" target="_blank">
                                WhatsApp <?= e($settings['whatsapp'] ?? '') ?>
                                <span class="visualmente-oculto">(abre WhatsApp en una pestaña nueva)</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="pie__enlace t-body-sm" href="mailto:<?= e($settings['email'] ?? '') ?>">
                            <?= e($settings['email'] ?? '') ?>
                        </a>
                    </li>
                    <li>
                        <a class="pie__enlace t-body-sm" href="<?= e($settings['instagram'] ?? '') ?>"
                           rel="noopener" target="_blank">Instagram
                            <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
                        </a>
                    </li>
                    <li class="pie__dato t-mono-texto-sm"><?= e($settings['horario'] ?? '') ?></li>
                </ul>
            </section>

        </div>

        <?php /* La misma pieza que el pie del mega-menú y el cajón de celular.
                 Antes acá había una frase escrita a mano que decía lo mismo:
                 dos redacciones de la regla de precio es una de más. */ ?>
        <div class="pie__regla">
            <?php require RASTRO_VIEWS . '/partials/regla-precio.php'; ?>
        </div>
    </div>

    <div class="pie__legales">
        <div class="contenedor pie__legales-barra">
            <p class="pie__razon t-mono-texto-sm">
                <?= e($settings['razon_social'] ?? '') ?> ·
                CUIT <?= e($settings['cuit'] ?? '') ?> ·
                © <?= e(date('Y')) ?>
            </p>

            <ul class="pie__legales-lista">
                <li>
                    <?php /* Obligatorio por la Resolución 424/2020 de Defensa del Consumidor:
                             tiene que estar visible en la home y en todas las páginas. */ ?>
                    <a class="pie__arrepentimiento t-mono-label-sm" href="<?= e(url('/arrepentimiento')) ?>">
                        Botón de arrepentimiento
                    </a>
                </li>
                <li>
                    <a class="pie__legal t-mono-texto-sm" href="<?= e(url('/terminos')) ?>">
                        Términos y condiciones
                    </a>
                </li>
                <li>
                    <a class="pie__legal t-mono-texto-sm" rel="noopener" target="_blank"
                       href="https://autogestion.produccion.gob.ar/consumidores">
                        Defensa de las y los consumidores
                        <span class="visualmente-oculto">(sitio del Estado, abre en una pestaña nueva)</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <?php /* La marca sangrando por el borde inferior. Decorativa. */ ?>
    <div class="pie__marca" aria-hidden="true"><span>Rastro</span></div>

</footer>

<script src="<?= e(asset('js/nav.js')) ?>" defer></script>
<script src="<?= e(asset('js/carrito.js')) ?>" defer></script>

<?php
/* Scripts propios de una página, DESPUÉS de los comunes. El orden importa:
   los defer se ejecutan en el orden del marcado, así que un archivo que
   use window.Carrito tiene que venir detrás de carrito.js. Por eso lo pone
   el layout y no la vista.

   La vista lo declara antes de incluir head.php:  $scripts = ['carrito-pagina']; */
foreach (array_filter(
    (array) ($scripts ?? []),
    static fn ($js): bool => is_string($js) && preg_match('/^[a-z0-9-]+$/', $js) === 1
) as $js): ?>
    <script src="<?= e(asset('js/' . $js . '.js')) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
