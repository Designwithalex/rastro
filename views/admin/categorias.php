<?php
/**
 * admin/categorias.php — ABM de categorías.
 *
 * No tiene pantalla propia en Figma: es el mismo patrón que Marcas y está
 * explicado en la anotación de al lado de esa pantalla. Por eso las dos
 * usan `_abm.php`.
 *
 * Las categorías arman el mega-menú, el bento de la home y el filtro del
 * catálogo, así que el orden importa: es el que se ve en los tres lados.
 */

declare(strict_types=1);

$abm = [
    'titulo'   => 'Categorías',
    'seccion'  => 'categorias',
    'singular' => 'una categoría',
    'listar'   => 'repo_categories',
    'guardar'  => 'repo_categoria_guardar',
    'borrar'   => 'repo_categoria_borrar',
    'nota'     => 'El orden es el que se ve en el mega-menú, en el bento de la home y en el filtro '
                . 'del catálogo. El bento muestra las cinco primeras más la celda "Ver todo".',
    'campos'   => [
        'nombre'      => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'requerido' => true],
        'slug'        => ['etiqueta' => 'URL', 'tipo' => 'texto',
                          'ayuda' => 'Se calcula del nombre si se deja vacío. Cambiarla rompe los enlaces ya compartidos.'],
        'orden'       => ['etiqueta' => 'Orden', 'tipo' => 'numero'],
        'pictograma'  => ['etiqueta' => 'Pictograma', 'tipo' => 'texto',
                          'placeholder' => 'img/iconos/icono-disco-blanco.png',
                          'ayuda' => 'Ruta relativa a assets/. Se ve en el bento y en el mega-menú.'],
        'descripcion' => ['etiqueta' => 'Descripción', 'tipo' => 'area', 'ancho' => true,
                          'ayuda' => 'Es la meta description de la página de la categoría.'],

        /* No es editable, pero sí una columna de la tabla: es el dato que
           dice si borrar una categoría va a dejar productos sueltos. */
        'productos_count' => ['etiqueta' => 'Productos', 'tipo' => 'texto'],
    ],
    'columnas' => ['nombre', 'slug', 'orden', 'productos_count'],
];

require RASTRO_VIEWS . '/admin/_abm.php';
