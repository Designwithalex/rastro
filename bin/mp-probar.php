<?php
/**
 * bin/mp-probar.php — comprobación de la integración con Mercado Pago.
 *
 *   php bin/mp-probar.php
 *
 * Contesta, en veinte segundos, las cuatro preguntas que uno se hace cuando
 * el checkout no anda y no se sabe por dónde empezar:
 *
 *   1. ¿Este PHP puede hablar con la API? (cURL o streams, TLS, salida a red)
 *   2. ¿El access token sirve y de qué cuenta es?
 *   3. ¿Se puede crear una preferencia con los datos reales del catálogo?
 *   4. ¿La validación de la firma del webhook está bien implementada?
 *
 * Sirve para probar en la máquina de cada dev y —sobre todo— por SSH en
 * Hostinger, que es donde aparecen los problemas que en local no se ven:
 * un PHP sin cURL, un firewall de salida, un certificado viejo.
 *
 * NO CREA NADA QUE COBRE. Una preferencia es una intención de cobro: se
 * puede crear, mirar y abandonar. No mueve un peso. Aun así, el script se
 * planta si detecta credenciales de producción, porque una preferencia real
 * queda registrada en la cuenta del cliente y ensucia sus reportes.
 *
 * No se despliega: bin/ está excluido en .github/workflows/deploy.yml.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre desde la consola.\n");
}

$raiz = dirname(__DIR__);

/* Se levanta el mismo entorno que index.php, en el mismo orden. Si algo se
   rompe acá, se rompe igual en el sitio. */
$config = is_file($raiz . '/app/config.php')
    ? (array) require $raiz . '/app/config.php'
    : [];

require $raiz . '/app/helpers.php';
require $raiz . '/app/repository.php';
require $raiz . '/app/mercadopago.php';
require $raiz . '/app/checkout.php';

// checkout_url_absoluta() la usa para armar las back_urls.
define('RASTRO_BASE', '');

mp_config($config);

/* --- Salida -------------------------------------------------------------- */

$colores = stream_isatty(STDOUT);

$pintar = static function (string $texto, string $color) use ($colores): string {
    if (!$colores) {
        return $texto;
    }

    $codigos = ['verde' => '32', 'rojo' => '31', 'gris' => '90', 'ambar' => '33'];

    return "\033[" . ($codigos[$color] ?? '0') . "m" . $texto . "\033[0m";
};

$bien  = static fn (string $t) => print($pintar('  ok   ', 'verde') . $t . "\n");
$mal   = static fn (string $t) => print($pintar('  MAL  ', 'rojo') . $t . "\n");
$nota  = static fn (string $t) => print($pintar('       ' . $t, 'gris') . "\n");
$aviso = static fn (string $t) => print($pintar('  ojo  ', 'ambar') . $t . "\n");

$titulo = static function (string $t) use ($pintar): void {
    echo "\n" . $pintar('── ' . $t . ' ' . str_repeat('─', max(0, 60 - strlen($t))), 'gris') . "\n";
};

$fallas = 0;

echo "\nRastro Fitness · comprobación de Mercado Pago\n";

/* ==========================================================================
   1. El entorno
   ========================================================================== */

$titulo('El entorno');

if (function_exists('curl_init')) {
    $bien('cURL disponible.');
} elseif (ini_get('allow_url_fopen')) {
    $aviso('Sin cURL. Se usa el transporte por streams, que funciona igual.');
    $nota('En Hostinger cURL está: si este mensaje sale en el servidor, revisar el PHP elegido en hPanel.');
} else {
    $mal('Sin cURL y sin allow_url_fopen: este PHP no puede llamar a la API.');
    $fallas++;
}

$config_mp = mp_config();

/* Las pruebas contra la API necesitan credenciales; la de la firma no. Si no
   hay token se saltean las primeras y se corre igual la última, que es la que
   verifica la pieza de seguridad de la integración. Así el script sirve en una
   máquina recién clonada, antes de tener ninguna credencial. */
$con_red = true;

