<?php
/**
 * ============================================================================
 * mercadopago.php — cliente de la API de Mercado Pago.
 * ============================================================================
 *
 * QUÉ HACE Y QUÉ NO
 *
 * Este archivo habla HTTP con api.mercadopago.com y nada más. No sabe qué es
 * un pedido de Rastro, no lee el carrito, no toca el repository y no imprime
 * una sola etiqueta HTML. Recibe arrays ya armados y devuelve arrays.
 *
 * La traducción "carrito de Rastro -> preferencia de Mercado Pago" vive en
 * app/checkout.php. La separación importa para el handoff: el día que el
 * backend dev cambie los mocks por MySQL, este archivo no se toca.
 *
 * POR QUÉ NO USAMOS EL SDK OFICIAL (mercadopago/dx-php)
 *
 * El SDK se instala con Composer y arrastra PSR-7, PSR-18 y un autoloader.
 * El proyecto no tiene build step ni vendor/ (CLAUDE.md §3) y el deploy es un
 * rsync por FTP a un hosting compartido. Meter Composer para tres endpoints
 * —crear preferencia, leer pago, leer merchant order— es cambiar la forma del
 * proyecto por comodidad de tipado.
 *
 * A cambio, acá se paga el precio de escribir el transporte a mano. Está
 * acotado y probado: mp_http() son 60 líneas y bin/mp-probar.php las ejercita.
 *
 * Si el backend dev prefiere el SDK, el reemplazo es limpio: mantiene las
 * firmas de mp_crear_preferencia() / mp_obtener_pago() y cambia el cuerpo.
 *
 * DOS TRANSPORTES A PROPÓSITO
 *
 * Se usa cURL si está; si no, streams con allow_url_fopen. Hostinger trae
 * cURL, pero varios PHP de escritorio no (el de esta máquina, sin ir más
 * lejos), y un checkout que no se puede probar en local no se prueba.
 *
 * SEGURIDAD
 *
 *   · El access token NUNCA sale de app/config.php, que está en .gitignore
 *     y no se sincroniza por FTP. No se imprime, no se loguea, no viaja al
 *     navegador. Lo único público es la public key, y hoy ni eso se usa:
 *     Checkout Pro redirige, no embebe.
 *   · Toda notificación entrante se valida con HMAC-SHA256 antes de mirarle
 *     el cuerpo (mp_firma_valida()).
 *   · El estado de un pago NUNCA se toma de la URL de retorno. Se pregunta
 *     por API. Ver el comentario largo en views/checkout/retorno.php.
 *
 * REFERENCIAS
 *   https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/overview
 *   https://www.mercadopago.com.ar/developers/es/reference/preferences/_checkout_preferences/post
 *   https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/payment-notifications
 */

declare(strict_types=1);

/** Base de la API. Es la misma para prueba y para producción: lo que cambia
 *  es el access token, no la URL. No existe un api-sandbox.mercadopago.com. */
const MP_API = 'https://api.mercadopago.com';

/** Cuánto esperamos una respuesta de la API, en segundos. Corto a propósito:
 *  el visitante está mirando una pantalla en blanco mientras tanto. */
const MP_TIMEOUT = 12;

/** Ventana de tolerancia para el `ts` de una notificación, en segundos.
 *  Una firma válida pero vieja es un replay: se rechaza. */
const MP_VENTANA_FIRMA = 300;

/* ==========================================================================
   Configuración
   ========================================================================== */

/**
 * Configuración de Mercado Pago, ya normalizada.
 *
 * Sale de app/config.php (las claves `mp_*`) y, si ahí no está, de variables
 * de entorno. El fallback a entorno existe para dos casos concretos: correr
 * bin/mp-probar.php sin escribir un config, y un día poder mover las
 * credenciales a las variables de entorno de hPanel sin tocar código.
 *
 * @param array|null $override Sólo para tests y para el script de CLI.
 *
 * @return array{
 *     modo:string, access_token:string, public_key:string,
 *     webhook_secret:string, base_url:string, notification_url:string
 * }
 */
