<?php
/**
 * admin/banners.php — ABM de banners.
 *
 * Sigue el frame "Admin · Banners".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=84-449
 *
 * DÓNDE SE VE CADA POSICIÓN
 *
 *   hero       el primero es la foto de fondo del hero de la home. Del
 *              SEGUNDO en adelante se dibujan como las placas del collage
 *              de la derecha (PENDIENTES #61).
 *   mayorista  el bloque de la landing mayorista.
 *   franja     la banda de descuento.
 *
 * EL TÍTULO NO LLEVA NÚMEROS ESCRITOS A MANO. Lleva marcadores que el
 * sitio reemplaza por el dato vivo: {descuento}, {envio_gratis} y
 * {whatsapp}. Un banner que dice "15%" es una segunda fuente de verdad del
 * dato más importante del sitio, y queda desactualizado el día que se
 * cambie el porcentaje en Configuración.
 */

declare(strict_types=1);

$abm = [
    'titulo'   => 'Banners',
    'seccion'  => 'banners',
    'singular' => 'un banner',
    'listar'   => 'repo_banners',
    'guardar'  => 'repo_banner_guardar',
    'borrar'   => 'repo_banner_borrar',
    'nota'     => 'En el hero, el primer banner es la foto de fondo; del segundo en adelante se '
                . 'dibujan como las placas del collage de la derecha.',
    'campos'   => [
        'titulo'   => ['etiqueta' => 'Título', 'tipo' => 'texto', 'ancho' => true,
                       'ayuda' => 'Podés usar {descuento}, {envio_gratis} y {whatsapp}: el sitio los reemplaza por el dato real. No escribas el número a mano.'],
        'imagen'   => ['etiqueta' => 'Imagen', 'tipo' => 'texto', 'placeholder' => 'img/ambiente/…',
                       'ayuda' => 'Ruta relativa a assets/.'],
        'enlace'   => ['etiqueta' => 'Enlace', 'tipo' => 'texto', 'placeholder' => '/catalogo'],
        'posicion' => ['etiqueta' => 'Posición', 'tipo' => 'select', 'opciones' => [
            'hero'      => 'Hero de la home',
            'mayorista' => 'Bloque mayorista',
            'franja'    => 'Franja de descuento',
        ]],
        'orden'    => ['etiqueta' => 'Orden', 'tipo' => 'numero'],
        'activo'   => ['etiqueta' => 'Activo', 'tipo' => 'casilla',
                       'ayuda' => 'Desmarcado, el banner no se dibuja pero no se pierde.'],
    ],
    'columnas' => ['titulo', 'posicion', 'orden', 'activo'],
];

require RASTRO_VIEWS . '/admin/_abm.php';
