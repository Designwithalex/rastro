<?php
/**
 * ============================================================================
 * repository.php — el único punto de entrada a los datos de Rastro Fitness.
 * ============================================================================
 *
 * REGLA DE ORO DEL PROYECTO
 *
 *   Ninguna vista lee un JSON. Ninguna vista arma una consulta. Toda lectura
 *   de datos pasa por una de las funciones `repo_*` de este archivo.
 *
 * Hoy cada función lee un mock de `data/*.json`. Cuando entre el backend real,
 * lo único que cambia es el CUERPO de estas funciones: donde dice
 * `_repo_json('products')` va a decir `SELECT ... FROM productos`. La firma,
 * el nombre de las claves y la forma del array que devuelven NO se tocan,
 * porque eso es lo que consumen las vistas.
 *
 *   → Si una vista necesita un dato que acá no está, se agrega una función
 *     nueva a este archivo. Nunca un file_get_contents en la vista.
 *   → El contrato completo está documentado en docs/DATA-CONTRACT.md.
 *
 * CONVENCIONES QUE EL BACKEND TIENE QUE SOSTENER
 *
 *   · Las rutas de imagen son relativas a `assets/` y se resuelven en la vista
 *     con `asset()`. Ejemplo: "img/productos/foo.jpg".
 *   · Un producto con `activo: false` no existe para el catálogo: no aparece
 *     en listados, ni en la ficha, ni en relacionados. Sí se resuelve por id
 *     en `repo_cart_items()` y sigue figurando en pedidos viejos.
 *   · Los precios son enteros en pesos. El descuento por transferencia NO se
 *     calcula acá: lo calcula `precio_con_descuento()` en helpers.php.
 *   · El copy editable —hoy `banners.titulo`— no lleva números escritos a
 *     mano. Lleva marcadores: `{descuento}`, `{envio_gratis}`, `{whatsapp}`,
 *     que la vista resuelve con `interpolar()`. Un banner que dice "15%" es
 *     una segunda fuente de verdad del dato más importante del sitio y queda
 *     desactualizado el día que el cliente cambie el porcentaje.
 *   · Un pedido guarda su propio `precio_unitario` y su propio
 *     `descuento_aplicado_pct`. Nunca se recalculan contra el producto:
 *     el precio de hoy no es el precio al que se vendió en abril.
 *   · Las funciones nunca devuelven el `password_hash` de un usuario.
 *
 * AMPLIACIÓN DEL CONTRATO — 2026-08-25, `repo_nosotros()`
 *
 *   El contrato original de CLAUDE.md §4.2 cubría catálogo, prueba social,
 *   configuración, cuenta y carrito. La página /nosotros necesita contenido
 *   que no entra en ninguno de esos grupos —fundadores, hitos, obras y
 *   cuatro cifras— y se agrega UNA sola función que devuelve todo el
 *   contenido de esa sección de una vez.
 *
 *   Una y no tres a propósito: `repo_fundadores()`, `repo_hitos()` y
 *   `repo_obras()` serían tres consultas y tres pantallas de ABM para lo que
 *   el cliente piensa como una sola cosa, "la página de Nosotros". Del lado
 *   del panel es una sección con varios bloques, no tres secciones.
 *
 *   TODO(backend): esto es la NOVENA sección del panel de administración.
 *   Las ocho diseñadas son Dashboard, Productos, Pedidos, Marcas oficiales,
 *   Logos de clientes, Banners, Categorías y Configuración; falta dibujar
 *   "Nosotros" (PENDIENTES #49). Mientras tanto el contenido vive en
 *   data/nosotros.json y todo lo que está en null es un hueco declarado.
 *
 * MARCA PROPIA vs. MARCA DE TERCEROS  (`brands.es_propia`)
 *
 *   Rastro vende dos cosas distintas y la UI no las puede mezclar:
 *
 *     · Marcas de terceros — Greencore y compañía. Rastro es su vendedor
 *       oficial, y esa es la prueba social de la home: la franja dice
 *       "somos vendedores oficiales de estas marcas".
 *     · Línea propia — productos que Rastro fabrica o marca. Ponerlos en
 *       esa franja le saca el sentido a la frase: uno no es vendedor
 *       oficial de sí mismo.
 *
 *   Por eso una marca con `es_propia: true` existe en la tabla —un producto
 *   siempre tiene que poder resolver el nombre de su marca— pero
 *   `repo_brands()` NO la devuelve: esa función alimenta la franja de la
 *   home y solo lista terceros.
 *
 *   El nombre de la marca de un producto no se resuelve llamando a
 *   `repo_brands()`: viene ya en el propio producto, en `marca_nombre`,
 *   que el repository resuelve contra la tabla completa (propias incluidas).
 *   TODO(backend): eso es un LEFT JOIN marcas ON marcas.slug = productos.marca,
 *   sin el WHERE que filtra las propias.
 */

