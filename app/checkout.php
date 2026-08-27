<?php
/**
 * ============================================================================
 * checkout.php — la traducción entre el carrito de Rastro y Mercado Pago.
 * ============================================================================
 *
 * Es la capa del medio, y existe para que no se toquen dos cosas que no se
 * tienen que tocar:
 *
 *   app/mercadopago.php   habla HTTP con la API. No sabe qué es un producto.
 *   app/repository.php    guarda y lee. No sabe qué es Mercado Pago.
 *   app/checkout.php      valida el carrito, calcula la plata y arma el pedido.
 *   views/checkout/*      imprimen. No calculan nada.
 *
 * LAS TRES REGLAS QUE MANDAN ACÁ
 *
 * 1. EL PRECIO LO PONE EL SERVIDOR, SIEMPRE.
 *    Del navegador llegan `id` y `cantidad` y nada más. Los precios se vuelven
 *    a resolver contra el repository. Si el checkout confiara en un precio que
 *    viene del cliente, cualquiera con la consola abierta compra una barra
 *    olímpica a un peso. Es el agujero clásico de los checkouts caseros y por
 *    eso está escrito acá arriba y no en un comentario perdido.
 *
 * 2. EL PRECIO PUBLICADO ES EL PRECIO DE MERCADO PAGO.
 *    CLAUDE.md §1: el número grande del sitio es lo que se paga con Mercado
 *    Pago; el descuento del X% es por transferencia o efectivo. Entonces:
 *
 *      · medio "mercado_pago"  -> se cobra el precio publicado, descuento 0.
 *      · medio "transferencia" -> se cobra el precio con descuento, y no pasa
 *        por Mercado Pago: se coordina por WhatsApp.
 *
 *    Cobrar el precio con descuento por Mercado Pago sería regalar el X% que
 *    justamente cubre la comisión de la pasarela.
 *
 * 3. EL DESCUENTO SE CALCULA EN UN SOLO LADO.
 *    En precio_con_descuento() de helpers.php (CLAUDE.md §4.3). Acá se lo
 *    llama; no se multiplica ningún porcentaje a mano.
 *
 * ESTADO DE ESTA PIEZA
 *
 * Es una integración de PRUEBA. Funciona de punta a punta con credenciales de
 * prueba, y lo que le falta para producción está listado en docs/MERCADOPAGO.md
 * §"Lo que falta para producción": sesión real, stock reservado, mail de
 * confirmación y descuento de stock. Todo eso es del backend dev.
 */

declare(strict_types=1);

/** Cuántas unidades de un mismo producto se aceptan en una línea. */
const CHECKOUT_MAX_UNIDADES = 99;

/** Cuánto vive una preferencia antes de vencer, en segundos. */
const CHECKOUT_VIGENCIA = 3600 * 24;

/* ==========================================================================
   Medios de pago
   ========================================================================== */

/**
 * Los dos caminos de pago del sitio, con su regla de precio.
 *
 * `descuenta` es lo que decide qué precio se cobra, y es la traducción
 * directa de la regla de negocio de CLAUDE.md §1. Está como dato y no como
 * un `if` desparramado para que agregar un tercer medio mañana —efectivo en
 * el local, por ejemplo— sea agregar una entrada acá.
 */
function checkout_medios(): array
{
    return [
        'mercado_pago' => [
            'nombre'    => 'Mercado Pago',
            'detalle'   => 'Tarjeta de crédito, débito o dinero en cuenta.',
            'descuenta' => false,
            'online'    => true,
        ],
        'transferencia' => [
            'nombre'    => 'Transferencia o efectivo',
            'detalle'   => 'Coordinamos por WhatsApp y te pasamos los datos.',
            'descuenta' => true,
            'online'    => false,
        ],
    ];
}

/** ¿Es un medio de pago que el sitio conoce? */
function checkout_medio_valido(string $medio): bool
{
    return isset(checkout_medios()[$medio]);
}

