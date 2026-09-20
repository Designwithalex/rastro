<?php
/**
 * bin/verificar.php — corre las cuatro verificaciones de la base.
 *
 *   php bin/verificar.php
 *
 * Es lo que hay que correr antes de tocar producción.
 *
 *   escritura    guardar y volver a leer no deforma nada, y el panel da de
 *                alta, edita y borra un producto sin perder nada
 *   pedidos      el alta no pierde compras simultáneas y el webhook
 *                actualiza sin romper
 *   formularios  arrepentimientos, recuperación de clave e intentos
 *   correo       el cliente SMTP, contra un servidor de mentira que habla
 *                TLS de verdad. No toca la base ni manda nada afuera.
 *
 * `escritura`, `pedidos` y `formularios` escriben en la base y borran lo
 * suyo al terminar.
 *
 * Sale con 0 si las cuatro pasan. No se despliega: bin/ está excluido
 * del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

/* `verificar-paridad.php` NO está acá, y es a propósito.

   Esa suite compara la base contra los data/*.json, que son la semilla: la
   foto del día que se migró. Ya cumplió su función y no se puede volver a
   correr como control: en cuanto alguien carga un producto o se registra un
   cliente, la base y los archivos dejan de coincidir, y marcaría como falla
   un dato nuevo que está perfectamente bien.

   Un control que se pone en rojo por funcionar es un control que la gente
   aprende a ignorar. Se corre a mano, y sólo al migrar:

       php bin/verificar-paridad.php

   Las cuatro de abajo sí valen siempre: no comparan contra la semilla, sino
   que ejercitan el comportamiento. */
$suites = [
    'escritura'   => 'verificar-escritura.php',
    'pedidos'     => 'verificar-pedidos.php',
    'formularios' => 'verificar-formularios.php',
    'correo'      => 'verificar-correo.php',
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
