<?php
/**
 * index.php — front controller de Rastro Fitness.
 *
 * Todo el tráfico entra por acá (ver .htaccess de la raíz). El trabajo es
 * corto y siempre el mismo: cargar la configuración, resolver la ruta y
 * ceder el control a una vista. La lógica de datos vive en app/repository.php
 * y el formato de salida en app/helpers.php.
 */

declare(strict_types=1);

define('RASTRO_RAIZ', __DIR__);
define('RASTRO_VIEWS', __DIR__ . '/views');

/* --- Configuración -------------------------------------------------------
   app/config.php no se versiona ni se sincroniza por FTP: vive en el
   servidor. Si falta, el sitio igual levanta con los valores por defecto,
   porque en esta etapa los datos son mocks y no hay base. */
$config = is_file(__DIR__ . '/app/config.php')
    ? (array) require __DIR__ . '/app/config.php'
    : [];

$entorno = (string) ($config['entorno'] ?? 'produccion');

if ($entorno === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/repository.php';
require __DIR__ . '/app/router.php';

/* --- Base del sitio ------------------------------------------------------
   Si el proyecto queda colgado de un subdirectorio, todos los enlaces se
   corrigen solos porque url() y asset() anteponen esta base. */
$base = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$base = rtrim($base, '/');
define('RASTRO_BASE', $base === '/' ? '' : $base);

/* --- Ruta pedida --------------------------------------------------------- */
$camino = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$camino = rawurldecode($camino);

if (RASTRO_BASE !== '' && str_starts_with($camino, RASTRO_BASE)) {
    $camino = substr($camino, strlen(RASTRO_BASE));
}

// El front controller no es una URL: /index.php es la home, no un 404.
if ($camino === '/index.php') {
    header('Location: ' . url('/'), true, 301);
    exit;
}

// Una sola URL por página: /catalogo/ redirige a /catalogo.
if ($camino !== '/' && str_ends_with($camino, '/')) {
    $destino = url(rtrim($camino, '/'));
    $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');

    header('Location: ' . $destino . ($consulta !== '' ? '?' . $consulta : ''), true, 301);
    exit;
}

$camino = '/' . trim($camino, '/');
define('RASTRO_RUTA', $camino);

/* --- Despacho ------------------------------------------------------------ */
$ruta   = router_resolver(RASTRO_RUTA);
$params = $ruta['params'];

http_response_code($ruta['estado']);
header('Content-Type: text/html; charset=utf-8');

$archivo = RASTRO_VIEWS . '/' . $ruta['vista'] . '.php';

if (is_file($archivo)) {
    require $archivo;
} else {
    /* Todas las rutas de la tabla tienen su vista desde el cierre de la
       Fase 2, así que llegar acá significa que falta un archivo: un
       despliegue a medias o un renombre sin actualizar el router. Es un
       500, no un 404 — la página existe, el servidor está mal— y por eso
       no muestra un 404 bonito, que escondería el problema. */
    error_log('Falta la vista views/' . $ruta['vista'] . '.php para la ruta ' . RASTRO_RUTA);

    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error 500</title>'
       . '<p>Error del servidor. Ya estamos mirando qué pasó.</p>';
}