if ($config_mp['access_token'] === '') {
    $aviso('No hay access token: se saltean las pruebas contra la API.');
    $nota('Copiar app/config.example.php a app/config.php y completar mp_access_token,');
    $nota('o exportar MP_ACCESS_TOKEN en el entorno. Ver docs/MERCADOPAGO.md.');
    $con_red = false;
} elseif ($config_mp['modo'] === 'produccion') {
    /* Una preferencia no cobra, pero queda registrada en la cuenta del
       cliente. Contra la cuenta real este script no corre. */
    $aviso('El config declara modo produccion: no se toca la cuenta real.');
    $nota('Para probar, poner mp_modo => "test" con las credenciales de prueba.');
    $con_red = false;
} else {
    $bien('Access token cargado (modo declarado: ' . $config_mp['modo'] . ').');
}

if ($config_mp['webhook_secret'] === '') {
    $aviso('Sin mp_webhook_secret: el webhook va a rechazar TODA notificación con 401.');
    $nota('Se saca de Tus integraciones -> Webhooks -> Revelar clave secreta.');
} else {
    $bien('Clave secreta del webhook cargada.');
}

if ($config_mp['base_url'] === '') {
    $aviso('Sin base_url: las back_urls se van a armar desde $_SERVER, que en CLI no existe.');
} else {
    $bien('base_url: ' . $config_mp['base_url']);

    $host = (string) parse_url($config_mp['base_url'], PHP_URL_HOST);

    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        $mal('Mercado Pago rechaza localhost y las IP: back_urls necesita un dominio con DNS.');
        $fallas++;
    }
}

/* ==========================================================================
   2. El token
   ========================================================================== */

if ($con_red) {
    $titulo('El access token');

    /* /users/me es el endpoint más barato para saber si un token sirve: no
       crea nada y devuelve de qué cuenta y de qué país es. */
    $yo = mp_http('GET', '/users/me');

    if (!$yo['ok']) {
        $mal('La API rechazó el token: ' . $yo['error']);
        $nota('401 = token mal copiado o de otra aplicación. 403 = falta el permiso en la aplicación.');
        $fallas++;
        $con_red = false;
    } else {
        $bien('Token válido. Cuenta ' . (string) ($yo['datos']['id'] ?? '?')
            . ' · ' . (string) ($yo['datos']['nickname'] ?? '?')
            . ' · sitio ' . (string) ($yo['datos']['site_id'] ?? '?'));

        if ((string) ($yo['datos']['site_id'] ?? '') !== 'MLA') {
            $aviso('La cuenta no es de Argentina (MLA). Los precios en ARS pueden ser rechazados.');
        }
    }
}

/* ==========================================================================
   3. Una preferencia con datos reales
   ========================================================================== */

if ($con_red) {
$titulo('Crear una preferencia');

$productos = repo_products(['en_stock' => true], 1, 2)['items'];

if ($productos === []) {
    $mal('El catálogo no devolvió productos con stock: no hay con qué armar la prueba.');
    echo "\n";
    exit(1);
}

/* Se arma el pedido por el mismo camino que el checkout de verdad —las
   mismas funciones, en el mismo orden— para que esta prueba diga algo sobre
   el sitio y no sobre sí misma. */
$carrito = checkout_lineas(
    array_map(static fn (array $p): array => ['id' => $p['id'], 'cantidad' => 1], $productos),
    'mercado_pago'
);

$settings = repo_settings();
$totales  = checkout_totales($carrito['lineas'], 'mercado_pago', $settings);

$pedido = checkout_armar_pedido($carrito['lineas'], $totales, [
    'nombre' => 'Comprador', 'apellido' => 'De Prueba',
    'email' => 'test_user_prueba@testuser.com', 'telefono' => '1155555555',
    'documento' => '12345678', 'calle' => 'Av. Siempreviva 742',
    'localidad' => 'CABA', 'provincia' => 'CABA',
    'codigo_postal' => '1425', 'notas' => '',
], 'mercado_pago');

// El código se pone a mano: este pedido NO se guarda en data/pedidos.json.
// Es una prueba de la API, no una compra, y no tiene por qué ensuciar el
// archivo de pedidos.
$pedido['codigo']     = 'RF-PRUEBA-' . date('His');
$pedido['referencia'] = $pedido['codigo'];

echo '       ' . count($carrito['lineas']) . ' línea(s), total '
    . moneda($totales['total']) . "\n";

$respuesta = mp_crear_preferencia(checkout_preferencia_desde_pedido($pedido));

if (!$respuesta['ok']) {
    $mal('No se pudo crear la preferencia: ' . $respuesta['error']);
    $nota('"invalid back_urls" casi siempre es base_url apuntando a localhost.');
    $nota('"auto_return invalid" es back_urls.success vacía.');
    $fallas++;
} else {
    $bien('Preferencia creada: ' . (string) ($respuesta['datos']['id'] ?? '?'));

    $destino = mp_url_de_pago($respuesta['datos']);

    if ($destino === null) {
        $mal('La respuesta no trae init_point ni sandbox_init_point.');
        $fallas++;
    } else {
        $bien('Abrir en el navegador para pagar con una tarjeta de prueba:');
        echo "\n       " . $destino . "\n\n";
    }

    $nota('notification_url: ' . (string) ($respuesta['datos']['notification_url'] ?? '(vacía)'));
    $nota('back_urls.success: ' . (string) ($respuesta['datos']['back_urls']['success'] ?? '(vacía)'));
}
}

