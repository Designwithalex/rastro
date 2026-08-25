<?php
/**
 * bin/server.php — servidor de desarrollo local.
 *
 *   php -S localhost:8000 bin/server.php
 *
 * El router es obligatorio: `php -S localhost:8000` a secas sirve el árbol
 * de archivos tal cual y entrega data/users.json, data/settings.json y el
 * código de app/ a cualquiera que los pida. En producción eso lo tapa el
 * .htaccess de cada carpeta privada, que el servidor embebido de PHP ignora.
 * Este archivo replica ese comportamiento en local.
 *
 * No se despliega: bin/ está excluido en .github/workflows/deploy.yml.
 */

declare(strict_types=1);

$raiz = realpath(__DIR__ . '/..');

if ($raiz === false) {
    http_response_code(500);
    echo '500 · no se pudo resolver la raíz del proyecto';
    return true;
}

$camino = (string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$camino = rawurldecode($camino);

/**
 * Devuelve true si $archivo está dentro de $carpeta.
 * Se compara sobre rutas ya resueltas por realpath(): comparar las cadenas
 * de la URL no alcanza, porque /assets/../data/users.json empieza con
 * "/assets/" y sin embargo termina en data/.
 */
$dentro_de = static function (string $archivo, string $carpeta): bool {
    return $archivo === $carpeta
        || str_starts_with($archivo, rtrim($carpeta, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
};

$archivo = realpath($raiz . '/' . ltrim($camino, '/'));

// Nada de lo que no sea un archivo existente y real se sirve directo:
// va al front controller, que decide si es una ruta o un 404.
if ($camino !== '/' && $archivo !== false && is_file($archivo)) {

    // 1. Fuera de la raíz del proyecto. No debería pasar nunca, pero si el
    //    camino escapa con ../ se corta acá.
    if (!$dentro_de($archivo, $raiz)) {
        http_response_code(403);
        echo '403 · fuera del proyecto';
        return true;
    }

    // 2. Carpetas privadas: las mismas que en producción cierra
    //    su propio .htaccess con Require all denied.
    foreach (['app', 'views', 'data', 'bin', '.originales-img'] as $privada) {
        $absoluta = realpath($raiz . '/' . $privada);

        if ($absoluta !== false && $dentro_de($archivo, $absoluta)) {
            http_response_code(403);
            echo '403 · carpeta privada';
            return true;
        }
    }

    // 3. Archivos que el .htaccess de la raíz niega en cualquier ubicación.
    $nombre = basename($archivo);

    if (preg_match('/\.(json|md|env)$/i', $nombre) || str_starts_with($nombre, '.')) {
        http_response_code(403);
        echo '403 · archivo no público';
        return true;
    }

    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require $raiz . '/index.php';
