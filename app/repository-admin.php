<?php
/**
 * ============================================================================
 * repository-admin.php — el lado de escritura del contrato.
 * ============================================================================
 *
 * Misma regla que `repository.php`: ninguna vista arma una consulta. Este
 * archivo tiene las funciones que el panel usa para LEER lo que el sitio
 * público no ve —productos dados de baja, todos los pedidos— y para
 * ESCRIBIR.
 *
 * Está aparte y no adentro de repository.php por dos razones:
 *
 *   1. El sitio público no necesita ni una de estas funciones. Lo carga
 *      sólo `views/admin/_cabecera.php`, que ya corrió el guard, así que
 *      ninguna request anónima llega siquiera a interpretar este archivo.
 *   2. repository.php es el contrato que lee el sitio. Mezclarle veinte
 *      funciones de ABM lo vuelve difícil de leer justo para quien tiene
 *      que entender qué consume cada vista.
 *
 * REGLAS DE ESTE ARCHIVO
 *
 *   · Toda operación que toque más de una tabla va en una transacción.
 *     Guardar un producto son tres escrituras —el producto, sus imágenes
 *     y sus especificaciones— y a medias no sirve.
 *   · Las funciones de guardado VALIDAN y devuelven los errores por campo.
 *     La vista dibuja; no decide qué es válido.
 *   · Nada se borra de verdad si tiene historia. Un producto se da de
 *     baja (`activo = 0`), no se borra: si se borrara, los pedidos viejos
 *     perderían la referencia.
 *   · Ningún id llega por concatenación. Todo va por parámetro.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ==========================================================================
   Dashboard
   ========================================================================== */

/**
 * Las cuatro cifras de la portada del panel.
 *
 * "Ventas del mes" cuenta lo facturado en el mes corriente, sin los
 * pedidos cancelados: un cancelado no es una venta.
 *
 * @return array{ventas_mes:int, pedidos_mes:int, pedidos_nuevos:int,
 *               productos_activos:int, productos_total:int, sin_stock:int}
 */
function repo_admin_metricas(): array
{
    $mes = db_q(
        "SELECT COALESCE(SUM(total), 0) AS monto, COUNT(*) AS cantidad
         FROM pedidos
         WHERE estado <> 'cancelado'
           AND YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())"
    )->fetch();

    return [
        'ventas_mes'        => (int) $mes['monto'],
        'pedidos_mes'       => (int) $mes['cantidad'],
        'pedidos_nuevos'    => (int) db_q("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn(),
        'productos_activos' => (int) db_q('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn(),
        'productos_total'   => (int) db_q('SELECT COUNT(*) FROM productos')->fetchColumn(),
        'sin_stock'         => (int) db_q('SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock <= 0')->fetchColumn(),
    ];
}

/* ==========================================================================
   Productos
   ========================================================================== */

/**
 * Listado del panel. A diferencia de `repo_products()`, ESTE VE TODO:
 * también lo que está dado de baja, porque el panel es justamente donde
 * se lo vuelve a dar de alta.
 *
 * @return array{items:array, total:int, pagina:int, paginas:int}
 */
function repo_admin_products(array $filtros = [], int $page = 1, int $perPage = 25): array
{
    $where  = ['1 = 1'];
    $params = [];

    if (trim((string) ($filtros['q'] ?? '')) !== '') {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $filtros['q'])) . '%';
        $where[] = '(p.nombre LIKE ? OR p.sku LIKE ?)';
        array_push($params, $q, $q);
    }

    if (!empty($filtros['categoria'])) {
        $where[]  = 'c.slug = ?';
        $params[] = (string) $filtros['categoria'];
    }

    /* Tres estados y no dos: "todos" tiene que poder pedirse explícito,
       porque el panel arranca mostrando todo. */
    if (($filtros['estado'] ?? '') === 'activos') {
        $where[] = 'p.activo = 1';
    } elseif (($filtros['estado'] ?? '') === 'bajas') {
        $where[] = 'p.activo = 0';
    } elseif (($filtros['estado'] ?? '') === 'sin_stock') {
        $where[] = 'p.activo = 1 AND p.stock <= 0';
    }

    $sql_where = 'WHERE ' . implode(' AND ', $where);

    $total   = (int) db_q(
        'SELECT COUNT(*) FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id ' . $sql_where,
        $params
    )->fetchColumn();

    $perPage = max(1, $perPage);
    $paginas = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $paginas);
    $offset  = ($page - 1) * $perPage;

    $filas = db_q(
        'SELECT p.id, p.slug, p.sku, p.nombre, p.precio_lista, p.stock, p.activo,
                p.destacado, p.imagen, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas     m ON m.id = p.marca_id
         ' . $sql_where . ' ORDER BY p.actualizado DESC, p.id DESC
         LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
        $params
    )->fetchAll();

    return [
        'items' => array_map(static fn (array $f): array => [
            'id'               => (int) $f['id'],
            'slug'             => (string) $f['slug'],
            'sku'              => (string) $f['sku'],
            'nombre'           => (string) $f['nombre'],
            'precio_lista'     => (int) $f['precio_lista'],
            'stock'            => (int) $f['stock'],
            'activo'           => (bool) $f['activo'],
            'destacado'        => (bool) $f['destacado'],
            'imagen'           => (string) ($f['imagen'] ?? ''),
            'categoria_nombre' => (string) ($f['categoria_nombre'] ?? '—'),
            'marca_nombre'     => (string) ($f['marca_nombre'] ?? '—'),
        ], $filas),
        'total'   => $total,
        'pagina'  => $page,
        'paginas' => $paginas,
    ];
}

