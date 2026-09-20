<?php
/**
 * bin/verificar-pedidos.php — el alta de pedidos, incluida la carrera.
 *
 *   php bin/verificar-pedidos.php
 *
 * Prueba tres cosas sobre pedidos del checkout, que son los que llevan
 * plata y los que no pasan por `_repo_json()`:
 *
 *   1. Alta y relectura: lo que se guarda vuelve igual, con sus items y
 *      sus tres bloques anidados (comprador, entrega, pago).
 *   2. Actualización: el webhook puede pisar `pago.estado` sin borrar lo
 *      que escribió el checkout, y sin tocar los items.
 *   3. LA CARRERA. Dos compras en el mismo instante tienen que entrar las
 *      dos. Es el motivo por el que el catálogo pasó a MySQL.
 *
 * Sobre la carrera: el ciclo viejo era leer la lista entera, agregarle uno
 * y reescribirla. Dos personas comprando a la vez leen la MISMA lista, y
 * la que escribe segunda pisa a la primera. Acá se reproduce ese
 * intercalado a propósito —las dos leen antes de que escriba ninguna— y se
 * comprueba que con INSERT las dos sobreviven.
 *
 * ESCRIBE EN LA BASE: crea pedidos de prueba con código RF-TEST-* y los
 * borra al terminar, pase lo que pase. Nada queda dado de alta.
 *
 * No se despliega: bin/ está excluido del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

require $raiz . '/app/helpers.php';
require $raiz . '/app/db.php';
require $raiz . '/app/repository-mysql.php';
require $raiz . '/app/repository.php';

if (!db_activa()) {
    exit("✗ No hay base configurada en app/config.php.\n");
}

$fallas = 0;
$creados = [];

/** Borra los pedidos de prueba, corra bien o corra mal. */
function limpiar(array $codigos): void
{
    foreach ($codigos as $c) {
        try {
            // pedido_items se va solo: la llave foránea es ON DELETE CASCADE.
            db_q('DELETE FROM pedidos WHERE codigo = ?', [$c]);
        } catch (Throwable $e) {
            echo "  (no se pudo limpiar $c: " . $e->getMessage() . ")\n";
        }
    }
}

register_shutdown_function(static function () use (&$creados): void {
    limpiar($creados);
});

/** Un pedido con la forma exacta que arma checkout_armar_pedido(). */
function pedido_de_prueba(string $codigo, int $total): array
{
    return [
        'codigo'     => $codigo,
        'usuario_id' => 0,
        'fecha'      => date('Y-m-d'),
        'estado'     => 'pendiente_pago',
        'medio_pago' => 'mercado_pago',
        'items'      => [[
            'producto_id'     => 1,
            'nombre'          => 'Disco bumper Greencore 10 kg',
            'sku'             => 'RS-DB-010',
            'cantidad'        => 2,
            'precio_unitario' => 74900,
        ]],
        'subtotal'               => 149800,
        'envio'                  => 0,
        'descuento_aplicado_pct' => 0,
        'total'                  => $total,
        'comprador' => [
            'nombre' => 'Prueba', 'apellido' => 'Automática',
            'email' => 'prueba@example.com', 'telefono' => '+54 9 351 000 0000',
            'documento' => '00000000',
        ],
        'entrega' => [
            'calle' => 'Av. Colón 1234', 'localidad' => 'Córdoba',
            'provincia' => 'Córdoba', 'codigo_postal' => '5000', 'notas' => '',
        ],
        'pago' => [
            'proveedor' => 'mercado_pago', 'entorno' => 'test',
            'preferencia' => null, 'payment_id' => null, 'estado' => null,
            'detalle' => null, 'monto' => null, 'actualizado' => null,
        ],
    ];
}

/* --- 1. Alta y relectura ------------------------------------------- */

echo "1. Alta de un pedido y relectura\n";

$uno = pedido_de_prueba('RF-TEST-AAAA', 149800);
$alta = repo_order_create($uno);
$creados[] = 'RF-TEST-AAAA';

