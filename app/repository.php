<?php
/**
 * ============================================================================
 * repository.php — el único punto de entrada a los datos de Rastro Fitness.
 * ============================================================================
 *
 * REGLA DE ORO DEL PROYECTO
 *
 *   Ninguna vista lee un JSON. Ninguna vista arma una consulta. Toda lectura
 *   y toda escritura de datos pasa por una de las funciones `repo_*` de este
 *   archivo.
 *
 * Desde el 27/08/2026 este archivo habla con MySQL. Antes leía los mocks de
 * `data/*.json`. **Las firmas y la forma de las respuestas no cambiaron**, así
 * que ninguna de las once vistas del sitio se tocó al migrar: esa era
 * exactamente la apuesta del contrato, y funcionó.
 *
 * Los JSON de `data/` quedan como semilla histórica. Los carga una sola vez
 * `bin/migrar.php` y después no los edita nadie.
 *
 *   → Si una vista necesita un dato que acá no está, se agrega una función
 *     nueva a este archivo. Nunca un `file_get_contents` ni un `SELECT` en
 *     la vista.
 *   → El contrato completo está en `docs/DATA-CONTRACT.md`.
 *
 * CONVENCIONES QUE SOSTIENE ESTE ARCHIVO
 *
 *   · Las rutas de imagen son relativas a `assets/` y se resuelven en la
 *     vista con `asset()`. Ejemplo: "img/productos/foo.jpg".
 *   · Un producto con `activo = 0` no existe para el catálogo: no aparece en
 *     listados, ni en la ficha, ni en relacionados. Sí se resuelve por id en
 *     `repo_cart_items()` y sigue figurando en pedidos viejos.
 *   · Los precios son enteros en pesos. El descuento por transferencia NO se
 *     calcula acá: lo calcula `precio_con_descuento()` en helpers.php.
 *   · El copy editable no lleva números escritos a mano: lleva marcadores
 *     `{descuento}`, `{envio_gratis}`, `{whatsapp}` que la vista resuelve con
 *     `interpolar()`.
 *   · Un pedido guarda su propio `precio_unitario` y su propio
 *     `descuento_aplicado_pct`. Nunca se recalculan contra el producto: el
 *     precio de hoy no es el precio al que se vendió en abril.
 *   · Las funciones nunca devuelven el `password_hash` de un usuario.
 *
 * POR QUÉ TANTO CUIDADO CON LAS CONSULTAS
 *
 *   Todo valor que venga de afuera viaja como parámetro de una sentencia
 *   preparada. No hay una sola concatenación de datos en este archivo. Los
 *   únicos fragmentos que se arman con strings son nombres de columna para
 *   el ORDER BY, y salen de una lista blanca cerrada acá adentro.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ==========================================================================
   Privado. Nada de esto es parte del contrato.
   ========================================================================== */

/**
 * Las columnas del producto que salen a la vista, con los joins que
 * resuelven el slug de la categoría y el nombre de la marca.
 *
 * `marca_nombre` sale de la tabla COMPLETA de marcas, la línea propia
 * incluida: un producto siempre tiene que poder decir de qué marca es,
 * aunque esa marca no aparezca en la franja de vendedores oficiales.
 */
function _repo_select_producto(): string
{
    return 'SELECT p.*, c.slug AS categoria, m.slug AS marca, m.nombre AS marca_nombre
            FROM productos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN marcas     m ON m.id = p.marca_id';
}

/**
 * Convierte una fila de la base en el producto que esperan las vistas.
 *
 * MySQL devuelve los TINYINT como 0/1 y los DECIMAL como string. Las vistas
 * fueron escritas contra JSON, donde `destacado` era `true` y `peso_kg` era
 * un número. Ese casteo se hace acá, una sola vez, y no repartido por las
 * vistas con `== 1` y `(float)`.
 */