/* ==========================================================================
   El carrito
   ========================================================================== */

/**
 * Convierte lo que mandó el navegador en líneas de pedido con precio de
 * servidor.
 *
 * Entra `[['id' => 7, 'cantidad' => 2], ...]` —o el JSON crudo del campo
 * oculto del formulario— y sale una lista de líneas listas para cobrar, más
 * los avisos de todo lo que hubo que corregir en el camino.
 *
 * Los avisos NO son un detalle: si alguien tenía tres barras en el carrito y
 * quedaba stock para una, el checkout tiene que decirlo, no ajustar la
 * cantidad en silencio y cobrar otra cosa.
 *
 * @param mixed  $crudo Array de líneas o el string JSON del formulario.
 * @param string $medio Decide qué precio se aplica.
 *
 * @return array{lineas:array, avisos:array<string>}
 */
function checkout_lineas(mixed $crudo, string $medio): array
{
    $settings = repo_settings();
    $descuenta = checkout_medios()[$medio]['descuenta'] ?? false;

    if (is_string($crudo)) {
        $crudo = json_decode($crudo, true);
    }

    if (!is_array($crudo)) {
        return ['lineas' => [], 'avisos' => []];
    }

    /* Primero se juntan las cantidades por id. El JS ya no debería mandar el
       mismo producto dos veces, pero el formulario se puede reenviar a mano
       y dos líneas del mismo producto darían dos precios distintos. */
    $pedidas = [];

    foreach ($crudo as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id       = (int) ($item['id'] ?? 0);
        $cantidad = (int) ($item['cantidad'] ?? 0);

        if ($id <= 0 || $cantidad <= 0) {
            continue;
        }

        $pedidas[$id] = ($pedidas[$id] ?? 0) + $cantidad;
    }

    if ($pedidas === []) {
        return ['lineas' => [], 'avisos' => []];
    }

    $productos = [];
    foreach (repo_cart_items(array_keys($pedidas)) as $producto) {
        $productos[(int) ($producto['id'] ?? 0)] = $producto;
    }

    $lineas = [];
    $avisos = [];

    foreach ($pedidas as $id => $cantidad) {
        $producto = $productos[$id] ?? null;

        if ($producto === null) {
            $avisos[] = 'Sacamos un producto del carrito porque ya no está en el catálogo.';
            continue;
        }

        $nombre = (string) ($producto['nombre'] ?? 'Producto');

        // Un producto dado de baja se resuelve por id (así el carrito puede
        // avisar) pero no se puede comprar.
        if (($producto['activo'] ?? true) !== true) {
            $avisos[] = sprintf('«%s» ya no está a la venta y lo sacamos del pedido.', $nombre);
            continue;
        }

        $stock = (int) ($producto['stock'] ?? 0);

        if ($stock <= 0) {
            $avisos[] = sprintf('«%s» se quedó sin stock y lo sacamos del pedido.', $nombre);
            continue;
        }

        $cantidad = min($cantidad, CHECKOUT_MAX_UNIDADES);

        if ($cantidad > $stock) {
            $avisos[] = sprintf(
                'De «%s» quedan %d. Ajustamos la cantidad del pedido.',
                $nombre,
                $stock
            );
            $cantidad = $stock;
        }

        // Acá se decide la plata. El helper es el único que sabe multiplicar
        // por el porcentaje; este archivo sólo elige cuál de los dos números
        // usar, según el medio de pago.
        $precio    = precio_con_descuento($producto, $settings);
        $unitario  = $descuenta ? $precio['con_descuento'] : $precio['publicado'];

        if ($unitario <= 0) {
            $avisos[] = sprintf('«%s» no tiene precio cargado y lo sacamos del pedido.', $nombre);
            continue;
        }

        $lineas[] = [
            'producto_id'     => $id,
            'nombre'          => $nombre,
            'sku'             => (string) ($producto['sku'] ?? ''),
            'slug'            => (string) ($producto['slug'] ?? ''),
            'categoria'       => (string) ($producto['categoria'] ?? ''),
            'imagen'          => (string) ($producto['imagen'] ?? ''),
            'cantidad'        => $cantidad,
            'precio_unitario' => $unitario,
            // Se guardan los dos precios y el porcentaje aplicado: un pedido
            // tiene que poder explicarse solo dentro de un año, cuando el
            // precio de lista ya sea otro.
            'precio_publicado' => $precio['publicado'],
            'subtotal'         => $unitario * $cantidad,
        ];
    }

    return ['lineas' => $lineas, 'avisos' => $avisos];
}

