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

/* Como constante para que la lean las funciones, que no ven $config.
   El acceso es config('clave') y está en helpers.php. */
define('RASTRO_CONFIG', $config);

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

/* db.php y repository-mysql.php van ANTES que repository.php porque
   `_repo_json()` los llama en su primera línea. Cargarlos no abre ninguna
   conexión: si app/config.php no tiene credenciales, `db_activa()` devuelve
   false mirando el archivo y el sitio lee los JSON como siempre. */
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/repository-mysql.php';
require __DIR__ . '/app/repository.php';
require __DIR__ . '/app/router.php';

/* El checkout y su cliente de Mercado Pago se cargan siempre, no sólo en las
   rutas de pago: el header muestra el contador del carrito en todas las
   páginas y mañana va a querer saber si el pago online está habilitado.
   Son dos archivos de funciones sueltas, sin efectos al incluirse.
   mp_config() lee $config, que existe en el ámbito global desde acá arriba. */
require __DIR__ . '/app/mercadopago.php';
require __DIR__ . '/app/checkout.php';

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

/* --- El panel -------------------------------------------------------------
   Sesión, CSRF, subidas y el lado de escritura del repository sólo se cargan
   cuando la ruta es del panel. El sitio público no escribe nada, y arrancar
   una sesión en cada visita a la home costaría un archivo de sesión por
   visitante para no guardar nada adentro. */
/* La sesión de los clientes va en TODAS las páginas, porque la cabecera
   necesita saber si decir "Mi cuenta" o "Ingresar". No es caro: sesion_abrir()
   sin forzar no arranca nada si el visitante no trae la cookie, así que quien
   nunca se identificó no paga un archivo de sesión (app/sesion.php).

   Las pantallas que además ESCRIBEN —registro, y el arrepentimiento que deja
   constancia— necesitan el lado de escritura del repository. */
require __DIR__ . '/app/sesion.php';

/* Se abre ACÁ, antes de que la vista imprima un solo byte. Una sesión de PHP
   manda una cabecera Set-Cookie, y una cabecera no se puede mandar después
   del HTML: si se arranca a mitad del renderizado, PHP no la inicia y escupe
   tres warnings adentro de la página.

   Sin forzar: si el visitante no trae la cookie no se arranca nada, que es
   la razón por la que esto se puede permitir en todas las rutas. */
sesion_abrir();

if (in_array(RASTRO_RUTA, ['/registro', '/arrepentimiento'], true)
    || str_starts_with(RASTRO_RUTA, '/recuperar')) {
    require __DIR__ . '/app/repository-escritura.php';
}

/* El arrepentimiento avisa por mail, y el panel tiene una prueba de envío
   en Configuración: los dos necesitan el mismo archivo. */
if (RASTRO_RUTA === '/arrepentimiento'
    || RASTRO_RUTA === '/registro'
    || str_starts_with(RASTRO_RUTA, '/admin')
    || str_starts_with(RASTRO_RUTA, '/recuperar')) {
    // smtp.php antes que correo.php: correo_enviar() lo consulta en su
    // última línea. Cargarlo no abre ninguna conexión.
    require __DIR__ . '/app/smtp.php';
    require __DIR__ . '/app/correo.php';
}

if (RASTRO_RUTA === '/admin' || str_starts_with(RASTRO_RUTA, '/admin/')) {
    if (!function_exists('repo_save_product')) {
        require __DIR__ . '/app/repository-escritura.php';
    }

    require __DIR__ . '/app/panel.php';

    /* El panel no se indexa ni se cachea. Es lo primero que se manda porque
       una vista que redirige con panel_ir() termina antes de llegar al head. */
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, private');
}

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