declare(strict_types=1);

/* ==========================================================================
   Privado. Nada de esto es parte del contrato: son los andamios del mock.
   ========================================================================== */

/**
 * Lee un JSON de data/ y lo cachea en memoria: dentro del mismo request el
 * archivo se abre una sola vez por más veces que se lo pida.
 *
 * `$recargar` está para el panel de administración, que en un mismo request
 * lee, escribe y vuelve a leer. Sin él la segunda lectura devolvería lo que
 * había ANTES de guardar y la pantalla mostraría el dato viejo justo después
 * de tocar "Guardar" —el bug más desconcertante que puede tener un panel—.
 * Lo usa `_repo_escribir_json()` y nadie más: una vista nunca tiene motivo
 * para pedir una recarga.
 *
 * TODO(backend): reemplazar por la conexión PDO que sale de app/config.php.
 */
function _repo_json(string $archivo, bool $recargar = false): array
{
    static $cache = [];

    if (!$recargar && isset($cache[$archivo])) {
        return $cache[$archivo];
    }

    $ruta = dirname(__DIR__) . '/data/' . $archivo . '.json';

    if (!is_file($ruta)) {
        return $cache[$archivo] = [];
    }

    $datos = json_decode((string) file_get_contents($ruta), true);

    return $cache[$archivo] = is_array($datos) ? $datos : [];
}

/**
 * Normaliza un texto para comparar: minúsculas y sin acentos.
 * Es el equivalente pobre de un COLLATE utf8mb4_unicode_ci.
 */
function _repo_normalizar(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');

    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);
}

/**
 * Todas las marcas indexadas por slug, la línea propia incluida.
 * Es el lado "uno" del join: sirve para resolver el nombre de la marca de
 * un producto, no para listar marcas en pantalla. Para eso está repo_brands().
 */
function _repo_marcas_por_slug(): array
{
    static $indice = null;

    if ($indice === null) {
        $indice = [];
        foreach (_repo_json('brands') as $marca) {
            $indice[(string) ($marca['slug'] ?? '')] = $marca;
        }
    }

    return $indice;
}

/**
 * Deja un producto listo para salir del repository: le agrega el nombre de
 * su marca, que es lo que la vista muestra. Ningún producto sale de acá sin
 * pasar por esta función.
 */
function _repo_producto_publico(array $producto): array
{
    $marcas = _repo_marcas_por_slug();
    $slug   = (string) ($producto['marca'] ?? '');

    $producto['marca_nombre'] = $marcas[$slug]['nombre'] ?? null;

    return $producto;
}

/**
 * Saca el hash de contraseña de un usuario antes de que salga de acá.
 */