/**
 * Un producto por id, con sus imágenes y especificaciones, esté activo o
 * no. Es lo que abre el formulario de edición.
 */
function repo_admin_product(int $id): ?array
{
    $fila = db_q(
        'SELECT p.*, c.slug AS categoria, m.slug AS marca, m.nombre AS marca_nombre
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN marcas     m ON m.id = p.marca_id
         WHERE p.id = ? LIMIT 1',
        [$id]
    )->fetch();

    if ($fila === false) {
        return null;
    }

    $producto = _repo_fila_a_producto($fila);

    foreach (db_q('SELECT ruta FROM producto_imagenes WHERE producto_id = ? ORDER BY orden, id', [$id]) as $f) {
        $producto['imagenes'][] = (string) $f['ruta'];
    }

    foreach (db_q('SELECT etiqueta, valor FROM producto_especificaciones WHERE producto_id = ? ORDER BY orden, id', [$id]) as $f) {
        $producto['especificaciones'][] = ['label' => (string) $f['etiqueta'], 'valor' => (string) $f['valor']];
    }

    return $producto;
}

/**
 * Alta o edición de un producto.
 *
 * @param array $datos Los campos del formulario, sin sanear.
 * @param int|null $id null para alta.
 *
 * @return array{ok:bool, errores:array<string,string>, id:?int}
 */