/**
 * Los totales del pedido.
 *
 * El umbral de envío gratis se compara contra el subtotal PUBLICADO, no
 * contra el que se paga. Es la decisión que quedó tomada en PENDIENTES #48 y
 * está replicada en assets/js/carrito.js: si cambia, cambian los dos.
 *
 * @return array{
 *     unidades:int, subtotal:int, subtotal_publicado:int, envio:int,
 *     envio_gratis:bool, descuento_pct:float, ahorro:int, total:int
 * }
 */
function checkout_totales(array $lineas, string $medio, array $settings): array
{
    $descuenta = checkout_medios()[$medio]['descuenta'] ?? false;

    $unidades  = 0;
    $subtotal  = 0;
    $publicado = 0;

    foreach ($lineas as $linea) {
        $unidades  += (int) $linea['cantidad'];
        $subtotal  += (int) $linea['subtotal'];
        $publicado += (int) $linea['precio_publicado'] * (int) $linea['cantidad'];
    }

    $umbral       = (int) ($settings['envio_gratis_desde'] ?? 0);
    $envio_gratis = $umbral > 0 && $publicado >= $umbral;

    // El costo de envío es 0 hasta que el cliente pase la tabla real
    // (PENDIENTES #8). No se inventa un número: la vista dice "a coordinar".
    $envio = 0;

    return [
        'unidades'           => $unidades,
        'subtotal'           => $subtotal,
        'subtotal_publicado' => $publicado,
        'envio'              => $envio,
        'envio_gratis'       => $envio_gratis,
        'descuento_pct'      => $descuenta ? numero_decimal($settings['descuento_transferencia_pct'] ?? 0) : 0.0,
        'ahorro'             => $publicado - $subtotal,
        'total'              => $subtotal + $envio,
    ];
}

/* ==========================================================================
   Datos del comprador
   ========================================================================== */

/**
 * Valida el formulario del checkout.
 *
 * Devuelve los datos limpios y los errores por campo, con la misma forma que
 * repo_register(): así las vistas de checkout y de registro pintan los
 * errores igual.
 *
 * TODO(backend): cuando exista sesión, estos campos vienen precargados del
 * usuario logueado y sólo se piden los que falten.
 *
 * @return array{ok:bool, datos:array, errores:array<string,string>}
 */
function checkout_validar_comprador(array $post): array
{
    $limpiar = static fn (string $clave): string => trim((string) ($post[$clave] ?? ''));

    $datos = [
        'nombre'         => $limpiar('nombre'),
        'apellido'       => $limpiar('apellido'),
        'email'          => $limpiar('email'),
        'telefono'       => $limpiar('telefono'),
        'tipo_documento' => 'DNI',
        'documento'      => preg_replace('/\D+/', '', $limpiar('documento')) ?? '',
        'calle'          => $limpiar('calle'),
        'localidad'      => $limpiar('localidad'),
        'provincia'      => $limpiar('provincia'),
        'codigo_postal'  => $limpiar('codigo_postal'),
        'notas'          => mb_substr($limpiar('notas'), 0, 500),
    ];

    $errores = [];

    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'Escribí tu nombre.';
    }

    if ($datos['apellido'] === '') {
        $errores['apellido'] = 'Escribí tu apellido.';
    }

    // El mail no es opcional aunque el pago sea por transferencia: es por
    // donde viaja el comprobante, y Mercado Pago lo pide para el payer.
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisá el correo: no parece una dirección válida.';
    }

    if (strlen(preg_replace('/\D+/', '', $datos['telefono']) ?? '') < 8) {
        $errores['telefono'] = 'Dejanos un teléfono con característica, para coordinar la entrega.';
    }

    if (strlen($datos['documento']) < 7) {
        $errores['documento'] = 'El DNI tiene que tener al menos 7 números.';
    }

    if ($datos['calle'] === '') {
        $errores['calle'] = 'Escribí la calle y el número.';
    }

    if ($datos['localidad'] === '') {
        $errores['localidad'] = 'Escribí la localidad.';
    }

    if ($datos['provincia'] === '') {
        $errores['provincia'] = 'Elegí la provincia.';
    }

    return ['ok' => $errores === [], 'datos' => $datos, 'errores' => $errores];
}

