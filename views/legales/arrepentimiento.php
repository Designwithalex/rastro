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
 * EL FORMULARIO ENVÍA, y deja constancia de tres formas, porque la
 * resolución no pide sólo recibir el arrepentimiento: pide poder
 * DEMOSTRAR que se recibió y cuándo.
 *
 *   1. Se guarda en data/arrepentimientos.json con número de trámite.
 *      Ésa es la constancia.
 *   2. Se le avisa a Rastro por mail.
 *   3. Se le manda copia a quien lo pide, con su número.
 *
 * Los dos mails pueden fallar —un hosting compartido, un dominio sin
 * configurar— y por eso el orden importa: primero se guarda. Si el mail
 * no sale, el trámite existe igual, la persona ve su número en pantalla y
 * la página le dice que lo anote.
 */

declare(strict_types=1);

$titulo      = 'Botón de arrepentimiento';
$descripcion = 'Arrepentite de tu compra dentro de los 10 días corridos, sin costo.';
$clase_body  = 'pagina-legal';
$estilos     = ['componentes', 'catalogo', 'legales'];

/* El token se acuña antes de abrir el documento: acuñarlo crea la sesión y
   crear la sesión manda una cabecera. */
$csrf = sesion_csrf();

$enviado   = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$errores   = [];
$tramite   = null;
$aviso_mail = true;

$campos = ['pedido' => '', 'nombre' => '', 'email' => '', 'detalle' => ''];