function _repo_usuario_publico(array $usuario): array
{
    unset($usuario['password_hash']);

    return $usuario;
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
    $productos = array_filter(
        _repo_json('products'),
        static fn (array $p): bool => ($p['activo'] ?? false) === true
    );

    if (!empty($filters['categoria'])) {
        $categoria = (string) $filters['categoria'];
        $productos = array_filter($productos, static fn ($p) => ($p['categoria'] ?? '') === $categoria);
    }

    if (!empty($filters['marca'])) {
        $marca = (string) $filters['marca'];
        $productos = array_filter($productos, static fn ($p) => ($p['marca'] ?? '') === $marca);
    }

    if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
        $q = _repo_normalizar((string) $filters['q']);
        $productos = array_filter($productos, static function (array $p) use ($q): bool {
            $heno = _repo_normalizar(implode(' ', [
                $p['nombre'] ?? '',
                $p['sku'] ?? '',
                $p['descripcion_corta'] ?? '',
                $p['descripcion'] ?? '',
                $p['categoria'] ?? '',
                $p['marca'] ?? '',
            ]));

            return str_contains($heno, $q);
        });
    }

    if (isset($filters['precio_min']) && $filters['precio_min'] !== '') {
        $min = (int) $filters['precio_min'];
        $productos = array_filter($productos, static fn ($p) => (int) ($p['precio_lista'] ?? 0) >= $min);
    }

    if (isset($filters['precio_max']) && $filters['precio_max'] !== '') {
        $max = (int) $filters['precio_max'];
        $productos = array_filter($productos, static fn ($p) => (int) ($p['precio_lista'] ?? 0) <= $max);
    }

    if (!empty($filters['en_stock'])) {
        $productos = array_filter($productos, static fn ($p) => (int) ($p['stock'] ?? 0) > 0);
    }

    if (!empty($filters['destacado'])) {
        $productos = array_filter($productos, static fn ($p) => ($p['destacado'] ?? false) === true);
    }

    $productos = array_values($productos);

    // Orden. "relevancia" es el orden comercial: primero lo destacado,
    // después lo nuevo, y dentro de cada grupo lo que no tiene stock va al final.
    $orden = (string) ($filters['orden'] ?? 'relevancia');

    usort($productos, static function (array $a, array $b) use ($orden): int {
        return match ($orden) {
            'precio_asc'  => ((int) ($a['precio_lista'] ?? 0)) <=> ((int) ($b['precio_lista'] ?? 0)),
            'precio_desc' => ((int) ($b['precio_lista'] ?? 0)) <=> ((int) ($a['precio_lista'] ?? 0)),
            'nombre'      => strcmp(
                _repo_normalizar((string) ($a['nombre'] ?? '')),
                _repo_normalizar((string) ($b['nombre'] ?? ''))
            ),
            default       => [(int) ($b['stock'] ?? 0) > 0, $b['destacado'] ?? false, $b['nuevo'] ?? false, (int) ($a['id'] ?? 0)]
                             <=> [(int) ($a['stock'] ?? 0) > 0, $a['destacado'] ?? false, $a['nuevo'] ?? false, (int) ($b['id'] ?? 0)],
        };
    });

    $total   = count($productos);
    $perPage = max(1, $perPage);
    $paginas = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $paginas);

    return [
        'items'   => array_map('_repo_producto_publico', array_slice($productos, ($page - 1) * $perPage, $perPage)),
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
    foreach (_repo_json('products') as $producto) {
        if (($producto['slug'] ?? '') === $slug && ($producto['activo'] ?? false) === true) {
            return _repo_producto_publico($producto);
        }
    }

    return null;
}

/**
 * Productos de la misma categoría, sin repetir el que se está viendo.
 * Primero los de la misma marca y los destacados.
 */