/* ==========================================================================
   Armado del pedido
   ========================================================================== */

/**
 * Arma el pedido con la forma de data/orders.json más lo que agrega el pago.
 *
 * Se respeta el vocabulario que ya existía —`codigo`, `items`, `subtotal`,
 * `envio`, `descuento_aplicado_pct`, `total`— porque es lo que leen /cuenta y
 * el panel de administración. Lo nuevo va en `comprador`, `entrega` y `pago`,
 * que son bloques aparte y no rompen a nadie que ya lea un pedido viejo.
 */
function checkout_armar_pedido(array $lineas, array $totales, array $comprador, string $medio): array
{
    return [
        'codigo'      => null, // lo pone el repository
        'usuario_id'  => 0,    // TODO(backend): el id del usuario logueado
        'fecha'       => date('Y-m-d'),
        'estado'      => 'pendiente_pago',
        'medio_pago'  => $medio,
        'items'       => array_map(static fn (array $l): array => [
            'producto_id'     => $l['producto_id'],
            'nombre'          => $l['nombre'],
            'sku'             => $l['sku'],
            'cantidad'        => $l['cantidad'],
            'precio_unitario' => $l['precio_unitario'],
        ], $lineas),
        'subtotal'               => $totales['subtotal'],
        'envio'                  => $totales['envio'],
        'descuento_aplicado_pct' => $totales['descuento_pct'],
        'total'                  => $totales['total'],
        'comprador' => [
            'nombre'   => $comprador['nombre'],
            'apellido' => $comprador['apellido'],
            'email'    => $comprador['email'],
            'telefono' => $comprador['telefono'],
            'documento' => $comprador['documento'],
        ],
        'entrega' => [
            'calle'         => $comprador['calle'],
            'localidad'     => $comprador['localidad'],
            'provincia'     => $comprador['provincia'],
            'codigo_postal' => $comprador['codigo_postal'],
            'notas'         => $comprador['notas'],
        ],
        'pago' => [
            'proveedor'     => $medio === 'mercado_pago' ? 'mercado_pago' : null,
            'entorno'       => mp_config()['modo'],
            'preferencia'   => null,
            'payment_id'    => null,
            'estado'        => null,
            'detalle'       => null,
            'monto'         => null,
            'actualizado'   => null,
        ],
    ];
}

/* ==========================================================================
   URLs
   ========================================================================== */

/**
 * URL absoluta de una ruta del sitio, para mandársela a Mercado Pago.
 *
 * url() de helpers.php devuelve rutas relativas al dominio, que sirven para
 * un href pero no para `back_urls`: Mercado Pago necesita el esquema y el
 * host, y rechaza localhost y 127.0.0.1 (necesita un dominio con DNS).
 *
 * Se prefiere `base_url` de app/config.php sobre lo que diga $_SERVER, porque
 * detrás del proxy de un hosting compartido el Host puede llegar cambiado.
 */
