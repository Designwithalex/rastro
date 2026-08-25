<?php
/**
 * bin/server.php — servidor de desarrollo local.
 *
 *   php -S localhost:8080 bin/server.php
 *
 * Imita lo que hace el .htaccess en Hostinger: si el archivo pedido existe
 * se sirve tal cual, y si no, entra por el front controller.
 * No se despliega: bin/ está excluido en .github/workflows/deploy.yml.
 */

declare(strict_types=1);

$camino = (string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$archivo = __DIR__ . '/..' . $camino;

if ($camino !== '/' && is_file($archivo)) {
    // Las carpetas privadas no se sirven, igual que con su .htaccess.
    foreach (['/app/', '/views/', '/data/', '/bin/'] as $privada) {
        if (str_starts_with($camino, $privada)) {
            http_response_code(403);
            echo '403 · carpeta privada';
            return true;
        }
    }

    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/../index.php';
