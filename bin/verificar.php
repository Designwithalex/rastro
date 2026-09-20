<?php
/**
 * bin/verificar.php — corre las cuatro verificaciones de la base.
 *
 *   php bin/verificar.php
 *
 * Es lo que hay que correr antes de tocar producción, y lo que contesta
 * la única pregunta que importa: ¿el sitio leyendo de MySQL se comporta
 * igual que leyendo los archivos?
 *
 *   paridad      las nueve entidades devuelven lo mismo que su JSON,
 *                campo por campo y con tipos estrictos
 *   escritura    guardar y volver a leer no deforma nada
 *   pedidos      el alta no pierde compras simultáneas y el webhook
 *                actualiza sin romper
 *   formularios  arrepentimientos, recuperación de clave e intentos
 *
 * Las dos últimas escriben en la base y borran lo suyo al terminar.
 *
 * Sale con 0 si las cuatro pasan. No se despliega: bin/ está excluido
 * del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$suites = [
    'paridad'     => 'verificar-paridad.php',
    'escritura'   => 'verificar-escritura.php',
    'pedidos'     => 'verificar-pedidos.php',
    'formularios' => 'verificar-formularios.php',
];

$php    = PHP_BINARY;
$fallas = [];

foreach ($suites as $nombre => $archivo) {
    $salida = [];
    $codigo = 0;

    exec(
        escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/' . $archivo) . ' 2>&1',
        $salida,
        $codigo
    );

    if ($codigo === 0) {
        echo '✓ ' . str_pad($nombre, 12) . "\n";
        continue;
    }

    $fallas[] = $nombre;

    echo '✗ ' . str_pad($nombre, 12) . "falló\n";

    // De la que falla se muestra todo: es lo que hay que leer.
    foreach ($salida as $linea) {
        echo '    ' . $linea . "\n";
    }
}

echo "\n";

if ($fallas === []) {
    echo "Las cuatro pasan. El sitio se comporta igual con MySQL que con los archivos.\n";
    exit(0);
}

echo 'Falló: ' . implode(', ', $fallas) . ".\n";
exit(1);