function repo_related_products(string $slug, int $limit = 4): array
{
    $actual = repo_product($slug);

    if ($actual === null) {
        return [];
    }

    $candidatos = array_values(array_filter(
        _repo_json('products'),
        static fn (array $p): bool => ($p['activo'] ?? false) === true
            && ($p['categoria'] ?? '') === ($actual['categoria'] ?? '')
            && ($p['slug'] ?? '') !== $slug
    ));

    usort($candidatos, static function (array $a, array $b) use ($actual): int {
        return [
            ($b['marca'] ?? '') === ($actual['marca'] ?? ''),
            (int) ($b['stock'] ?? 0) > 0,
            $b['destacado'] ?? false,
        ] <=> [
            ($a['marca'] ?? '') === ($actual['marca'] ?? ''),
            (int) ($a['stock'] ?? 0) > 0,
            $a['destacado'] ?? false,
        ];
    });

    return array_map('_repo_producto_publico', array_slice($candidatos, 0, max(0, $limit)));
}

/**
 * Categorías del catálogo, ordenadas.
 *
 * `productos_count` se recalcula sobre los productos activos en vez de leerse
 * del mock: un número que miente en la grilla de la home es peor que no tenerlo.
 * TODO(backend): resolverlo con un COUNT agrupado, no con un bucle por categoría.
 */
function repo_categories(): array
{
    $categorias = _repo_json('categories');

    $conteo = [];
    foreach (_repo_json('products') as $producto) {
        if (($producto['activo'] ?? false) === true) {
            $slug = (string) ($producto['categoria'] ?? '');
            $conteo[$slug] = ($conteo[$slug] ?? 0) + 1;
        }
    }

    foreach ($categorias as &$categoria) {
        $categoria['productos_count'] = $conteo[$categoria['slug'] ?? ''] ?? 0;
    }
    unset($categoria);

    usort($categorias, static fn ($a, $b) => ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0)));

    return $categorias;
}

/**
 * Una categoría por su slug.
 */
function repo_category(string $slug): ?array
{
    foreach (repo_categories() as $categoria) {
        if (($categoria['slug'] ?? '') === $slug) {
            return $categoria;
        }
    }

    return null;
}

/* ==========================================================================
   Marca y prueba social
   ========================================================================== */

/**
 * Marcas de TERCEROS de las que Rastro es vendedor oficial, ordenadas.
 *
 * Deja afuera la línea propia (`es_propia: true`): esta función alimenta la
 * franja de prueba social de la home, y ahí Rastro no va. Ver el bloque
 * "marca propia vs. marca de terceros" arriba de todo.
 *
 * AMPLIACIÓN — 2026-08-26, `$incluir_propias`
 *
 * El filtro de marca del catálogo necesita lo contrario: ahí la pregunta no
 * es "¿de quién somos vendedores oficiales?" sino "¿de qué marca es este
 * producto?", y la línea propia es la respuesta de 19 de los 30 productos
 * del catálogo. Sin la propia, ese filtro esconde dos tercios del catálogo.
 *
 * Son dos preguntas distintas sobre la misma tabla, así que es un
 * parámetro y no una función nueva. El valor por defecto es el
 * comportamiento viejo: ninguna llamada existente cambia de resultado.
 *
 * TODO(backend): con MySQL es el mismo SELECT con o sin
 * `WHERE es_propia = 0`.
 */
function repo_brands(bool $incluir_propias = false): array
{
    $marcas = array_values(array_filter(
        _repo_json('brands'),
        static fn (array $m): bool => $incluir_propias || empty($m['es_propia'])
    ));

    usort($marcas, static fn ($a, $b) => ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0)));

    return $marcas;
}

/**
 * Logos de "Confían en nosotros": clientes a los que ya se les vendió.
 */
function repo_clients(): array
{
    $clientes = _repo_json('clients');
    usort($clientes, static fn ($a, $b) => ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0)));

    return $clientes;
}

/**
 * Banners activos, ordenados. La vista elige por `posicion`
 * (hero | mayorista | franja).
 */
function repo_banners(): array
{
    $banners = array_values(array_filter(
        _repo_json('banners'),
        static fn (array $b): bool => ($b['activo'] ?? false) === true
    ));

    usort($banners, static fn ($a, $b) => [(string) ($a['posicion'] ?? ''), (int) ($a['orden'] ?? 0)]
                                      <=> [(string) ($b['posicion'] ?? ''), (int) ($b['orden'] ?? 0)]);

    return $banners;
}

