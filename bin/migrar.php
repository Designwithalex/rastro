<?php
/**
 * bin/migrar.php — carga los mocks de data/ a MySQL.
 *
 *   php bin/migrar.php              carga, sin borrar lo que ya está
 *   php bin/migrar.php --recrear    corre db/esquema.sql y carga de cero
 *
 * Se corre UNA vez, cuando se conecta la base. Después de eso los datos
 * viven en MySQL y los JSON de `data/` quedan como semilla histórica: si
 * alguien vuelve a correr esto sobre una base con contenido cargado por
 * el cliente, le pisa el trabajo. Por eso pide confirmación.
 *
 * No se despliega: bin/ está excluido en el workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

require $raiz . '/app/db.php';

$recrear = in_array('--recrear', $argv, true);
$forzar  = in_array('--forzar', $argv, true);

function decir(string $texto): void
{
    echo $texto . PHP_EOL;
}

function leer_json(string $raiz, string $archivo): array
{
    $ruta = $raiz . '/data/' . $archivo . '.json';

    if (!is_file($ruta)) {
        decir("  · $archivo.json no existe, se saltea");

        return [];
    }

    $datos = json_decode((string) file_get_contents($ruta), true);

    if (!is_array($datos)) {
        throw new RuntimeException("$archivo.json no es JSON válido");
    }

    return $datos;
}

/* --- Antes de tocar nada ------------------------------------------- */

try {
    $pdo = db();
} catch (Throwable $e) {
    decir('✗ ' . $e->getMessage());
    exit(1);
}

decir('Base conectada: ' . db()->query('SELECT DATABASE()')->fetchColumn());

if ($recrear) {
    $esquema = $raiz . '/db/esquema.sql';

    if (!is_file($esquema)) {
        decir('✗ Falta db/esquema.sql');
        exit(1);
    }

    decir('· Recreando el esquema…');
    db()->exec((string) file_get_contents($esquema));
    decir('  esquema listo');
}

/* Si ya hay productos cargados, esto probablemente sea una base viva y
   no una instalación nueva. Pisar el catálogo del cliente con los mocks
   es el error caro de este script, así que hay que pedirlo explícito. */
$existentes = (int) db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();

if ($existentes > 0 && !$recrear && !$forzar) {
    decir("");
    decir("✗ La base ya tiene $existentes productos.");
    decir("  Volver a migrar los mocks encima le pisa el catálogo al cliente.");
    decir("  Si es lo que querés: php bin/migrar.php --forzar");
    exit(1);
}

/* --- La carga, entera o nada ---------------------------------------- */

$contadores = [];