if (!$alta['ok']) {
    echo "   ✗ no se pudo crear: " . ($alta['error'] ?? '?') . "\n";
    $fallas++;
} else {
    $leido = repo_order_local('RF-TEST-AAAA');

    $checks = [
        'existe'              => $leido !== null,
        'total'               => ($leido['total'] ?? null) === 149800,
        'items'               => count($leido['items'] ?? []) === 1,
        'item.precio'         => ($leido['items'][0]['precio_unitario'] ?? null) === 74900,
        'item.cantidad'       => ($leido['items'][0]['cantidad'] ?? null) === 2,
        'comprador.email'     => ($leido['comprador']['email'] ?? null) === 'prueba@example.com',
        'entrega.codigo_postal' => ($leido['entrega']['codigo_postal'] ?? null) === '5000',
        'pago.proveedor'      => ($leido['pago']['proveedor'] ?? null) === 'mercado_pago',
        'referencia'          => ($leido['referencia'] ?? null) === 'RF-TEST-AAAA',
        'sin actualizado'     => !array_key_exists('actualizado', $leido ?? []),
    ];

    foreach ($checks as $que => $ok) {
        echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
        $ok || $fallas++;
    }
}

/* --- 2. El webhook actualiza el pago ------------------------------- */

echo "\n2. El webhook pisa pago.estado sin romper el resto\n";

$actualizado = repo_order_update('RF-TEST-AAAA', [
    'estado' => 'pagado',
    'pago'   => ['estado' => 'approved', 'payment_id' => '123456789'],
]);

$releido = repo_order_local('RF-TEST-AAAA');

$checks = [
    'devolvió el pedido'   => $actualizado !== null,
    'estado nuevo'         => ($releido['estado'] ?? null) === 'pagado',
    'pago.estado nuevo'    => ($releido['pago']['estado'] ?? null) === 'approved',
    'pago.payment_id'      => ($releido['pago']['payment_id'] ?? null) === '123456789',
    'items intactos'       => count($releido['items'] ?? []) === 1,
    'comprador intacto'    => ($releido['comprador']['email'] ?? null) === 'prueba@example.com',
    'quedó `actualizado`'  => array_key_exists('actualizado', $releido ?? []),
];

foreach ($checks as $que => $ok) {
    echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
    $ok || $fallas++;
}

/* --- 3. La carrera -------------------------------------------------- */

echo "\n3. Dos compras en el mismo instante\n";

$antes = count(_repo_pedidos_leer());

/* El intercalado del bug: las DOS leen antes de que escriba ninguna. Con
   archivos, acá cada una se queda con una copia de la misma lista y la
   segunda en escribir borra el pedido de la primera. */
$listaQueVeA = _repo_pedidos_leer();
$listaQueVeB = _repo_pedidos_leer();

echo '   las dos leyeron la misma lista de ' . count($listaQueVeA) . " pedidos\n";

$a = repo_order_create(pedido_de_prueba('RF-TEST-BBBB', 111111));
$creados[] = 'RF-TEST-BBBB';

$b = repo_order_create(pedido_de_prueba('RF-TEST-CCCC', 222222));
$creados[] = 'RF-TEST-CCCC';

$despues = _repo_pedidos_leer();
$codigos = array_column($despues, 'codigo');

$checks = [
    'entró la primera'      => in_array('RF-TEST-BBBB', $codigos, true),
    'entró la segunda'      => in_array('RF-TEST-CCCC', $codigos, true),
    'ninguna pisó a la otra' => count($despues) === $antes + 2,
];

foreach ($checks as $que => $ok) {
    echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
    $ok || $fallas++;
}

echo '   pedidos antes: ' . $antes . ' · después: ' . count($despues) . "\n";

/* Y el código repetido tiene que ser rechazado, no pisar al que ya está. */
echo "\n4. Un código repetido se rechaza en vez de pisar\n";

$repetido = repo_order_create(pedido_de_prueba('RF-TEST-BBBB', 999999));
$sigue    = repo_order_local('RF-TEST-BBBB');

$checks = [
    'el alta falló'          => ($repetido['ok'] ?? null) === false,
    'el original sobrevivió' => ($sigue['total'] ?? null) === 111111,
];

foreach ($checks as $que => $ok) {
    echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
    $ok || $fallas++;
}

echo "\n";

limpiar($creados);
$creados = [];

echo 'Pedidos de prueba borrados. Quedan ' . count(_repo_pedidos_leer()) . " pedidos del sitio.\n\n";

if ($fallas === 0) {
    echo "Pedidos verificados: el alta no pierde nada, el webhook actualiza sin\n";
    echo "romper, y dos compras simultáneas entran las dos.\n";
    exit(0);
}

echo "$fallas comprobación(es) fallaron.\n";
exit(1);
