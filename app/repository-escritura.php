<?php
/**
 * repository-escritura.php — el otro lado del contrato de datos.
 *
 * `repository.php` es la única frontera de LECTURA entre presentación y
 * datos (CLAUDE.md §4.1). Este archivo es la misma frontera para la
 * ESCRITURA, y existe por la misma razón: el día que entre MySQL se cambia
 * el cuerpo de estas funciones y ni una pantalla del panel se toca.
 *
 * Es un archivo aparte y no más funciones al final de repository.php porque
 * el sitio público no escribe NADA. Separarlos deja que index.php cargue
 * sólo la mitad que necesita, y deja a la vista que sí escribe declarando
 * explícitamente que va a hacerlo.
 *
 * ---------------------------------------------------------------------
 * LAS TRES REGLAS DE ESTE ARCHIVO
 *
 * 1. NADIE ESCRIBE UN JSON DIRECTO. Todo pasa por `_repo_escribir_json()`,
 *    que toma el lock, escribe en un temporal y renombra. Un rename en el
 *    mismo sistema de archivos es atómico: o está el archivo viejo entero
 *    o el nuevo entero, nunca medio JSON. Sin eso, dos pedidos simultáneos
 *    —o un timeout de PHP a mitad de un fwrite— dejan el catálogo roto y
 *    sin forma de recuperarlo, porque no hay base de datos con backup.
 *
 * 2. LAS FUNCIONES DEVUELVEN LO GUARDADO, no un booleano. La vista necesita
 *    el id que se acaba de asignar para redirigir a la ficha nueva, y el
 *    registro completo para mostrar lo que quedó y no lo que se mandó.
 *
 * 3. VALIDAR NO ES TAREA DE ACÁ. Estas funciones asumen datos ya limpios:
 *    de eso se ocupa `panel_campos_*()` en `app/panel.php`, que es quien
 *    sabe qué formulario los mandó y puede devolver errores por campo. Acá
 *    sólo se normaliza la FORMA (tipos, claves, orden), no el CONTENIDO.
 *
 * LOS NOMBRES PÚBLICOS VAN EN INGLÉS, como los de lectura. El resto del
 * código está en castellano y estas funciones no son la excepción por
 * gusto: `repo_*` es UNA sola API, es lo que documenta
 * `docs/DATA-CONTRACT.md` y es lo que el backend dev va a implementar.
 * Una mitad en inglés y otra en castellano obliga a recordar de qué lado
 * está cada función antes de escribirla. Los helpers privados `_repo_*`
 * sí van en castellano, como `_repo_normalizar()`: no salen de acá.
 *
 * TODO(backend): reemplazar los cuerpos por INSERT/UPDATE/DELETE con PDO.
 * La firma y lo que devuelve cada función no cambian.
 */

declare(strict_types=1);

/* ==========================================================================
   Cimientos
   ========================================================================== */

/**
 * Escribe un JSON de data/ de forma atómica y refresca la caché de lectura.
 *
 * Devuelve false si no pudo: la vista tiene que poder avisar "no se guardó"
 * en vez de mostrar un cartel de éxito sobre un archivo que no cambió. En
 * hosting compartido esto pasa de verdad —permisos, cuota de disco—, así
 * que no es una rama teórica.
 */
function _repo_escribir_json(string $archivo, array $datos): bool
{
    $ruta = dirname(__DIR__) . '/data/' . $archivo . '.json';

    $json = json_encode(
        $datos,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        error_log('repo: no se pudo serializar ' . $archivo . ': ' . json_last_error_msg());

        return false;
    }

    /* El temporal va en la MISMA carpeta que el destino a propósito: rename()
       sólo es atómico dentro del mismo sistema de archivos, y /tmp puede
       estar en otro. Con el prefijo de punto queda oculto si algo falla y
       el archivo sobrevive. */
    $temporal = dirname($ruta) . '/.' . basename($ruta) . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temporal, $json . "\n", LOCK_EX) === false) {
        error_log('repo: no se pudo escribir el temporal de ' . $archivo);

        return false;
    }

    if (!@rename($temporal, $ruta)) {
        @unlink($temporal);
        error_log('repo: no se pudo renombrar el temporal de ' . $archivo);

        return false;
    }

    // La caché de lectura quedó vieja en este mismo request.
    _repo_json($archivo, true);

    return true;
}

