<?php
/**
 * checkout/index.php — los datos de la compra y la salida hacia el pago.
 *
 * Es el puente entre el carrito, que vive en el navegador, y Mercado Pago,
 * que vive del otro lado de una API.
 *
 * POR QUÉ EL FORMULARIO SE MANDA A SÍ MISMO
 *
 * Igual que /ingresar. Si el POST fuera a otra ruta, un error de validación
 * obligaría a redirigir y se perdería todo lo que la persona escribió, o a
 * guardarlo en sesión, que todavía no existe en el proyecto. Mandándolo acá,
 * los errores se pintan al lado del campo con los valores puestos.
 *
 * QUÉ SE MANDA Y QUÉ NO
 *
 * Del navegador salen `id` y `cantidad` de cada línea, y NADA MÁS. Los
 * precios los vuelve a resolver checkout_lineas() contra el repository. El
 * campo oculto `items` es un dato del cliente y se trata como tal: puede
 * venir vacío, mentido o roto, y el peor caso posible es que el pedido salga
 * con menos líneas de las esperadas. Nunca con otro precio.
 *
 * EL RESUMEN LO DIBUJA EL JAVASCRIPT
 *
 * Por lo mismo que en /carrito: el servidor no sabe qué hay en el carrito de
 * quien entra. Se imprime el índice del catálogo con los dos precios ya
 * calculados por PHP y el JS multiplica y suma. Ningún porcentaje se calcula
 * en el navegador (CLAUDE.md §4.3).
 *
 * TODO(backend):
 *   · Token CSRF en cuanto exista sesión. Hoy hay una comprobación de origen
 *     en checkout_mismo_origen(), que frena el caso simple y nada más.
 *   · Reservar stock al crear el pedido y descontarlo cuando el pago se
 *     aprueba. Hoy el stock se lee para validar y no se toca.
 */

declare(strict_types=1);

$titulo      = 'Finalizar compra';
$descripcion = 'Completá tus datos y elegí cómo querés pagar.';
$clase_body  = 'pagina-checkout';
$estilos     = ['componentes', 'catalogo', 'carrito', 'cuenta', 'checkout'];
$scripts     = ['checkout'];

$settings = repo_settings();
$medios   = checkout_medios();

/* --- Estado de la página -------------------------------------------------
   Todo esto se define antes del POST para que el marcado de más abajo no
   tenga que preguntar si existe. */
$enviado  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$errores  = [];
$avisos   = [];
$falla    = null;
$medio    = 'mercado_pago';
$items_crudos = '';

$datos = [
    'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => '',
    'documento' => '', 'calle' => '', 'localidad' => '', 'provincia' => '',
    'codigo_postal' => '', 'notas' => '',
];

/* Con la sesión abierta, el formulario arranca con los datos de la cuenta.
   Sólo en el GET: si el POST volvió con errores, se muestra lo que la
   persona escribió, aunque sea distinto de lo guardado. */
$usuario = sesion_usuario();

if ($usuario !== null && !$enviado) {
    $datos = checkout_datos_de_usuario($usuario) + $datos;
}

/* El pago online sólo se ofrece si hay credenciales cargadas. Sin token, el
   botón llevaría a una pantalla de error de Mercado Pago y la persona se
   quedaría sin entender qué pasó; es mejor mostrar el camino que sí funciona. */
$mp_disponible = mp_activo();

if (!$mp_disponible) {
    $medio = 'transferencia';
}

/* ==========================================================================
   POST — el pedido
   ========================================================================== */