/**
 * TODOS los banners, activos y apagados, para el panel.
 *
 * `repo_banners()` filtra por `activo` porque alimenta el sitio público, y
 * ahí un banner apagado no existe. El panel necesita justo lo contrario: si
 * no ve los apagados, apagar un banner es lo mismo que borrarlo y no hay
 * forma de volver a prenderlo. Son dos preguntas distintas y por eso son
 * dos funciones, no un parámetro que haya que acordarse de pasar.
 */
function repo_all_banners(): array
{
    $banners = _repo_json('banners');

    usort($banners, static fn ($a, $b) => [(string) ($a['posicion'] ?? ''), (int) ($a['orden'] ?? 0)]
                                      <=> [(string) ($b['posicion'] ?? ''), (int) ($b['orden'] ?? 0)]);

    return $banners;
}

/**
 * El catálogo entero, sin paginar y sin filtrar, para el panel.
 *
 * `repo_products()` pagina de a 12 y ordena por relevancia, que es lo que
 * necesita quien compra. Quien administra necesita la lista completa para
 * buscar un SKU o ver de un vistazo qué se quedó sin stock.
 */
function repo_all_products(): array
{
    $productos = _repo_json('products');

    usort($productos, static fn ($a, $b) => strcmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? '')));

    return $productos;
}

/**
 * Todos los pedidos, del más nuevo al más viejo, para el panel.
 *
 * `repo_orders()` pide un `usuario_id` porque en /cuenta cada quien ve los
 * suyos. Acá el filtro sería un estorbo: el panel administra los de todos.
 */
function repo_all_orders(): array
{
    $pedidos = _repo_json('orders');

    usort($pedidos, static fn ($a, $b) => strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? '')));

    return $pedidos;
}

/* ==========================================================================
   Contenido de página
   ========================================================================== */

/**
 * Todo el contenido de la sección "Nosotros", de una sola vez.
 *
 * Alimenta las dos piezas que usan ese contenido con roles distintos: la
 * página /nosotros completa y la franja de la home. Por eso devuelve el
 * paquete entero y no un pedazo por llamada.
 *
 * @return array{
 *     provisorio:bool,
 *     encabezado:array{kicker:string,titulo:string,declaracion:string},
 *     cifras:array<int,array{clave:string,rotulo:string,valor:?int}>,
 *     fundadores:array{titulo:string,texto:string,foto:?string,foto_alt:?string,personas:array},
 *     historia:array{titulo:string,texto:string,hitos:array},
 *     como_trabajamos:array{titulo:string,texto:string,pasos:array},
 *     obras:array{titulo:string,texto:string,items:array},
 *     garantia:array{titulo:string,items:array},
 *     donde_estamos:array{titulo:string,texto:string,direccion:?string,foto:?string,foto_alt:?string},
 *     cierre:array{titulo:string,texto:string,acciones:array},
 *     franja:array{titulo:string,texto:string,enlace_texto:string}
 * }
 *
 * Reglas que la vista puede dar por sentadas:
 *
 *   · Un valor en null es un hueco DECLARADO, no un error: la vista lo
 *     dibuja como marcador visible en vez de esconder la fila. Hoy son las
 *     tres cifras que el cliente no pasó, los años de los hitos y la
 *     dirección del depósito.
 *   · `obras.items` puede venir vacío y eso NO es un estado de error: la
 *     página esconde la sección entera. Una sección de obras vacía es peor
 *     que no tenerla.
 *   · `fundadores.foto` puede ser null y la página colapsa ese bloque a un
 *     párrafo firmado con los dos nombres. No se reemplaza por un retrato
 *     de archivo: una página sin foto es honesta.
 *   · La cifra `productos_en_catalogo` NO se carga a mano. Se resuelve acá
 *     contra el catálogo, porque un número escrito en un JSON al lado de la
 *     tabla que lo contradice queda desactualizado el mismo día.
 *
 * TODO(backend): pasa a ser la novena sección del panel. Cada bloque de
 * este array es un grupo de campos de esa pantalla; `productos_en_catalogo`
 * no lleva campo, es un COUNT.
 */
