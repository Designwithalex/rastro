<?php
/**
 * home.php — la portada.
 *
 * Sigue el frame "Home BOLD · Desktop 1440" de Figma, página Sitio · Home.
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=28-3
 *
 * ORDEN DE LAS SECCIONES, y por qué ese
 *
 *   hero            qué vendemos y a quién, más las dos puertas
 *   garantías       las cuatro razones para seguir leyendo
 *   05 categorías   por dónde entrar al catálogo
 *   06 destacados   producto real, con precio, lo antes posible
 *   07 medios de pago  la regla de precio, dicha una sola vez y en grande
 *   08 confían      quién ya compró
 *   09 vendedores   a quién representamos
 *   10 quiénes somos   la franja de Nosotros
 *   11 mayoristas   recién al final se pide el contacto
 *
 * La prueba de confianza va ANTES del pedido de contacto: primero se
 * contesta "¿quiénes son estos?" y después se pide que escriban.
 *
 * NUMERACIÓN. Los índices [ 0X ] son correlativos sobre las secciones que
 * de verdad se dibujan. Ojo al comparar con Figma: en los frames la franja
 * de Nosotros todavía dice [ 07 ] —número que ya usa Medios de pago— y
 * Mayoristas dice [ 10 ]. Acá van 10 y 11. Anotado en PENDIENTES #59.
 *
 * TODO EL CONTENIDO SALE DEL REPOSITORY salvo dos bloques de copy que hoy
 * son texto de diseño y todavía no tienen dónde vivir: las cuatro garantías
 * y el panel mayorista (PENDIENTES #28). Están declarados arriba de todo,
 * en un solo lugar, para que se vean y para que el día que el panel los
 * edite se reemplace el array por una llamada al repository.
 */

declare(strict_types=1);

$titulo      = null; // usa el título por defecto, que es el de la marca
$descripcion = 'Discos, barras, mancuernas, kettlebells y racks para gimnasios, clubes y '
             . 'entrenamiento en casa. Venta minorista y mayorista en todo el país.';
$clase_body  = 'pagina-home';
$estilos     = ['componentes', 'home', 'nosotros'];

/* El carrusel del hero. Sin él la pista sigue siendo un scroller con snap:
   el script sólo agrega los controles, el autoplay y el foco. */
$scripts     = ['carrusel'];

/* Saira Condensed la dibuja solamente el titular del hero, que existe nada
   más que en esta página. Se precarga acá y en ningún otro lado. */
$precargar_titular = true;

require RASTRO_VIEWS . '/layout/head.php';

/* --- Datos --------------------------------------------------------- */

$categorias  = repo_categories();
$destacados  = repo_products(['destacado' => true], 1, 4)['items'];
$catalogo    = repo_products([], 1, 1);
$clientes    = repo_clients();
$marcas      = repo_brands();

/* El fondo del hero y las placas que rotan arriba son dos cosas distintas,
   y por eso son dos posiciones distintas. Hasta el hero v3 la vista tomaba
   el PRIMER banner de `hero` como foto de fondo y del segundo en adelante
   armaba el collage: una regla implicita que el panel no tenia como
   explicarle al cliente (#61). Ahora `hero_fondo` es la foto de atras y
   `hero` son las placas de promocion. Una posicion, una intencion. */
$banners = repo_banners();

$hero_fondo = null;
foreach ($banners as $banner) {
    if (($banner['posicion'] ?? '') === 'hero_fondo') {
        $hero_fondo = $banner;
        break;
    }
}

$banners_hero = array_values(array_filter(
    $banners,
    static fn (array $b): bool => ($b['posicion'] ?? '') === 'hero'
));

$whatsapp = whatsapp_link(
    $settings,
    'Hola Rastro, estoy equipando un espacio y quiero pedir una cotización.'
);

/* Las seis del bento son las cinco primeras categorías más la celda "Ver
   todo", tal como el frame. Con seis categorías cargadas, la sexta no entra
   en la home y se ve en el catálogo y en el mega-menú (PENDIENTES #60). */
$categorias_bento = array_slice($categorias, 0, 5);