function checkout_url_absoluta(string $ruta): string
{
    $base = mp_config()['base_url'];

    if ($base === '') {
        $esquema = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
                ? 'https'
                : 'http';

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $esquema . '://' . $host . RASTRO_BASE;
    }

    return rtrim($base, '/') . '/' . ltrim($ruta, '/');
}

/**
 * Las cuatro URLs que viajan en la preferencia.
 *
 * Las tres de retorno son la MISMA página con un parámetro distinto: lo que
 * cambia es el título y el mensaje, no la lógica, porque igual hay que ir a
 * preguntarle a la API en qué estado quedó el pago.
 *
 * `notificacion` sale de mp_notification_url si está cargada. Ese rodeo
 * existe para probar con un túnel (ngrok, cloudflared) apuntando a una
 * máquina de escritorio, donde la URL pública no es la del sitio.
 */
function checkout_urls(string $codigo): array
{
    $config  = mp_config();
    $retorno = '/checkout/retorno?pedido=' . rawurlencode($codigo) . '&estado=';

    $notificacion = $config['notification_url'] !== ''
        ? $config['notification_url']
        : checkout_url_absoluta('/webhooks/mercadopago');

    return [
        'exito'        => checkout_url_absoluta($retorno . 'exito'),
        'pendiente'    => checkout_url_absoluta($retorno . 'pendiente'),
        'error'        => checkout_url_absoluta($retorno . 'error'),
        'notificacion' => $notificacion,
    ];
}

/**
 * Traduce un pedido guardado a la estructura que espera mp_crear_preferencia().
 */
function checkout_preferencia_desde_pedido(array $pedido): array
{
    $items = [];

    foreach ((array) $pedido['items'] as $item) {
        $items[] = [
            'id'              => (string) $item['sku'],
            'titulo'          => (string) $item['nombre'],
            'descripcion'     => 'Código ' . (string) $item['sku'],
            'categoria'       => 'sports',
            'cantidad'        => (int) $item['cantidad'],
            'precio_unitario' => (int) $item['precio_unitario'],
        ];
    }

    $comprador = (array) ($pedido['comprador'] ?? []);

    return [
        'codigo'     => (string) $pedido['codigo'],
        'referencia' => (string) ($pedido['referencia'] ?? $pedido['codigo']),
        'items'      => $items,
        'envio'      => (int) ($pedido['envio'] ?? 0),
        'comprador'  => [
            'nombre'         => (string) ($comprador['nombre'] ?? ''),
            'apellido'       => (string) ($comprador['apellido'] ?? ''),
            'email'          => (string) ($comprador['email'] ?? ''),
            'telefono'       => preg_replace('/\D+/', '', (string) ($comprador['telefono'] ?? '')) ?? '',
            'tipo_documento' => 'DNI',
            'documento'      => (string) ($comprador['documento'] ?? ''),
        ],
        'urls'     => checkout_urls((string) $pedido['codigo']),
        'vence_en' => time() + CHECKOUT_VIGENCIA,
    ];
}

/* ==========================================================================
   Sincronización con Mercado Pago
   ========================================================================== */

/**
 * Le pregunta a la API en qué estado quedó un pago y actualiza el pedido.
 *
 * La usan los dos caminos de vuelta —el webhook y la página de retorno— y por
 * eso está acá y no adentro de una vista: son dos entradas al mismo trabajo,
 * llegan casi juntas, y la que llegue segunda tiene que encontrar todo hecho
 * y no romper nada.
 *
 * Es idempotente: correrla diez veces con el mismo payment_id deja el pedido
 * igual que correrla una. Eso es un requisito, no una virtud: Mercado Pago
 * reintenta una notificación hasta ocho veces si no le contestás 200 rápido.
 *
 * VALIDACIÓN CRUZADA
 *
 * No alcanza con que el pago exista y esté aprobado. Se comprueba que su
 * `external_reference` sea la del pedido que se está tocando; si no, se
 * descarta. Sin esa comprobación, alguien que conozca el id de OTRO pago
 * aprobado puede pegarlo en la URL de retorno y hacer figurar su pedido como
 * pagado.
 *
 * @return array{ok:bool, pedido:?array, pago:?array, error:?string}
 */