function _repo_fila_a_producto(array $f): array
{
    return [
        'id'                => (int) $f['id'],
        'slug'              => (string) $f['slug'],
        'sku'               => (string) $f['sku'],
        'nombre'            => (string) $f['nombre'],
        'categoria'         => $f['categoria'] !== null ? (string) $f['categoria'] : '',
        'marca'             => $f['marca'] !== null ? (string) $f['marca'] : '',
        'marca_nombre'      => $f['marca_nombre'] !== null ? (string) $f['marca_nombre'] : null,
        'descripcion_corta' => (string) ($f['descripcion_corta'] ?? ''),
        'descripcion'       => (string) ($f['descripcion'] ?? ''),
        'precio_lista'      => (int) $f['precio_lista'],
        'precio_mayorista'  => $f['precio_mayorista'] !== null ? (int) $f['precio_mayorista'] : null,
        'descuento_pct'     => $f['descuento_pct'] !== null ? (float) $f['descuento_pct'] : null,
        'stock'             => (int) $f['stock'],
        'destacado'         => (bool) $f['destacado'],
        'nuevo'             => (bool) $f['nuevo'],
        'imagen'            => (string) ($f['imagen'] ?? ''),
        'peso_kg'           => $f['peso_kg'] !== null ? (float) $f['peso_kg'] : null,
        'activo'            => (bool) $f['activo'],
        'ancho_px'          => $f['ancho_px'] !== null ? (int) $f['ancho_px'] : null,
        'alto_px'           => $f['alto_px'] !== null ? (int) $f['alto_px'] : null,
        'imagenes'          => [],
        'especificaciones'  => [],
    ];
}

/**
 * Le agrega a un lote de productos sus imágenes y sus especificaciones.
 *
 * DOS consultas para todo el lote, no dos por producto. Con doce productos
 * en la grilla del catálogo la diferencia es 2 consultas contra 24, y esa
 * es justamente la clase de detalle que no se nota con 30 productos de mock
 * y sí con el catálogo real.
 *
 * @param array<int,array> $productos indexados por id
 */
function _repo_completar_productos(array $productos): array
{
    if ($productos === []) {
        return $productos;
    }

    $ids   = array_keys($productos);
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $imagenes = db_q(
        "SELECT producto_id, ruta FROM producto_imagenes
         WHERE producto_id IN ($marks) ORDER BY producto_id, orden, id",
        $ids
    );

    foreach ($imagenes as $fila) {
        $productos[(int) $fila['producto_id']]['imagenes'][] = (string) $fila['ruta'];
    }

    $especs = db_q(
        "SELECT producto_id, etiqueta, valor FROM producto_especificaciones
         WHERE producto_id IN ($marks) ORDER BY producto_id, orden, id",
        $ids
    );

    foreach ($especs as $fila) {
        // La clave sigue siendo `label` y no `etiqueta`: así la escribieron
        // los mocks y así la leen la ficha y el panel. Cambiarla ahora sería
        // romper el contrato por una cuestión de gusto.
        $productos[(int) $fila['producto_id']]['especificaciones'][] = [
            'label' => (string) $fila['etiqueta'],
            'valor' => (string) $fila['valor'],
        ];
    }

    return $productos;
}

/**
 * Hidrata las filas de una consulta de productos, en orden.
 *
 * @param array<int,array> $filas
 */
function _repo_productos_desde_filas(array $filas): array
{
    $porId = [];
    $orden = [];

    foreach ($filas as $fila) {
        $id = (int) $fila['id'];
        $porId[$id] = _repo_fila_a_producto($fila);
        $orden[] = $id;
    }

    $porId = _repo_completar_productos($porId);

    // El orden lo decide la consulta, no el array asociativo.
    return array_map(static fn (int $id): array => $porId[$id], $orden);
}

/**
 * Saca el hash de contraseña de un usuario antes de que salga de acá, y le
 * arma la dirección con la forma que esperan las vistas.
 */