function mp_config(?array $override = null): array
{
    static $cache = null;

    if ($override !== null) {
        return $cache = _mp_normalizar_config($override);
    }

    if ($cache !== null) {
        return $cache;
    }

    /* De dónde sale la configuración, en orden:

       1. RASTRO_CONFIG, la constante que define index.php y que lee el
          helper config(). Es la forma correcta y la que usa el resto del
          proyecto, porque una constante sí la ven las funciones.
       2. $GLOBALS['config'], para bin/mp-probar.php y para cualquier script
          que cargue estos archivos sueltos sin pasar por el front controller.

       El array vacío del final no es un error: sin credenciales el sitio
       levanta igual y el checkout muestra solamente el camino por
       transferencia. */
    $config = defined('RASTRO_CONFIG')
        ? RASTRO_CONFIG
        : ($GLOBALS['config'] ?? []);

    return $cache = _mp_normalizar_config(is_array($config) ? $config : []);
}

/**
 * Arma la configuración final resolviendo, clave por clave, config.php
 * primero y entorno después.
 */
function _mp_normalizar_config(array $config): array
{
    $leer = static function (string $clave, string $env, string $defecto = '') use ($config): string {
        $valor = $config[$clave] ?? '';

        if (!is_string($valor) || trim($valor) === '') {
            $valor = (string) (getenv($env) ?: '');
        }

        $valor = trim($valor);

        return $valor !== '' ? $valor : $defecto;
    };

    $modo = strtolower($leer('mp_modo', 'MP_MODO', 'test'));

    return [
        // 'test' o 'produccion'. Sólo se usa para avisar en pantalla en qué
        // ambiente está el checkout; la API no distingue, distingue el token.
        'modo'             => $modo === 'produccion' ? 'produccion' : 'test',
        'access_token'     => $leer('mp_access_token', 'MP_ACCESS_TOKEN'),
        'public_key'       => $leer('mp_public_key', 'MP_PUBLIC_KEY'),
        'webhook_secret'   => $leer('mp_webhook_secret', 'MP_WEBHOOK_SECRET'),
        'base_url'         => rtrim($leer('base_url', 'RASTRO_BASE_URL'), '/'),
        // Si el sitio se prueba detrás de un túnel (ngrok, cloudflared), la
        // URL pública del webhook no coincide con base_url. Por eso es aparte.
        'notification_url' => $leer('mp_notification_url', 'MP_NOTIFICATION_URL'),
    ];
}

/**
 * ¿Hay credenciales cargadas? Si no, el checkout no ofrece Mercado Pago:
 * muestra el camino por transferencia y lo dice, en vez de mandar al
 * visitante a una pantalla de error de un tercero.
 */
function mp_activo(): bool
{
    return mp_config()['access_token'] !== '';
}

/**
 * ¿Estamos en el ambiente de prueba? La vista lo usa para pintar el cartel
 * de "ambiente de prueba, no se cobra nada".
 */
function mp_es_prueba(): bool
{
    return mp_config()['modo'] !== 'produccion';
}

/* ==========================================================================
   Transporte
   ========================================================================== */

/**
 * Una llamada a la API.
 *
 * Nunca lanza excepciones y nunca devuelve null: siempre el mismo sobre, con
 * `ok` en false y un `error` legible cuando algo falla. Un checkout que
 * explota con un fatal deja al visitante sin página y sin explicación.
 *
 * @param string     $metodo Método HTTP.
 * @param string     $ruta   Ruta desde la raíz de la API: /checkout/preferences
 * @param array|null $cuerpo Se serializa a JSON. null = sin cuerpo.
 * @param array      $extras Cabeceras extra, ['X-Idempotency-Key' => '...'].
 *
 * @return array{ok:bool, estado:int, datos:array, error:?string}
 */
function mp_http(string $metodo, string $ruta, ?array $cuerpo = null, array $extras = []): array
{
    $config = mp_config();

    if ($config['access_token'] === '') {
        return _mp_falla(0, 'Falta mp_access_token en app/config.php.');
    }

    $metodo = strtoupper($metodo);
    $url    = MP_API . $ruta;
    $json   = $cuerpo === null
        ? null
        : json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($cuerpo !== null && $json === false) {
        return _mp_falla(0, 'No se pudo serializar el cuerpo del pedido a JSON.');
    }

    $cabeceras = [
        'Authorization: Bearer ' . $config['access_token'],
        'Accept: application/json',
        'User-Agent: RastroFitness/1.0 (+PHP ' . PHP_VERSION . ')',
    ];

    if ($json !== null) {
        $cabeceras[] = 'Content-Type: application/json';
    }

    foreach ($extras as $nombre => $valor) {
        $cabeceras[] = $nombre . ': ' . $valor;
    }

    $respuesta = function_exists('curl_init')
        ? _mp_http_curl($metodo, $url, $json, $cabeceras)
        : _mp_http_stream($metodo, $url, $json, $cabeceras);

    if ($respuesta['error'] !== null) {
        return _mp_falla((int) $respuesta['estado'], (string) $respuesta['error']);
    }

    $datos = json_decode((string) $respuesta['crudo'], true);
    $datos = is_array($datos) ? $datos : [];

    if ($respuesta['estado'] < 200 || $respuesta['estado'] >= 300) {
        return [
            'ok'     => false,
            'estado' => $respuesta['estado'],
            'datos'  => $datos,
            'error'  => _mp_mensaje_error($datos, $respuesta['estado']),
        ];
    }

    return ['ok' => true, 'estado' => $respuesta['estado'], 'datos' => $datos, 'error' => null];
}

