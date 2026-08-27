<?php
/**
 * subidas.php — subida de imágenes desde el panel.
 *
 * Lo carga sólo el panel, después del guard. Nada de acá se ejecuta en
 * una request anónima.
 *
 * POR QUÉ NO ALCANZA CON MIRAR LA EXTENSIÓN
 *
 * El nombre del archivo lo elige quien sube, y `$_FILES['type']` lo manda
 * el navegador: los dos son datos de afuera. Un `.jpg` puede ser un PHP.
 * Por eso el tipo se decide con `getimagesize()`, que lee los bytes, y el
 * nombre final lo inventa este archivo a partir del SKU.
 *
 * Aun así, la defensa que de verdad importa es que `assets/img/` no
 * ejecuta PHP: lo garantiza el `.htaccess` que se escribe al crear la
 * carpeta, más abajo.
 */

declare(strict_types=1);

/* 6 MB por archivo. Las fotos que manda el cliente vienen de cámara y
   pesan 3-5 MB; más que eso es un archivo sin optimizar y conviene que
   se entere ahora y no cuando el sitio tarde en cargar. */
const SUBIDA_MAX_BYTES = 6 * 1024 * 1024;

/** Los tres formatos que el sitio sabe mostrar, con su extensión real. */
function _subida_tipos(): array
{
    return [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
}

/**
 * Guarda una imagen subida en assets/img/<carpeta>/ y devuelve la ruta
 * relativa que espera el repository ("img/productos/foo.jpg").
 *
 * @param array  $archivo Una entrada de $_FILES.
 * @param string $carpeta Subcarpeta de assets/img/. Lista blanca.
 * @param string $base    Nombre deseado, sin extensión. Se pasa por slug().
 *
 * @return array{ok:bool, ruta:?string, error:?string, ancho:?int, alto:?int}
 */
function subir_imagen(array $archivo, string $carpeta, string $base): array
{
    $carpetas = ['productos', 'marca', 'ambiente', 'iconos'];

    if (!in_array($carpeta, $carpetas, true)) {
        return _subida_error('Carpeta de destino no permitida.');
    }

    $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'ruta' => null, 'error' => null, 'ancho' => null, 'alto' => null];
    }

    if ($codigo !== UPLOAD_ERR_OK) {
        /* Los dos primeros son el mismo problema visto desde php.ini y
           desde el formulario. Se dicen juntos porque para quien sube son
           lo mismo: el archivo pesa demasiado. */
        $mensajes = [
            UPLOAD_ERR_INI_SIZE   => 'El archivo pesa más de lo que permite el servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo pesa demasiado.',
            UPLOAD_ERR_PARTIAL    => 'La subida se cortó por la mitad. Probá de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene dónde guardar el archivo temporal.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo.',
        ];

        return _subida_error($mensajes[$codigo] ?? 'No se pudo subir el archivo.');
    }

    $tmp = (string) ($archivo['tmp_name'] ?? '');

    /* Sin esto, un POST con un tmp_name apuntando a /etc/passwd haría que
       el resto de la función trabaje sobre un archivo del servidor. */
    if (!is_uploaded_file($tmp)) {
        return _subida_error('El archivo no llegó por una subida válida.');
    }

    if (filesize($tmp) > SUBIDA_MAX_BYTES) {
        return _subida_error('La imagen no puede pesar más de 6 MB. Bajale la resolución antes de subirla.');
    }

    // El tipo se decide leyendo los bytes, no el nombre ni lo que dijo el
    // navegador.
    $medidas = @getimagesize($tmp);

    if ($medidas === false || !isset(_subida_tipos()[$medidas[2]])) {
        return _subida_error('El archivo no es una imagen JPG, PNG o WebP.');
    }

    $extension = _subida_tipos()[$medidas[2]];

    $destino_dir = dirname(__DIR__) . '/assets/img/' . $carpeta;

    if (!is_dir($destino_dir) && !@mkdir($destino_dir, 0755, true) && !is_dir($destino_dir)) {
        return _subida_error('No se pudo crear la carpeta de destino.');
    }

    _subida_blindar_carpeta($destino_dir);

    /* El nombre lo arma el servidor. Al SKU se le suma un sufijo corto
       para no pisar una foto anterior del mismo producto: el cliente sube
       la foto nueva y la vieja sigue ahí hasta que alguien la borre a
       mano, que es lo prudente. */
    $nombre = slug($base) ?: 'imagen';
    $nombre .= '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extension;

    if (!move_uploaded_file($tmp, $destino_dir . '/' . $nombre)) {
        return _subida_error('No se pudo guardar la imagen en el servidor.');
    }

    @chmod($destino_dir . '/' . $nombre, 0644);

    return [
        'ok'    => true,
        'ruta'  => 'img/' . $carpeta . '/' . $nombre,
        'error' => null,
        'ancho' => (int) $medidas[0],
        'alto'  => (int) $medidas[1],
    ];
}

function _subida_error(string $mensaje): array
{
    return ['ok' => false, 'ruta' => null, 'error' => $mensaje, 'ancho' => null, 'alto' => null];
}

/**
 * Deja un .htaccess en la carpeta de subidas que apaga la ejecución de
 * PHP y de CGI.
 *
 * Es la defensa que importa: aunque alguien logre subir un archivo con
 * código adentro —una imagen con PHP en los metadatos, por ejemplo—, en
 * esa carpeta no se ejecuta. Se escribe una sola vez y se comprueba en
 * cada subida porque una carpeta creada a mano por FTP no lo tendría.
 */
function _subida_blindar_carpeta(string $dir): void
{
    $htaccess = $dir . '/.htaccess';

    if (is_file($htaccess)) {
        return;
    }

    @file_put_contents($htaccess, <<<'HTACCESS'
    # Generado por app/subidas.php. No borrar.
    # En una carpeta de imágenes no se ejecuta nada: es la defensa que
    # queda en pie si alguna vez se cuela un archivo que no debería estar.
    php_flag engine off
    RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .pl .py .cgi
    RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8

    <IfModule mod_rewrite.c>
        RewriteEngine Off
    </IfModule>
    HTACCESS);
}
