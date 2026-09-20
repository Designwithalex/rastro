<?php
/**
 * ============================================================================
 * repository-mysql.php — los datos, leídos de MySQL en vez de data/*.json
 * ============================================================================
 *
 * POR QUÉ ESTE ARCHIVO EXISTE Y NO SON 62 FUNCIONES REESCRITAS
 *
 * Todo el acceso a datos del sitio pasa por dos funciones: `_repo_json()`
 * para leer y `_repo_escribir_json()` para escribir. Las 62 funciones
 * `repo_*` de repository.php y repository-escritura.php no saben de dónde
 * salen los datos: reciben arrays y devuelven arrays.
 *
 * Así que la migración no es reescribir 62 funciones. Es reimplementar esas
 * dos, y que las 62 sigan como están. Menos código, menos superficie de
 * error, y el comportamiento del sitio no cambia: las vistas reciben
 * exactamente lo mismo que recibían del JSON.
 *
 * LA REGLA DE ESTE ARCHIVO
 *
 *   Lo que devuelve `_repo_mysql_leer('products')` tiene que ser IDÉNTICO
 *   —mismas claves, mismo orden, mismos tipos de PHP— a lo que devolvía
 *   `json_decode(products.json)`. No parecido: idéntico.
 *
 * Eso no es purismo. Una vista que hace `$p['activo'] === true` funciona
 * con `true` y falla con `1`, y MySQL devuelve `1`. Lo mismo con `peso_kg`:
 * la columna es DECIMAL y PDO la entrega como la cadena "10.00" donde el
 * JSON tenía el entero 10. Cada conversión de acá abajo tapa uno de esos
 * agujeros, y `bin/verificar-paridad.php` los compara campo por campo.
 *
 * DÓNDE ESTÁ EL INTERRUPTOR
 *
 * En `db_activa()` (app/db.php). Si `app/config.php` no tiene credenciales,
 * nada de este archivo se ejecuta y el sitio lee los JSON como siempre.
 */

declare(strict_types=1);

/* ==========================================================================
   Conversiones de tipo

   MySQL no tiene booleanos y PDO entrega los DECIMAL como cadena. Las tres
   funciones de acá abajo son las que devuelven los valores a la forma que
   tenían en el JSON.
   ========================================================================== */

/** TINYINT(1) -> bool. La columna nunca es NULL en estas tablas. */
function _repo_my_bool(mixed $v): bool
{
    return (bool) (int) $v;
}

/**
 * DECIMAL -> int si es un número redondo, float si no, null si es NULL.
 *
 * `peso_kg` vale 10 en el JSON y "10.00" en la base; `descuento_pct` es
 * null en casi todos los productos y 12.5 en alguno. Un `(float)` a secas
 * convertiría el 10 en 10.0, que json_encode escribe como "10.0" y rompe
 * la comparación con el archivo original.
 */
function _repo_my_num(mixed $v): int|float|null
{
    if ($v === null || $v === '') {
        return null;
    }

    $f = (float) $v;

    return $f == (int) $f ? (int) $f : $f;
}

/**
 * Cadena vacía -> null.
 *
 * La migración guardó '' donde el JSON tenía null, porque en MySQL una
 * columna VARCHAR NOT NULL no puede guardar otra cosa. `empresa` y `cuit`
 * de un usuario particular son los casos reales.
 */
function _repo_my_nulo(mixed $v): ?string
{
    $v = (string) ($v ?? '');

    return $v === '' ? null : $v;
}

/* ==========================================================================
   El despachador
   ========================================================================== */

/**
 * Devuelve una entidad entera con la misma forma que tenía su JSON.
 *
 * El nombre que recibe es el del archivo que reemplaza —'products',
 * 'categories'— y no el de la tabla, porque quien llama es `_repo_json()`
 * y ese es el vocabulario del resto del proyecto.
 *
 * @throws RuntimeException si le piden una entidad que todavía no migró.
 */
function _repo_mysql_leer(string $archivo): array
{
    return match ($archivo) {
        'products'   => _repo_my_products(),
        'categories' => _repo_my_categories(),
        'brands'     => _repo_my_brands(),
        'clients'    => _repo_my_clients(),
        'banners'    => _repo_my_banners(),
        'settings'   => _repo_my_settings(),
        'users'      => _repo_my_users(),
        'orders'     => _repo_my_orders(),
        'nosotros'   => _repo_my_nosotros(),
        default      => throw new RuntimeException(
            "repository-mysql: no sé leer '$archivo' de la base."
        ),
    };
}

/* ==========================================================================
   Catálogo
   ========================================================================== */

/**
 * Los productos, con sus imágenes y especificaciones adentro.
 *
 * Son tres tablas y se resuelven en tres consultas, no en una con JOIN:
 * un producto con 3 imágenes y 6 especificaciones devolvería 18 filas que
 * después hay que desduplicar en PHP. Tres consultas y dos bucles es más
 * corto de leer y más barato de correr.
 *
 * `categoria` y `marca` salen como slug, no como id: es lo que tenía el
 * JSON y es lo que las vistas usan para armar los enlaces.
 */