/**
 * Transporte con cURL. Es el camino normal en Hostinger.
 *
 * @return array{estado:int, crudo:string, error:?string}
 */
function _mp_http_curl(string $metodo, string $url, ?string $json, array $cabeceras): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => MP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $crudo  = curl_exec($ch);
    $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $fallo  = curl_error($ch);

    curl_close($ch);

    if ($crudo === false) {
        return ['estado' => 0, 'crudo' => '', 'error' => 'No se pudo conectar con Mercado Pago: ' . $fallo];
    }

    return ['estado' => $estado, 'crudo' => (string) $crudo, 'error' => null];
}

/**
 * Transporte con streams, para cuando la extensión cURL no está compilada.
 *
 * `ignore_errors` es lo que hace que un 400 devuelva el cuerpo en vez de
 * false: sin eso perdemos el mensaje de error de la API, que es lo único
 * que dice qué campo de la preferencia está mal.
 *
 * @return array{estado:int, crudo:string, error:?string}
 */
function _mp_http_stream(string $metodo, string $url, ?string $json, array $cabeceras): array
{
    if (!ini_get('allow_url_fopen')) {
        return [
            'estado' => 0,
            'crudo'  => '',
            'error'  => 'Este PHP no tiene cURL ni allow_url_fopen: no puede hablar con Mercado Pago.',
        ];
    }

    $contexto = stream_context_create([
        'http' => [
            'method'        => $metodo,
            'header'        => implode("\r\n", $cabeceras),
            'content'       => $json ?? '',
            'timeout'       => MP_TIMEOUT,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $crudo = @file_get_contents($url, false, $contexto);

    // $http_response_header lo inyecta PHP en el ámbito local. Es feo, y es
    // la única forma de leer el código de estado con este transporte.
    $estado    = 0;
    $cabeceras = $http_response_header ?? [];

    foreach ($cabeceras as $linea) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $linea, $m)) {
            $estado = (int) $m[1];
        }
    }

    if ($crudo === false) {
        return ['estado' => $estado, 'crudo' => '', 'error' => 'No se pudo conectar con Mercado Pago.'];
    }

    return ['estado' => $estado, 'crudo' => (string) $crudo, 'error' => null];
}

/** Sobre de error, con la misma forma que una respuesta buena. */
function _mp_falla(int $estado, string $mensaje): array
{
    return ['ok' => false, 'estado' => $estado, 'datos' => [], 'error' => $mensaje];
}

/**
 * Saca el mensaje útil de un error de la API.
 *
 * Mercado Pago devuelve el detalle en `cause[]`, y ahí está lo que sirve
 * ("invalid back_urls.success"). El `message` de arriba suele ser genérico.
 */
function _mp_mensaje_error(array $datos, int $estado): string
{
    $partes = [];

    foreach ((array) ($datos['cause'] ?? []) as $causa) {
        if (is_array($causa)) {
            $partes[] = trim((string) ($causa['description'] ?? $causa['message'] ?? ''));
        } elseif (is_string($causa)) {
            $partes[] = $causa;
        }
    }

    $partes = array_values(array_filter($partes));

    if ($partes === []) {
        $mensaje = trim((string) ($datos['message'] ?? $datos['error'] ?? ''));
        $partes  = $mensaje !== '' ? [$mensaje] : ['Mercado Pago respondió ' . $estado . ' sin detalle.'];
    }

    return implode(' · ', $partes);
}

/* ==========================================================================
   Endpoints
   ========================================================================== */

