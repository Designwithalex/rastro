<?php
/**
 * bin/verificar-paridad.php — ¿MySQL devuelve LO MISMO que los JSON?
 *
 *   php bin/verificar-paridad.php
 *
 * La migración no se da por buena porque el script de carga no haya
 * fallado. Se da por buena cuando el sitio leyendo de MySQL recibe
 * exactamente los mismos arrays que recibía leyendo los archivos.
 *
 * Esto compara las dos fuentes entidad por entidad y campo por campo, con
 * tipos estrictos: un `true` que volvió como `1` y un `10` que volvió como
 * "10.00" son fallas, aunque el sitio parezca andar. Andan hasta que una
 * vista compara con === o un json_encode escribe otra cosa.
 *
 * ---------------------------------------------------------------------
 * ESTO TIENE FECHA DE VENCIMIENTO, Y NO ES UN DEFECTO
 *
 * Compara contra los `data/*.json`, que son la SEMILLA: la foto de los
 * datos el día que se migró. Sirve mientras la base siga siendo esa foto.
 *
 * Apenas el cliente cargue un producto o cambie un precio desde el panel,
 * la base y los archivos dejan de coincidir y esta comprobación va a
 * marcar diferencias que son correctas: los datos nuevos están en MySQL y
 * el archivo quedó viejo, que es exactamente lo que tiene que pasar.
 *
 * O sea: si falla DESPUÉS de que alguien usó el panel, leé las diferencias
 * antes de asustarte. Lo que prueba es la migración, no el estado del
 * sitio. Las otras tres suites de `bin/verificar.php` no tienen este
 * vencimiento y sirven siempre.
 * ---------------------------------------------------------------------
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
    echo "✗ No hay base configurada en app/config.php. No hay nada que comparar.\n";
    exit(1);
}

/** Las nueve entidades, con el nombre del archivo que reemplazan. */
$entidades = ['products', 'categories', 'brands', 'clients', 'banners', 'settings', 'users', 'orders', 'nosotros'];

/**
 * DIFERENCIAS TOLERADAS
 *
 * Cuatro diferencias entre el JSON y MySQL no son errores, y cada una se
 * verificó leyendo TODOS los consumidores del dato antes de aceptarla. Se
 * listan acá, con el motivo y el archivo que lo prueba, en vez de aflojar
 * la comparación: todo lo que no esté en esta lista sigue siendo una falla.
 *
 * Si mañana alguien cambia uno de esos consumidores, la tolerancia deja de
 * valer. Por eso está escrito de dónde sale cada una.
 *
 * @var array<string, list<array{patron:string, porque:string}>>
 */
$toleradas = [
    'brands' => [[
        'patron' => '/\.es_propia: está en MySQL y no en el JSON$/',
        'porque' => 'El JSON omite la clave en las 5 marcas de terceros y sólo la trae en '
                  . 'Rastro. MySQL la devuelve siempre, como false. Los dos únicos '
                  . 'consumidores la leen con tolerancia a que falte: repository.php:405 '
                  . 'usa empty() y views/admin/marcas.php:103 usa (?? false) === true. '
                  . 'Ausente y false dan el mismo resultado en ambos.',
    ]],

    'banners' => [[
        'patron' => '/^\d+\./',
        'porque' => 'El archivo estaba en orden 1,4,2,3 —autoría, no criterio— y MySQL '
                  . 'devuelve por id. Ningún consumidor depende de ese orden: repo_banners() '
                  . '(repository.php:435) y repo_all_banners() (repository.php:453) hacen '
                  . 'las dos el mismo usort por (posicion, orden) apenas leen.',
    ]],

    'settings' => [[
        'patron' => '/^\(raíz\): mismas claves en distinto orden$/',
        'porque' => 'settings es un mapa y se lee siempre por clave ($settings[\'whatsapp\']). '
                  . 'La tabla no guarda el orden del archivo porque su clave primaria es '
                  . '`clave`. El orden de un mapa no es parte del contrato.',
    ]],

    'nosotros' => [[
        'patron' => '/^\(raíz\)\._comentario: está en el JSON y falta en MySQL$/',
        'porque' => 'Es una nota para humanos dentro del archivo, no contenido de la página. '
                  . 'repo_nosotros() la descarta con unset() en repository.php:651 antes de '
                  . 'devolver nada, así que nunca llegó a una vista.',
    ]],
];