function _repo_my_products(): array
{
    // Las hijas primero, agrupadas por producto.
    $imagenes = [];
    foreach (db_q('SELECT producto_id, ruta FROM producto_imagenes ORDER BY producto_id, orden, id') as $f) {
        $imagenes[(int) $f['producto_id']][] = (string) $f['ruta'];
    }

    $especificaciones = [];
    foreach (db_q('SELECT producto_id, etiqueta, valor FROM producto_especificaciones ORDER BY producto_id, orden, id') as $f) {
        // En el JSON la clave es `label`, no `etiqueta`. El contrato con las
        // vistas manda sobre el nombre de la columna.
        $especificaciones[(int) $f['producto_id']][] = [
            'label' => (string) $f['etiqueta'],
            'valor' => (string) $f['valor'],
        ];
    }

    $filas = db_q(
        'SELECT p.id, p.slug, p.sku, p.nombre,
                c.slug AS categoria, m.slug AS marca,
                p.descripcion_corta, p.descripcion,
                p.precio_lista, p.precio_mayorista, p.descuento_pct,
                p.stock, p.destacado, p.nuevo, p.imagen, p.peso_kg, p.activo
           FROM productos p
           LEFT JOIN categorias c ON c.id = p.categoria_id
           LEFT JOIN marcas     m ON m.id = p.marca_id
          ORDER BY p.id'
    )->fetchAll();

    $salida = [];

    foreach ($filas as $f) {
        $id = (int) $f['id'];

        /* El orden de las claves es el del JSON original a propósito:
           `imagenes` y `especificaciones` van entre `imagen` y `peso_kg`.
           Dos arrays con las mismas claves en otro orden son == pero no
           ===, y la verificación de paridad compara en serio. */
        $salida[] = [
            'id'                => $id,
            'slug'              => (string) $f['slug'],
            'sku'               => (string) $f['sku'],
            'nombre'            => (string) $f['nombre'],
            'categoria'         => (string) $f['categoria'],
            'marca'             => (string) $f['marca'],
            'descripcion_corta' => (string) $f['descripcion_corta'],
            'descripcion'       => (string) $f['descripcion'],
            'precio_lista'      => (int) $f['precio_lista'],
            'precio_mayorista'  => (int) $f['precio_mayorista'],
            'descuento_pct'     => _repo_my_num($f['descuento_pct']),
            'stock'             => (int) $f['stock'],
            'destacado'         => _repo_my_bool($f['destacado']),
            'nuevo'             => _repo_my_bool($f['nuevo']),
            'imagen'            => (string) $f['imagen'],
            'imagenes'          => $imagenes[$id] ?? [],
            'especificaciones'  => $especificaciones[$id] ?? [],
            'peso_kg'           => _repo_my_num($f['peso_kg']),
            'activo'            => _repo_my_bool($f['activo']),
        ];
    }

    return $salida;
}

/**
 * Las categorías, con la cuenta de productos activos de cada una.
 *
 * `productos_count` en el JSON era un número escrito al lado de los datos
 * que lo contradicen —el problema que `repo_recount_categories()` existe
 * para arreglar a mano—. Acá se calcula en la misma consulta y no puede
 * quedar desactualizado nunca más.
 *
 * Cuenta sólo los activos: es lo que hace el catálogo, y una categoría que
 * anuncia 6 productos y muestra 5 es un bug visible.
 */
function _repo_my_categories(): array
{
    $filas = db_q(
        'SELECT c.id, c.slug, c.nombre, c.descripcion, c.pictograma, c.orden,
                COUNT(p.id) AS productos_count
           FROM categorias c
           LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
          GROUP BY c.id, c.slug, c.nombre, c.descripcion, c.pictograma, c.orden
          ORDER BY c.id'
    )->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'              => (int) $f['id'],
        'slug'            => (string) $f['slug'],
        'nombre'          => (string) $f['nombre'],
        'descripcion'     => (string) $f['descripcion'],
        'pictograma'      => (string) $f['pictograma'],
        'orden'           => (int) $f['orden'],
        'productos_count' => (int) $f['productos_count'],
    ], $filas);
}

function _repo_my_brands(): array
{
    $filas = db_q('SELECT id, slug, nombre, logo, orden, es_propia FROM marcas ORDER BY id')->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'        => (int) $f['id'],
        'slug'      => (string) $f['slug'],
        'nombre'    => (string) $f['nombre'],
        'logo'      => (string) $f['logo'],
        'orden'     => (int) $f['orden'],
        'es_propia' => _repo_my_bool($f['es_propia']),
    ], $filas);
}

function _repo_my_clients(): array
{
    $filas = db_q('SELECT id, nombre, logo, orden FROM clientes ORDER BY id')->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'     => (int) $f['id'],
        'nombre' => (string) $f['nombre'],
        'logo'   => (string) $f['logo'],
        'orden'  => (int) $f['orden'],
    ], $filas);
}