function _repo_fila_a_usuario(array $f): array
{
    $usuario = [
        'id'        => (int) $f['id'],
        'nombre'    => (string) $f['nombre'],
        'apellido'  => (string) $f['apellido'],
        'email'     => (string) $f['email'],
        'telefono'  => $f['telefono'] !== null ? (string) $f['telefono'] : null,
        'rol'       => (string) $f['rol'],
        'empresa'   => $f['empresa'] !== null ? (string) $f['empresa'] : null,
        'cuit'      => $f['cuit'] !== null ? (string) $f['cuit'] : null,
        'direccion' => null,
        'creado'    => (string) $f['creado'],
        'activo'    => (bool) $f['activo'],
    ];

    $dir = db_q(
        'SELECT calle, ciudad, provincia, codigo_postal FROM direcciones
         WHERE usuario_id = ? ORDER BY principal DESC, id LIMIT 1',
        [$usuario['id']]
    )->fetch();

    if ($dir !== false) {
        $usuario['direccion'] = [
            'calle'         => (string) $dir['calle'],
            'ciudad'        => (string) ($dir['ciudad'] ?? ''),
            'provincia'     => (string) ($dir['provincia'] ?? ''),
            'codigo_postal' => (string) ($dir['codigo_postal'] ?? ''),
        ];
    }

    return $usuario;
}

/**
 * Normaliza un correo para comparar y para guardar.
 * La columna es utf8mb4_unicode_ci, así que la base ya no distingue
 * mayúsculas; esto deja además la forma canónica en la tabla.
 */
function _repo_normalizar_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

/* ==========================================================================
   Catálogo
   ========================================================================== */

/**
 * Listado paginado del catálogo.
 *
 * @param array $filters Acepta:
 *   q           string  texto libre: nombre, SKU o descripción
 *   categoria   string  slug de categoría
 *   marca       string  slug de marca
 *   precio_min  int     sobre el precio de lista
 *   precio_max  int     sobre el precio de lista
 *   orden       string  relevancia | precio_asc | precio_desc | nombre
 *   en_stock    bool    solo lo que tiene stock disponible
 *   destacado   bool    solo destacados
 *
 * @return array{items:array, total:int, pagina:int, paginas:int}
 */