/* ==========================================================================
   4. La firma del webhook
   ========================================================================== */

$titulo('Validación de la firma del webhook');

/* Se firma un manifiesto con una clave conocida y se comprueba que
   mp_firma_valida() lo acepte, y que rechace las cuatro formas en que una
   notificación puede venir mal. Es la única parte de la integración que se
   puede probar entera sin red. */
$secreto    = 'clave-de-prueba-para-el-test';
$ahora      = (string) time();
$data_id    = '123456789';
$request_id = 'abc-def-123';
$manifiesto = 'id:' . $data_id . ';request-id:' . $request_id . ';ts:' . $ahora . ';';
$firma      = hash_hmac('sha256', $manifiesto, $secreto);

$casos = [
    'firma correcta se acepta' => [
        'firma'    => 'ts=' . $ahora . ',v1=' . $firma,
        'request'  => $request_id,
        'id'       => $data_id,
        'esperado' => true,
    ],
    'firma alterada se rechaza' => [
        'firma'    => 'ts=' . $ahora . ',v1=' . str_repeat('0', 64),
        'request'  => $request_id,
        'id'       => $data_id,
        'esperado' => false,
    ],
    'otro request-id se rechaza' => [
        'firma'    => 'ts=' . $ahora . ',v1=' . $firma,
        'request'  => 'otro-request-id',
        'id'       => $data_id,
        'esperado' => false,
    ],
    'otro id de pago se rechaza' => [
        'firma'    => 'ts=' . $ahora . ',v1=' . $firma,
        'request'  => $request_id,
        'id'       => '987654321',
        'esperado' => false,
    ],
    'firma vieja se rechaza (replay)' => [
        'firma'    => 'ts=' . (string) (time() - 4000) . ',v1=' . hash_hmac(
            'sha256',
            'id:' . $data_id . ';request-id:' . $request_id . ';ts:' . (string) (time() - 4000) . ';',
            $secreto
        ),
        'request'  => $request_id,
        'id'       => $data_id,
        'esperado' => false,
    ],
    'cabecera vacía se rechaza' => [
        'firma'    => '',
        'request'  => $request_id,
        'id'       => $data_id,
        'esperado' => false,
    ],
];

foreach ($casos as $nombre => $caso) {
    $resultado = mp_firma_valida($caso['firma'], $caso['request'], $caso['id'], $secreto);

    if ($resultado === $caso['esperado']) {
        $bien($nombre);
    } else {
        $mal($nombre . ' — dio ' . var_export($resultado, true));
        $fallas++;
    }
}

/* ==========================================================================
   Cierre
   ========================================================================== */

$titulo('Resultado');

if ($fallas === 0) {
    echo $pintar("  Todo en orden.\n", 'verde');
    $nota('Siguiente paso: abrir el init_point de arriba y pagar con la tarjeta de prueba');
    $nota('5031 7557 3453 0604 · 11/30 · 123 · titular APRO · DNI 12345678.');
    $nota('Después revisar data/mp-eventos.log y data/pedidos.json.');
    echo "\n";
    exit(0);
}

echo $pintar('  ' . $fallas . " comprobación(es) fallaron.\n", 'rojo');
$nota('El detalle de cada error y qué lo causa está en docs/MERCADOPAGO.md.');
echo "\n";
exit(1);
