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

        // /nosotros se lleva también el contacto: el menú apunta a
        // /nosotros#contacto y no hay una página de contacto aparte.
        '#^/nosotros$#'                => ['nosotros',               []],

        '#^/ingresar$#'                => ['auth/ingresar',          []],
        '#^/registro$#'                => ['auth/registro',          []],
        '#^/salir$#'                   => ['auth/salir',             []],
        '#^/cuenta$#'                  => ['cuenta/index',           []],

        // Panel de administración. Todas exigen sesión con rol admin: el
        // guard está en views/admin/_guard.php, que incluye cada vista.
        // Una sección desconocida cae en el 404 del sitio, no en el panel.
        '#^/admin$#'                   => ['admin/dashboard',        []],
        '#^/admin/productos$#'         => ['admin/productos',        []],
        '#^/admin/productos/nuevo$#'   => ['admin/producto-editar',  []],
        '#^/admin/productos/(\d+)$#'   => ['admin/producto-editar',  ['id']],
        '#^/admin/pedidos$#'           => ['admin/pedidos',          []],
        '#^/admin/pedidos/([A-Za-z0-9-]+)$#' => ['admin/pedido-detalle', ['codigo']],
        '#^/admin/categorias$#'        => ['admin/categorias',       []],
        '#^/admin/marcas$#'            => ['admin/marcas',           []],
        '#^/admin/clientes$#'          => ['admin/clientes',         []],
        '#^/admin/banners$#'           => ['admin/banners',          []],
        '#^/admin/nosotros$#'          => ['admin/nosotros',         []],
        '#^/admin/configuracion$#'     => ['admin/configuracion',    []],

        // Obligatorias por la ley argentina de defensa del consumidor.
        // El enlace al botón de arrepentimiento tiene que estar en el pie de
        // todas las páginas, así que la ruta existe desde el primer día.
        '#^/arrepentimiento$#'         => ['legales/arrepentimiento', []],
        '#^/terminos$#'                => ['legales/terminos',        []],
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