function repo_products(array $filters = [], int $page = 1, int $perPage = 12): array
{
    $where  = ['p.activo = 1'];
    $params = [];

    if (!empty($filters['categoria'])) {
        $where[]  = 'c.slug = ?';
        $params[] = (string) $filters['categoria'];
    }

    if (!empty($filters['marca'])) {
        $where[]  = 'm.slug = ?';
        $params[] = (string) $filters['marca'];
    }

    if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
        /* LIKE y no FULLTEXT a propósito. La búsqueda del sitio tiene que
           encontrar "disco" adentro de "discos" y "bumper" adentro de
           "RS-DB-010"; FULLTEXT trabaja por palabras completas y tiene un
           largo mínimo de token. Con la colación utf8mb4_unicode_ci esto
           además ignora acentos y mayúsculas, que es lo que hacía
           _repo_normalizar() en los mocks.

           TODO(backend): si el catálogo pasa de unos miles de productos,
           esto es un escaneo de tabla y conviene un índice FULLTEXT con
           ngram, o Meilisearch. Con 30 productos no tiene sentido. */
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $filters['q'])) . '%';

        $where[] = '(p.nombre LIKE ? OR p.sku LIKE ? OR p.descripcion_corta LIKE ?
                     OR p.descripcion LIKE ? OR c.nombre LIKE ? OR m.nombre LIKE ?)';
        array_push($params, $q, $q, $q, $q, $q, $q);
    }

    if (isset($filters['precio_min']) && $filters['precio_min'] !== '' && $filters['precio_min'] !== null) {
        $where[]  = 'p.precio_lista >= ?';
        $params[] = (int) $filters['precio_min'];
    }

    if (isset($filters['precio_max']) && $filters['precio_max'] !== '' && $filters['precio_max'] !== null) {
        $where[]  = 'p.precio_lista <= ?';
        $params[] = (int) $filters['precio_max'];
    }

    if (!empty($filters['en_stock'])) {
        $where[] = 'p.stock > 0';
    }

    if (!empty($filters['destacado'])) {
        $where[] = 'p.destacado = 1';
    }

    $sql_where = 'WHERE ' . implode(' AND ', $where);

    /* Lista blanca cerrada. `orden` viene de la URL y es el único pedazo de
       la consulta que se arma con un string, así que no puede salir de
       ningún lado que no sea este array.

       "relevancia" es el orden comercial: primero lo que tiene stock,
       después lo destacado, después lo nuevo. */
    $ordenes = [
        'relevancia'  => '(p.stock > 0) DESC, p.destacado DESC, p.nuevo DESC, p.id ASC',
        'precio_asc'  => 'p.precio_lista ASC, p.id ASC',
        'precio_desc' => 'p.precio_lista DESC, p.id ASC',
        'nombre'      => 'p.nombre ASC, p.id ASC',
    ];

    $sql_orden = $ordenes[(string) ($filters['orden'] ?? 'relevancia')] ?? $ordenes['relevancia'];

    $total = (int) db_q(
        'SELECT COUNT(*) FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas     m ON m.id = p.marca_id
         ' . $sql_where,
        $params
    )->fetchColumn();

    $perPage = max(1, $perPage);
    $paginas = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $paginas);

    /* LIMIT y OFFSET van interpolados y no como parámetros: con
       ATTR_EMULATE_PREPARES en false, MySQL los recibe como string y
       rechaza la sentencia. Son enteros que ya pasaron por (int) y por el
       recorte de arriba, así que no hay valor de afuera acá. */
    $limit  = (int) $perPage;
    $offset = (int) (($page - 1) * $perPage);

    $filas = db_q(
        _repo_select_producto() . ' ' . $sql_where . " ORDER BY $sql_orden LIMIT $limit OFFSET $offset",
        $params
    )->fetchAll();

    return [
        'items'   => _repo_productos_desde_filas($filas),
        'total'   => $total,
        'pagina'  => $page,
        'paginas' => $paginas,
    ];
}

/**
 * Un producto por su slug. null si no existe o si está dado de baja.
 */
function repo_product(string $slug): ?array
{
    $fila = db_q(
        _repo_select_producto() . ' WHERE p.slug = ? AND p.activo = 1 LIMIT 1',
        [$slug]
    )->fetch();

    if ($fila === false) {
        return null;
    }

    return _repo_productos_desde_filas([$fila])[0];
}

/**
 * Productos de la misma categoría, sin repetir el que se está viendo.
 * Primero los de la misma marca y los destacados.
 */
function repo_related_products(string $slug, int $limit = 4): array
{
    $actual = db_q(
        'SELECT id, categoria_id, marca_id FROM productos WHERE slug = ? AND activo = 1 LIMIT 1',
        [$slug]
    )->fetch();

    if ($actual === false || $limit <= 0) {
        return [];
    }

    $limit = (int) $limit;

    $filas = db_q(
        _repo_select_producto() . '
         WHERE p.activo = 1 AND p.categoria_id <=> ? AND p.id <> ?
         ORDER BY (p.marca_id <=> ?) DESC, (p.stock > 0) DESC, p.destacado DESC, p.id ASC
         LIMIT ' . $limit,
        [$actual['categoria_id'], (int) $actual['id'], $actual['marca_id']]
    )->fetchAll();

    return _repo_productos_desde_filas($filas);
}

/**
 * Categorías del catálogo, ordenadas.
 *
 * `productos_count` se resuelve con un LEFT JOIN agrupado y no con un bucle
 * por categoría: un número que miente en la grilla de la home es peor que no
 * tenerlo, y contar 30 veces para dibujar 6 celdas no tiene sentido.
 */
