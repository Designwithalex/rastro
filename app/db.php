<?php
/**
 * db.php — la conexión a MySQL.
 *
 * Una sola conexión por request, abierta la primera vez que alguien la
 * pide. `repository.php` es el único archivo que la usa: si aparece un
 * `db()` en una vista, algo se rompió.
 *
 * Las credenciales salen de `app/config.php`, que no está en el repo ni
 * se sincroniza por FTP: vive en el servidor y en la máquina de cada dev.
 */

declare(strict_types=1);

/**
 * La conexión PDO, cacheada por request.
 *
 * @throws RuntimeException si falta la configuración o la base no responde.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = _db_config();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            // Los errores se lanzan como excepción. El modo silencioso
            // hace que una consulta rota devuelva false y el código siga
            // como si nada, que es cómo se publican páginas en blanco.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Sentencias preparadas de verdad, del lado del servidor. Con
            // la emulación prendida, PDO interpola los parámetros él mismo
            // y además devuelve todos los enteros como string.
            PDO::ATTR_EMULATE_PREPARES   => false,

            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        // El mensaje de PDO trae usuario y host. No puede llegar a la
        // pantalla de nadie: va al log y afuera sale algo genérico.
        error_log('No se pudo conectar a MySQL: ' . $e->getMessage());

        throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
    }

    return $pdo;
}

/**
 * Lee y valida la configuración de la base.
 *
 * @return array{host:string, port:int, name:string, user:string, pass:string, charset:string}
 */
function _db_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $archivo = __DIR__ . '/config.php';

    if (!is_file($archivo)) {
        throw new RuntimeException(
            'Falta app/config.php. Copiá app/config.example.php y completá las credenciales.'
        );
    }

    /** @var array $valores */
    $valores = (array) require $archivo;

    foreach (['db_host', 'db_name', 'db_user'] as $clave) {
        if (trim((string) ($valores[$clave] ?? '')) === '') {
            throw new RuntimeException("Falta $clave en app/config.php.");
        }
    }

    return $config = [
        'host'    => (string) $valores['db_host'],
        'port'    => (int) ($valores['db_port'] ?? 3306),
        'name'    => (string) $valores['db_name'],
        'user'    => (string) $valores['db_user'],
        'pass'    => (string) ($valores['db_pass'] ?? ''),
        'charset' => (string) ($valores['db_charset'] ?? 'utf8mb4'),
    ];
}

/**
 * Atajo para una consulta con parámetros.
 *
 *   db_q('SELECT * FROM productos WHERE slug = ?', [$slug])->fetch()
 *
 * Existe para que el repository no repita prepare/execute en cada
 * función. Los parámetros van SIEMPRE por acá: no hay una sola
 * consulta en el proyecto que concatene un valor.
 */
function db_q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st;
}

/**
 * ¿Hay una base configurada?
 *
 * Es el interruptor de toda la migración. `repository.php` la consulta en
 * cada función: si devuelve true lee de MySQL, si devuelve false lee de
 * `data/*.json` exactamente como se hizo hasta ahora.
 *
 * A diferencia de `db()`, esta función NO lanza: la falta de credenciales
 * no es un error, es el modo en que el sitio viene funcionando. Por eso
 * vaciar db_host en app/config.php alcanza para volver a los JSON, sin
 * tocar el código ni redesplegar el sitio.
 *
 * Tampoco abre la conexión: se llama muchas veces por request y conectar
 * para preguntar si hay que conectarse sería absurdo.
 */
function db_activa(): bool
{
    static $activa = null;

    if ($activa !== null) {
        return $activa;
    }

    $archivo = __DIR__ . '/config.php';

    if (!is_file($archivo)) {
        return $activa = false;
    }

    $valores = (array) require $archivo;

    foreach (['db_host', 'db_name', 'db_user'] as $clave) {
        if (trim((string) ($valores[$clave] ?? '')) === '') {
            return $activa = false;
        }
    }

    return $activa = true;
}

/**
 * Corre un bloque dentro de una transacción y lo revierte si algo falla.
 *
 * Guardar un producto son tres escrituras —el producto, sus imágenes y
 * sus especificaciones— y a medias no sirven: un producto sin sus fotos
 * es peor que el producto anterior.
 *
 * @template T
 * @param callable():T $bloque
 * @return T
 */
function db_transaccion(callable $bloque): mixed
{
    $pdo = db();

    // Si ya hay una transacción abierta más arriba, esta llamada se
    // suma a ella en vez de abrir otra: PDO no soporta anidarlas.
    if ($pdo->inTransaction()) {
        return $bloque();
    }

    $pdo->beginTransaction();

    try {
        $resultado = $bloque();
        $pdo->commit();

        return $resultado;
    } catch (Throwable $e) {
        $pdo->rollBack();

        throw $e;
    }
}
