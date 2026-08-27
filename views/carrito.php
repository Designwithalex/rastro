<?php
/**
 * carrito.php — el carrito.
 *
 * Sigue el frame "Carrito · Desktop 1440" y su estado vacío.
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=64-3
 *
 * POR QUÉ ESTA PÁGINA SE DIBUJA CON JAVASCRIPT
 *
 * El carrito vive en localStorage y guarda apenas `id` y `cantidad`
 * (assets/js/carrito.js). El servidor no sabe qué tiene cargado quien
 * entra, así que no puede imprimir las filas: las arma el navegador.
 *
 * QUIÉN CALCULA LOS PRECIOS
 *
 * PHP, como en todo el sitio. Esta vista imprime un índice del catálogo
 * con los DOS precios de cada producto —publicado y con descuento— ya
 * resueltos por `precio_con_descuento()`. El JavaScript multiplica por la
 * cantidad y suma; no aplica ningún porcentaje. Si algún día aparece un
 * cálculo de descuento en el JS, hay dos fuentes de verdad y una de las
 * dos está mal (CLAUDE.md §4.3).
 *
 * El índice viaja en un atributo `data-` y no en un <script> en línea
 * porque la Content-Security-Policy del .htaccess no permite scripts
 * embebidos. Para eso está `e_json()` en helpers.php.
 *
 * TODO(backend): hoy el índice son los 30 productos activos, unos 6 KB.
 * Con un catálogo real esto no escala y hay que darlo vuelta: un endpoint
 * que reciba los ids del carrito y devuelva sólo esos. La función que
 * resuelve eso ya existe y ya está en el contrato: `repo_cart_items()`.
 */

declare(strict_types=1);

$titulo      = 'Tu carrito';
$descripcion = 'Revisá lo que cargaste y elegí cómo pagar.';
$clase_body  = 'pagina-carrito';
$estilos     = ['componentes', 'catalogo', 'carrito'];
$scripts     = ['carrito-pagina'];

require RASTRO_VIEWS . '/layout/head.php';

/* Índice del catálogo con los dos precios ya calculados del lado del
   servidor. Sólo lo que el carrito necesita dibujar una fila. */
$indice = [];

foreach (repo_products([], 1, 9999)['items'] as $p) {
    $precio = precio_con_descuento($p, $settings);

    $indice[(string) $p['id']] = [
        'slug'          => $p['slug'] ?? '',
        'nombre'        => $p['nombre'] ?? '',
        'sku'           => $p['sku'] ?? '',
        'imagen'        => !empty($p['imagen']) ? asset((string) $p['imagen']) : null,
        'stock'         => (int) ($p['stock'] ?? 0),
        'url'           => url('/producto/' . ($p['slug'] ?? '')),
        'publicado'     => $precio['publicado'],
        'transferencia' => $precio['con_descuento'],
    ];
}

/* Lo que el JS necesita para formatear y para los totales. El costo de
   envío es 0 hasta que el cliente pase la tabla real (PENDIENTES #8): se
   muestra "a coordinar", no un número inventado. */
$config_carrito = [
    'envioGratisDesde' => (int) ($settings['envio_gratis_desde'] ?? 0),
    'costoEnvio'       => 0,
    'descuentoPct'     => descuento_global($settings),
    'urlCatalogo'      => url('/catalogo'),
];

$whatsapp_carrito = whatsapp_link($settings, 'Hola Rastro, quiero coordinar el pago por transferencia de un pedido.');
?>