function repo_nosotros(): array
{
    $contenido = _repo_json('nosotros');

    // El comentario del mock no es parte del contrato.
    unset($contenido['_comentario']);

    $contenido['provisorio'] = (bool) ($contenido['provisorio'] ?? false);

    // Forma garantizada: la vista no tiene que preguntar si existe la clave,
    // solo si está vacía. Es la diferencia entre un estado vacío y un error.
    $contenido['encabezado']      = (array) ($contenido['encabezado'] ?? []);
    $contenido['cifras']          = array_values((array) ($contenido['cifras'] ?? []));
    $contenido['fundadores']      = (array) ($contenido['fundadores'] ?? []);
    $contenido['historia']        = (array) ($contenido['historia'] ?? []);
    $contenido['como_trabajamos'] = (array) ($contenido['como_trabajamos'] ?? []);
    $contenido['obras']           = (array) ($contenido['obras'] ?? []);
    $contenido['garantia']        = (array) ($contenido['garantia'] ?? []);
    $contenido['donde_estamos']   = (array) ($contenido['donde_estamos'] ?? []);
    $contenido['cierre']          = (array) ($contenido['cierre'] ?? []);
    $contenido['franja']          = (array) ($contenido['franja'] ?? []);

    $contenido['fundadores']['personas']   = array_values((array) ($contenido['fundadores']['personas'] ?? []));
    $contenido['historia']['hitos']        = array_values((array) ($contenido['historia']['hitos'] ?? []));
    $contenido['como_trabajamos']['pasos'] = array_values((array) ($contenido['como_trabajamos']['pasos'] ?? []));
    $contenido['obras']['items']           = array_values((array) ($contenido['obras']['items'] ?? []));
    $contenido['garantia']['items']        = array_values((array) ($contenido['garantia']['items'] ?? []));
    $contenido['cierre']['acciones']       = array_values((array) ($contenido['cierre']['acciones'] ?? []));

    // La única cifra que no se carga: sale del catálogo.
    // TODO(backend): SELECT COUNT(*) FROM productos WHERE activo = 1.
    $total_catalogo = repo_products([], 1, 1)['total'];

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
 * TODO(backend): pasa a ser la tabla que edita el panel de administración.
 */
function repo_settings(): array
{
    return _repo_json('settings');
}

/* ==========================================================================
   Cuenta — mock. Todo este bloque lo conecta el backend dev.
   ========================================================================== */

/**
 * Verifica credenciales y devuelve el usuario sin su hash, o null.
 *
 * TODO(backend): además de validar, tiene que abrir la sesión, regenerar el
 * id de sesión y aplicar límite de intentos. La maqueta no persiste nada.
 * Usuarios de prueba: demo@rastrofitness.com.ar y mayorista@rastrofitness.com.ar,
 * los dos con la contraseña "rastro2026".
 */
function repo_login(string $email, string $password): ?array
{
    $email = _repo_normalizar($email);

    foreach (_repo_json('users') as $usuario) {
        if (_repo_normalizar((string) ($usuario['email'] ?? '')) !== $email) {
            continue;
        }

        if (($usuario['activo'] ?? false) !== true) {
            return null;
        }

        if (password_verify($password, (string) ($usuario['password_hash'] ?? ''))) {
            return _repo_usuario_publico($usuario);
        }

        return null;
    }

    // Se gasta el mismo tiempo aunque el mail no exista, para no filtrar
    // por diferencia de tiempos qué direcciones están registradas.
    password_verify($password, '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexiste');

    return null;
}

/**
 * Alta de usuario. Valida y devuelve el resultado; NO persiste.
 *
 * @return array{ok:bool, errores:array<string,string>, usuario:?array}
 *
 * TODO(backend): guardar el usuario con password_hash(), verificar que el mail
 * no esté tomado, mandar el mail de bienvenida y abrir la sesión.
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
        foreach (_repo_json('users') as $usuario) {
            if (_repo_normalizar((string) $usuario['email']) === _repo_normalizar($email)) {
                $errores['email'] = 'Ya hay una cuenta con este correo.';
                break;
            }
        }
    }

    if (mb_strlen($password) < 8) {
        $errores['password'] = 'La contraseña necesita al menos 8 caracteres.';
    }

    if ($errores !== []) {
        return ['ok' => false, 'errores' => $errores, 'usuario' => null];
    }

    return [
        'ok'      => true,
        'errores' => [],
        'usuario' => [
            'id'        => 0,
            'nombre'    => $nombre,
            'apellido'  => $apellido,
            'email'     => $email,
            'telefono'  => trim((string) ($data['telefono'] ?? '')),
            'rol'       => ($data['es_empresa'] ?? false) ? 'mayorista' : 'cliente',
            'empresa'   => trim((string) ($data['empresa'] ?? '')) ?: null,
            'cuit'      => trim((string) ($data['cuit'] ?? '')) ?: null,
            'direccion' => null,
            'creado'    => date('Y-m-d'),
            'activo'    => true,
        ],
    ];
}

/**
 * Un usuario por id, sin su hash de contraseña.
 */
function repo_user(int $id): ?array
{
    foreach (_repo_json('users') as $usuario) {
        if ((int) ($usuario['id'] ?? 0) === $id) {
            return _repo_usuario_publico($usuario);
        }
    }

    return null;
}

/**
 * Pedidos de un usuario, del más nuevo al más viejo.
 *
 * Los items vienen tal como se guardaron: nombre, SKU y precio unitario del
 * día de la compra. No se enriquecen contra el catálogo actual a propósito.
 */
function repo_orders(int $userId): array
{
    $pedidos = array_values(array_filter(
        _repo_json('orders'),
        static fn (array $o): bool => (int) ($o['usuario_id'] ?? 0) === $userId
    ));

    usort($pedidos, static fn ($a, $b) => strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? '')));

    return $pedidos;
}