if ($enviado) {
    $items_crudos = (string) ($_POST['items'] ?? '');
    $medio        = (string) ($_POST['medio'] ?? 'mercado_pago');

    if (!checkout_medio_valido($medio) || ($medio === 'mercado_pago' && !$mp_disponible)) {
        $medio = $mp_disponible ? 'mercado_pago' : 'transferencia';
    }

    $validacion = checkout_validar_comprador($_POST);
    $datos      = $validacion['datos'];
    $errores    = $validacion['errores'];

    $carrito = checkout_lineas($items_crudos, $medio);
    $avisos  = $carrito['avisos'];
    $lineas  = $carrito['lineas'];

    if (!checkout_mismo_origen()) {
        // No se dice "CSRF": no aporta nada a quien lo lee y a quien lo
        // intenta tampoco. Se corta y listo.
        $falla = 'No pudimos verificar de dónde vino el formulario. Volvé a intentar desde el carrito.';
    } elseif ($lineas === []) {
        $falla = 'No quedó nada para comprar en el carrito. Revisá el catálogo y volvé a intentar.';
    } elseif ($errores === []) {
        $totales = checkout_totales($lineas, $medio, $settings);
        $pedido  = checkout_armar_pedido($lineas, $totales, $datos, $medio, (int) ($usuario['id'] ?? 0));

        $alta = repo_order_create($pedido);

        if (!$alta['ok']) {
            // Si el pedido no se pudo guardar, NO se manda a nadie a pagar:
            // el pago volvería con una referencia que no existe del lado de
            // Rastro y no habría forma de saber qué se compró.
            $falla = 'No pudimos registrar el pedido. Probá de nuevo en un minuto o escribinos por WhatsApp.';
            repo_log_pago('pedido.alta_fallida', ['error' => $alta['error']]);
        } else {
            $pedido = $alta['pedido'];

            /* Quien acaba de hacer el pedido puede verlo sin cuenta y sin
               volver a escribir el correo: al volver de Mercado Pago, el
               botón "Ver mi pedido" abre directo. Va antes de cualquier
               redirección porque escribe una cookie. */
            sesion_desbloquear_pedido((string) $pedido['codigo']);

            if ($medio === 'transferencia') {
                // La transferencia no pasa por ninguna pasarela: el pedido
                // queda anotado y se sigue por WhatsApp.
                repo_order_update((string) $pedido['codigo'], ['estado' => 'pendiente_transferencia']);

                header('Location: ' . url('/checkout/retorno?pedido=' . rawurlencode((string) $pedido['codigo']) . '&estado=transferencia'), true, 303);
                exit;
            }

            $respuesta = mp_crear_preferencia(checkout_preferencia_desde_pedido($pedido));

            if (!$respuesta['ok']) {
                $falla = 'Mercado Pago no pudo abrir el pago en este momento. Probá de nuevo o coordiná por transferencia.';

                repo_log_pago('preferencia.fallida', [
                    'pedido' => $pedido['codigo'],
                    'estado' => $respuesta['estado'],
                    'error'  => $respuesta['error'],
                ]);
            } else {
                $destino = mp_url_de_pago($respuesta['datos']);

                if ($destino === null) {
                    $falla = 'Mercado Pago respondió sin la dirección de pago. Probá de nuevo en un minuto.';
                    repo_log_pago('preferencia.sin_init_point', ['pedido' => $pedido['codigo']]);
                } else {
                    repo_order_update((string) $pedido['codigo'], [
                        'pago' => [
                            'proveedor'   => 'mercado_pago',
                            'preferencia' => (string) ($respuesta['datos']['id'] ?? ''),
                            'entorno'     => mp_config()['modo'],
                            'actualizado' => date('c'),
                        ],
                    ]);

                    repo_log_pago('preferencia.creada', [
                        'pedido'      => $pedido['codigo'],
                        'preferencia' => (string) ($respuesta['datos']['id'] ?? ''),
                    ]);

                    /* 303 y no 302: después de un POST, el 303 obliga al
                       navegador a pedir el destino con GET. Con 302 algunos
                       clientes reenvían el POST al dominio de Mercado Pago. */
                    header('Location: ' . $destino, true, 303);
                    exit;
                }
            }
        }
    }
}

/* --- Índice de precios para el resumen -----------------------------------
   Mismo mecanismo que /carrito: los dos precios de cada producto ya
   resueltos por PHP, en un atributo data-, porque el CSP no permite
   <script> en línea. */
$indice = [];

foreach (repo_products([], 1, 9999)['items'] as $p) {
    $precio = precio_con_descuento($p, $settings);

    $indice[(string) $p['id']] = [
        'nombre'        => $p['nombre'] ?? '',
        'sku'           => $p['sku'] ?? '',
        'imagen'        => !empty($p['imagen']) ? asset((string) $p['imagen']) : null,
        'url'           => url('/producto/' . ($p['slug'] ?? '')),
        'publicado'     => $precio['publicado'],
        'transferencia' => $precio['con_descuento'],
    ];
}

$config_checkout = [
    'envioGratisDesde' => (int) ($settings['envio_gratis_desde'] ?? 0),
    'descuentoPct'     => descuento_global($settings),
    'urlCarrito'       => url('/carrito'),
];

$whatsapp = whatsapp_link($settings, 'Hola Rastro, quiero coordinar el pago de un pedido por transferencia.');

$provincias = provincias();

