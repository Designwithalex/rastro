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
        'texto'  => 'Si el producto llegó fallado, roto o no es el que pediste, escribinos dentro '
                  . 'de los 10 días corridos de recibirlo y lo cambiamos o te devolvemos el dinero, '
                  . 'a tu elección. El flete de ida y de vuelta lo pagamos nosotros: es lo que '
                  . 'corresponde cuando el problema es nuestro.

                  Si simplemente cambiaste de idea, el camino es el botón de arrepentimiento, que '
                  . 'tiene su propia sección más abajo y también es sin costo para vos.

                  Fuera de esos dos casos, un cambio por otro producto lo coordinamos por WhatsApp '
                  . 'según el estado en el que esté la mercadería. El equipamiento de gimnasio se '
                  . 'marca con el uso y un disco que ya se levantó no vuelve a ser nuevo, así que '
                  . 'lo vemos caso por caso y te lo decimos de frente antes de que lo despaches.',
        'pendiente' => 'BORRADOR escrito por Chichalabs el 11/09/2026 sobre los mínimos de la Ley '
                     . '24.240. Falta que el cliente confirme si quiere ofrecer un cambio por '
                     . 'arrepentimiento más allá de los 10 días de ley, y que lo revise quien le '
                     . 'lleva lo legal (PENDIENTES #10).',
    ],
    [
        'titulo' => 'Garantía',
        'texto'  => 'Todo el equipamiento que vendemos tiene 12 meses de garantía por defecto de '
                  . 'fabricación, contados desde la entrega. Es más que los 6 meses que exige la '
                  . 'Ley 24.240 para bienes muebles no consumibles.

                  La garantía cubre fallas de fabricación: una soldadura que cede, un buje que se '
                  . 'afloja, un recubrimiento que se desprende sin motivo. No cubre el desgaste '
                  . 'normal de uso ni los golpes. En un gimnasio eso pasa todos los días y no es '
                  . 'una falla: un disco que se marca contra el piso está trabajando, no fallando.

                  Para usarla necesitás la factura y una foto o un video de lo que pasó. Nos '
                  . 'escribís, lo miramos, y si corresponde reparamos, reemplazamos la pieza o '
                  . 'cambiamos el producto, en ese orden. El traslado durante el período de '
                  . 'garantía corre por nuestra cuenta.',
        'pendiente' => 'BORRADOR escrito por Chichalabs el 11/09/2026. Los 12 meses salen del copy '
                     . 'que ya estaba en la ficha de producto y en /nosotros: hay que confirmar que '
                     . 'sea el plazo real y no un número de diseño (PENDIENTES #33). Si el fabricante '
                     . 'da otro plazo para alguna línea, va acá.',
    ],
    [
        'titulo' => 'Datos personales',
        'texto'  => 'Para venderte necesitamos algunos datos tuyos y te contamos exactamente cuáles.

                  Cuando comprás: nombre, apellido, correo, teléfono, documento y la dirección de '
                  . 'entrega. Los usamos para armar el pedido, despacharlo y facturarlo. Si creás '
                  . 'una cuenta guardamos además tu correo y tu contraseña, que no se guarda tal '
                  . 'cual sino cifrada: nadie de Rastro puede leerla, ni nosotros.

                  Con quién los compartimos: con Mercado Pago cuando pagás por ahí, porque el pago '
                  . 'lo procesa esa plataforma y no nosotros —los datos de tu tarjeta nunca pasan '
                  . 'por este sitio ni los podemos ver—, y con el transporte que lleva el paquete, '
                  . 'que necesita tu dirección y tu teléfono. Con nadie más. No vendemos ni cedemos '
                  . 'datos a terceros para publicidad.

                  Podés pedirnos en cualquier momento que te digamos qué datos tuyos tenemos, que '
                  . 'los corrijamos o que los borremos, escribiéndonos al correo de contacto. Es un '
                  . 'derecho que te da la Ley 25.326 y ejercerlo es gratis.

                  La Agencia de Acceso a la Información Pública es el organismo de control de esa '
                  . 'ley y atiende las denuncias de quien considere que sus datos fueron mal '
                  . 'tratados.',
        'pendiente' => 'BORRADOR escrito por Chichalabs el 11/09/2026 a partir de los datos que el '
                     . 'sitio DE VERDAD recolecta hoy (checkout, registro y Mercado Pago). Falta: '
                     . 'cuánto tiempo se conservan los datos y quién es el responsable inscripto '
                     . 'ante la AAIP. Lo tiene que revisar quien le lleva lo legal al cliente.',
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