/**
 * Crea una preferencia de pago y devuelve el sobre de mp_http().
 *
 * Una "preferencia" es la descripción de lo que se va a cobrar. No es un
 * pago: es el objeto que Mercado Pago usa para dibujar su checkout. De la
 * respuesta importan tres campos:
 *
 *   · `id`                 — para reconciliar después
 *   · `init_point`         — la URL a la que se manda al visitante
 *   · `sandbox_init_point` — la equivalente en el ambiente de prueba
 *
 * Con un access token de prueba, `init_point` ya apunta al ambiente de
 * prueba, así que en la práctica alcanza con `init_point`. Igual se
 * devuelven las dos y elige mp_url_de_pago().
 *
 * @param array $pedido Lo que arma checkout_preferencia_desde_pedido().
 */
function mp_crear_preferencia(array $pedido): array
{
    $items = [];

    foreach ((array) ($pedido['items'] ?? []) as $item) {
        $items[] = array_filter([
            'id'          => (string) ($item['id'] ?? ''),
            'title'       => (string) ($item['titulo'] ?? ''),
            'description' => (string) ($item['descripcion'] ?? ''),
            'picture_url' => (string) ($item['imagen'] ?? ''),
            'category_id' => (string) ($item['categoria'] ?? 'sports'),
            'quantity'    => (int) ($item['cantidad'] ?? 1),
            'currency_id' => 'ARS',
            // La API pide un número, no un string. Los precios del catálogo
            // son enteros en pesos, así que el float es exacto.
            'unit_price'  => (float) ($item['precio_unitario'] ?? 0),
        ], static fn ($v) => $v !== '' && $v !== null);
    }

    $comprador = (array) ($pedido['comprador'] ?? []);

    $preferencia = [
        'items'              => $items,
        'external_reference' => (string) ($pedido['referencia'] ?? ''),
        'notification_url'   => (string) ($pedido['urls']['notificacion'] ?? ''),
        'back_urls'          => [
            'success' => (string) ($pedido['urls']['exito'] ?? ''),
            'pending' => (string) ($pedido['urls']['pendiente'] ?? ''),
            'failure' => (string) ($pedido['urls']['error'] ?? ''),
        ],
        // Con 'approved' Mercado Pago vuelve solo al sitio cuando el pago se
        // aprueba, sin obligar a apretar un botón. Es el único valor que la
        // API acepta hoy.
        'auto_return'          => 'approved',
        // Lo que el comprador ve en el resumen de su tarjeta. Sin espacios ni
        // acentos: algunos emisores lo truncan feo.
        'statement_descriptor' => 'RASTROFITNESS',
        // false = un pago puede quedar 'in_process' (revisión manual). Es lo
        // correcto para un ecommerce: con binary_mode en true esos casos se
        // rechazan y se pierden ventas buenas.
        'binary_mode'          => false,
        'metadata'             => [
            'pedido'  => (string) ($pedido['codigo'] ?? ''),
            'origen'  => 'web',
            'entorno' => mp_config()['modo'],
        ],
    ];

    if ($comprador !== []) {
        $payer = array_filter([
            'name'    => (string) ($comprador['nombre'] ?? ''),
            'surname' => (string) ($comprador['apellido'] ?? ''),
            'email'   => (string) ($comprador['email'] ?? ''),
            'phone'   => array_filter([
                'area_code' => (string) ($comprador['area'] ?? ''),
                'number'    => (string) ($comprador['telefono'] ?? ''),
            ]),
            'identification' => array_filter([
                'type'   => (string) ($comprador['tipo_documento'] ?? ''),
                'number' => (string) ($comprador['documento'] ?? ''),
            ]),
        ], static fn ($v) => $v !== '' && $v !== []);

        if ($payer !== []) {
            $preferencia['payer'] = $payer;
        }
    }

    // El envío va como `shipments.cost` y no como un item más: si fuera un
    // item, Mercado Pago lo listaría como un producto y el comprador vería
    // "Envío" mezclado con los discos. Hoy el costo es 0 (PENDIENTES #8) y
    // por eso la clave ni se manda.
    $envio = (int) ($pedido['envio'] ?? 0);

    if ($envio > 0) {
        $preferencia['shipments'] = ['mode' => 'not_specified', 'cost' => (float) $envio];
    }

    if (!empty($pedido['vence_en'])) {
        $preferencia['expires']              = true;
        $preferencia['expiration_date_from'] = date('c');
        $preferencia['expiration_date_to']   = date('c', (int) $pedido['vence_en']);
    }

    // La clave de idempotencia es el seguro contra el doble clic y contra el
    // reintento del navegador: dos POST con la misma clave devuelven la misma
    // preferencia en vez de crear dos. Va atada al código del pedido, que ya
    // es único.
    return mp_http('POST', '/checkout/preferences', $preferencia, [
        'X-Idempotency-Key' => 'rastro-' . (string) ($pedido['codigo'] ?? ''),
    ]);
}