/** Imprime el mensaje de error de un campo, si lo hay. */
$error_de = static function (string $campo) use ($errores): string {
    return isset($errores[$campo])
        ? '<span class="campo__error t-mono-texto-sm" id="error-' . e($campo) . '">'
        . '<span class="mensaje__marca" aria-hidden="true">!</span> ' . e($errores[$campo]) . '</span>'
        : '';
};

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1"
      data-checkout
      data-catalogo="<?= e_json($indice) ?>"
      data-config="<?= e_json($config_checkout) ?>">

    <div class="contenedor barra-pagina barra-pagina--carrito">
        <div class="barra-pagina__titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <div class="barra-pagina__linea">
                <h1 class="barra-pagina__nombre t-display-l">Finalizar compra</h1>
                <p class="barra-pagina__conteo t-mono-texto" data-checkout-conteo></p>
            </div>
        </div>
    </div>

    <?php if (mp_es_prueba() && $mp_disponible): ?>
        <?php /* El cartel de ambiente de prueba no es decorativo: sin él, en
                 la primera demo al cliente alguien va a poner una tarjeta de
                 verdad. Va arriba de todo y con las tarjetas de prueba a mano. */ ?>
        <div class="contenedor">
            <p class="mensaje mensaje--aviso t-mono-texto" role="status">
                <span class="mensaje__marca" aria-hidden="true">⚙</span>
                <strong>Ambiente de prueba.</strong>
                No se cobra nada. Usá la tarjeta de prueba
                <span class="t-mono-dato">5031 7557 3453 0604</span>, vencimiento 11/30,
                código 123, titular <span class="t-mono-dato">APRO</span> y DNI
                <span class="t-mono-dato">12345678</span>.
                Las demás tarjetas y los nombres para forzar cada resultado están en
                <span class="t-mono-dato">docs/MERCADOPAGO.md</span>.
            </p>
        </div>
    <?php endif; ?>

    <noscript>
        <div class="contenedor">
            <div class="vacio">
                <p class="vacio__indice t-mono-label">Hace falta JavaScript</p>
                <h2 class="t-display-m">No podemos abrir el checkout</h2>
                <p class="vacio__texto t-body-md">
                    Tu carrito se guarda en este navegador y necesita JavaScript para
                    leerse. Escribinos por WhatsApp con los códigos que te interesan y
                    lo cerramos por ahí.
                </p>
                <?php if ($whatsapp !== null): ?>
                    <div class="vacio__acciones">
                        <a class="boton boton--acento" href="<?= e($whatsapp) ?>" rel="noopener" target="_blank">
                            <span class="t-mono-label">Escribirnos por WhatsApp</span>
                            <span class="boton__flecha" aria-hidden="true">→</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </noscript>

    <?php /* Carrito vacío. Como en /carrito, arranca oculto y lo muestra el JS:
             al revés, un checkout con productos parpadearía en vacío. */ ?>
    <div class="contenedor" data-checkout-vacio hidden>
        <div class="vacio">
            <p class="vacio__indice t-mono-label">Carrito vacío</p>
            <h2 class="t-display-m">No hay nada para pagar</h2>
            <p class="vacio__texto t-body-md">
                Cargá algo en el carrito y volvé para completar la compra.
            </p>
            <div class="vacio__acciones">
                <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                    <span class="t-mono-label">Ver el catálogo</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </div>

    <div class="checkout reticula" data-checkout-cuerpo hidden>

        <section class="checkout__datos" aria-label="Datos de la compra">

            <?php if ($falla !== null): ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    <?= e($falla) ?>
                </p>
            <?php endif; ?>

            <?php foreach ($avisos as $aviso): ?>
                <?php /* Un aviso es un cambio que el servidor le hizo al pedido:
                         algo se agotó o se dio de baja mientras estaba en el
                         carrito. Se dice antes de cobrar, nunca después. */ ?>
                <p class="mensaje mensaje--aviso t-mono-texto" role="status">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    <?= e($aviso) ?>
                </p>
            <?php endforeach; ?>

            <?php if ($errores !== []): ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    Revisá los campos marcados y volvé a intentar.
                </p>
            <?php endif; ?>

            <form class="formulario formulario--checkout" method="post"
                  action="<?= e(url('/checkout')) ?>" novalidate>

                <?php /* Las líneas del carrito viajan acá, en JSON. Lo llena el
                         JavaScript al cargar la página; si el POST vuelve con
                         errores, el servidor lo devuelve tal como llegó para no
                         depender de que localStorage siga igual. */ ?>
                <input type="hidden" name="items" value="<?= e($items_crudos) ?>" data-checkout-items>

                <fieldset class="formulario__grupo">
                    <legend class="formulario__leyenda t-mono-label">
                        <span class="indice">02</span> Tus datos
                    </legend>

                    <?php /* Con sesión, se dice con qué cuenta se compra: el
                             pedido va a quedar en sus pedidos. Sin sesión, se
                             ofrece entrar, que completa todo esto solo. */ ?>
                    <?php if ($usuario !== null): ?>
                        <p class="checkout__cuenta t-mono-texto-sm">
                            Comprás con tu cuenta. El pedido va a quedar en
                            <a href="<?= e(url('/cuenta')) ?>">Mis pedidos</a>.
                        </p>
                    <?php else: ?>
                        <p class="checkout__cuenta t-mono-texto-sm">
                            ¿Ya tenés cuenta?
                            <a href="<?= e(url('/ingresar') . '?volver=' . rawurlencode('/checkout')) ?>">Ingresá</a>
                            y completamos tus datos.
                        </p>
                    <?php endif; ?>

                    <div class="formulario__fila">
                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="nombre">Nombre</label>
                            <input class="campo t-mono-texto" type="text" id="nombre" name="nombre"
                                   autocomplete="given-name" value="<?= e($datos['nombre']) ?>"
                                   <?= isset($errores['nombre']) ? 'aria-invalid="true" aria-describedby="error-nombre"' : '' ?>>
                            <?= $error_de('nombre') ?>
                        </p>

                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="apellido">Apellido</label>
                            <input class="campo t-mono-texto" type="text" id="apellido" name="apellido"
                                   autocomplete="family-name" value="<?= e($datos['apellido']) ?>"
                                   <?= isset($errores['apellido']) ? 'aria-invalid="true" aria-describedby="error-apellido"' : '' ?>>
                            <?= $error_de('apellido') ?>
                        </p>
                    </div>

                    <div class="formulario__fila">
                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                            <input class="campo t-mono-texto" type="email" id="email" name="email"
                                   autocomplete="email" value="<?= e($datos['email']) ?>"
                                   <?= isset($errores['email']) ? 'aria-invalid="true" aria-describedby="error-email"' : '' ?>>
                            <?= $error_de('email') ?>
                            <span class="campo__ayuda t-mono-texto-sm">Ahí te mandamos el comprobante.</span>
                        </p>

                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="telefono">Teléfono</label>
                            <input class="campo t-mono-texto" type="tel" id="telefono" name="telefono"
                                   autocomplete="tel" placeholder="11 5555 5555"
                                   value="<?= e($datos['telefono']) ?>"
                                   <?= isset($errores['telefono']) ? 'aria-invalid="true" aria-describedby="error-telefono"' : '' ?>>
                            <?= $error_de('telefono') ?>
                        </p>
                    </div>

                    <p class="formulario__campo formulario__campo--corto">
                        <label class="formulario__etiqueta t-mono-label-sm" for="documento">DNI</label>
                        <input class="campo t-mono-texto" type="text" id="documento" name="documento"
                               inputmode="numeric" value="<?= e($datos['documento']) ?>"
                               <?= isset($errores['documento']) ? 'aria-invalid="true" aria-describedby="error-documento"' : '' ?>>
                        <?= $error_de('documento') ?>
                        <span class="campo__ayuda t-mono-texto-sm">Lo pide Mercado Pago para emitir el pago.</span>
                    </p>
                </fieldset>

                <fieldset class="formulario__grupo">
                    <legend class="formulario__leyenda t-mono-label">
                        <span class="indice">03</span> Entrega
                    </legend>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="calle">Calle y número</label>
                        <input class="campo t-mono-texto" type="text" id="calle" name="calle"
                               autocomplete="street-address" value="<?= e($datos['calle']) ?>"
                               <?= isset($errores['calle']) ? 'aria-invalid="true" aria-describedby="error-calle"' : '' ?>>
                        <?= $error_de('calle') ?>
                    </p>

                    <div class="formulario__fila">
                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="localidad">Localidad</label>
                            <input class="campo t-mono-texto" type="text" id="localidad" name="localidad"
                                   autocomplete="address-level2" value="<?= e($datos['localidad']) ?>"
                                   <?= isset($errores['localidad']) ? 'aria-invalid="true" aria-describedby="error-localidad"' : '' ?>>
                            <?= $error_de('localidad') ?>
                        </p>

                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="provincia">Provincia</label>
                            <select class="campo t-mono-texto" id="provincia" name="provincia"
                                    autocomplete="address-level1"
                                    <?= isset($errores['provincia']) ? 'aria-invalid="true" aria-describedby="error-provincia"' : '' ?>>
                                <option value="">Elegí una</option>
                                <?php foreach ($provincias as $p): ?>
                                    <option value="<?= e($p) ?>" <?= $datos['provincia'] === $p ? 'selected' : '' ?>>
                                        <?= e($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $error_de('provincia') ?>
                        </p>

                        <p class="formulario__campo formulario__campo--corto">
                            <label class="formulario__etiqueta t-mono-label-sm" for="codigo_postal">Código postal</label>
                            <input class="campo t-mono-texto" type="text" id="codigo_postal" name="codigo_postal"
                                   autocomplete="postal-code" inputmode="numeric"
                                   value="<?= e($datos['codigo_postal']) ?>">
                        </p>
                    </div>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="notas">
                            Aclaraciones para la entrega <span class="campo__opcional">(opcional)</span>
                        </label>
                        <textarea class="campo campo--area t-mono-texto" id="notas" name="notas"
                                  rows="3" maxlength="500"><?= e($datos['notas']) ?></textarea>
                        <span class="campo__ayuda t-mono-texto-sm">
                            Piso, timbre, horarios, o si hay que subir escalera: los discos pesan.
                        </span>
                    </p>
                </fieldset>

                <fieldset class="formulario__grupo">
                    <legend class="formulario__leyenda t-mono-label">
                        <span class="indice">04</span> Cómo pagás
                    </legend>

                    <?php if (!$mp_disponible): ?>
                        <p class="mensaje mensaje--aviso t-mono-texto" role="status">
                            <span class="mensaje__marca" aria-hidden="true">!</span>
                            El pago online todavía no está habilitado en este servidor.
                            Se puede cerrar la compra por transferencia.
                        </p>
                    <?php endif; ?>

                    <div class="medios">
                        <?php foreach ($medios as $clave => $m): ?>
                            <?php $bloqueado = $clave === 'mercado_pago' && !$mp_disponible; ?>
                            <label class="medio <?= $bloqueado ? 'medio--bloqueado' : '' ?>">
                                <input type="radio" name="medio" value="<?= e($clave) ?>"
                                       data-checkout-medio
                                       <?= $medio === $clave ? 'checked' : '' ?>
                                       <?= $bloqueado ? 'disabled' : '' ?>>
                                <span class="medio__cuerpo">
                                    <span class="medio__nombre t-mono-label"><?= e($m['nombre']) ?></span>
                                    <span class="medio__detalle t-body-sm"><?= e($m['detalle']) ?></span>
                                    <?php if ($m['descuenta']): ?>
                                        <span class="badge badge--descuento t-mono-label-sm">
                                            <?= e(descuento_global($settings)) ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p class="formulario__nota t-mono-texto-sm">
                        El precio publicado es el precio pagando con Mercado Pago.
                        Por transferencia o efectivo se descuenta
                        <?= e(descuento_global($settings)) ?>%.
                    </p>
                </fieldset>

                <button class="boton boton--acento checkout__enviar" type="submit" data-checkout-enviar>
                    <span class="t-mono-label" data-checkout-enviar-texto>Ir a pagar</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>

                <p class="checkout__legal t-mono-texto-sm">
                    Al continuar aceptás los
                    <a href="<?= e(url('/terminos')) ?>">términos y condiciones</a>.
                    El envío se coordina después de confirmar el pedido.
                </p>
            </form>
        </section>

        <aside class="resumen resumen--checkout" aria-labelledby="resumen-titulo">
            <h2 class="resumen__titulo t-display-m" id="resumen-titulo">Tu pedido</h2>

            <ul class="resumen__items" data-checkout-lineas></ul>

            <dl class="resumen__filas">
                <div class="resumen__fila">
                    <dt class="t-mono-texto" data-checkout-resumen-productos>Productos</dt>
                    <dd class="t-mono-texto" data-checkout-resumen-subtotal></dd>
                </div>
                <div class="resumen__fila">
                    <dt class="t-mono-texto">Envío</dt>
                    <dd class="t-mono-texto" data-checkout-resumen-envio></dd>
                </div>
                <div class="resumen__fila" data-checkout-fila-ahorro hidden>
                    <dt class="t-mono-texto">Descuento transferencia</dt>
                    <dd class="t-mono-texto resumen__ahorro" data-checkout-resumen-ahorro></dd>
                </div>
            </dl>

            <div class="resumen__total">
                <p class="resumen__publicado">
                    <span class="visualmente-oculto">Total a pagar:</span>
                    <span class="plata t-precio-lg" data-checkout-total></span>
                </p>
                <p class="resumen__nota t-mono-texto-sm" data-checkout-nota></p>
            </div>

            <a class="lineas__seguir t-mono-label" href="<?= e(url('/carrito')) ?>">
                <span aria-hidden="true">←</span> Volver al carrito
            </a>
        </aside>
    </div>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