function repo_categories(): array
{
    $filas = db_q(
        'SELECT c.id, c.slug, c.nombre, c.descripcion, c.pictograma, c.orden,
                COUNT(p.id) AS productos_count
         FROM categorias c
         LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
         GROUP BY c.id
         ORDER BY c.orden ASC, c.id ASC'
    )->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'              => (int) $f['id'],
        'slug'            => (string) $f['slug'],
        'nombre'          => (string) $f['nombre'],
        'descripcion'     => (string) ($f['descripcion'] ?? ''),
        'pictograma'      => (string) ($f['pictograma'] ?? ''),
        'orden'           => (int) $f['orden'],
        'productos_count' => (int) $f['productos_count'],
    ], $filas);
}

/**
 * Una categoría por su slug.
 */
function repo_category(string $slug): ?array
{
    foreach (repo_categories() as $categoria) {
        if ($categoria['slug'] === $slug) {
            return $categoria;
        }
    }

    return null;
}

/* ==========================================================================
   Marca y prueba social
   ========================================================================== */

/**
 * Marcas ordenadas.
 *
 * Por defecto deja afuera la línea propia (`es_propia = 1`): esta función
 * alimenta la franja de prueba social de la home, y ahí Rastro no va, porque
 * uno no es vendedor oficial de sí mismo.
 *
 * Con `$incluir_propias = true` devuelve todas. La usa el filtro de marca
 * del catálogo, que hace la pregunta contraria —"¿de qué marca es este
 * producto?"— y sin la propia esconde dos tercios del catálogo.
 */
function repo_brands(bool $incluir_propias = false): array
{
    $sql = 'SELECT id, slug, nombre, logo, orden, es_propia FROM marcas';

    if (!$incluir_propias) {
        $sql .= ' WHERE es_propia = 0';
    }

    $filas = db_q($sql . ' ORDER BY orden ASC, id ASC')->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'        => (int) $f['id'],
        'slug'      => (string) $f['slug'],
        'nombre'    => (string) $f['nombre'],
        'logo'      => (string) ($f['logo'] ?? ''),
        'orden'     => (int) $f['orden'],
        'es_propia' => (bool) $f['es_propia'],
    ], $filas);
}

/**
 * Logos de "Confían en nosotros": clientes a los que ya se les vendió.
 */
function repo_clients(): array
{
    $filas = db_q('SELECT id, nombre, logo, orden FROM clientes ORDER BY orden ASC, id ASC')->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'     => (int) $f['id'],
        'nombre' => (string) $f['nombre'],
        'logo'   => (string) ($f['logo'] ?? ''),
        'orden'  => (int) $f['orden'],
    ], $filas);
}

/**
 * Banners activos, ordenados. La vista elige por `posicion`
 * (hero | mayorista | franja).
 */
function repo_banners(): array
{
    $filas = db_q(
        'SELECT id, titulo, imagen, enlace, posicion, activo, orden
         FROM banners WHERE activo = 1 ORDER BY posicion ASC, orden ASC, id ASC'
    )->fetchAll();

    return array_map(static fn (array $f): array => [
        'id'       => (int) $f['id'],
        'titulo'   => (string) ($f['titulo'] ?? ''),
        'imagen'   => (string) ($f['imagen'] ?? ''),
        'enlace'   => (string) ($f['enlace'] ?? ''),
        'posicion' => (string) $f['posicion'],
        'activo'   => (bool) $f['activo'],
        'orden'    => (int) $f['orden'],
    ], $filas);
}

/* ==========================================================================
   Contenido de página
   ========================================================================== */