db_transaccion(static function () use ($raiz, &$contadores): void {

    // Orden: primero lo que otras tablas referencian.

    decir('· Categorías…');
    $categorias = [];
    foreach (leer_json($raiz, 'categories') as $c) {
        db_q(
            'INSERT INTO categorias (slug, nombre, descripcion, pictograma, orden)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion),
                                     pictograma = VALUES(pictograma), orden = VALUES(orden)',
            [$c['slug'], $c['nombre'], $c['descripcion'] ?? null, $c['pictograma'] ?? null, (int) ($c['orden'] ?? 0)]
        );
        $categorias[$c['slug']] = (int) db()->lastInsertId() ?: null;
    }
    // lastInsertId() devuelve 0 en un UPDATE, así que los ids se releen.
    foreach (db()->query('SELECT id, slug FROM categorias') as $fila) {
        $categorias[$fila['slug']] = (int) $fila['id'];
    }
    $contadores['categorías'] = count($categorias);

    decir('· Marcas…');
    $marcas = [];
    foreach (leer_json($raiz, 'brands') as $m) {
        db_q(
            'INSERT INTO marcas (slug, nombre, logo, orden, es_propia)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), logo = VALUES(logo),
                                     orden = VALUES(orden), es_propia = VALUES(es_propia)',
            [$m['slug'], $m['nombre'], $m['logo'] ?? null, (int) ($m['orden'] ?? 0), !empty($m['es_propia']) ? 1 : 0]
        );
    }
    foreach (db()->query('SELECT id, slug FROM marcas') as $fila) {
        $marcas[$fila['slug']] = (int) $fila['id'];
    }
    $contadores['marcas'] = count($marcas);

    decir('· Productos…');
    $productos = leer_json($raiz, 'products');
    foreach ($productos as $p) {
        db_q(
            'INSERT INTO productos
                (id, slug, sku, nombre, categoria_id, marca_id, descripcion_corta, descripcion,
                 precio_lista, precio_mayorista, descuento_pct, stock, destacado, nuevo,
                 imagen, peso_kg, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $p['id'],
                $p['slug'],
                $p['sku'],
                $p['nombre'],
                $categorias[$p['categoria'] ?? ''] ?? null,
                $marcas[$p['marca'] ?? ''] ?? null,
                $p['descripcion_corta'] ?? null,
                $p['descripcion'] ?? null,
                (int) ($p['precio_lista'] ?? 0),
                isset($p['precio_mayorista']) ? (int) $p['precio_mayorista'] : null,
                $p['descuento_pct'] ?? null,
                (int) ($p['stock'] ?? 0),
                !empty($p['destacado']) ? 1 : 0,
                !empty($p['nuevo']) ? 1 : 0,
                $p['imagen'] ?? null,
                $p['peso_kg'] ?? null,
                ($p['activo'] ?? true) ? 1 : 0,
            ]
        );

        $id = (int) $p['id'];

        foreach (array_values((array) ($p['imagenes'] ?? [])) as $i => $ruta) {
            db_q(
                'INSERT INTO producto_imagenes (producto_id, ruta, orden) VALUES (?, ?, ?)',
                [$id, $ruta, $i]
            );
        }

        foreach (array_values((array) ($p['especificaciones'] ?? [])) as $i => $espec) {
            db_q(
                'INSERT INTO producto_especificaciones (producto_id, etiqueta, valor, orden) VALUES (?, ?, ?, ?)',
                [$id, $espec['label'] ?? '', $espec['valor'] ?? '', $i]
            );
        }
    }
    $contadores['productos'] = count($productos);

    decir('· Clientes y banners…');
    foreach (leer_json($raiz, 'clients') as $c) {
        db_q(
            'INSERT INTO clientes (id, nombre, logo, orden) VALUES (?, ?, ?, ?)',
            [(int) $c['id'], $c['nombre'], ($c['logo'] ?? '') !== '' ? $c['logo'] : null, (int) ($c['orden'] ?? 0)]
        );
    }
    foreach (leer_json($raiz, 'banners') as $b) {
        db_q(
            'INSERT INTO banners (id, titulo, imagen, enlace, posicion, activo, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $b['id'],
                $b['titulo'] ?? null,
                ($b['imagen'] ?? '') !== '' ? $b['imagen'] : null,
                $b['enlace'] ?? null,
                $b['posicion'] ?? 'hero',
                ($b['activo'] ?? true) ? 1 : 0,
                (int) ($b['orden'] ?? 0),
            ]
        );
    }

    decir('· Configuración…');
    $settings = leer_json($raiz, 'settings');
    foreach ($settings as $clave => $valor) {
        db_q(
            'INSERT INTO settings (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE)]
        );
    }
    $contadores['ajustes'] = count($settings);

    decir('· Nosotros…');
    $nosotros = leer_json($raiz, 'nosotros');
    unset($nosotros['_comentario']);
    db_q(
        'INSERT INTO nosotros (id, contenido) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE contenido = VALUES(contenido)',
        [json_encode($nosotros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
    );

    decir('· Usuarios…');
    $usuarios = leer_json($raiz, 'users');
    foreach ($usuarios as $u) {
        db_q(
            'INSERT INTO usuarios
                (id, nombre, apellido, email, password_hash, telefono, rol, empresa, cuit, creado, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $u['id'],
                $u['nombre'],
                $u['apellido'],
                mb_strtolower(trim((string) $u['email'])),
                $u['password_hash'],
                $u['telefono'] ?? null,
                $u['rol'] ?? 'cliente',
                $u['empresa'] ?? null,
                $u['cuit'] ?? null,
                $u['creado'] ?? date('Y-m-d'),
                ($u['activo'] ?? true) ? 1 : 0,
            ]
        );

        if (!empty($u['direccion']['calle'])) {
            db_q(
                'INSERT INTO direcciones (usuario_id, calle, ciudad, provincia, codigo_postal, principal)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [
                    (int) $u['id'],
                    $u['direccion']['calle'],
                    $u['direccion']['ciudad'] ?? null,
                    $u['direccion']['provincia'] ?? null,
                    $u['direccion']['codigo_postal'] ?? null,
                ]
            );
        }
    }
    $contadores['usuarios'] = count($usuarios);

    decir('· Pedidos…');
    $pedidos = leer_json($raiz, 'orders');
    foreach ($pedidos as $o) {
        db_q(
            'INSERT INTO pedidos
                (codigo, usuario_id, fecha, estado, medio_pago, subtotal, envio,
                 descuento_aplicado_pct, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $o['codigo'],
                (int) ($o['usuario_id'] ?? 0) ?: null,
                $o['fecha'],
                $o['estado'] ?? 'pendiente',
                $o['medio_pago'] ?? null,
                (int) ($o['subtotal'] ?? 0),
                (int) ($o['envio'] ?? 0),
                (float) ($o['descuento_aplicado_pct'] ?? 0),
                (int) ($o['total'] ?? 0),
            ]
        );

        $pedido_id = (int) db()->lastInsertId();

        foreach ((array) ($o['items'] ?? []) as $item) {
            db_q(
                'INSERT INTO pedido_items (pedido_id, producto_id, nombre, sku, cantidad, precio_unitario)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $pedido_id,
                    (int) ($item['producto_id'] ?? 0) ?: null,
                    $item['nombre'] ?? '',
                    $item['sku'] ?? null,
                    (int) ($item['cantidad'] ?? 1),
                    (int) ($item['precio_unitario'] ?? 0),
                ]
            );
        }
    }
    $contadores['pedidos'] = count($pedidos);
});

decir('');
decir('Listo.');
foreach ($contadores as $que => $cuantos) {
    decir(sprintf('  %-12s %d', $que, $cuantos));
}
decir('');
decir('El catálogo ahora vive en MySQL. Los JSON de data/ quedan como semilla:');
decir('no los edita nadie más y no se sincronizan al servidor.');