function checkout_sincronizar_pago(array $pedido, string $payment_id): array
{
    $codigo = (string) ($pedido['codigo'] ?? '');

    if ($payment_id === '' || $codigo === '') {
        return ['ok' => false, 'pedido' => $pedido, 'pago' => null, 'error' => 'Faltan datos para consultar el pago.'];
    }

    $respuesta = mp_obtener_pago($payment_id);

    if (!$respuesta['ok']) {
        repo_log_pago('pago.consulta_fallida', [
            'pedido' => $codigo,
            'pago'   => $payment_id,
            'error'  => $respuesta['error'],
        ]);

        return ['ok' => false, 'pedido' => $pedido, 'pago' => null, 'error' => $respuesta['error']];
    }

    $pago      = $respuesta['datos'];
    $esperada  = (string) ($pedido['referencia'] ?? $codigo);
    $declarada = (string) ($pago['external_reference'] ?? '');

    if ($declarada !== $esperada) {
        repo_log_pago('pago.referencia_no_coincide', [
            'pedido'    => $codigo,
            'pago'      => $payment_id,
            'esperada'  => $esperada,
            'declarada' => $declarada,
        ]);

        return [
            'ok'     => false,
            'pedido' => $pedido,
            'pago'   => null,
            'error'  => 'El pago no corresponde a este pedido.',
        ];
    }

    $estado_pago = (string) ($pago['status'] ?? '');
    $detalle     = (string) ($pago['status_detail'] ?? '');

    $actualizado = repo_order_update($codigo, [
        'estado' => mp_estado_pedido($estado_pago),
        'pago'   => [
            'proveedor'   => 'mercado_pago',
            'payment_id'  => (string) ($pago['id'] ?? $payment_id),
            'estado'      => $estado_pago,
            'detalle'     => $detalle,
            'metodo'      => (string) ($pago['payment_method_id'] ?? ''),
            'tipo'        => (string) ($pago['payment_type_id'] ?? ''),
            'cuotas'      => (int) ($pago['installments'] ?? 0),
            'monto'       => (float) ($pago['transaction_amount'] ?? 0),
            'actualizado' => date('c'),
        ],
    ]);

    repo_log_pago('pago.sincronizado', [
        'pedido' => $codigo,
        'pago'   => (string) ($pago['id'] ?? $payment_id),
        'estado' => $estado_pago,
    ]);

    return [
        'ok'     => $actualizado !== null,
        'pedido' => $actualizado ?? $pedido,
        'pago'   => $pago,
        'error'  => $actualizado === null ? 'No se pudo guardar el estado del pago.' : null,
    ];
}

/**
 * Defensa mínima contra el envío del formulario desde otro sitio.
 *
 * No es un token CSRF: para eso hace falta sesión, y el proyecto todavía no
 * la tiene (docs/HANDOFF.md lo pone primero en la lista). Mientras tanto se
 * comprueba que el Origin —o el Referer, para navegadores que no mandan el
 * primero— sea el propio sitio. Frena el caso simple: un formulario en otra
 * página apuntando a nuestro /checkout/pagar.
 *
 * TODO(backend): reemplazar por token CSRF en cuanto exista $_SESSION.
 */
function checkout_mismo_origen(): bool
{
    $propio = parse_url(checkout_url_absoluta('/'), PHP_URL_HOST);

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $cabecera) {
        $valor = (string) ($_SERVER[$cabecera] ?? '');

        if ($valor === '') {
            continue;
        }

        return parse_url($valor, PHP_URL_HOST) === $propio;
    }

    // Sin Origin ni Referer no se puede decidir. Se deja pasar para no romper
    // navegadores con la política de referrer al máximo; la validación de
    // precios del servidor es la que sostiene la seguridad del monto.
    return true;
}