function repo_producto_guardar(array $datos, ?int $id = null): array
{
    $errores = [];

    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $sku    = mb_strtoupper(trim((string) ($datos['sku'] ?? '')), 'UTF-8');
    $slug   = trim((string) ($datos['slug'] ?? ''));

    if ($nombre === '') {
        $errores['nombre'] = 'El producto necesita un nombre.';
    }

    if ($sku === '') {
        $errores['sku'] = 'El código es obligatorio: es con lo que se identifica el producto.';
    }

    /* El slug es la URL del producto. Si viene vacío se calcula del nombre,
       pero si el producto ya existe NO se recalcula: cambiar el slug de un
       producto publicado rompe el enlace que alguien mandó por WhatsApp. */
    if ($slug === '') {
        $slug = $id === null ? slug($nombre) : '';
    } else {
        $slug = slug($slug);
    }

    if ($id === null && $slug === '') {
        $errores['nombre'] = 'Con ese nombre no se puede armar una URL. Escribí un slug a mano.';
    }

    $precio = (int) round(numero_decimal($datos['precio_lista'] ?? 0));

    if ($precio <= 0) {
        $errores['precio_lista'] = 'El precio publicado tiene que ser mayor que cero.';
    }

    /* El descuento por producto es un override del global. Vacío significa
       "usá el global", que no es lo mismo que 0. */
    $descuento = trim((string) ($datos['descuento_pct'] ?? ''));
    $descuento = $descuento === '' ? null : numero_decimal($descuento);

    if ($descuento !== null && ($descuento < 0 || $descuento >= 100)) {
        $errores['descuento_pct'] = 'El descuento tiene que estar entre 0 y 99,99. Dejalo vacío para usar el global.';
    }

    // Unicidad, sin pisar al propio producto cuando se está editando.
    foreach ([['sku', $sku], ['slug', $slug]] as [$campo, $valor]) {
        if ($valor === '') {
            continue;
        }

        $sql = "SELECT id FROM productos WHERE $campo = ?" . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1';
        $par = $id !== null ? [$valor, $id] : [$valor];

        if (db_q($sql, $par)->fetchColumn() !== false) {
            $errores[$campo] = $campo === 'sku'
                ? 'Ya hay otro producto con este código.'
                : 'Ya hay otro producto con esta URL.';
        }
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'id' => $id];
    }

    $categoria_id = _repo_id_de('categorias', (string) ($datos['categoria'] ?? ''));
    $marca_id     = _repo_id_de('marcas', (string) ($datos['marca'] ?? ''));

    $guardado = db_transaccion(static function () use ($datos, $id, $nombre, $sku, $slug, $precio, $descuento, $categoria_id, $marca_id): int {
        $comunes = [
            $nombre,
            $sku,
            $categoria_id,
            $marca_id,
            trim((string) ($datos['descripcion_corta'] ?? '')) ?: null,
            trim((string) ($datos['descripcion'] ?? '')) ?: null,
            $precio,
            ($datos['precio_mayorista'] ?? '') !== '' ? (int) round(numero_decimal($datos['precio_mayorista'])) : null,
            $descuento,
            (int) ($datos['stock'] ?? 0),
            !empty($datos['destacado']) ? 1 : 0,
            !empty($datos['nuevo']) ? 1 : 0,
            trim((string) ($datos['imagen'] ?? '')) ?: null,
            ($datos['peso_kg'] ?? '') !== '' ? numero_decimal($datos['peso_kg']) : null,
            !empty($datos['activo']) ? 1 : 0,
        ];

        if ($id === null) {
            db_q(
                'INSERT INTO productos
                    (nombre, sku, categoria_id, marca_id, descripcion_corta, descripcion,
                     precio_lista, precio_mayorista, descuento_pct, stock, destacado, nuevo,
                     imagen, peso_kg, activo, slug)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$comunes, $slug]
            );

            $nuevo = (int) db()->lastInsertId();
        } else {
            /* El slug sólo se actualiza si vino uno nuevo: ver arriba. */
            $sql = 'UPDATE productos SET
                        nombre = ?, sku = ?, categoria_id = ?, marca_id = ?,
                        descripcion_corta = ?, descripcion = ?, precio_lista = ?,
                        precio_mayorista = ?, descuento_pct = ?, stock = ?,
                        destacado = ?, nuevo = ?, imagen = ?, peso_kg = ?, activo = ?'
                 . ($slug !== '' ? ', slug = ?' : '')
                 . ' WHERE id = ?';

            $par = $slug !== '' ? [...$comunes, $slug, $id] : [...$comunes, $id];
            db_q($sql, $par);

            $nuevo = $id;
        }

        /* Imágenes y especificaciones se reemplazan enteras. Es una lista
           ordenada que el formulario manda completa: intentar un diff
           campo por campo sería más código y más formas de fallar. */
        db_q('DELETE FROM producto_imagenes WHERE producto_id = ?', [$nuevo]);

        $orden = 0;
        foreach ((array) ($datos['imagenes'] ?? []) as $ruta) {
            $ruta = trim((string) $ruta);
            if ($ruta !== '') {
                db_q('INSERT INTO producto_imagenes (producto_id, ruta, orden) VALUES (?, ?, ?)', [$nuevo, $ruta, $orden++]);
            }
        }

        db_q('DELETE FROM producto_especificaciones WHERE producto_id = ?', [$nuevo]);

        $orden = 0;
        $etiquetas = (array) ($datos['espec_label'] ?? []);
        $valores   = (array) ($datos['espec_valor'] ?? []);

        foreach ($etiquetas as $i => $etiqueta) {
            $etiqueta = trim((string) $etiqueta);
            $valor    = trim((string) ($valores[$i] ?? ''));

            // Una fila con etiqueta y sin valor —o al revés— es una fila que
            // quedó a medio llenar: no se guarda.
            if ($etiqueta !== '' && $valor !== '') {
                db_q(
                    'INSERT INTO producto_especificaciones (producto_id, etiqueta, valor, orden) VALUES (?, ?, ?, ?)',
                    [$nuevo, $etiqueta, $valor, $orden++]
                );
            }
        }

        return $nuevo;
    });

    return ['ok' => true, 'errores' => [], 'id' => $guardado];
}