/**
 * El id siguiente de una colección.
 *
 * MAX + 1 y no COUNT + 1: si se borra el último registro, count() devuelve
 * un id que ya usó otro y el borrado le pisa la historia al que quedó.
 */
function _repo_siguiente_id(array $coleccion, string $clave = 'id'): int
{
    $ids = array_map(
        static fn (array $fila): int => (int) ($fila[$clave] ?? 0),
        $coleccion
    );

    return $ids === [] ? 1 : max($ids) + 1;
}

/**
 * Inserta o reemplaza una fila por id y devuelve [$coleccion, $fila].
 *
 * Concentra el "si viene con id es un update, si no es un alta" que si no
 * estaría repetido —y divergiendo— en las siete colecciones.
 */
function _repo_upsert(array $coleccion, array $fila, string $clave = 'id'): array
{
    $id = (int) ($fila[$clave] ?? 0);

    if ($id <= 0) {
        $fila[$clave] = _repo_siguiente_id($coleccion, $clave);
        $coleccion[]  = $fila;

        return [$coleccion, $fila];
    }

    foreach ($coleccion as $i => $existente) {
        if ((int) ($existente[$clave] ?? 0) === $id) {
            /* El registro guardado gana campo por campo sobre el que estaba,
               pero lo que el formulario no manda se conserva. Así una
               pantalla que edita cuatro campos no borra los otros veinte. */
            $coleccion[$i] = array_replace($existente, $fila);

            return [$coleccion, $coleccion[$i]];
        }
    }

    // Vino con id pero no existe: es un alta con id propio, no un error.
    $coleccion[] = $fila;

    return [$coleccion, $fila];
}

/** Saca una fila por id. Devuelve la colección sin ella. */
function _repo_quitar(array $coleccion, int $id, string $clave = 'id'): array
{
    return array_values(array_filter(
        $coleccion,
        static fn (array $fila): bool => (int) ($fila[$clave] ?? 0) !== $id
    ));
}

/* ==========================================================================
   Catálogo
   ========================================================================== */

/**
 * Alta o edición de un producto. Devuelve el producto guardado, o null si
 * la escritura falló.
 *
 * El `productos_count` de las categorías se recalcula acá y no en la vista:
 * es un dato derivado, y un derivado que se actualiza desde el formulario
 * se desincroniza el día que alguien borre un producto desde otra pantalla.
 */
function repo_save_product(array $datos): ?array
{
    $productos = _repo_json('products');

    [$productos, $producto] = _repo_upsert($productos, $datos);

    if (!_repo_escribir_json('products', $productos)) {
        return null;
    }

    repo_recount_categories();

    return $producto;
}

function repo_delete_product(int $id): bool
{
    $productos = _repo_quitar(_repo_json('products'), $id);

    if (!_repo_escribir_json('products', $productos)) {
        return false;
    }

    repo_recount_categories();

    return true;
}

/**
 * Recalcula cuántos productos tiene cada categoría.
 *
 * `productos_count` alimenta el bento de la home y el mega-menú. Es lo
 * primero que se nota cuando queda viejo —la home promete seis discos y el
 * catálogo muestra cinco—, así que se recalcula en cada alta y en cada baja
 * en vez de confiar en que alguien se acuerde.
 */
function repo_recount_categories(): bool
{
    $productos  = _repo_json('products');
    $categorias = _repo_json('categories');

    $conteo = [];
    foreach ($productos as $producto) {
        $slug = (string) ($producto['categoria'] ?? '');

        if ($slug !== '') {
            $conteo[$slug] = ($conteo[$slug] ?? 0) + 1;
        }
    }

    foreach ($categorias as $i => $categoria) {
        $slug = (string) ($categoria['slug'] ?? '');
        $categorias[$i]['productos_count'] = $conteo[$slug] ?? 0;
    }

    return _repo_escribir_json('categories', $categorias);
}