/**
 * Un pago por id. Es LA fuente de verdad del estado de una compra.
 *
 * @return array{ok:bool, estado:int, datos:array, error:?string}
 */
function mp_obtener_pago(string $id): array
{
    return mp_http('GET', '/v1/payments/' . rawurlencode($id));
}

/**
 * Una merchant order por id. Agrupa todos los intentos de pago de una misma
 * preferencia; sirve cuando alguien reintenta y quedan dos pagos colgando de
 * un mismo pedido.
 */
function mp_obtener_merchant_order(string $id): array
{
    return mp_http('GET', '/merchant_orders/' . rawurlencode($id));
}

/**
 * La URL a la que hay que mandar al visitante.
 *
 * Con token de prueba las dos apuntan al ambiente de prueba; se prefiere
 * `init_point` y `sandbox_init_point` queda de respaldo por si la API deja
 * de devolver la primera en alguna cuenta vieja.
 */
function mp_url_de_pago(array $preferencia): ?string
{
    foreach (['init_point', 'sandbox_init_point'] as $clave) {
        $url = trim((string) ($preferencia[$clave] ?? ''));

        if ($url !== '' && str_starts_with($url, 'https://')) {
            return $url;
        }
    }

    return null;
}

/* ==========================================================================
   Notificaciones entrantes
   ========================================================================== */

/**
 * Valida la firma de una notificación (webhook).
 *
 * Sin esto, la URL del webhook es un endpoint público que cualquiera puede
 * golpear diciendo "el pedido RF-0001 está pagado". Es la pieza de seguridad
 * más importante de toda la integración.
 *
 * Cómo funciona, según la documentación de Mercado Pago:
 *
 *   1. La cabecera `x-signature` viene como `ts=1704908010,v1=618c85345248...`
 *   2. Se arma el manifiesto `id:{data.id};request-id:{x-request-id};ts:{ts};`
 *      Si algún valor no vino, se saca ESE tramo del manifiesto.
 *   3. Se calcula HMAC-SHA256 del manifiesto con la clave secreta que
 *      Mercado Pago genera al configurar el webhook.
 *   4. Se compara contra `v1`, en tiempo constante.
 *
 * Dos detalles que la documentación menciona al pasar y rompen la validación
 * si se ignoran:
 *   · si el `data.id` es alfanumérico va en minúsculas;
 *   · cada tramo del manifiesto termina en punto y coma, el último incluido.
 *
 * Encima de eso se agrega una ventana de tiempo: una firma legítima
 * capturada hace tres días sigue siendo válida para siempre si nadie mira el
 * `ts`. Con MP_VENTANA_FIRMA, un replay viejo se cae.
 *
 * @param string      $x_signature  Cabecera x-signature tal como llegó.
 * @param string      $x_request_id Cabecera x-request-id tal como llegó.
 * @param string      $data_id      El ?data.id= de la query string.
 * @param string|null $secreto      Sólo para tests; por defecto sale del config.
 */
function mp_firma_valida(string $x_signature, string $x_request_id, string $data_id, ?string $secreto = null): bool
{
    $secreto ??= mp_config()['webhook_secret'];

    if ($secreto === '') {
        return false;
    }

    $partes = [];

    foreach (explode(',', $x_signature) as $tramo) {
        $pedazo = explode('=', trim($tramo), 2);

        if (count($pedazo) === 2) {
            $partes[trim($pedazo[0])] = trim($pedazo[1]);
        }
    }

    $ts   = $partes['ts'] ?? '';
    $hash = $partes['v1'] ?? '';

    if ($ts === '' || $hash === '') {
        return false;
    }

    // El ts viene en segundos, pero algunas cuentas lo mandan en
    // milisegundos. Se normaliza por magnitud antes de comparar.
    $momento = (int) $ts;

    if ($momento > 100000000000) {
        $momento = (int) ($momento / 1000);
    }

    if (abs(time() - $momento) > MP_VENTANA_FIRMA) {
        return false;
    }

    // Un id alfanumérico va en minúsculas; uno numérico queda igual.
    if ($data_id !== '' && !ctype_digit($data_id)) {
        $data_id = strtolower($data_id);
    }

    $manifiesto = '';

    if ($data_id !== '') {
        $manifiesto .= 'id:' . $data_id . ';';
    }

    if ($x_request_id !== '') {
        $manifiesto .= 'request-id:' . $x_request_id . ';';
    }

    $manifiesto .= 'ts:' . $ts . ';';

    return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), $hash);
}