if ($enviado) {
    foreach ($campos as $k => $_) {
        $campos[$k] = trim((string) ($_POST[$k] ?? ''));
    }

    if (!sesion_csrf_valido()) {
        $errores['general'] = 'El formulario venció. Volvé a enviarlo.';
    }

    if ($campos['nombre'] === '') {
        $errores['nombre'] = 'Necesitamos tu nombre para identificar la compra.';
    }

    if ($campos['email'] === '' || !filter_var($campos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Escribí un correo válido: ahí te mandamos la constancia.';
    }

    /* El número de pedido NO es obligatorio, y es deliberado. La ley no
       condiciona el arrepentimiento a que la persona encuentre un código:
       exigirlo sería poner una traba a un derecho. Si no lo tiene, Rastro
       lo busca por nombre y correo. */

    if ($errores === []) {
        $tramite = repo_save_arrepentimiento([
            'pedido'  => $campos['pedido'],
            'nombre'  => $campos['nombre'],
            'email'   => $campos['email'],
            'detalle' => $campos['detalle'],
            'origen'  => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);

        if ($tramite === null) {
            $errores['general'] = 'No pudimos registrar la solicitud. Escribinos por WhatsApp y la tomamos igual.';
        } else {
            $resumen = sprintf(
                "Arrepentimiento %s

Fecha:   %s
Nombre:  %s
Correo:  %s
Pedido:  %s

Comentario:
%s
",
                $tramite['codigo'],
                date('d/m/Y H:i'),
                $campos['nombre'],
                $campos['email'],
                $campos['pedido'] !== '' ? $campos['pedido'] : '(no lo indicó)',
                $campos['detalle'] !== '' ? $campos['detalle'] : '(sin comentario)'
            );

            $a_rastro = correo_enviar(
                (string) ($settings['email'] ?? ''),
                'Arrepentimiento ' . $tramite['codigo'],
                $resumen . "
Hay 10 días corridos para resolverlo (Resolución 424/2020).
",
                $campos['email']
            );

            $a_cliente = correo_enviar(
                $campos['email'],
                'Tu solicitud de arrepentimiento ' . $tramite['codigo'],
                sprintf(
                    "Hola %s,

Recibimos tu solicitud de arrepentimiento.
"
                    . "Número de trámite: %s
Fecha: %s

"
                    . "Guardá este número. Te vamos a escribir para coordinar el retiro "
                    . "del producto, que no tiene costo para vos.

Rastro Fitness
%s
",
                    $campos['nombre'],
                    $tramite['codigo'],
                    date('d/m/Y H:i'),
                    (string) config('base_url', '')
                ),
                (string) ($settings['email'] ?? '')
            );

            /* Si alguno de los dos no salió, la pantalla lo dice. Callarlo
               dejaría a la persona creyendo que le va a llegar un mail que
               nunca va a ver. */
            $aviso_mail = $a_rastro && $a_cliente;
        }
    }
}

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

                <?php /* ============================================================
                         Ya enviado: se muestra el número y NO el formulario.
                         Dejar el formulario abajo invita a mandarlo de nuevo
                         y a duplicar un trámite que ya quedó registrado.
                         ============================================================ */ ?>
                <?php if ($tramite !== null): ?>

                    <div class="legal__constancia" role="status">
                        <p class="t-body-lg"><strong>Listo. Recibimos tu solicitud.</strong></p>

                        <p class="legal__tramite t-mono-dato">
                            <span class="t-mono-label-sm">Número de trámite</span>
                            <strong><?= e($tramite['codigo']) ?></strong>
                        </p>

                        <p class="t-body-md">
                            Anotá ese número. Te vamos a escribir a
                            <strong><?= e($campos['email']) ?></strong> para coordinar el retiro
                            del producto, que no tiene costo para vos.
                        </p>

                        <?php /* Si el mail no salió hay que decirlo. Callarlo deja a la
                                 persona esperando una confirmación que no va a llegar, y
                                 creyendo que el trámite no quedó hecho. Quedó hecho. */ ?>
                        <?php if (!$aviso_mail): ?>
                            <p class="mensaje mensaje--error t-mono-texto" role="alert">
                                <span class="mensaje__marca" aria-hidden="true">!</span>
                                Tu solicitud quedó registrada con ese número, pero no pudimos
                                enviarte la copia por correo. Guardá el número y, si podés,
                                escribinos por WhatsApp para confirmarlo.
                            </p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                <?php if (isset($errores['general'])): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        <?= e($errores['general']) ?>
                    </p>
                <?php endif; ?>

                <form class="formulario formulario--legal" method="post"
                      action="<?= e(url('/arrepentimiento')) ?>">

                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="pedido">
                            Número de pedido <span class="formulario__opcional">(si lo tenés)</span>
                        </label>
                        <input class="campo t-mono-texto" type="text" id="pedido" name="pedido"
                               placeholder="RF-2026-4F7A" value="<?= e($campos['pedido']) ?>">
                        <span class="formulario__ayuda t-mono-texto-sm">
                            Si no lo encontrás, no importa: lo buscamos por tu nombre y correo.
                        </span>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="nombre">Nombre y apellido</label>
                        <input class="campo t-mono-texto<?= isset($errores['nombre']) ? ' es-error' : '' ?>"
                               type="text" id="nombre" name="nombre"
                               autocomplete="name" required value="<?= e($campos['nombre']) ?>">
                        <?php if (isset($errores['nombre'])): ?>
                            <span class="formulario__error t-mono-texto-sm"><?= e($errores['nombre']) ?></span>
                        <?php endif; ?>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                        <input class="campo t-mono-texto<?= isset($errores['email']) ? ' es-error' : '' ?>"
                               type="email" id="email" name="email"
                               autocomplete="email" required value="<?= e($campos['email']) ?>">
                        <?php if (isset($errores['email'])): ?>
                            <span class="formulario__error t-mono-texto-sm"><?= e($errores['email']) ?></span>
                        <?php endif; ?>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="detalle">
                            Comentario <span class="formulario__opcional">(opcional)</span>
                        </label>
                        <textarea class="campo campo--area t-mono-texto" id="detalle" name="detalle" rows="3"><?= e($campos['detalle']) ?></textarea>
                        <span class="formulario__ayuda t-mono-texto-sm">
                            No hace falta que expliques por qué: la ley no te lo exige.
                        </span>
                    </p>

                    <button class="boton boton--acento formulario__enviar" type="submit">
                        <span class="t-mono-label">Enviar solicitud</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>

                </form>

                <?php endif; ?>
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