/* ==========================================================================
   Categorías, marcas y clientes

   Las tres son listas cortas con la misma forma —id, nombre, orden— y por
   eso comparten pantalla y comparten estas funciones. Lo único que las
   distingue es el archivo y un par de campos propios.
   ========================================================================== */

function repo_save_category(array $datos): ?array
{
    [$categorias, $categoria] = _repo_upsert(_repo_json('categories'), $datos);

    $categorias = _repo_ordenar($categorias);

    if (!_repo_escribir_json('categories', $categorias)) {
        return null;
    }

    repo_recount_categories();

    return $categoria;
}

/**
 * Borra una categoría, pero sólo si está vacía.
 *
 * Devuelve el motivo cuando no puede: la vista necesita decir "tiene 6
 * productos" y no un "no se pudo" que obligue a adivinar. Borrar la
 * categoría de un producto lo dejaría fuera del catálogo y de la búsqueda
 * sin que nada lo avise —el producto sigue existiendo, pero no aparece en
 * ningún lado—, y eso es peor que no dejar borrar.
 */
function repo_delete_category(int $id): array
{
    $categoria = null;
    foreach (_repo_json('categories') as $fila) {
        if ((int) ($fila['id'] ?? 0) === $id) {
            $categoria = $fila;
            break;
        }
    }

    if ($categoria === null) {
        return ['ok' => false, 'motivo' => 'Esa categoría ya no existe.'];
    }

    $usados = count(array_filter(
        _repo_json('products'),
        static fn (array $p): bool => ($p['categoria'] ?? '') === ($categoria['slug'] ?? '')
    ));

    if ($usados > 0) {
        return [
            'ok'     => false,
            'motivo' => sprintf(
                'No se puede borrar: %d producto%s de esta categoría. Movelos a otra primero.',
                $usados,
                $usados === 1 ? '' : 's'
            ),
        ];
    }

    if (!_repo_escribir_json('categories', _repo_quitar(_repo_json('categories'), $id))) {
        return ['ok' => false, 'motivo' => 'No se pudo guardar el archivo.'];
    }

    return ['ok' => true, 'motivo' => ''];
}

function repo_save_brand(array $datos): ?array
{
    [$marcas, $marca] = _repo_upsert(_repo_json('brands'), $datos);

    if (!_repo_escribir_json('brands', _repo_ordenar($marcas))) {
        return null;
    }

    return $marca;
}

/** Misma lógica que las categorías: una marca con productos no se borra. */
function repo_delete_brand(int $id): array
{
    $marca = null;
    foreach (_repo_json('brands') as $fila) {
        if ((int) ($fila['id'] ?? 0) === $id) {
            $marca = $fila;
            break;
        }
    }

    if ($marca === null) {
        return ['ok' => false, 'motivo' => 'Esa marca ya no existe.'];
    }

    $usados = count(array_filter(
        _repo_json('products'),
        static fn (array $p): bool => ($p['marca'] ?? '') === ($marca['slug'] ?? '')
    ));

    if ($usados > 0) {
        return [
            'ok'     => false,
            'motivo' => sprintf(
                'No se puede borrar: %d producto%s de esta marca.',
                $usados,
                $usados === 1 ? '' : 's'
            ),
        ];
    }

    if (!_repo_escribir_json('brands', _repo_quitar(_repo_json('brands'), $id))) {
        return ['ok' => false, 'motivo' => 'No se pudo guardar el archivo.'];
    }

    return ['ok' => true, 'motivo' => ''];
}

function repo_save_client(array $datos): ?array
{
    [$clientes, $cliente] = _repo_upsert(_repo_json('clients'), $datos);

    if (!_repo_escribir_json('clients', _repo_ordenar($clientes))) {
        return null;
    }

    return $cliente;
}

/** Un logo de "confían en nosotros" no lo referencia nadie: se borra y ya. */
function repo_delete_client(int $id): bool
{
    return _repo_escribir_json('clients', _repo_quitar(_repo_json('clients'), $id));
}