function _repo_my_banners(): array
{
    $filas = db_q('SELECT id, titulo, imagen, enlace, posicion, activo, orden FROM banners ORDER BY id')->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'       => (int) $f['id'],
        'titulo'   => (string) $f['titulo'],
        'imagen'   => (string) $f['imagen'],
        'enlace'   => (string) $f['enlace'],
        'posicion' => (string) $f['posicion'],
        'activo'   => _repo_my_bool($f['activo']),
        'orden'    => (int) $f['orden'],
    ], $filas);
}

/* ==========================================================================
   Configuración y contenido
   ========================================================================== */

/**
 * Los ajustes, como mapa clave => valor.
 *
 * Es la única entidad que no es una lista: el JSON era un objeto y las
 * vistas hacen `$settings['whatsapp']`.
 *
 * La tabla guarda todo como texto, pero el JSON tenía enteros en
 * `descuento_transferencia_pct` y `envio_gratis_desde`, y esos dos números
 * entran en cuentas de plata. Se recuperan mirando el valor: si son
 * dígitos y nada más, vuelven a ser int.
 *
 * Ojo si mañana aparece un ajuste que es dígitos pero NO es un número
 * —un CUIT sin guiones, un código postal—: habría que exceptuarlo acá.
 * Los diez de hoy están cubiertos; el CUIT actual lleva guiones y no cae
 * en la trampa.
 */
function _repo_my_settings(): array
{
    $salida = [];

    foreach (db_q('SELECT clave, valor FROM settings ORDER BY clave') as $f) {
        $valor = (string) $f['valor'];

        $salida[(string) $f['clave']] = ctype_digit($valor) ? (int) $valor : $valor;
    }

    return $salida;
}

/**
 * El contenido de "Nosotros", que es un documento y no una tabla.
 *
 * Son diez secciones anidadas con listas adentro: normalizarlo serían seis
 * tablas que nadie consulta por separado —siempre se pide el paquete
 * entero— y un JOIN de seis vías para dibujar una página. Vive como
 * documento en una columna, que es lo que es.
 */
function _repo_my_nosotros(): array
{
    $fila = db_q('SELECT contenido FROM nosotros ORDER BY id LIMIT 1')->fetch();

    if (!$fila) {
        return [];
    }

    $datos = json_decode((string) $fila['contenido'], true);

    return is_array($datos) ? $datos : [];
}

/* ==========================================================================
   Cuentas y pedidos
   ========================================================================== */

/**
 * Los usuarios, con su dirección principal adentro.
 *
 * Devuelve el `password_hash`: esta función es el reemplazo del archivo, no
 * de la API pública. Quien filtra el hash antes de que salga del repository
 * es `_repo_usuario_publico()`, y sigue haciéndolo igual que antes.
 */
