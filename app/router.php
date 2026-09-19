<?php
/**
 * router.php — resuelve una ruta limpia a un archivo de vista.
 *
 * El router no lee datos y no decide contenido: traduce una URL a un nombre
 * de vista y a un puñado de parámetros. Validar que la categoría o el
 * producto existan es tarea de la vista, que para eso llama al repository
 * y, si no encuentra nada, dispara router_404().
 */

declare(strict_types=1);

/**
 * Tabla de rutas. Cada entrada es:
 *   patrón de la ruta => [vista, [nombres de los parámetros capturados]]
 *
 * La vista es una ruta relativa a views/, sin la extensión .php.
 */
function router_tabla(): array
{
    return [
        '#^/$#'                        => ['home',                   []],
        '#^/catalogo$#'                => ['catalogo',               []],
        '#^/catalogo/([a-z0-9-]+)$#'   => ['catalogo',               ['categoria']],
        '#^/producto/([a-z0-9-]+)$#'   => ['producto',               ['slug']],
        '#^/carrito$#'                 => ['carrito',                []],
        '#^/mayoristas$#'              => ['mayoristas',             []],

        /* Checkout y pago.

           /checkout recibe el formulario y se lo manda a sí misma, igual que
           /ingresar: así los errores de validación se pintan al lado de cada
           campo sin perder lo que la persona ya escribió.

           /webhooks/mercadopago no es una página: es el endpoint que llama
           Mercado Pago para avisar que un pago cambió de estado. Contesta
           texto plano y no imprime una sola etiqueta HTML. El router no mira
           el método, así que esa vista verifica que sea POST.

           Las rutas de pago existen aunque no haya credenciales cargadas: sin
           ellas el checkout muestra solamente el camino por transferencia.
           Ver app/mercadopago.php y docs/MERCADOPAGO.md. */
        '#^/checkout$#'                => ['checkout/index',          []],
        '#^/checkout/retorno$#'        => ['checkout/retorno',        []],
        '#^/webhooks/mercadopago$#'    => ['webhooks/mercadopago',    []],

        // /nosotros se lleva también el contacto: el menú apunta a
        // /nosotros#contacto y no hay una página de contacto aparte.
        '#^/nosotros$#'                => ['nosotros',               []],

        '#^/ingresar$#'                => ['auth/ingresar',          []],
        '#^/registro$#'                => ['auth/registro',          []],
        '#^/cuenta$#'                  => ['cuenta/index',           []],
        '#^/salir$#'                   => ['auth/salir',            []],
        '#^/recuperar$#'               => ['auth/recuperar',        []],
        '#^/recuperar/([a-f0-9]{64})$#' => ['auth/restablecer',      ['token']],

        // Obligatorias por la ley argentina de defensa del consumidor.
        // El enlace al botón de arrepentimiento tiene que estar en el pie de
        // todas las páginas, así que la ruta existe desde el primer día.
        '#^/arrepentimiento$#'         => ['legales/arrepentimiento', []],
        '#^/terminos$#'                => ['legales/terminos',        []],

        /* --- Panel de administración ---------------------------------
           Van al final para que ninguna ruta pública quede tapada por un
           patrón de acá. Cada vista se ocupa de exigir la sesión: el
           router traduce URLs, no decide quién entra (app/panel.php).

           Las pantallas de colección son una sola vista que hace de lista
           y de formulario. Un alta y una edición del mismo registro son la
           misma pantalla con o sin datos adentro; separarlas duplicaría el
           formulario y garantizaría que se desincronicen. */
        '#^/admin$#'                        => ['admin/tablero',       []],
        '#^/admin/ingresar$#'               => ['admin/ingresar',      []],
        '#^/admin/salir$#'                  => ['admin/salir',         []],

        '#^/admin/productos$#'              => ['admin/productos',     []],
        '#^/admin/productos/nuevo$#'        => ['admin/producto',      []],
        '#^/admin/productos/(\d+)$#'        => ['admin/producto',      ['id']],

        '#^/admin/pedidos$#'                => ['admin/pedidos',       []],
        '#^/admin/pedidos/([A-Za-z0-9-]+)$#' => ['admin/pedido',       ['codigo']],

        '#^/admin/categorias$#'             => ['admin/categorias',    []],
        '#^/admin/marcas$#'                 => ['admin/marcas',        []],
        '#^/admin/clientes$#'               => ['admin/clientes',      []],
        '#^/admin/banners$#'                => ['admin/banners',       []],
        '#^/admin/arrepentimientos$#'       => ['admin/arrepentimientos', []],
        '#^/admin/nosotros$#'               => ['admin/nosotros',      []],
        '#^/admin/configuracion$#'          => ['admin/configuracion', []],
    ];
}

/**
 * Resuelve una ruta.
 *
 * @return array{vista:string, params:array<string,string>, estado:int}
 */
function router_resolver(string $ruta): array
{
    foreach (router_tabla() as $patron => [$vista, $nombres]) {
        if (!preg_match($patron, $ruta, $coincidencias)) {
            continue;
        }

        $params = [];
        foreach ($nombres as $i => $nombre) {
            $params[$nombre] = $coincidencias[$i + 1] ?? '';
        }

        return ['vista' => $vista, 'params' => $params, 'estado' => 200];
    }

    return ['vista' => 'errors/404', 'params' => [], 'estado' => 404];
}

/**
 * Corta la vista actual y muestra el 404 con el código correcto.
 * La usa una vista cuando el repository le devuelve null: producto que no
 * existe, categoría inventada, pedido de otro usuario.
 */
function router_404(): never
{
    http_response_code(404);

    require RASTRO_VIEWS . '/errors/404.php';

    exit;
}
