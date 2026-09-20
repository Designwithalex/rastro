<?php
/**
 * bin/aplicar-migracion.php — corre un archivo de db/migraciones/.
 *
 *   php bin/aplicar-migracion.php db/migraciones/001-pedidos-del-sitio.sql
 *
 * Una migración lleva una base QUE YA TIENE DATOS de una versión del
 * esquema a la siguiente, sin borrar nada. Es el camino que hay que usar
 * en producción: `bin/migrar.php --recrear` borra las tablas y recarga los
 * mocks, que sirve para montar una base de cero y arruina una que está
 * funcionando.
 *
 * Corre sentencia por sentencia y dentro de una transacción, así que un
 * archivo a medias no deja la base a medias. Ojo: MySQL hace COMMIT
 * implícito en cada ALTER TABLE, así que la transacción protege menos de
 * lo que parece. Por eso cada sentencia informa cómo salió y el script
 * frena en la primera que falle, en vez de seguir de largo.
 *
 * Una migración ya aplicada vuelve a fallar si se corre dos veces
 * ("Duplicate column name"), y eso es correcto: mejor un error ruidoso que
 * una columna duplicada en silencio.
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

$archivo = $argv[1] ?? '';

if ($archivo === '') {
    exit("Uso: php bin/aplicar-migracion.php db/migraciones/NNN-nombre.sql\n");
}

$ruta = str_starts_with($archivo, '/') ? $archivo : $raiz . '/' . $archivo;

if (!is_file($ruta)) {
    exit("✗ No existe $archivo\n");
}

if (!db_activa()) {
    exit("✗ No hay base configurada en app/config.php.\n");
}

echo 'Base: ' . db()->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo 'Archivo: ' . basename($ruta) . "\n\n";

$sql = (string) file_get_contents($ruta);

/* Se parte por punto y coma a final de línea. Alcanza para estos archivos:
   son ALTER y UPDATE, no hay procedimientos ni literales con ';' adentro.
   Los comentarios de línea se sacan antes para que no queden pegados a la
   sentencia siguiente. */
$sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

$sentencias = array_values(array_filter(
    array_map('trim', explode(";\n", $sinComentarios . "\n")),
    static fn (string $s): bool => trim($s, " \t\n\r;") !== ''
));

echo count($sentencias) . " sentencia(s).\n\n";

foreach ($sentencias as $i => $s) {
    $s      = rtrim($s, " \t\n\r;");
    $titulo = preg_replace('/\s+/', ' ', mb_substr($s, 0, 64));

    try {
        $n = db()->exec($s);
        printf("  [%d/%d] ✓ %s…  (%s filas)\n", $i + 1, count($sentencias), $titulo, var_export($n, true));
    } catch (Throwable $e) {
        printf("  [%d/%d] ✗ %s…\n", $i + 1, count($sentencias), $titulo);
        echo "\n✗ " . $e->getMessage() . "\n";
        echo "\nSe frenó acá. Las sentencias anteriores ya se aplicaron.\n";
        exit(1);
    }
}

echo "\nMigración aplicada.\n";