function _repo_my_users(): array
{
    $direcciones = [];

    foreach (db_q('SELECT usuario_id, calle, ciudad, provincia, codigo_postal
                     FROM direcciones
                    ORDER BY usuario_id, principal DESC, id') as $f) {
        $uid = (int) $f['usuario_id'];

        // La primera que aparece es la principal, por el ORDER BY.
        if (!isset($direcciones[$uid])) {
            $direcciones[$uid] = [
                'calle'         => (string) $f['calle'],
                'ciudad'        => (string) $f['ciudad'],
                'provincia'     => (string) $f['provincia'],
                'codigo_postal' => (string) $f['codigo_postal'],
            ];
        }
    }

    $filas = db_q(
        'SELECT id, nombre, apellido, email, password_hash, telefono,
                rol, empresa, cuit, creado, activo
           FROM usuarios
          ORDER BY id'
    )->fetchAll();

    return array_map(static function (array $f) use ($direcciones): array {
        $id = (int) $f['id'];

        return [
            'id'            => $id,
            'nombre'        => (string) $f['nombre'],
            'apellido'      => (string) $f['apellido'],
            'email'         => (string) $f['email'],
            'password_hash' => (string) $f['password_hash'],
            'telefono'      => (string) $f['telefono'],
            'rol'           => (string) $f['rol'],
            'empresa'       => _repo_my_nulo($f['empresa']),
            'cuit'          => _repo_my_nulo($f['cuit']),
            'direccion'     => $direcciones[$id] ?? null,
            'creado'        => (string) $f['creado'],
            'activo'        => _repo_my_bool($f['activo']),
        ];
    }, $filas);
}

/**
 * Los pedidos con sus items.
 *
 * El `id` de la tabla no sale: el JSON no lo tenía y la clave de un pedido
 * para todo el resto del código es `codigo` (RF-2026-0418). El id es
 * interno, sirve para la llave foránea de pedido_items y termina ahí.
 *
 * `precio_unitario` y `descuento_aplicado_pct` se leen del pedido y NO se
 * recalculan contra el producto: el precio de hoy no es el precio al que
 * se vendió en abril. Es la misma regla que ya documentaba repository.php.
 */
function _repo_my_orders(): array
{
    $items = [];

    foreach (db_q('SELECT pedido_id, producto_id, nombre, sku, cantidad, precio_unitario
                     FROM pedido_items
                    ORDER BY pedido_id, id') as $f) {
        $items[(int) $f['pedido_id']][] = [
            'producto_id'     => (int) $f['producto_id'],
            'nombre'          => (string) $f['nombre'],
            'sku'             => (string) $f['sku'],
            'cantidad'        => (int) $f['cantidad'],
            'precio_unitario' => (int) $f['precio_unitario'],
        ];
    }

    /* Sólo los de la maqueta. Los del checkout real viven en la misma
       tabla con origen = 'sitio' y se leen por _repo_my_pedidos_leer(),
       porque antes de la migración eran otro archivo y el panel los
       distingue. */
    $filas = db_q(
        "SELECT id, codigo, usuario_id, fecha, estado, medio_pago,
                subtotal, envio, descuento_aplicado_pct, total
           FROM pedidos
          WHERE origen = 'mock'
          ORDER BY id"
    )->fetchAll();

    return array_map(static fn (array $f): array => [
        'codigo'                 => (string) $f['codigo'],
        'usuario_id'             => (int) $f['usuario_id'],
        'fecha'                  => (string) $f['fecha'],
        'estado'                 => (string) $f['estado'],
        'medio_pago'             => (string) $f['medio_pago'],
        'items'                  => $items[(int) $f['id']] ?? [],
        'subtotal'               => (int) $f['subtotal'],
        'envio'                  => (int) $f['envio'],
        'descuento_aplicado_pct' => _repo_my_num($f['descuento_aplicado_pct']),
        'total'                  => (int) $f['total'],
    ], $filas);
}

/* ==========================================================================
   ESCRITURA

   `_repo_escribir_json()` recibe siempre la colección ENTERA ya modificada:
   las funciones `repo_save_…` y `repo_delete_…` leen todo, cambian un
   registro y mandan el conjunto de vuelta. Así que estas funciones no
   reciben "guardá este producto" sino "que la tabla quede así".

   El patrón es el mismo en todas: upsert de lo que viene y borrado de lo que
   ya no está, dentro de una transacción. No se vacía y se recarga la tabla
   porque los ids son el destino de ocho llaves foráneas —un pedido apunta a
   sus productos— y porque un DELETE masivo seguido de un INSERT deja, en el
   medio, un catálogo vacío que otro visitante puede llegar a leer.
   ========================================================================== */

/**
 * Guarda una entidad entera. Devuelve false si no pudo, como la versión
 * de archivos: la vista tiene que poder avisar "no se guardó".
 */
function _repo_mysql_guardar(string $archivo, array $datos): bool
{
    try {
        return (bool) db_transaccion(static function () use ($archivo, $datos): bool {
            match ($archivo) {
                'products'   => _repo_my_guardar_products($datos),
                'categories' => _repo_my_guardar_simple('categorias', $datos,
                                    ['slug', 'nombre', 'descripcion', 'pictograma', 'orden']),
                'brands'     => _repo_my_guardar_simple('marcas', $datos,
                                    ['slug', 'nombre', 'logo', 'orden', 'es_propia']),
                'clients'    => _repo_my_guardar_simple('clientes', $datos,
                                    ['nombre', 'logo', 'orden']),
                'banners'    => _repo_my_guardar_simple('banners', $datos,
                                    ['titulo', 'imagen', 'enlace', 'posicion', 'activo', 'orden']),
                'settings'   => _repo_my_guardar_settings($datos),
                'users'      => _repo_my_guardar_users($datos),
                'orders'     => _repo_my_guardar_orders($datos),
                'nosotros'   => _repo_my_guardar_nosotros($datos),
                default      => throw new RuntimeException(
                    "repository-mysql: no sé guardar '$archivo' en la base."
                ),
            };

            return true;
        });
    } catch (Throwable $e) {
        error_log('repository-mysql: falló al guardar ' . $archivo . ': ' . $e->getMessage());

        return false;
    }
}

/**
 * Convierte un valor de PHP a algo que PDO pueda mandar.
 *
 * Los bool tienen que viajar como 1/0: PDO manda `false` como cadena vacía
 * y MySQL la guarda como 0 en un TINYINT, pero como '' en un VARCHAR. Es un
 * error que no falla, sólo guarda mal.
 */
function _repo_my_param(mixed $v): mixed
{
    return is_bool($v) ? (int) $v : $v;
}

/**
 * El caso general: tablas planas con `id`, sin hijas.
 *
 * @param list<string> $columnas  Las columnas a escribir, con el mismo
 *                                nombre que la clave del array. Alcanza
 *                                para categorías, marcas, clientes y
 *                                banners, donde el JSON y la tabla usan
 *                                los mismos nombres.
 */
function _repo_my_guardar_simple(string $tabla, array $datos, array $columnas): void
{
    $ids = [];

    foreach ($datos as $r) {
        $valores = [];
        foreach ($columnas as $c) {
            $valores[] = _repo_my_param($r[$c] ?? null);
        }

        $asignaciones = implode(', ', array_map(static fn ($c) => "$c = VALUES($c)", $columnas));
        $marcas       = implode(', ', array_fill(0, count($columnas) + 1, '?'));

        db_q(
            "INSERT INTO $tabla (id, " . implode(', ', $columnas) . ")
             VALUES ($marcas)
             ON DUPLICATE KEY UPDATE $asignaciones",
            array_merge([(int) ($r['id'] ?? 0)], $valores)
        );

        $ids[] = (int) ($r['id'] ?? 0);
    }

    _repo_my_borrar_sobrantes($tabla, 'id', $ids);
}

/**
 * Borra lo que ya no está en la colección que llegó.
 *
 * Con la lista vacía borra la tabla entera, que es lo correcto: si el panel
 * manda cero banners, es que los borró a todos.
 *
 * @param list<int|string> $conservar
 */
function _repo_my_borrar_sobrantes(string $tabla, string $clave, array $conservar): void
{
    if ($conservar === []) {
        db_q("DELETE FROM $tabla");

        return;
    }

    $marcas = implode(', ', array_fill(0, count($conservar), '?'));

    db_q("DELETE FROM $tabla WHERE $clave NOT IN ($marcas)", $conservar);
}

/**
 * Productos, con sus imágenes y especificaciones.
 *
 * `categoria` y `marca` llegan como slug y la tabla guarda ids, así que se
 * resuelven contra un mapa leído una sola vez. Un slug que no existe queda
 * en NULL en vez de reventar: es lo mismo que pasaba con el JSON, donde una
 * categoría inventada simplemente no matcheaba nada.
 *
 * Las hijas se borran y se reinsertan por producto en vez de intentar un
 * diff: son tres imágenes y seis especificaciones, y el diff costaría más
 * de leer que de correr.
 */
function _repo_my_guardar_products(array $datos): void
{
    $categorias = [];
    foreach (db_q('SELECT id, slug FROM categorias') as $f) {
        $categorias[(string) $f['slug']] = (int) $f['id'];
    }

    $marcas = [];
    foreach (db_q('SELECT id, slug FROM marcas') as $f) {
        $marcas[(string) $f['slug']] = (int) $f['id'];
    }

    $ids = [];

    foreach ($datos as $p) {
        $id = (int) ($p['id'] ?? 0);

        db_q(
            'INSERT INTO productos
                (id, slug, sku, nombre, categoria_id, marca_id, descripcion_corta,
                 descripcion, precio_lista, precio_mayorista, descuento_pct, stock,
                 destacado, nuevo, imagen, peso_kg, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                slug = VALUES(slug), sku = VALUES(sku), nombre = VALUES(nombre),
                categoria_id = VALUES(categoria_id), marca_id = VALUES(marca_id),
                descripcion_corta = VALUES(descripcion_corta), descripcion = VALUES(descripcion),
                precio_lista = VALUES(precio_lista), precio_mayorista = VALUES(precio_mayorista),
                descuento_pct = VALUES(descuento_pct), stock = VALUES(stock),
                destacado = VALUES(destacado), nuevo = VALUES(nuevo),
                imagen = VALUES(imagen), peso_kg = VALUES(peso_kg), activo = VALUES(activo)',
            [
                $id,
                (string) ($p['slug'] ?? ''),
                (string) ($p['sku'] ?? ''),
                (string) ($p['nombre'] ?? ''),
                $categorias[(string) ($p['categoria'] ?? '')] ?? null,
                $marcas[(string) ($p['marca'] ?? '')] ?? null,
                (string) ($p['descripcion_corta'] ?? ''),
                (string) ($p['descripcion'] ?? ''),
                (int) ($p['precio_lista'] ?? 0),
                (int) ($p['precio_mayorista'] ?? 0),
                $p['descuento_pct'] ?? null,
                (int) ($p['stock'] ?? 0),
                _repo_my_param((bool) ($p['destacado'] ?? false)),
                _repo_my_param((bool) ($p['nuevo'] ?? false)),
                (string) ($p['imagen'] ?? ''),
                $p['peso_kg'] ?? null,
                _repo_my_param((bool) ($p['activo'] ?? true)),
            ]
        );

        $ids[] = $id;

        db_q('DELETE FROM producto_imagenes WHERE producto_id = ?', [$id]);

        foreach (array_values((array) ($p['imagenes'] ?? [])) as $orden => $ruta) {
            db_q(
                'INSERT INTO producto_imagenes (producto_id, ruta, orden) VALUES (?,?,?)',
                [$id, (string) $ruta, $orden]
            );
        }

        db_q('DELETE FROM producto_especificaciones WHERE producto_id = ?', [$id]);

        foreach (array_values((array) ($p['especificaciones'] ?? [])) as $orden => $e) {
            db_q(
                'INSERT INTO producto_especificaciones (producto_id, etiqueta, valor, orden)
                 VALUES (?,?,?,?)',
                [$id, (string) ($e['label'] ?? ''), (string) ($e['valor'] ?? ''), $orden]
            );
        }
    }

    _repo_my_borrar_sobrantes('productos', 'id', $ids);
}

/**
 * Los ajustes. La tabla es clave/valor, así que el mapa entra tal cual.
 *
 * `productos_count` de categorías no tiene equivalente acá y no hace falta:
 * la lectura lo calcula con un COUNT. Por eso `repo_recount_categories()`
 * sigue funcionando y no hace nada, que es lo correcto.
 */
function _repo_my_guardar_settings(array $datos): void
{
    $claves = [];

    foreach ($datos as $clave => $valor) {
        db_q(
            'INSERT INTO settings (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [(string) $clave, (string) _repo_my_param($valor)]
        );

        $claves[] = (string) $clave;
    }

    _repo_my_borrar_sobrantes('settings', 'clave', $claves);
}

/** El documento de "Nosotros", entero, en su única fila. */
function _repo_my_guardar_nosotros(array $datos): void
{
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('no se pudo serializar el contenido de nosotros');
    }

    db_q(
        'INSERT INTO nosotros (id, contenido) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE contenido = VALUES(contenido)',
        [$json]
    );
}

/**
 * Usuarios y su dirección principal.
 *
 * `direccion` puede venir en null —un usuario que nunca cargó uno— y en ese
 * caso la fila de direcciones se borra en vez de guardarse vacía.
 */
function _repo_my_guardar_users(array $datos): void
{
    $ids = [];

    foreach ($datos as $u) {
        $id = (int) ($u['id'] ?? 0);

        db_q(
            'INSERT INTO usuarios
                (id, nombre, apellido, email, password_hash, telefono, rol,
                 empresa, cuit, creado, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre), apellido = VALUES(apellido), email = VALUES(email),
                password_hash = VALUES(password_hash), telefono = VALUES(telefono),
                rol = VALUES(rol), empresa = VALUES(empresa), cuit = VALUES(cuit),
                creado = VALUES(creado), activo = VALUES(activo)',
            [
                $id,
                (string) ($u['nombre'] ?? ''),
                (string) ($u['apellido'] ?? ''),
                (string) ($u['email'] ?? ''),
                (string) ($u['password_hash'] ?? ''),
                (string) ($u['telefono'] ?? ''),
                (string) ($u['rol'] ?? 'cliente'),
                (string) ($u['empresa'] ?? ''),
                (string) ($u['cuit'] ?? ''),
                (string) ($u['creado'] ?? date('Y-m-d')),
                _repo_my_param((bool) ($u['activo'] ?? true)),
            ]
        );

        $ids[] = $id;

        db_q('DELETE FROM direcciones WHERE usuario_id = ?', [$id]);

        $d = $u['direccion'] ?? null;

        if (is_array($d) && $d !== []) {
            db_q(
                'INSERT INTO direcciones
                    (usuario_id, calle, ciudad, provincia, codigo_postal, principal)
                 VALUES (?,?,?,?,?,1)',
                [
                    $id,
                    (string) ($d['calle'] ?? ''),
                    (string) ($d['ciudad'] ?? ''),
                    (string) ($d['provincia'] ?? ''),
                    (string) ($d['codigo_postal'] ?? ''),
                ]
            );
        }
    }

    _repo_my_borrar_sobrantes('usuarios', 'id', $ids);
}

/**
 * Pedidos con sus items.
 *
 * La clave acá es `codigo`, no `id`: el JSON nunca tuvo id y todo el resto
 * del código identifica un pedido por RF-2026-0418. El id de la tabla es
 * interno y hay que releerlo después del upsert para poder insertar los
 * items.
 */
function _repo_my_guardar_orders(array $datos): void
{
    $codigos = [];

    foreach ($datos as $o) {
        $codigo = (string) ($o['codigo'] ?? '');

        if ($codigo === '') {
            continue;
        }

        db_q(
            "INSERT INTO pedidos
                (codigo, usuario_id, fecha, estado, medio_pago,
                 subtotal, envio, descuento_aplicado_pct, total, origen)
             VALUES (?,?,?,?,?,?,?,?,?,'mock')
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id), fecha = VALUES(fecha),
                estado = VALUES(estado), medio_pago = VALUES(medio_pago),
                subtotal = VALUES(subtotal), envio = VALUES(envio),
                descuento_aplicado_pct = VALUES(descuento_aplicado_pct),
                total = VALUES(total)",
            [
                $codigo,
                ($o['usuario_id'] ?? null) ?: null,
                (string) ($o['fecha'] ?? date('Y-m-d')),
                (string) ($o['estado'] ?? ''),
                (string) ($o['medio_pago'] ?? ''),
                (int) ($o['subtotal'] ?? 0),
                (int) ($o['envio'] ?? 0),
                $o['descuento_aplicado_pct'] ?? 0,
                (int) ($o['total'] ?? 0),
            ]
        );

        $codigos[] = $codigo;

        $id = (int) db_q('SELECT id FROM pedidos WHERE codigo = ?', [$codigo])->fetchColumn();

        db_q('DELETE FROM pedido_items WHERE pedido_id = ?', [$id]);

        foreach ((array) ($o['items'] ?? []) as $it) {
            db_q(
                'INSERT INTO pedido_items
                    (pedido_id, producto_id, nombre, sku, cantidad, precio_unitario)
                 VALUES (?,?,?,?,?,?)',
                [
                    $id,
                    ($it['producto_id'] ?? null) ?: null,
                    (string) ($it['nombre'] ?? ''),
                    (string) ($it['sku'] ?? ''),
                    (int) ($it['cantidad'] ?? 0),
                    (int) ($it['precio_unitario'] ?? 0),
                ]
            );
        }
    }

    /* El borrado de sobrantes va acotado a los 'mock' A PROPÓSITO, y es lo
       más peligroso de este archivo: `$datos` es la colección de la maqueta,
       así que un DELETE ... NOT IN sin el filtro de origen borraría todas
       las ventas reales la primera vez que alguien toque un pedido de
       ejemplo en el panel. */
    if ($codigos === []) {
        db_q("DELETE FROM pedidos WHERE origen = 'mock'");

        return;
    }

    $marcas = implode(', ', array_fill(0, count($codigos), '?'));

    db_q("DELETE FROM pedidos WHERE origen = 'mock' AND codigo NOT IN ($marcas)", $codigos);
}

/* ==========================================================================
   PEDIDOS DEL SITIO

   Los que crea el checkout, que antes vivían en data/pedidos.json y no
   pasaban por `_repo_json()`: tienen su propio par de funciones porque el
   webhook de Mercado Pago escribe sin sesión y sin caché.

   ACÁ ESTÁ EL MOTIVO DE TODA LA MIGRACIÓN.

   Con archivos, crear un pedido era: leer la lista entera, agregarle uno,
   y reescribirla. Si dos personas compran en el mismo segundo, las dos leen
   la misma lista, cada una le suma el suyo, y la que escribe segunda pisa a
   la primera. Un pedido cobrado que no existe en ningún lado.

   El LOCK_EX que tenía `_repo_pedidos_guardar()` no alcanzaba: serializa las
   escrituras, no el ciclo leer-modificar-escribir.

   Por eso estas funciones NO reescriben la colección. Insertan una fila y
   actualizan una fila. Dos compras simultáneas son dos INSERT y entran las
   dos, que es lo que hace una base de datos y un archivo no.
   ========================================================================== */

/** Decodifica una columna JSON a array, tolerando NULL y basura. */
function _repo_my_json(mixed $v): ?array
{
    if ($v === null || $v === '') {
        return null;
    }

    $d = json_decode((string) $v, true);

    return is_array($d) ? $d : null;
}

/**
 * Los pedidos que hizo el sitio, en el orden en que entraron.
 *
 * Reemplaza a `_repo_pedidos_leer()`. Devuelve la misma forma que tenía
 * data/pedidos.json, incluidos los tres bloques anidados del checkout.
 */
function _repo_my_pedidos_leer(): array
{
    $items = [];

    foreach (db_q("SELECT i.pedido_id, i.producto_id, i.nombre, i.sku,
                          i.cantidad, i.precio_unitario
                     FROM pedido_items i
                     JOIN pedidos p ON p.id = i.pedido_id
                    WHERE p.origen = 'sitio'
                    ORDER BY i.pedido_id, i.id") as $f) {
        $items[(int) $f['pedido_id']][] = [
            'producto_id'     => (int) $f['producto_id'],
            'nombre'          => (string) $f['nombre'],
            'sku'             => (string) $f['sku'],
            'cantidad'        => (int) $f['cantidad'],
            'precio_unitario' => (int) $f['precio_unitario'],
        ];
    }

    $filas = db_q(
        "SELECT id, codigo, usuario_id, fecha, creado, referencia, estado, medio_pago,
                subtotal, envio, descuento_aplicado_pct, total,
                comprador, entrega, pago, actualizado
           FROM pedidos
          WHERE origen = 'sitio'
          ORDER BY id"
    )->fetchAll();

    return array_map(static function (array $f) use ($items): array {
        $p = [
            'codigo'                 => (string) $f['codigo'],
            'usuario_id'             => (int) $f['usuario_id'],
            'fecha'                  => (string) $f['fecha'],
            'estado'                 => (string) $f['estado'],
            'medio_pago'             => (string) $f['medio_pago'],
            'items'                  => $items[(int) $f['id']] ?? [],
            'subtotal'               => (int) $f['subtotal'],
            'envio'                  => (int) $f['envio'],
            'descuento_aplicado_pct' => _repo_my_num($f['descuento_aplicado_pct']),
            'total'                  => (int) $f['total'],
            'comprador'              => _repo_my_json($f['comprador']),
            'entrega'                => _repo_my_json($f['entrega']),
            'pago'                   => _repo_my_json($f['pago']),
            'creado'                 => _repo_my_iso($f['creado']),
            'referencia'             => (string) $f['referencia'],
        ];

        /* `actualizado` sólo aparece después del primer cambio de estado.
           Un pedido recién creado no tenía esa clave en el archivo, así
           que tampoco la tiene acá: hay código que pregunta por su
           existencia para saber si el webhook ya contestó. */
        if ($f['actualizado'] !== null) {
            $p['actualizado'] = _repo_my_iso($f['actualizado']);
        }

        return $p;
    }, $filas);
}

/**
 * DATETIME de MySQL -> la cadena ISO-8601 que escribía date('c').
 *
 * El archivo guardaba "2026-09-20T14:03:11-03:00" y la columna guarda
 * "2026-09-20 14:03:11". Sin esta vuelta, un `substr($p['creado'], 0, 10)`
 * sigue andando pero un `strtotime()` comparado contra otro ISO no.
 */
function _repo_my_iso(mixed $v): string
{
    if ($v === null || $v === '') {
        return '';
    }

    $t = strtotime((string) $v);

    return $t === false ? (string) $v : date('c', $t);
}

/**
 * Inserta UN pedido. Devuelve false si no pudo.
 *
 * Una fila, no una colección: es la diferencia entre perder una compra
 * simultánea y no perderla. El código es UNIQUE en la tabla, así que dos
 * pedidos con el mismo código fallan en vez de pisarse.
 */
function _repo_my_pedido_insertar(array $p): bool
{
    try {
        return (bool) db_transaccion(static function () use ($p): bool {
            db_q(
                "INSERT INTO pedidos
                    (codigo, usuario_id, fecha, creado, referencia, estado, medio_pago,
                     subtotal, envio, descuento_aplicado_pct, total,
                     comprador, entrega, pago, origen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'sitio')",
                [
                    (string) ($p['codigo'] ?? ''),
                    ($p['usuario_id'] ?? null) ?: null,
                    (string) ($p['fecha'] ?? date('Y-m-d')),
                    // date('c') trae zona horaria y DATETIME no la guarda.
                    date('Y-m-d H:i:s', strtotime((string) ($p['creado'] ?? 'now')) ?: time()),
                    (string) ($p['referencia'] ?? $p['codigo'] ?? ''),
                    (string) ($p['estado'] ?? 'pendiente_pago'),
                    (string) ($p['medio_pago'] ?? ''),
                    (int) ($p['subtotal'] ?? 0),
                    (int) ($p['envio'] ?? 0),
                    $p['descuento_aplicado_pct'] ?? 0,
                    (int) ($p['total'] ?? 0),
                    _repo_my_json_col($p['comprador'] ?? null),
                    _repo_my_json_col($p['entrega'] ?? null),
                    _repo_my_json_col($p['pago'] ?? null),
                ]
            );

            $id = (int) db()->lastInsertId();

            foreach ((array) ($p['items'] ?? []) as $it) {
                db_q(
                    'INSERT INTO pedido_items
                        (pedido_id, producto_id, nombre, sku, cantidad, precio_unitario)
                     VALUES (?,?,?,?,?,?)',
                    [
                        $id,
                        ($it['producto_id'] ?? null) ?: null,
                        (string) ($it['nombre'] ?? ''),
                        (string) ($it['sku'] ?? ''),
                        (int) ($it['cantidad'] ?? 0),
                        (int) ($it['precio_unitario'] ?? 0),
                    ]
                );
            }

            return true;
        });
    } catch (Throwable $e) {
        error_log('repository-mysql: no se pudo insertar el pedido: ' . $e->getMessage());

        return false;
    }
}

/**
 * Actualiza las claves de primer nivel de UN pedido, por código.
 *
 * Los items no se tocan: lo que cambia después de creado un pedido es su
 * estado y su bloque `pago`, nunca lo que se compró.
 */
function _repo_my_pedido_actualizar(string $codigo, array $pedido): bool
{
    try {
        db_q(
            'UPDATE pedidos
                SET estado = ?, medio_pago = ?, referencia = ?,
                    subtotal = ?, envio = ?, descuento_aplicado_pct = ?, total = ?,
                    comprador = ?, entrega = ?, pago = ?, actualizado = ?
              WHERE codigo = ?',
            [
                (string) ($pedido['estado'] ?? ''),
                (string) ($pedido['medio_pago'] ?? ''),
                (string) ($pedido['referencia'] ?? $codigo),
                (int) ($pedido['subtotal'] ?? 0),
                (int) ($pedido['envio'] ?? 0),
                $pedido['descuento_aplicado_pct'] ?? 0,
                (int) ($pedido['total'] ?? 0),
                _repo_my_json_col($pedido['comprador'] ?? null),
                _repo_my_json_col($pedido['entrega'] ?? null),
                _repo_my_json_col($pedido['pago'] ?? null),
                isset($pedido['actualizado'])
                    ? date('Y-m-d H:i:s', strtotime((string) $pedido['actualizado']) ?: time())
                    : null,
                $codigo,
            ]
        );

        return true;
    } catch (Throwable $e) {
        error_log('repository-mysql: no se pudo actualizar el pedido ' . $codigo . ': ' . $e->getMessage());

        return false;
    }
}

/** Array -> columna JSON. null se guarda como NULL, no como "null". */
function _repo_my_json_col(mixed $v): ?string
{
    if (!is_array($v)) {
        return null;
    }

    $j = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $j === false ? null : $j;
}