/**
 * Todo el contenido de la sección "Nosotros", de una sola vez.
 *
 * Se guarda como un documento en una única fila y no repartido en seis
 * tablas: el cliente lo piensa como una sola cosa y lo edita una sola
 * pantalla del panel. Ver docs/DATA-CONTRACT.md §4.
 *
 * Reglas que la vista puede dar por sentadas:
 *
 *   · Un valor en null es un hueco DECLARADO, no un error: la vista lo
 *     dibuja como marcador visible en vez de esconder la fila.
 *   · `obras.items` puede venir vacío y eso NO es un estado de error: la
 *     página esconde la sección entera.
 *   · `fundadores.foto` puede ser null y la página colapsa ese bloque a un
 *     párrafo firmado con los dos nombres.
 *   · La cifra `productos_en_catalogo` NO se carga a mano: se resuelve acá
 *     contra el catálogo.
 */
function repo_nosotros(): array
{
    $crudo = db_q('SELECT contenido FROM nosotros WHERE id = 1')->fetchColumn();

    $contenido = is_string($crudo) ? json_decode($crudo, true) : null;
    $contenido = is_array($contenido) ? $contenido : [];

    unset($contenido['_comentario']);

    $contenido['provisorio'] = (bool) ($contenido['provisorio'] ?? false);

    // Forma garantizada: la vista no tiene que preguntar si existe la clave,
    // solo si está vacía. Es la diferencia entre un estado vacío y un error.
    foreach (['encabezado', 'fundadores', 'historia', 'como_trabajamos',
              'obras', 'garantia', 'donde_estamos', 'cierre', 'franja'] as $bloque) {
        $contenido[$bloque] = (array) ($contenido[$bloque] ?? []);
    }

    $contenido['cifras'] = array_values((array) ($contenido['cifras'] ?? []));

    $contenido['fundadores']['personas']   = array_values((array) ($contenido['fundadores']['personas'] ?? []));
    $contenido['historia']['hitos']        = array_values((array) ($contenido['historia']['hitos'] ?? []));
    $contenido['como_trabajamos']['pasos'] = array_values((array) ($contenido['como_trabajamos']['pasos'] ?? []));
    $contenido['obras']['items']           = array_values((array) ($contenido['obras']['items'] ?? []));
    $contenido['garantia']['items']        = array_values((array) ($contenido['garantia']['items'] ?? []));
    $contenido['cierre']['acciones']       = array_values((array) ($contenido['cierre']['acciones'] ?? []));

    // La única cifra que no se carga: sale del catálogo.
    $total_catalogo = (int) db_q('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn();

    foreach ($contenido['cifras'] as $i => $cifra) {
        if (($cifra['clave'] ?? '') === 'productos_en_catalogo') {
            $contenido['cifras'][$i]['valor'] = $total_catalogo;
        }
    }

    return $contenido;
}

/* ==========================================================================
   Configuración
   ========================================================================== */

/**
 * Configuración del sitio: % de descuento, WhatsApp, envíos, redes, legales.
 *
 * La tabla es clave-valor y guarda todo como texto. Las claves numéricas se
 * devuelven casteadas para que la vista reciba lo mismo que recibía del
 * JSON: `envio_gratis_desde` tiene que ser un int y no la cadena "150000".
 */
function repo_settings(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $settings = [];

    foreach (db_q('SELECT clave, valor FROM settings') as $fila) {
        $settings[(string) $fila['clave']] = (string) ($fila['valor'] ?? '');
    }

    foreach (['envio_gratis_desde'] as $entero) {
        if (isset($settings[$entero])) {
            $settings[$entero] = (int) $settings[$entero];
        }
    }

    // El porcentaje puede ser decimal: 12,5 es un descuento válido.
    if (isset($settings['descuento_transferencia_pct'])) {
        $settings['descuento_transferencia_pct'] = (float) str_replace(',', '.', $settings['descuento_transferencia_pct']);
    }

    return $cache = $settings;
}

/* ==========================================================================
   Cuenta
   ========================================================================== */

/**
 * Verifica credenciales y devuelve el usuario sin su hash, o null.
 *
 * NO abre sesión: eso es tarea de `app/sesion.php`. Acá solo se responde
 * "¿este par de credenciales es válido?".
 *
 * Nunca se dice cuál de los dos datos estuvo mal, y se gasta el mismo
 * tiempo aunque el correo no exista, para no filtrar por diferencia de
 * tiempos qué direcciones están registradas.
 */
function repo_login(string $email, string $password): ?array
{
    $fila = db_q(
        'SELECT * FROM usuarios WHERE email = ? LIMIT 1',
        [_repo_normalizar_email($email)]
    )->fetch();

    if ($fila === false) {
        password_verify($password, '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexiste');

        return null;
    }

    if (!(bool) $fila['activo']) {
        return null;
    }

    if (!password_verify($password, (string) $fila['password_hash'])) {
        return null;
    }

    /* Si el hash quedó viejo —porque cambió el algoritmo o el costo—, se
       regraba ahora que tenemos la contraseña en claro. Es el único momento
       en el que se puede hacer. */
    if (password_needs_rehash((string) $fila['password_hash'], PASSWORD_DEFAULT)) {
        db_q(
            'UPDATE usuarios SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), (int) $fila['id']]
        );
    }

    return _repo_fila_a_usuario($fila);
}

/**
 * Alta de usuario. Valida, guarda y devuelve el resultado.
 *
 * @return array{ok:bool, errores:array<string,string>, usuario:?array}
 */
function repo_register(array $data): array
{
    $errores = [];

    $nombre   = trim((string) ($data['nombre'] ?? ''));
    $apellido = trim((string) ($data['apellido'] ?? ''));
    $email    = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($nombre === '') {
        $errores['nombre'] = 'Escribí tu nombre.';
    }

    if ($apellido === '') {
        $errores['apellido'] = 'Escribí tu apellido.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisá el correo: no parece una dirección válida.';
    } else {
        $tomado = db_q(
            'SELECT 1 FROM usuarios WHERE email = ? LIMIT 1',
            [_repo_normalizar_email($email)]
        )->fetchColumn();

        if ($tomado !== false) {
            $errores['email'] = 'Ya hay una cuenta con este correo.';
        }
    }

    if (mb_strlen($password) < 8) {
        $errores['password'] = 'La contraseña necesita al menos 8 caracteres.';
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'usuario' => null];
    }

    /* El rol lo decide el servidor, nunca el formulario. `es_empresa` es
       una casilla del navegador: si de ella saliera el rol, cualquiera se
       daría de alta como admin agregando un campo al POST. */
    $rol = !empty($data['es_empresa']) ? 'mayorista' : 'cliente';

    $id = db_transaccion(static function () use ($nombre, $apellido, $email, $password, $rol, $data): int {
        db_q(
            'INSERT INTO usuarios (nombre, apellido, email, password_hash, telefono, rol, empresa, cuit, creado, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $nombre,
                $apellido,
                _repo_normalizar_email($email),
                password_hash($password, PASSWORD_DEFAULT),
                trim((string) ($data['telefono'] ?? '')) ?: null,
                $rol,
                trim((string) ($data['empresa'] ?? '')) ?: null,
                trim((string) ($data['cuit'] ?? '')) ?: null,
                date('Y-m-d'),
            ]
        );

        return (int) db()->lastInsertId();
    });

    return ['ok' => true, 'errores' => [], 'usuario' => repo_user($id)];
}