/**
 * Da de baja o de alta un producto.
 *
 * NO borra. Un producto borrado deja los pedidos viejos sin referencia y
 * el carrito de quien lo tenía cargado sin explicación. La baja lo saca
 * del catálogo y lo deja recuperable.
 */
function repo_producto_activo(int $id, bool $activo): bool
{
    db_q('UPDATE productos SET activo = ? WHERE id = ?', [$activo ? 1 : 0, $id]);

    return true;
}

/* ==========================================================================
   Pedidos
   ========================================================================== */

/**
 * Estados que maneja el negocio. Están acá y no repartidos por las vistas
 * para que sumar uno sea tocar un solo lugar (PENDIENTES #36).
 */
function repo_estados_pedido(): array
{
    return [
        'pendiente' => 'Pendiente',
        'en_camino' => 'En camino',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];
}

/**
 * Todos los pedidos, con el nombre de quien compró.
 *
 * @return array{items:array, total:int, pagina:int, paginas:int}
 */
function repo_admin_pedidos(array $filtros = [], int $page = 1, int $perPage = 25): array
{
    $where  = ['1 = 1'];
    $params = [];

    if (!empty($filtros['estado']) && isset(repo_estados_pedido()[$filtros['estado']])) {
        $where[]  = 'o.estado = ?';
        $params[] = (string) $filtros['estado'];
    }

    if (trim((string) ($filtros['q'] ?? '')) !== '') {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $filtros['q'])) . '%';
        $where[] = '(o.codigo LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)';
        array_push($params, $q, $q, $q, $q);
    }

    $sql_where = 'WHERE ' . implode(' AND ', $where);

    $total = (int) db_q(
        'SELECT COUNT(*) FROM pedidos o LEFT JOIN usuarios u ON u.id = o.usuario_id ' . $sql_where,
        $params
    )->fetchColumn();

    $perPage = max(1, $perPage);
    $paginas = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $paginas);
    $offset  = ($page - 1) * $perPage;

    $filas = db_q(
        "SELECT o.id, o.codigo, o.fecha, o.estado, o.total, o.medio_pago,
                TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS cliente,
                u.email AS cliente_email
         FROM pedidos o
         LEFT JOIN usuarios u ON u.id = o.usuario_id
         $sql_where
         ORDER BY o.fecha DESC, o.id DESC
         LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
        $params
    )->fetchAll();

    return [
        'items' => array_map(static fn (array $f): array => [
            'id'            => (int) $f['id'],
            'codigo'        => (string) $f['codigo'],
            'fecha'         => (string) $f['fecha'],
            'estado'        => (string) $f['estado'],
            'total'         => (int) $f['total'],
            'medio_pago'    => (string) ($f['medio_pago'] ?? ''),
            // Un pedido puede no tener usuario: se le borró la cuenta y el
            // ON DELETE SET NULL dejó la facturación en pie.
            'cliente'       => trim((string) ($f['cliente'] ?? '')) ?: 'Cuenta eliminada',
            'cliente_email' => (string) ($f['cliente_email'] ?? ''),
        ], $filas),
        'total'   => $total,
        'pagina'  => $page,
        'paginas' => $paginas,
    ];
}

/**
 * Un pedido con sus items y los datos de quien compró. Para el panel, así
 * que NO filtra por dueño: acá el dueño es Rastro.
 */
function repo_admin_pedido(string $codigo): ?array
{
    $fila = db_q('SELECT * FROM pedidos WHERE codigo = ? LIMIT 1', [$codigo])->fetch();

    if ($fila === false) {
        return null;
    }

    $pedido = _repo_pedidos_con_items([$fila])[0];
    $pedido['cliente'] = $pedido['usuario_id'] !== null ? repo_user($pedido['usuario_id']) : null;

    return $pedido;
}

/**
 * Cambia el estado de un pedido.
 *
 * @return array{ok:bool, error:?string}
 */
function repo_pedido_estado(string $codigo, string $estado): array
{
    if (!isset(repo_estados_pedido()[$estado])) {
        return ['ok' => false, 'error' => 'Ese estado no existe.'];
    }

    $st = db_q('UPDATE pedidos SET estado = ? WHERE codigo = ?', [$estado, $codigo]);

    if ($st->rowCount() === 0 && db_q('SELECT 1 FROM pedidos WHERE codigo = ?', [$codigo])->fetchColumn() === false) {
        return ['ok' => false, 'error' => 'No existe un pedido con ese código.'];
    }

    return ['ok' => true, 'error' => null];
}

