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

/* Cada bloque declara si su contenido ya existe. `texto` en null es un
   hueco declarado y se dibuja como tal. */
$bloques = [
    [
        'titulo' => 'Quiénes somos',
        'texto'  => $settings['razon_social'] . ', CUIT ' . $settings['cuit'] . '. '
                  . 'Venta de equipamiento de gimnasio, minorista y mayorista, en todo el país. '
                  . 'Contacto: ' . ($settings['email'] ?? '') . ' · WhatsApp ' . ($settings['whatsapp'] ?? '') . '.',
    ],
    [
        'titulo' => 'Precios y medios de pago',
        'texto'  => 'Los precios publicados están expresados en pesos argentinos e incluyen IVA, '
                  . 'y corresponden al pago con Mercado Pago. Pagando por transferencia bancaria '
                  . 'o en efectivo se aplica un ' . descuento_global($settings) . '% de descuento '
                  . 'sobre el precio publicado. Los precios pueden cambiar sin aviso previo; el que '
                  . 'vale es el del momento de confirmar la compra.',
    ],
    [
        'titulo' => 'Envíos y retiro',
        'texto'  => 'Hacemos envíos a todo el país por transporte propio y por encomienda. '
                  . 'El envío es sin cargo a partir de ' . moneda($settings['envio_gratis_desde'] ?? 0)
                  . ' de compra. También se puede retirar en depósito sin cargo.',
        'pendiente' => 'Falta la tabla de costos por zona y los plazos de entrega reales (PENDIENTES #8).',
    ],
    [
        'titulo' => 'Cambios y devoluciones',
        'texto'  => null,
        'pendiente' => 'Falta el texto del cliente: en qué plazo se acepta un cambio, en qué estado '
                     . 'tiene que volver el producto y quién paga el flete.',
    ],
    [
        'titulo' => 'Garantía',
        'texto'  => null,
        'pendiente' => 'Falta confirmar el plazo real y qué cubre (PENDIENTES #33). La ficha de '
                     . 'producto hoy dice 12 meses por defecto de fabricación, que es copy provisorio.',
    ],
    [
        'titulo' => 'Datos personales',
        'texto'  => null,
        'pendiente' => 'Falta la política de privacidad: qué datos se guardan, para qué y cómo se '
                     . 'pide la baja. Ley 25.326.',
    ],
    [
        'titulo' => 'Botón de arrepentimiento',
        'texto'  => 'Si comprás a distancia podés arrepentirte dentro de los 10 días corridos de '
                  . 'recibido el producto, sin costo y sin tener que explicar por qué. El trámite '
                  . 'está en la página del botón de arrepentimiento.',
    ],
];
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
                        <p class="t-body-md"><?= e($bloque['texto']) ?></p>
                    <?php else: ?>
                        <p class="marcador marcador--bloque t-mono-label">[ Falta el texto legal ]</p>
                    <?php endif; ?>

                    <?php if (!empty($bloque['pendiente'])): ?>
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
