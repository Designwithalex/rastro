<?php
/**
 * admin/clientes.php — ABM de los logos de "Confían en nosotros".
 *
 * Sigue el frame "Admin · Logos de clientes".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=84-331
 *
 * Son los clientes a los que ya se les vendió, no los usuarios del sitio.
 * Mientras no haya logo se escribe el nombre: un nombre es información
 * real y se puede revisar; un recuadro vacío no le dice nada a nadie.
 */

declare(strict_types=1);

$abm = [
    'titulo'   => 'Clientes',
    'seccion'  => 'clientes',
    'singular' => 'un cliente',
    'listar'   => 'repo_clients',
    'guardar'  => 'repo_cliente_guardar',
    'borrar'   => 'repo_cliente_borrar',
    'nota'     => 'Salen en la franja "Confían en nosotros" de la home, en monocromo plata y a color '
                . 'al pasar el mouse. Conviene pedirlos en SVG o PNG con fondo transparente.',
    'campos'   => [
        'nombre' => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'requerido' => true,
                     'ayuda' => 'Es lo que se dibuja mientras no haya logo cargado.'],
        'logo'   => ['etiqueta' => 'Logo', 'tipo' => 'texto', 'placeholder' => 'img/clientes/…',
                     'ayuda' => 'Ruta relativa a assets/.'],
        'orden'  => ['etiqueta' => 'Orden', 'tipo' => 'numero'],
    ],
    'columnas' => ['nombre', 'logo', 'orden'],
];

require RASTRO_VIEWS . '/admin/_abm.php';