/* ==========================================================================
   Banners
   ========================================================================== */

/**
 * Los banners se renumeran POR POSICIÓN y no sobre la lista entera.
 *
 * `orden` sólo tiene sentido dentro de su grupo: la segunda placa del
 * carrusel es la segunda del carrusel, no la segunda de la tabla. Con una
 * renumeración global, cargar un banner de la franja mueve el número de
 * todas las placas, y el cliente ve saltar los órdenes de una sección que
 * no tocó. `repo_banners()` ordena por `posicion` y después por `orden`,
 * así que el número sólo se compara contra los de su mismo grupo.
 */
function repo_save_banner(array $datos): ?array
{
    [$banners, $banner] = _repo_upsert(_repo_json('banners'), $datos);

    $grupos = [];
    foreach ($banners as $fila) {
        $grupos[(string) ($fila['posicion'] ?? '')][] = $fila;
    }

    $ordenados = [];
    foreach ($grupos as $grupo) {
        foreach (_repo_ordenar($grupo) as $fila) {
            $ordenados[] = $fila;

            // El registro que se acaba de guardar vuelve con su orden final.
            if ((int) ($fila['id'] ?? 0) === (int) ($banner['id'] ?? 0)) {
                $banner = $fila;
            }
        }
    }

    if (!_repo_escribir_json('banners', $ordenados)) {
        return null;
    }

    return $banner;
}

function repo_delete_banner(int $id): bool
{
    return _repo_escribir_json('banners', _repo_quitar(_repo_json('banners'), $id));
}

/* ==========================================================================
   Pedidos
   ========================================================================== */

/**
 * Cambia el estado de un pedido. Es lo único que el panel toca de un pedido:
 * el resto —items, totales, quién compró— es historia y no se edita. Un
 * pedido al que se le puede cambiar el precio después de cobrado no sirve
 * como comprobante de nada.
 */
function repo_save_order_status(string $codigo, string $estado): bool
{
    $pedidos    = _repo_json('orders');
    $encontrado = false;

    foreach ($pedidos as $i => $pedido) {
        if ((string) ($pedido['codigo'] ?? '') === $codigo) {
            $pedidos[$i]['estado'] = $estado;
            $encontrado = true;
            break;
        }
    }

    if (!$encontrado) {
        return false;
    }

    return _repo_escribir_json('orders', $pedidos);
}

/* ==========================================================================
   Configuración y contenido de página
   ========================================================================== */

/**
 * Guarda settings.
 *
 * Se hace merge sobre lo que había en vez de reemplazar el archivo: el
 * formulario muestra diez campos y el archivo tiene más —los que agregue el
 * backend cuando conecte Mercado Pago, por ejemplo—. Un reemplazo completo
 * los borraría en silencio la primera vez que alguien toque "Guardar" en una
 * pantalla que no los conoce.
 */
function repo_save_settings(array $datos): ?array
{
    $settings = array_replace(_repo_json('settings'), $datos);

    if (!_repo_escribir_json('settings', $settings)) {
        return null;
    }

    return $settings;
}

/** Mismo criterio de merge que settings, y por la misma razón. */
function repo_save_nosotros(array $datos): ?array
{
    $nosotros = array_replace_recursive(_repo_json('nosotros'), $datos);

    if (!_repo_escribir_json('nosotros', $nosotros)) {
        return null;
    }

    return $nosotros;
}

/* ==========================================================================
   Utilidades
   ========================================================================== */

/**
 * Ordena por el campo `orden` y renumera de 1 en adelante.
 *
 * Renumerar y no dejar los valores que mandó el formulario: si el cliente
 * pone tres elementos en la posición 2, el orden queda indefinido y cambia
 * según cómo el motor resuelva el empate. Después de esto siempre hay un
 * primero, un segundo y un tercero.
 */
function _repo_ordenar(array $coleccion): array
{
    usort(
        $coleccion,
        static fn (array $a, array $b): int => ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0))
    );

    foreach ($coleccion as $i => $fila) {
        $coleccion[$i]['orden'] = $i + 1;
    }

    return array_values($coleccion);
}