/**
 * Un usuario por id, sin su hash de contraseña.
 */
function repo_user(int $id): ?array
{
    $fila = db_q('SELECT * FROM usuarios WHERE id = ? LIMIT 1', [$id])->fetch();

    return $fila === false ? null : _repo_fila_a_usuario($fila);
}

/**
 * Pedidos de un usuario, del más nuevo al más viejo.
 *
 * Los items vienen tal como se guardaron: nombre, SKU y precio unitario del
 * día de la compra. No se enriquecen contra el catálogo actual a propósito.
 */
function repo_orders(int $userId): array
{
    $filas = db_q(
        'SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY fecha DESC, id DESC',
        [$userId]
    )->fetchAll();

    return _repo_pedidos_con_items($filas);
}

/**
 * Un pedido por su código (RF-2026-0418).
 *
 * $usuarioId acota el pedido a su dueño. Es opcional para no romper las
 * llamadas viejas, pero el panel y la cuenta lo pasan SIEMPRE.
 *
 * El código es adivinable —RF-año-ddmm— y el pedido trae nombre, dirección
 * y total de una compra. Sin ese segundo argumento, cualquiera lee los
 * pedidos de cualquiera probando códigos. Por eso la ficha de pedido de
 * /cuenta no se dibujó hasta que existió este parámetro.
 */