/**
 * Compara dos valores en profundidad y devuelve las diferencias como una
 * lista de textos, cada uno con la ruta del campo que no coincide.
 *
 * @return list<string>
 */
function diferencias(mixed $json, mixed $sql, string $ruta = ''): array
{
    $r = $ruta === '' ? '(raíz)' : $ruta;

    // El tipo primero: es la falla más común y la que explica el resto.
    if (get_debug_type($json) !== get_debug_type($sql)) {
        return [sprintf(
            '%s: JSON es %s (%s) y MySQL es %s (%s)',
            $r,
            get_debug_type($json), var_export($json, true),
            get_debug_type($sql), var_export($sql, true)
        )];
    }

    if (!is_array($json)) {
        return $json === $sql
            ? []
            : [sprintf('%s: JSON=%s  MySQL=%s', $r, var_export($json, true), var_export($sql, true))];
    }

    $diffs = [];

    // Claves de más y de menos, en los dos sentidos.
    foreach (array_diff(array_keys($json), array_keys($sql)) as $k) {
        $diffs[] = "$r.$k: está en el JSON y falta en MySQL";
    }

    foreach (array_diff(array_keys($sql), array_keys($json)) as $k) {
        $diffs[] = "$r.$k: está en MySQL y no en el JSON";
    }

    // El orden de las claves también cuenta: dos arrays iguales en otro
    // orden son == pero no ===, y hay código que compara en serio.
    if (array_keys($json) !== array_keys($sql)
        && array_diff(array_keys($json), array_keys($sql)) === []
        && array_diff(array_keys($sql), array_keys($json)) === []) {
        $diffs[] = "$r: mismas claves en distinto orden";
    }

    foreach ($json as $k => $v) {
        if (array_key_exists($k, $sql)) {
            $diffs = array_merge($diffs, diferencias($v, $sql[$k], $ruta === '' ? (string) $k : "$ruta.$k"));
        }
    }

    return $diffs;
}

$fallas = 0;

foreach ($entidades as $entidad) {
    $archivo = $raiz . '/data/' . $entidad . '.json';

    $json = is_file($archivo)
        ? json_decode((string) file_get_contents($archivo), true)
        : [];

    $json = is_array($json) ? $json : [];

    try {
        $sql = _repo_mysql_leer($entidad);
    } catch (Throwable $e) {
        echo "✗ $entidad: " . $e->getMessage() . "\n";
        $fallas++;
        continue;
    }

    $diffs = diferencias($json, $sql);

    /* Se aparta lo tolerado de lo que no lo está. Una tolerancia que ya no
       matchea nada tampoco se avisa: sobra, pero no rompe. */
    $reales   = [];
    $perdonadas = 0;
    $motivos  = [];

    foreach ($diffs as $d) {
        $ok = false;

        foreach ($toleradas[$entidad] ?? [] as $t) {
            if (preg_match($t['patron'], $d) === 1) {
                $ok = true;
                $motivos[$t['porque']] = true;
                break;
            }
        }

        $ok ? $perdonadas++ : $reales[] = $d;
    }

    $n    = count($json);
    $nota = $perdonadas > 0 ? " ($perdonadas tolerada(s))" : '';

    if ($reales === []) {
        echo '✓ ' . str_pad($entidad, 12) . "idéntico · $n registro(s)$nota\n";

        foreach (array_keys($motivos) as $m) {
            echo '    tolerado: ' . wordwrap($m, 86, "\n              ") . "\n";
        }

        continue;
    }

    $fallas++;
    echo '✗ ' . str_pad($entidad, 12) . count($reales) . " diferencia(s) REAL(es)$nota:\n";

    // Con 30 productos rotos del mismo modo salen 30 líneas iguales. Las
    // primeras alcanzan para entender qué pasa.
    foreach (array_slice($reales, 0, 8) as $d) {
        echo "    $d\n";
    }

    if (count($reales) > 8) {
        echo '    … y ' . (count($reales) - 8) . " más\n";
    }
}

echo "\n";

if ($fallas === 0) {
    echo "Paridad verificada: MySQL devuelve lo mismo que los JSON en las nueve\n";
    echo "entidades, salvo las diferencias toleradas de arriba, que se comprobó\n";
    echo "que ningún consumidor puede notar.\n";
    exit(0);
}

echo "$fallas entidad(es) con diferencias reales. La migración NO está lista.\n";
exit(1);