<main id="contenido" tabindex="-1"
      data-carrito-pagina
      data-catalogo="<?= e_json($indice) ?>"
      data-config="<?= e_json($config_carrito) ?>">

    <div class="contenedor barra-pagina barra-pagina--carrito">
        <div class="barra-pagina__titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <div class="barra-pagina__linea">
                <h1 class="barra-pagina__nombre t-display-l">Tu carrito</h1>
                <p class="barra-pagina__conteo t-mono-texto" data-carrito-resumen-conteo></p>
            </div>
        </div>
    </div>

    <?php /* Sin JavaScript no hay carrito: vive entero en localStorage. En vez
             de mostrar un carrito vacío que miente, se dice qué pasa y se
             ofrece la salida que sí funciona (PENDIENTES #54). */ ?>
    <noscript>
        <div class="contenedor">
            <div class="vacio">
                <p class="vacio__indice t-mono-label">Hace falta JavaScript</p>
                <h2 class="t-display-m">El carrito no puede abrirse</h2>
                <p class="vacio__texto t-body-md">
                    Tu carrito se guarda en este navegador y necesita JavaScript para
                    leerse. Podés seguir mirando el catálogo y escribirnos por WhatsApp
                    con los códigos que te interesan.
                </p>
                <div class="vacio__acciones">
                    <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                        <span class="t-mono-label">Ver el catálogo</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </noscript>

    <?php /* Estado vacío. Arranca visible y lo esconde el JS si hay líneas:
             al revés, un carrito con productos parpadearía en vacío. */ ?>
    <div class="contenedor" data-carrito-vacio hidden>
        <div class="vacio">
            <p class="vacio__indice t-mono-label">Carrito vacío</p>
            <h2 class="t-display-m">Todavía no cargaste nada</h2>
            <p class="vacio__texto t-body-md">
                Cuando agregues productos van a aparecer acá con el precio publicado
                y el precio por transferencia.
            </p>
            <div class="vacio__acciones">
                <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                    <span class="t-mono-label">Ver el catálogo</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </div>

    <div class="carrito reticula" data-carrito-cuerpo hidden>

        <section class="lineas" aria-label="Productos en el carrito">
            <ul class="lineas__lista" data-carrito-lineas></ul>

            <a class="lineas__seguir t-mono-label" href="<?= e(url('/catalogo')) ?>">
                <span aria-hidden="true">←</span> Seguir comprando
            </a>
        </section>

        <aside class="resumen" aria-labelledby="resumen-titulo">
            <h2 class="resumen__titulo t-display-m" id="resumen-titulo">Resumen</h2>

            <dl class="resumen__filas">
                <div class="resumen__fila">
                    <dt class="t-mono-texto" data-carrito-resumen-productos>Productos</dt>
                    <dd class="t-mono-texto" data-carrito-resumen-subtotal></dd>
                </div>
                <div class="resumen__fila">
                    <dt class="t-mono-texto">Envío</dt>
                    <dd class="t-mono-texto" data-carrito-resumen-envio></dd>
                </div>
                <div class="resumen__fila">
                    <dt class="t-mono-texto">Descuento transferencia</dt>
                    <dd class="t-mono-texto resumen__ahorro" data-carrito-resumen-ahorro></dd>
                </div>
            </dl>

            <div class="resumen__total">
                <p class="resumen__publicado">
                    <span class="visualmente-oculto">Total pagando con Mercado Pago:</span>
                    <span class="plata t-precio-lg" data-carrito-total></span>
                    <span class="t-mono-label-sm" aria-hidden="true">MP</span>
                </p>
                <p class="resumen__transferencia">
                    <span class="visualmente-oculto">Total por transferencia o efectivo:</span>
                    <span class="t-mono-dato" data-carrito-total-transferencia></span>
                    <span class="badge badge--descuento t-mono-label-sm"><?= e(descuento_global($settings)) ?>% OFF</span>
                </p>
                <p class="resumen__nota t-mono-texto-sm" data-carrito-nota-ahorro></p>
            </div>

            <?php /* Desde el 27/08/2026 el botón lleva al checkout de verdad:
                     /checkout pide los datos, crea el pedido y abre Checkout
                     Pro. Es un enlace y no un <button> porque no dispara
                     ninguna acción: navega. Así funciona también con el clic
                     del medio y con "abrir en pestaña nueva". */ ?>
            <a class="boton boton--acento resumen__pagar" href="<?= e(url('/checkout')) ?>">
                <span class="t-mono-label">Finalizar compra</span>
                <span class="boton__flecha" aria-hidden="true">→</span>
            </a>

            <?php if ($whatsapp_carrito !== null): ?>
                <a class="boton boton--fantasma resumen__transferir"
                   href="<?= e($whatsapp_carrito) ?>" rel="noopener" target="_blank">
                    <span class="t-mono-label">Coordinar transferencia</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            <?php endif; ?>

            <p class="resumen__legal t-mono-texto-sm">
                Al pagar por transferencia o efectivo te contactamos para coordinar.
                El descuento se aplica sobre el precio publicado.
            </p>
        </aside>
    </div>

</main>


<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
