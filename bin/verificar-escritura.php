<?php
/**
 * bin/verificar-escritura.php — ¿lo que se guarda vuelve igual?
 *
 *   php bin/verificar-escritura.php
 *
 * Lee cada entidad, la vuelve a guardar SIN CAMBIARLE NADA, y la lee otra
 * vez. Si la segunda lectura no es idéntica a la primera, la escritura
 * pierde o deforma datos: un bool que vuelve como 1, una especificación
 * que se duplica, una imagen que se pierde al reinsertar las hijas.
 *
 * Es la prueba que le falta a la de paridad. Esa compara MySQL contra los
 * JSON, pero sólo del lado de la lectura: el panel podría estar rompiendo
 * el catálogo en cada "Guardar" sin que aparezca nada raro hasta la
 * siguiente visita.
 *
 * ESCRIBE EN LA BASE. No cambia ningún valor —guarda exactamente lo que
 * leyó— pero toca todas las tablas. Si algo sale mal, `php bin/migrar.php
 * --recrear` la deja como estaba: los data/*.json son la semilla y la
 * paridad contra ellos está verificada.
 *
 * No se despliega: bin/ está excluido del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

require $raiz . '/app/db.php';
require $raiz . '/app/repository-mysql.php';

if (!db_activa()) {
    echo "✗ No hay base configurada en app/config.php.\n";
    exit(1);
}

/** Igual que en verificar-paridad.php: comparación profunda y estricta. */
function difs(mixed $a, mixed $b, string $ruta = ''): array
{
    $r = $ruta === '' ? '(raíz)' : $ruta;

    if (get_debug_type($a) !== get_debug_type($b)) {
        return [sprintf('%s: antes %s (%s), después %s (%s)', $r,
            get_debug_type($a), var_export($a, true),
            get_debug_type($b), var_export($b, true))];
    }

    if (!is_array($a)) {
        return $a === $b ? [] : [sprintf('%s: antes=%s  después=%s',
            $r, var_export($a, true), var_export($b, true))];
    }

    $d = [];

    foreach (array_diff(array_keys($a), array_keys($b)) as $k) {
        $d[] = "$r.$k: se perdió al guardar";
    }

    foreach (array_diff(array_keys($b), array_keys($a)) as $k) {
        $d[] = "$r.$k: apareció al guardar";
    }

    foreach ($a as $k => $v) {
        if (array_key_exists($k, $b)) {
            $d = array_merge($d, difs($v, $b[$k], $ruta === '' ? (string) $k : "$ruta.$k"));
        }
    }

    return $d;
}

$entidades = ['products', 'categories', 'brands', 'clients', 'banners', 'settings', 'users', 'orders', 'nosotros'];

$fallas = 0;

foreach ($entidades as $e) {
    $antes = _repo_mysql_leer($e);

    if (!_repo_mysql_guardar($e, $antes)) {
        echo '✗ ' . str_pad($e, 12) . "no se pudo guardar (ver el log de errores)\n";
        $fallas++;
        continue;
    }

    $despues = _repo_mysql_leer($e);
    $d       = difs($antes, $despues);

    if ($d === []) {
        echo '✓ ' . str_pad($e, 12) . 'ida y vuelta sin pérdida · ' . count($antes) . " registro(s)\n";
        continue;
    }

    $fallas++;
    echo '✗ ' . str_pad($e, 12) . count($d) . " diferencia(s) tras guardar:\n";

    foreach (array_slice($d, 0, 8) as $x) {
        echo "    $x\n";
    }

    if (count($d) > 8) {
        echo '    … y ' . (count($d) - 8) . " más\n";
    }
}

/* ==========================================================================
   El panel, por sus funciones de verdad

   Lo de arriba prueba la capa de traducción. Esto prueba el camino que
   recorre un "Guardar" del panel: repo_save_* asigna el id, mete el
   registro en la colección y manda todo a escribir. Un alta que no
   devuelve el id nuevo deja al panel redirigiendo a una ficha que no
   existe, y eso no se ve en un round-trip.
   ========================================================================== */

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/repository.php';
require dirname(__DIR__) . '/app/repository-escritura.php';

echo "\nEl panel: alta, edición y baja de un producto\n";

$cuantos_antes = count(repo_all_products());

$nuevo = repo_save_product([
    'slug'              => 'producto-de-prueba-automatica',
    'sku'               => 'RS-TEST-001',
    'nombre'            => 'Producto de prueba automática',
    'categoria'         => 'discos',
    'marca'             => 'rastro',
    'descripcion_corta' => 'Se borra solo.',
    'descripcion'       => 'Alta hecha por bin/verificar-escritura.php.',
    'precio_lista'      => 12345,
    'precio_mayorista'  => 10000,
    'descuento_pct'     => null,
    'stock'             => 7,
    'destacado'         => false,
    'nuevo'             => true,
    'imagen'            => 'img/productos/placeholder.jpg',
    'imagenes'          => ['img/productos/placeholder.jpg'],
    'especificaciones'  => [['label' => 'Material', 'valor' => 'Prueba']],
    'peso_kg'           => 3,
    'activo'            => true,
]);

$id      = (int) ($nuevo['id'] ?? 0);
$releido = repo_product('producto-de-prueba-automatica');

$panel = [
    'el alta devolvió el registro'   => $nuevo !== null,
    'le asignó un id'                => $id > 0,
    'el id no pisó a otro producto'  => count(repo_all_products()) === $cuantos_antes + 1,
    'se puede releer por id'         => $releido !== null,
    'guardó el precio'               => ($releido['precio_lista'] ?? null) === 12345,
    'guardó el stock'                => ($releido['stock'] ?? null) === 7,
    'guardó la especificación'       => ($releido['especificaciones'][0]['label'] ?? null) === 'Material',
    'guardó la imagen'               => count($releido['imagenes'] ?? []) === 1,
    'los bool siguen siendo bool'    => ($releido['nuevo'] ?? null) === true
                                        && ($releido['destacado'] ?? null) === false,
];

// Edición: cambiar un campo no puede perder los demás.
if ($id > 0) {
    $editado = repo_save_product(array_merge($releido, ['precio_lista' => 99999]));
    $tras    = repo_product('producto-de-prueba-automatica');

    $panel['la edición cambia el precio']      = ($tras['precio_lista'] ?? null) === 99999;
    $panel['y conserva las especificaciones']  = count($tras['especificaciones'] ?? []) === 1;
    $panel['y no duplica el producto']         = count(repo_all_products()) === $cuantos_antes + 1;
}

// Baja.
if ($id > 0) {
    $panel['la baja devuelve true']  = repo_delete_product($id) === true;
    $panel['ya no se puede releer']  = repo_product('producto-de-prueba-automatica') === null;
    $panel['el catálogo vuelve a su tamaño'] = count(repo_all_products()) === $cuantos_antes;
}

foreach ($panel as $que => $ok) {
    echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
    $ok || $fallas++;
}

echo "\n";

if ($fallas === 0) {
    echo "Escritura verificada: guardar y volver a leer devuelve exactamente lo\n";
    echo "mismo en las nueve entidades, y el panel da de alta, edita y borra\n";
    echo "sin perder nada.\n";
    exit(0);
}

echo "$fallas entidad(es) se deforman al guardar. El panel NO es seguro todavía.\n";
exit(1);