/* ==========================================================================
   Categorías, marcas, clientes y banners

   Los cuatro son el mismo patrón: una lista corta, ordenable, que el
   cliente edita a mano. Comparten las dos funciones de abajo para no
   escribir cuatro veces el mismo INSERT/UPDATE.
   ========================================================================== */

/**
 * El id de una fila a partir de su slug. null si no existe o si el slug
 * viene vacío.
 */
function _repo_id_de(string $tabla, string $slug): ?int
{
    if ($slug === '' || !in_array($tabla, ['categorias', 'marcas'], true)) {
        return null;
    }

    $id = db_q("SELECT id FROM $tabla WHERE slug = ? LIMIT 1", [$slug])->fetchColumn();

    return $id === false ? null : (int) $id;
}

/**
 * Alta o edición de una categoría.
 *
 * @return array{ok:bool, errores:array<string,string>, id:?int}
 */
function repo_categoria_guardar(array $datos, ?int $id = null): array
{
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $slug   = slug(trim((string) ($datos['slug'] ?? '')) ?: $nombre);
    $errores = [];

    if ($nombre === '') {
        $errores['nombre'] = 'La categoría necesita un nombre.';
    }

    if ($slug === '') {
        $errores['slug'] = 'Hace falta un slug para la URL.';
    } else {
        $sql = 'SELECT id FROM categorias WHERE slug = ?' . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1';
        if (db_q($sql, $id !== null ? [$slug, $id] : [$slug])->fetchColumn() !== false) {
            $errores['slug'] = 'Ya hay otra categoría con esta URL.';
        }
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'id' => $id];
    }

    $par = [
        $nombre,
        $slug,
        trim((string) ($datos['descripcion'] ?? '')) ?: null,
        trim((string) ($datos['pictograma'] ?? '')) ?: null,
        (int) ($datos['orden'] ?? 0),
    ];

    if ($id === null) {
        db_q('INSERT INTO categorias (nombre, slug, descripcion, pictograma, orden) VALUES (?, ?, ?, ?, ?)', $par);
        $id = (int) db()->lastInsertId();
    } else {
        db_q('UPDATE categorias SET nombre = ?, slug = ?, descripcion = ?, pictograma = ?, orden = ? WHERE id = ?', [...$par, $id]);
    }

    return ['ok' => true, 'errores' => [], 'id' => $id];
}

/**
 * Borra una categoría.
 *
 * Se puede borrar de verdad porque la relación con productos es
 * ON DELETE SET NULL: los productos quedan sin categoría, visibles y
 * editables, en vez de desaparecer. Igual se avisa cuántos van a quedar
 * sueltos antes de confirmar.
 */
function repo_categoria_borrar(int $id): array
{
    $usos = (int) db_q('SELECT COUNT(*) FROM productos WHERE categoria_id = ?', [$id])->fetchColumn();

    db_q('DELETE FROM categorias WHERE id = ?', [$id]);

    return ['ok' => true, 'sueltos' => $usos];
}

/**
 * Alta o edición de una marca.
 */
function repo_marca_guardar(array $datos, ?int $id = null): array
{
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $slug   = slug(trim((string) ($datos['slug'] ?? '')) ?: $nombre);
    $errores = [];

    if ($nombre === '') {
        $errores['nombre'] = 'La marca necesita un nombre.';
    }

    if ($slug !== '') {
        $sql = 'SELECT id FROM marcas WHERE slug = ?' . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1';
        if (db_q($sql, $id !== null ? [$slug, $id] : [$slug])->fetchColumn() !== false) {
            $errores['slug'] = 'Ya hay otra marca con esta URL.';
        }
    } else {
        $errores['nombre'] = 'Con ese nombre no se puede armar un slug.';
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'id' => $id];
    }

    /* `es_propia` decide si la marca aparece en la franja "vendedores
       oficiales" de la home. Es la única bandera de esta pantalla que
       cambia algo en el sitio público, y por eso el formulario la explica. */
    $par = [
        $nombre,
        $slug,
        trim((string) ($datos['logo'] ?? '')) ?: null,
        (int) ($datos['orden'] ?? 0),
        !empty($datos['es_propia']) ? 1 : 0,
    ];

    if ($id === null) {
        db_q('INSERT INTO marcas (nombre, slug, logo, orden, es_propia) VALUES (?, ?, ?, ?, ?)', $par);
        $id = (int) db()->lastInsertId();
    } else {
        db_q('UPDATE marcas SET nombre = ?, slug = ?, logo = ?, orden = ?, es_propia = ? WHERE id = ?', [...$par, $id]);
    }

    return ['ok' => true, 'errores' => [], 'id' => $id];
}

