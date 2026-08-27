<?php
/**
 * legales/arrepentimiento.php — el botón de arrepentimiento.
 *
 * OBLIGATORIO POR LEY en Argentina: Resolución 424/2020 de la Secretaría
 * de Comercio Interior. El enlace tiene que estar visible en la home y en
 * todas las páginas —está en el pie, en views/layout/footer.php— y el
 * trámite tiene que poder hacerse por el mismo medio por el que se
 * compró, sin pedir explicaciones y sin costo.
 *
 * Por eso esta página existe desde el primer día, con ruta propia en el
 * router, aunque el texto legal fino todavía no esté (PENDIENTES #10).
 * Lo que sí está es el plazo y el procedimiento, que los fija la ley y no
 * el cliente: 10 días corridos desde que se recibe el producto.
 *
 * TODO(backend): el formulario todavía no envía. Cuando se conecte, el
 * envío tiene que dejar constancia —número de trámite y copia por mail a
 * quien lo pide— porque eso es justamente lo que la resolución exige
 * poder demostrar.
 */

declare(strict_types=1);

$titulo      = 'Botón de arrepentimiento';
$descripcion = 'Arrepentite de tu compra dentro de los 10 días corridos, sin costo.';
$clase_body  = 'pagina-legal';
$estilos     = ['componentes', 'catalogo', 'legales'];

require RASTRO_VIEWS . '/layout/head.php';

$whatsapp = whatsapp_link($settings, 'Hola Rastro, quiero ejercer el botón de arrepentimiento de una compra.');
?>

<main id="contenido" tabindex="-1">
    <div class="contenedor legal">

        <?php
        $miga = [['texto' => 'Botón de arrepentimiento']];
        require RASTRO_VIEWS . '/partials/breadcrumb.php';
        ?>

        <h1 class="legal__titulo t-display-l">Botón de arrepentimiento</h1>

        <p class="legal__bajada t-body-lg">
            Si compraste a distancia podés arrepentirte dentro de los <strong>10 días
            corridos</strong> de recibido el producto. No tenés que explicar por qué y
            no tiene ningún costo para vos.
        </p>

        <div class="legal__cuerpo">

            <section class="legal__bloque">
                <h2 class="legal__seccion t-display-s">
                    <span class="legal__numero t-mono-label-sm">01</span>
                    Cómo funciona
                </h2>
                <ol class="legal__pasos">
                    <li class="t-body-md">Completás el formulario de acá abajo, o nos escribís por WhatsApp.</li>
                    <li class="t-body-md">Te confirmamos la recepción y coordinamos el retiro del producto.</li>
                    <li class="t-body-md">El producto vuelve sin uso y con su embalaje. El flete lo pagamos nosotros.</li>
                    <li class="t-body-md">Te devolvemos el importe por el mismo medio por el que pagaste.</li>
                </ol>
            </section>

            <section class="legal__bloque">
                <h2 class="legal__seccion t-display-s">
                    <span class="legal__numero t-mono-label-sm">02</span>
                    Pedir la devolución
                </h2>

                <form class="formulario formulario--legal" method="post"
                      action="<?= e(url('/arrepentimiento')) ?>">

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="pedido">Número de pedido</label>
                        <input class="campo t-mono-texto" type="text" id="pedido" name="pedido"
                               placeholder="RF-2026-0418" required>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="nombre">Nombre y apellido</label>
                        <input class="campo t-mono-texto" type="text" id="nombre" name="nombre"
                               autocomplete="name" required>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                        <input class="campo t-mono-texto" type="email" id="email" name="email"
                               autocomplete="email" required>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="detalle">
                            Comentario <span class="formulario__opcional">(opcional)</span>
                        </label>
                        <textarea class="campo campo--area t-mono-texto" id="detalle" name="detalle" rows="3"></textarea>
                        <span class="formulario__ayuda t-mono-texto-sm">
                            No hace falta que expliques por qué: la ley no te lo exige.
                        </span>
                    </p>

                    <button class="boton boton--acento formulario__enviar" type="submit">
                        <span class="t-mono-label">Enviar solicitud</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>

                    <?php
                    $nota_maqueta = 'El formulario todavía no envía. Cuando se conecte tiene que '
                                  . 'dejar constancia: número de trámite y copia por mail a quien lo '
                                  . 'pide. Eso es lo que la Resolución 424/2020 exige poder demostrar.';
                    require RASTRO_VIEWS . '/partials/nota-maqueta.php';
                    ?>
                </form>
            </section>

            <?php if ($whatsapp !== null): ?>
                <section class="legal__bloque">
                    <h2 class="legal__seccion t-display-s">
                        <span class="legal__numero t-mono-label-sm">03</span>
                        Por WhatsApp
                    </h2>
                    <p class="t-body-md">
                        También podés hacerlo por el mismo medio por el que compraste.
                    </p>
                    <a class="boton boton--fantasma legal__cta" href="<?= e($whatsapp) ?>"
                       rel="noopener" target="_blank">
                        <span class="t-mono-label">Escribinos por WhatsApp</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                </section>
            <?php endif; ?>
        </div>

        <p class="legal__pie t-mono-texto-sm">
            <?= e($settings['razon_social'] ?? '') ?> · CUIT <?= e($settings['cuit'] ?? '') ?> ·
            <?= e($settings['email'] ?? '') ?>. Si algo no se resuelve, podés reclamar en
            <a class="legal__enlace" href="https://autogestion.produccion.gob.ar/consumidores"
               rel="noopener" target="_blank">Defensa de las y los consumidores</a>.
        </p>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