/**
 * Un pedido por su código (RF-2026-0418).
 *
 * TODO(backend): esta función NO valida quién pide el pedido. Hoy da igual
 * porque no hay sesión y la vista no está escrita, pero el código es
 * adivinable (RF-año-ddmm) y devuelve nombre, dirección y total de una
 * compra. Antes de exponerla en /cuenta hay que exigir sesión y comparar
 * usuario_id contra el usuario logueado, o recibir el id del dueño como
 * segundo argumento y filtrar acá. Si no, cualquiera lee los pedidos de
 * cualquiera probando códigos.
 */
function repo_order(string $code): ?array
{
    foreach (_repo_json('orders') as $pedido) {
        if (($pedido['codigo'] ?? '') === $code) {
            return $pedido;
        }
    }

    return null;
}

/* ==========================================================================
   Carrito
   ========================================================================== */

/**
 * Resuelve los ids que el carrito guarda en localStorage.
 *
 * Devuelve los productos en el mismo orden en que llegaron los ids, con su
 * `activo` y su `stock` puestos, para que el carrito pueda avisar que algo se
 * dio de baja o se quedó sin stock en vez de hacerlo desaparecer sin explicación.
 * Las cantidades no viven acá: las pone el JS.
 */
function repo_cart_items(array $ids): array
{
    $porId = [];
    foreach (_repo_json('products') as $producto) {
        $porId[(int) ($producto['id'] ?? 0)] = $producto;
    }

    $items = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if (isset($porId[$id])) {
            $items[] = _repo_producto_publico($porId[$id]);
        }
    }

    return $items;
}