function repo_marca_borrar(int $id): array
{
    $usos = (int) db_q('SELECT COUNT(*) FROM productos WHERE marca_id = ?', [$id])->fetchColumn();

    db_q('DELETE FROM marcas WHERE id = ?', [$id]);

    return ['ok' => true, 'sueltos' => $usos];
}

/**
 * Alta o edición de un logo de "Confían en nosotros".
 */
function repo_cliente_guardar(array $datos, ?int $id = null): array
{
    $nombre = trim((string) ($datos['nombre'] ?? ''));

    if ($nombre === '') {
        return ['ok' => false, 'errores' => ['nombre' => 'Hace falta el nombre del cliente.'], 'id' => $id];
    }

    $par = [$nombre, trim((string) ($datos['logo'] ?? '')) ?: null, (int) ($datos['orden'] ?? 0)];

    if ($id === null) {
        db_q('INSERT INTO clientes (nombre, logo, orden) VALUES (?, ?, ?)', $par);
        $id = (int) db()->lastInsertId();
    } else {
        db_q('UPDATE clientes SET nombre = ?, logo = ?, orden = ? WHERE id = ?', [...$par, $id]);
    }

    return ['ok' => true, 'errores' => [], 'id' => $id];
}

function repo_cliente_borrar(int $id): array
{
    db_q('DELETE FROM clientes WHERE id = ?', [$id]);

    return ['ok' => true, 'sueltos' => 0];
}

/**
 * Posiciones válidas de un banner. El sitio las lee por nombre, así que
 * inventar una acá no la dibuja en ningún lado.
 */
function repo_posiciones_banner(): array
{
    return [
        'hero'      => 'Hero de la home',
        'mayorista' => 'Bloque mayorista',
        'franja'    => 'Franja de descuento',
    ];
}

/**
 * Alta o edición de un banner.
 */
function repo_banner_guardar(array $datos, ?int $id = null): array
{
    $errores  = [];
    $posicion = (string) ($datos['posicion'] ?? 'hero');

    if (!isset(repo_posiciones_banner()[$posicion])) {
        $errores['posicion'] = 'Esa posición no existe en el sitio.';
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'id' => $id];
    }

    /* El título admite los marcadores {descuento}, {envio_gratis} y
       {whatsapp}, que la vista resuelve con interpolar(). Escribir "15%"
       a mano crea una segunda fuente de verdad del dato más importante
       del sitio, y queda desactualizada el día que el cliente lo cambie. */
    $par = [
        trim((string) ($datos['titulo'] ?? '')) ?: null,
        trim((string) ($datos['imagen'] ?? '')) ?: null,
        trim((string) ($datos['enlace'] ?? '')) ?: null,
        $posicion,
        !empty($datos['activo']) ? 1 : 0,
        (int) ($datos['orden'] ?? 0),
    ];

    if ($id === null) {
        db_q('INSERT INTO banners (titulo, imagen, enlace, posicion, activo, orden) VALUES (?, ?, ?, ?, ?, ?)', $par);
        $id = (int) db()->lastInsertId();
    } else {
        db_q('UPDATE banners SET titulo = ?, imagen = ?, enlace = ?, posicion = ?, activo = ?, orden = ? WHERE id = ?', [...$par, $id]);
    }

    return ['ok' => true, 'errores' => [], 'id' => $id];
}

function repo_banner_borrar(int $id): array
{
    db_q('DELETE FROM banners WHERE id = ?', [$id]);

    return ['ok' => true, 'sueltos' => 0];
}

/**
 * Todas las categorías, marcas, clientes y banners para las pantallas de
 * listado del panel. Las de marcas incluyen la línea propia.
 */
function repo_admin_marcas(): array
{
    return repo_brands(true);
}

/* ==========================================================================
   Nosotros y configuración
   ========================================================================== */

/**
 * Guarda el contenido de /nosotros.
 *
 * Recibe el array completo tal como lo devuelve `repo_nosotros()` y lo
 * escribe como documento. La pantalla del panel arma ese array a partir
 * de sus campos: la forma la decide el contrato, no el formulario.
 */