function repo_order(string $code, ?int $usuarioId = null): ?array
{
    $sql    = 'SELECT * FROM pedidos WHERE codigo = ?';
    $params = [$code];

    if ($usuarioId !== null) {
        $sql .= ' AND usuario_id = ?';
        $params[] = $usuarioId;
    }

    $fila = db_q($sql . ' LIMIT 1', $params)->fetch();

    if ($fila === false) {
        return null;
    }

    return _repo_pedidos_con_items([$fila])[0];
}

/**
 * Le pega los items a un lote de pedidos. Una consulta para todo el lote.
 *
 * @param array<int,array> $filas
 */
function _repo_pedidos_con_items(array $filas): array
{
    if ($filas === []) {
        return [];
    }

    $pedidos = [];

    foreach ($filas as $f) {
        $pedidos[(int) $f['id']] = [
            'id'                     => (int) $f['id'],
            'codigo'                 => (string) $f['codigo'],
            'usuario_id'             => $f['usuario_id'] !== null ? (int) $f['usuario_id'] : null,
            'fecha'                  => (string) $f['fecha'],
            'estado'                 => (string) $f['estado'],
            'medio_pago'             => (string) ($f['medio_pago'] ?? ''),
            'subtotal'               => (int) $f['subtotal'],
            'envio'                  => (int) $f['envio'],
            'descuento_aplicado_pct' => (float) $f['descuento_aplicado_pct'],
            'total'                  => (int) $f['total'],
            'items'                  => [],
        ];
    }

    $ids   = array_keys($pedidos);
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $items = db_q(
        "SELECT pedido_id, producto_id, nombre, sku, cantidad, precio_unitario
         FROM pedido_items WHERE pedido_id IN ($marks) ORDER BY pedido_id, id",
        $ids
    );

    foreach ($items as $item) {
        $pedidos[(int) $item['pedido_id']]['items'][] = [
            'producto_id'     => $item['producto_id'] !== null ? (int) $item['producto_id'] : null,
            'nombre'          => (string) $item['nombre'],
            'sku'             => (string) ($item['sku'] ?? ''),
            'cantidad'        => (int) $item['cantidad'],
            'precio_unitario' => (int) $item['precio_unitario'],
        ];
    }

    return array_values($pedidos);
}

/* ==========================================================================
   Carrito
   ========================================================================== */

/**
 * Resuelve los ids que el carrito guarda en localStorage.
 *
 * Devuelve los productos en el mismo orden en que llegaron los ids, con su
 * `activo` y su `stock` puestos, para que el carrito pueda avisar que algo
 * se dio de baja o se quedó sin stock en vez de hacerlo desaparecer sin
 * explicación. Las cantidades no viven acá: las pone el JS.
 */
function repo_cart_items(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));

    if ($ids === []) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($ids), '?'));

    $filas = db_q(
        _repo_select_producto() . " WHERE p.id IN ($marks)",
        $ids
    )->fetchAll();

    $porId = [];
    foreach (_repo_productos_desde_filas($filas) as $producto) {
        $porId[$producto['id']] = $producto;
    }

    // El orden lo pide quien llama, no la base.
    $items = [];
    foreach ($ids as $id) {
        if (isset($porId[$id])) {
            $items[] = $porId[$id];
        }
    }

    return $items;
}
