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
        // Con sesión no tiene sentido ofrecer "Ingresar" y "Crear cuenta".
        // "Consultar un pedido" es la puerta de quien compró sin cuenta.
        'enlaces' => sesion_usuario() !== null
            ? [
                ['texto' => 'Mis pedidos',   'ruta' => '/cuenta?seccion=pedidos'],
                ['texto' => 'Mis datos',     'ruta' => '/cuenta?seccion=datos'],
                ['texto' => 'Carrito',       'ruta' => '/carrito'],
            ]
            : [
                ['texto' => 'Ingresar',            'ruta' => '/ingresar'],
                ['texto' => 'Crear cuenta',        'ruta' => '/registro'],
                ['texto' => 'Consultar un pedido', 'ruta' => '/pedido'],
                ['texto' => 'Carrito',             'ruta' => '/carrito'],
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

    <?php
    /* Data Fiscal de ARCA (pedido del 22/10). La imagen y el enlace son los
       que da ARCA al adherir el sitio: el link se carga en Configuración. */
    $pie_data_fiscal = trim((string) (repo_settings()['data_fiscal_url'] ?? ''));
    ?>
    <?php if ($pie_data_fiscal !== ''): ?>
        <div class="contenedor pie__data-fiscal">
            <a href="<?= e($pie_data_fiscal) ?>" target="_F960AFIPInfo" rel="noopener">
                <img src="https://www.afip.gob.ar/images/f960/DATAWEB.jpg" width="60" height="82"
                     alt="Data Fiscal de ARCA" loading="lazy">
            </a>
        </div>
    <?php endif; ?>

    <?php /* La marca sangrando por el borde inferior. Decorativa. */ ?>
    <div class="pie__marca" aria-hidden="true"><span>Rastro</span></div>

</footer>

<?php
/* WhatsApp flotante (pedido del 22/10). El mensaje cambia según la página:
   la ficha de producto declara $whatsapp_mensaje_flotante con el nombre y
   el SKU, así quien atiende sabe de qué le hablan. Sin número cargado no
   se dibuja (whatsapp_link() devuelve null). */
$flotante = whatsapp_link(repo_settings(), $whatsapp_mensaje_flotante ?? null);
?>
<?php if ($flotante !== null && !str_starts_with((string) ($clase_body ?? ''), 'pagina-checkout')): ?>
    <a class="whatsapp-flotante" href="<?= e($flotante) ?>" rel="noopener" target="_blank">
        <svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.5-.3Z"/>
        </svg>
        <span class="visualmente-oculto">Escribinos por WhatsApp (abre en una pestaña nueva)</span>
    </a>
<?php endif; ?>

<?php
/* Medición: Meta Pixel y Google Analytics, sólo si hay IDs cargados en
   Configuración. Los IDs viajan en data- porque el CSP no deja scripts en
   línea; assets/js/medicion.js carga las librerías. */
$medicion = [
    'pixel' => preg_replace('/\D+/', '', (string) (repo_settings()['meta_pixel_id'] ?? '')),
    'ga'    => preg_replace('/[^A-Z0-9-]+/', '', strtoupper((string) (repo_settings()['ga_id'] ?? ''))),
];
?>
<?php if ($medicion['pixel'] !== '' || $medicion['ga'] !== ''): ?>
    <script src="<?= e(asset('js/medicion.js')) ?>" defer
            data-pixel="<?= e($medicion['pixel']) ?>"
            data-ga="<?= e($medicion['ga']) ?>"
            <?php if (isset($medicion_compra)): ?>
                data-compra="<?= e(json_encode($medicion_compra, JSON_UNESCAPED_UNICODE)) ?>"
            <?php endif; ?>></script>
<?php endif; ?>

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