function repo_nosotros_guardar(array $contenido): array
{
    // La cifra del catálogo se calcula sola: si se guardara, quedaría
    // congelada el día que alguien tocó la pantalla.
    foreach ($contenido['cifras'] ?? [] as $i => $cifra) {
        if (($cifra['clave'] ?? '') === 'productos_en_catalogo') {
            $contenido['cifras'][$i]['valor'] = null;
        }
    }

    db_q(
        'INSERT INTO nosotros (id, contenido) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE contenido = VALUES(contenido)',
        [json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
    );

    return ['ok' => true, 'errores' => []];
}

/**
 * Los ajustes que el panel deja editar, con su tipo y su explicación.
 *
 * Está acá y no en la vista porque es contrato: agregar un ajuste es
 * agregarlo a esta lista, y aparece solo en la pantalla.
 */
function repo_settings_editables(): array
{
    return [
        'descuento_transferencia_pct' => [
            'etiqueta' => 'Descuento por transferencia o efectivo',
            'tipo'     => 'decimal',
            'sufijo'   => '%',
            'ayuda'    => 'Es el dato más importante del sitio: aparece en cada producto, en el carrito y en la banda de la home. Se escribe una sola vez, acá.',
        ],
        'envio_gratis_desde' => [
            'etiqueta' => 'Envío gratis a partir de',
            'tipo'     => 'entero',
            'sufijo'   => '$',
            'ayuda'    => 'Se compara contra el subtotal publicado, no contra el de transferencia (PENDIENTES #48).',
        ],
        'whatsapp' => [
            'etiqueta' => 'WhatsApp',
            'tipo'     => 'texto',
            'ayuda'    => 'Con código de país. Si queda vacío, el sitio esconde todos los botones de WhatsApp en vez de mandar a un número que no existe.',
        ],
        'whatsapp_mensaje' => [
            'etiqueta' => 'Mensaje inicial de WhatsApp',
            'tipo'     => 'texto',
            'ayuda'    => 'Lo que aparece escrito cuando alguien abre el chat.',
        ],
        'email' => [
            'etiqueta' => 'Correo de contacto',
            'tipo'     => 'texto',
        ],
        'horario' => [
            'etiqueta' => 'Horario de atención',
            'tipo'     => 'texto',
        ],
        'instagram' => [
            'etiqueta' => 'Instagram',
            'tipo'     => 'texto',
        ],
        'razon_social' => [
            'etiqueta' => 'Razón social',
            'tipo'     => 'texto',
            'ayuda'    => 'Aparece en el pie de todas las páginas. Es obligatorio por ley.',
        ],
        'cuit' => [
            'etiqueta' => 'CUIT',
            'tipo'     => 'texto',
            'ayuda'    => 'También obligatorio en el pie.',
        ],
    ];
}

/**
 * Guarda los ajustes. Sólo las claves de la lista editable: un POST con
 * una clave inventada no crea un ajuste nuevo.
 *
 * @return array{ok:bool, errores:array<string,string>}
 */
function repo_settings_guardar(array $valores): array
{
    $editables = repo_settings_editables();
    $errores   = [];

    foreach ($editables as $clave => $definicion) {
        if (!array_key_exists($clave, $valores)) {
            continue;
        }

        $valor = trim((string) $valores[$clave]);

        if ($definicion['tipo'] === 'decimal') {
            $numero = numero_decimal($valor);

            if ($valor !== '' && ($numero < 0 || $numero >= 100)) {
                $errores[$clave] = 'Tiene que ser un porcentaje entre 0 y 99,99.';
                continue;
            }

            $valor = (string) $numero;
        }

        if ($definicion['tipo'] === 'entero') {
            $valor = (string) (int) round(numero_decimal($valor));
        }

        db_q(
            'INSERT INTO settings (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor]
        );
    }

    return ['ok' => $errores === [], 'errores' => $errores];
}

/* ==========================================================================
   Avisos entre pantallas
   ========================================================================== */

/**
 * Deja un mensaje para la pantalla siguiente.
 *
 * Va en la sesión porque después de un POST hay un redirect —para que
 * recargar no repita la acción— y el mensaje tiene que sobrevivir a ese
 * salto. Lo lee y lo borra `admin/_cabecera.php`.
 */
function admin_avisar(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo];
}