/**
 * Normaliza una notificación entrante a una forma sola.
 *
 * Mercado Pago manda la misma información de tres maneras distintas según la
 * antigüedad de la cuenta y el tipo de evento: en la query string vieja
 * (`topic` + `id`), en la moderna (`type` + `data.id`) y en el cuerpo JSON
 * (`type` + `data.id` + `action`). Esta función se come las tres.
 *
 * @return array{tipo:string, id:string, accion:string}
 */
function mp_leer_notificacion(array $query, array $cuerpo): array
{
    $tipo = (string) ($query['type'] ?? $query['topic'] ?? $cuerpo['type'] ?? $cuerpo['topic'] ?? '');

    // ?data.id=123 llega a PHP como $_GET['data_id']: los puntos se
    // convierten en guiones bajos. Es un clásico y cuesta una tarde encontrarlo.
    $id = (string) (
        $query['data_id']
        ?? $query['data.id']
        ?? $cuerpo['data']['id']
        ?? $query['id']
        ?? $cuerpo['id']
        ?? ''
    );

    return [
        'tipo'   => strtolower(trim($tipo)),
        'id'     => trim($id),
        'accion' => (string) ($cuerpo['action'] ?? ''),
    ];
}

/* ==========================================================================
   Traducción de estados
   ========================================================================== */

/**
 * Traduce el estado de un pago de Mercado Pago al estado de un pedido de
 * Rastro. El vocabulario de pedidos ya existía en data/orders.json y no se
 * cambia por la integración: es lo que muestran /cuenta y el panel.
 *
 * Los estados que devuelve la API son:
 *   approved, authorized, in_process, in_mediation,
 *   pending, rejected, cancelled, refunded, charged_back
 */
function mp_estado_pedido(string $estado_pago): string
{
    return match ($estado_pago) {
        'approved', 'authorized'   => 'pagado',
        'pending', 'in_process'    => 'pendiente_pago',
        'in_mediation'             => 'en_disputa',
        'rejected', 'cancelled'    => 'cancelado',
        'refunded', 'charged_back' => 'devuelto',
        default                    => 'pendiente_pago',
    };
}

/**
 * Explicación en castellano de por qué se rechazó un pago, para mostrarle al
 * visitante algo mejor que "error". Los códigos salen de `status_detail`.
 *
 * Importa el matiz: "no era el código de seguridad" se arregla reintentando,
 * "fondos insuficientes" no. Un mensaje genérico hace que la persona
 * reintente cinco veces con la misma tarjeta y se vaya.
 */
function mp_motivo_rechazo(string $detalle): string
{
    return match ($detalle) {
        'cc_rejected_insufficient_amount'      => 'La tarjeta no tiene fondos suficientes.',
        'cc_rejected_bad_filled_security_code' => 'El código de seguridad de la tarjeta no coincide.',
        'cc_rejected_bad_filled_date'          => 'La fecha de vencimiento de la tarjeta no coincide.',
        'cc_rejected_bad_filled_other',
        'cc_rejected_bad_filled_card_number'   => 'Hay un dato de la tarjeta mal cargado.',
        'cc_rejected_call_for_authorize'       => 'El banco necesita que autorices este pago. Llamalo y volvé a intentar.',
        'cc_rejected_card_disabled'            => 'La tarjeta está inhabilitada. Consultá con el banco.',
        'cc_rejected_duplicated_payment'       => 'Ya hay un pago igual hecho hace un momento. Revisá antes de reintentar.',
        'cc_rejected_high_risk'                => 'Mercado Pago no pudo aprobar el pago. Probá con otro medio.',
        'cc_rejected_max_attempts'             => 'Se alcanzó el máximo de intentos con esta tarjeta.',
        'cc_rejected_card_error'               => 'No se pudo procesar la tarjeta. Probá de nuevo o con otra.',
        'pending_contingency'                  => 'Mercado Pago está procesando el pago. Te avisamos por correo.',
        'pending_review_manual'                => 'Mercado Pago está revisando el pago. Te avisamos por correo.',
        default                                => 'El pago no se pudo completar.',
    };
}