/* Copy de diseño, todavía sin panel que lo edite. */
$garantias = [
    ['titulo' => 'Envío nacional',      'texto' => 'Transporte propio y encomienda a todo el país.', 'icono' => 'img/iconos/icono-envio-blanco.png'],
    ['titulo' => 'Importación directa', 'texto' => 'Traemos el contenedor. Sin intermediarios.',     'icono' => 'img/iconos/icono-contenedor-blanco.png'],
    ['titulo' => 'Factura A o B',       'texto' => 'Comprobante y garantía por escrito.',            'icono' => 'img/iconos/icono-documento-blanco.png'],
    ['titulo' => 'Asesoramiento',       'texto' => 'Armamos el equipamiento según tu espacio.',      'icono' => 'img/iconos/icono-pregunta-blanco.png'],
];

$mayorista_publicos = [
    'Clubes y gimnasios',
    'Hoteles y resorts',
    'Desarrolladoras y barrios cerrados',
    'Empresas con gimnasio propio',
];
?>

<main id="contenido" tabindex="-1">

    <?php /* ============================================================
             Hero
             ============================================================ */ ?>
    <section class="hero" aria-labelledby="hero-titulo">

        <?php
        $hero_foto = (string) ($hero_fondo['imagen'] ?? '');
        $hero_webp = $hero_foto !== '' ? imagen_webp($hero_foto) : null;
        ?>
        <?php if ($hero_foto !== ''): ?>
            <?php /* Decorativa: el alt vacío es correcto. Lo que la foto
                     aporta es clima, y el titular ya dice todo lo demás.
                     fetchpriority alto porque es la imagen más grande de
                     la primera pantalla. */ ?>
            <picture class="hero__fondo">
                <?php if ($hero_webp !== null): ?>
                    <source srcset="<?= e($hero_webp) ?>" type="image/webp">
                <?php endif; ?>
                <img src="<?= e(asset($hero_foto)) ?>" alt="" fetchpriority="high" decoding="async">
            </picture>
        <?php endif; ?>

        <span class="hero__velo" aria-hidden="true"></span>

        <div class="contenedor hero__interior">

            <div class="hero__texto">
                <p class="hero__ojal metal">Equipamiento</p>

                <h1 class="hero__titulo t-display-hero metal" id="hero-titulo">Profesional</h1>

                <span class="hero__regla" aria-hidden="true"></span>

                <p class="hero__bajada">
                    Equipamiento diseñado para acompañar cada etapa de tu entrenamiento.
                </p>

                <?php /* El claim es una sola frase partida en dos líneas y en
                         dos colores. Va en un <p> con <span>, no en dos <p>:
                         "Rastro." y "Marca el camino." son la misma oración. */ ?>
                <p class="hero__claim t-display-hero-sub">
                    <span class="hero__claim-marca metal">Rastro.</span>
                    <span class="hero__claim-frase metal">Marca el camino.</span>
                </p>

                <div class="hero__acciones">
                    <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                        <span class="t-mono-label">Ver catálogo</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                    <a class="boton boton--fantasma" href="<?= e(url('/mayoristas')) ?>">
                        <span class="t-mono-label">Soy mayorista</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <?php /* ------------------------------------------------------
                     El carrusel de placas.

                     En el frame son piezas de promoción de la marca —las
                     mismas que el cliente publica en Instagram— apiladas a
                     la derecha del titular: la del medio entera y las dos
                     vecinas asomando por los costados. Eso es lo que dibuja
                     este bloque, con las placas que tengan `posicion: hero`.

                     SIN JAVASCRIPT SIGUE ANDANDO. La pista es un contenedor
                     con scroll horizontal y scroll-snap: se arrastra con el
                     dedo y se recorre con el teclado sin que corra una línea
                     de JS. Los controles arrancan en `hidden` y los muestra
                     carrusel.js, porque son botones que sin JS no hacen nada
                     —el mismo criterio que la hamburguesa en sin-js.css.

                     Con una sola placa no hay nada que rotar: no se dibujan
                     controles y la pista queda quieta.
                     ------------------------------------------------------ */ ?>
            <?php if ($banners_hero !== []): ?>
                <?php $hero_placas = count($banners_hero); ?>
                <?php /* Con una sola placa no hay carrusel, y decirle
                         "carrusel" a un lector de pantalla es prometer
                         un recorrido que no existe. Los roles entran
                         recién cuando hay algo que recorrer. */ ?>
                <div class="hero__carrusel carrusel"
                     <?php if ($hero_placas > 1): ?>
                         data-carrusel
                         role="group"
                         aria-roledescription="carrusel"
                         aria-label="Promociones de Rastro Fitness"
                     <?php endif; ?>>

                    <ul class="carrusel__pista" data-carrusel-pista>
                        <?php foreach ($banners_hero as $i => $placa): ?>
                            <?php
                            $placa_img  = (string) ($placa['imagen'] ?? '');
                            $placa_webp = $placa_img !== '' ? imagen_webp($placa_img) : null;
                            $placa_txt  = (string) ($placa['titulo'] ?? '');
                            $placa_href = (string) ($placa['enlace'] ?? '');
                            ?>
                            <?php if ($placa_img !== ''): ?>
                                <li class="carrusel__placa"
                                    <?php if ($hero_placas > 1): ?>
                                        role="group"
                                        aria-roledescription="diapositiva"
                                        aria-label="<?= e(sprintf('%d de %d', $i + 1, $hero_placas)) ?>"
                                    <?php endif; ?>>

                                    <?php /* La placa YA dice lo que promociona: el texto
                                             está adentro de la imagen. Por eso el alt es
                                             el título del banner y no una descripción de
                                             la foto — es la única forma de que un lector
                                             de pantalla reciba lo mismo que se ve. */ ?>
                                    <?php ob_start(); ?>
                                        <picture class="carrusel__marco">
                                            <?php if ($placa_webp !== null): ?>
                                                <source srcset="<?= e($placa_webp) ?>" type="image/webp">
                                            <?php endif; ?>
                                            <img src="<?= e(asset($placa_img)) ?>"
                                                 alt="<?= e($placa_txt) ?>"
                                                 width="892" height="1115"
                                                 <?php /* La única imagen con prioridad alta del hero es
                                                          el fondo: es la más grande y la candidata a LCP.
                                                          Pedir dos altas es no pedir ninguna. La primera
                                                          placa va eager —está en la primera pantalla— y
                                                          las demás, diferidas. */ ?>
                                                 <?= $i === 0 ? '' : 'loading="lazy"' ?>
                                                 decoding="async">
                                        </picture>
                                    <?php $placa_marco = (string) ob_get_clean(); ?>

                                    <?php if ($placa_href !== ''): ?>
                                        <a class="carrusel__enlace" href="<?= e(url($placa_href)) ?>">
                                            <?= $placa_marco ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $placa_marco ?>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($hero_placas > 1): ?>
                        <div class="carrusel__controles" data-carrusel-controles hidden>
                            <button class="carrusel__flecha carrusel__flecha--anterior"
                                    type="button" data-carrusel-anterior>
                                <span class="visualmente-oculto">Placa anterior</span>
                                <span aria-hidden="true">←</span>
                            </button>

                            <ol class="carrusel__puntos" data-carrusel-puntos>
                                <?php foreach ($banners_hero as $i => $placa): ?>
                                    <li>
                                        <button class="carrusel__punto" type="button"
                                                data-carrusel-ir="<?= e((string) $i) ?>">
                                            <span class="visualmente-oculto">
                                                Ir a la placa <?= e((string) ($i + 1)) ?>
                                            </span>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ol>

                            <button class="carrusel__flecha carrusel__flecha--siguiente"
                                    type="button" data-carrusel-siguiente>
                                <span class="visualmente-oculto">Placa siguiente</span>
                                <span aria-hidden="true">→</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <?php /* ============================================================
             Garantías — las cuatro razones
             ============================================================ */ ?>
    <section class="garantias" aria-label="Por qué comprarnos">
        <ul class="garantias__grilla reticula">
            <?php foreach ($garantias as $i => $garantia): ?>
                <li class="garantias__item">
                    <p class="garantias__meta">
                        <span class="garantias__indice indice t-mono-label-sm"><?= e(sprintf('%02d', $i + 1)) ?></span>
                        <img class="garantias__pictograma"
                             src="<?= e(asset($garantia['icono'])) ?>"
                             alt="" width="26" height="26" loading="lazy" decoding="async">
                    </p>
                    <h2 class="garantias__titulo t-display-s"><?= e($garantia['titulo']) ?></h2>
                    <p class="garantias__texto t-mono-texto"><?= e($garantia['texto']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php /* ============================================================
             05 · Categorías — el bento
             ============================================================ */ ?>
    <section class="categorias" aria-labelledby="categorias-titulo">
        <div class="contenedor">
            <?php
            $seccion_indice = '05';
            $seccion_titulo = 'Categorías';
            $seccion_id     = 'categorias-titulo';
            $seccion_nota   = sprintf(
                '%d productos en %d categorías',
                $catalogo['total'],
                count($categorias)
            );
            require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
            ?>
        </div>

        <ul class="bento reticula">
            <?php foreach ($categorias_bento as $i => $categoria): ?>
                <?php
                $cat_foto = $i === 0 ? 'img/ambiente/ambiente-discos-en-gimnasio.jpg' : null;
                ?>
                <li class="bento__celda bento__celda--<?= e((string) ($i + 1)) ?><?= $cat_foto ? ' bento__celda--foto' : '' ?>">
                    <?php if ($cat_foto !== null): ?>
                        <?php /* Duotono bordeaux, la regla de las fotos de
                                 ambiente (CLAUDE.md §5.6). El velo lo pone el
                                 CSS con ::after, no una capa en el marcado. */ ?>
                        <img class="bento__foto" src="<?= e(asset($cat_foto)) ?>" alt=""
                             loading="lazy" decoding="async">
                    <?php endif; ?>

                    <p class="bento__meta">
                        <span class="bento__indice indice t-mono-label-sm"><?= e(sprintf('%02d', $i + 1)) ?></span>
                        <?php if (!empty($categoria['pictograma'])): ?>
                            <img class="bento__pictograma"
                                 src="<?= e(asset($categoria['pictograma'])) ?>"
                                 alt="" width="30" height="30" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </p>

                    <div class="bento__texto">
                        <h3 class="bento__nombre <?= $i === 0 ? 't-display-l' : 't-display-m' ?>">
                            <a class="bento__enlace" href="<?= e(url('/catalogo/' . ($categoria['slug'] ?? ''))) ?>">
                                <?= e($categoria['nombre'] ?? '') ?>
                            </a>
                        </h3>
                        <p class="bento__conteo t-mono-texto">
                            <?= e((string) ($categoria['productos_count'] ?? 0)) ?> productos
                        </p>
                    </div>
                </li>
            <?php endforeach; ?>

            <li class="bento__celda bento__celda--todo">
                <p class="bento__meta">
                    <span class="bento__indice indice t-mono-label-sm"><?= e(sprintf('%02d', count($categorias_bento) + 1)) ?></span>
                    <span class="bento__flecha" aria-hidden="true">→</span>
                </p>
                <div class="bento__texto">
                    <h3 class="bento__nombre t-display-m">
                        <a class="bento__enlace" href="<?= e(url('/catalogo')) ?>">Ver todo</a>
                    </h3>
                    <p class="bento__conteo t-mono-texto"><?= e((string) $catalogo['total']) ?> productos</p>
                </div>
            </li>
        </ul>
    </section>

    <?php /* ============================================================
             06 · Destacados
             ============================================================ */ ?>
    <?php if ($destacados !== []): ?>
        <section class="destacados" aria-labelledby="destacados-titulo">
            <div class="contenedor">
                <?php
                $seccion_indice = '06';
                $seccion_titulo = 'Destacados';
                $seccion_id     = 'destacados-titulo';
                $seccion_enlace = ['texto' => 'Ver todo', 'href' => url('/catalogo')];
                require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
                ?>
            </div>

            <ul class="grilla-productos reticula">
                <?php foreach ($destacados as $producto): ?>
                    <li class="grilla-productos__celda">
                        <?php
                        $card_producto = $producto;
                        $card_titulo   = 'h3';
                        require RASTRO_VIEWS . '/partials/card-producto.php';
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php /* ============================================================
             07 · Medios de pago — la regla de precio, una sola vez

             El número no está escrito en el marcado: sale del helper, que
             lee settings. Es el dato más importante del sitio y no puede
             tener dos fuentes de verdad (CLAUDE.md §4.3).
             ============================================================ */ ?>
    <section class="banda-pago" aria-labelledby="banda-pago-titulo">
        <span class="banda-pago__cifra" aria-hidden="true"><?= e(descuento_global($settings)) ?>%</span>

        <div class="contenedor banda-pago__interior">
            <p class="banda-pago__indice indice-seccion t-mono-label">
                <span class="indice">07</span> Medios de pago
            </p>

            <h2 class="banda-pago__titulo t-display-l" id="banda-pago-titulo">
                −<?= e(descuento_global($settings)) ?>% por transferencia o efectivo
            </h2>

            <p class="banda-pago__texto t-mono-texto">
                El precio publicado es pagando con Mercado Pago. Por transferencia bancaria
                o efectivo se descuenta un <?= e(descuento_global($settings)) ?>% sobre ese precio.
            </p>
        </div>
    </section>

    <?php /* ============================================================
             08 · Confían en nosotros
             ============================================================ */ ?>
    <?php if ($clientes !== []): ?>
        <section class="franja-prueba" aria-labelledby="clientes-titulo">
            <div class="contenedor">
                <?php
                $seccion_indice = '08';
                $seccion_titulo = 'Confían en nosotros';
                $seccion_id     = 'clientes-titulo';
                $seccion_nota   = 'Clubes, gimnasios, hoteles y empresas que ya equipamos';
                require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
                ?>
            </div>

            <?php
            $logos_items    = $clientes;
            $logos_columnas = 4;
            require RASTRO_VIEWS . '/partials/franja-logos.php';
            ?>
        </section>
    <?php endif; ?>

    <?php /* ============================================================
             09 · Vendedores oficiales

             repo_brands() ya deja afuera la línea propia: uno no es
             vendedor oficial de sí mismo (ver repository.php).
             ============================================================ */ ?>
    <?php if ($marcas !== []): ?>
        <section class="franja-prueba" aria-labelledby="marcas-titulo">
            <div class="contenedor">
                <?php
                $seccion_indice = '09';
                $seccion_titulo = 'Vendedores oficiales';
                $seccion_id     = 'marcas-titulo';
                $seccion_nota   = 'Marcas que representamos de forma oficial en Argentina';
                require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
                ?>
            </div>

            <?php
            $logos_items    = $marcas;
            $logos_columnas = 3;
            require RASTRO_VIEWS . '/partials/franja-logos.php';
            ?>
        </section>
    <?php endif; ?>

    <?php /* ============================================================
             10 · Quiénes somos
             ============================================================ */ ?>
    <?php
    $franja_indice = '10';
    require RASTRO_VIEWS . '/partials/franja-nosotros.php';
    ?>

    <?php /* ============================================================
             11 · Mayoristas — el único pedido de contacto de la home
             ============================================================ */ ?>
    <section class="mayoristas-franja reticula" aria-labelledby="mayoristas-titulo">

        <div class="mayoristas-franja__foto">
            <img src="<?= e(asset('img/ambiente/ambiente-disco-fundicion-galpon.jpg')) ?>" alt=""
                 loading="lazy" decoding="async">
            <p class="mayoristas-franja__pie t-mono-label-sm">Fig. 02 · Depósito propio</p>
        </div>

        <div class="mayoristas-franja__panel">
            <p class="mayoristas-franja__indice indice-seccion t-mono-label">
                <span class="indice">11</span> Venta mayorista
            </p>

            <h2 class="mayoristas-franja__titulo t-display-l" id="mayoristas-titulo">
                ¿Equipás un espacio completo?
            </h2>

            <ul class="mayoristas-franja__publicos">
                <?php foreach ($mayorista_publicos as $i => $publico): ?>
                    <li class="mayoristas-franja__publico">
                        <span class="mayoristas-franja__numero t-mono-label-sm"><?= e(sprintf('%02d', $i + 1)) ?></span>
                        <span class="mayoristas-franja__nombre t-mono-texto"><?= e($publico) ?></span>
                        <span class="mayoristas-franja__linea" aria-hidden="true"></span>
                        <span class="mayoristas-franja__consultar t-mono-label-sm">Consultar</span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php /* Sin número de WhatsApp cargado no se dibuja el botón:
                     un enlace a wa.me sin destinatario lleva a la home de
                     WhatsApp, que es peor que no ofrecerlo (helpers.php).
                     Mientras tanto queda la puerta a /mayoristas. */ ?>
            <?php if ($whatsapp !== null): ?>
                <a class="boton boton--acento mayoristas-franja__cta"
                   href="<?= e($whatsapp) ?>" rel="noopener" target="_blank">
                    <span class="t-mono-label">Consultar por WhatsApp</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            <?php else: ?>
                <a class="boton boton--acento mayoristas-franja__cta" href="<?= e(url('/mayoristas')) ?>">
                    <span class="t-mono-label">Pedir cotización</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>

            <p class="mayoristas-franja__nota t-mono-texto-sm">
                Precios mayoristas no publicados. Cotizamos el proyecto completo.
            </p>
        </div>

    </section>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
