<?php
/**
 * admin/marcas.php — ABM de marcas.
 *
 * Sigue el frame "Admin · Marcas oficiales".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=84-227
 *
 * LA CASILLA "LÍNEA PROPIA" ES LA QUE IMPORTA. Una marca marcada como
 * propia sigue existiendo para los productos —que necesitan poder decir de
 * qué marca son— pero NO aparece en la franja "vendedores oficiales" de la
 * home: uno no es vendedor oficial de sí mismo. Es la única casilla de
 * esta pantalla que cambia algo en el sitio público, y por eso el
 * formulario lo explica en vez de dejarlo a la intuición.
 */

declare(strict_types=1);

$abm = [
    'titulo'   => 'Marcas oficiales',
    'seccion'  => 'marcas',
    'singular' => 'una marca',
    'listar'   => 'repo_admin_marcas',
    'guardar'  => 'repo_marca_guardar',
    'borrar'   => 'repo_marca_borrar',
    'nota'     => 'Las marcas de terceros salen en la franja "Vendedores oficiales" de la home. '
                . 'La línea propia queda afuera de esa franja pero sigue disponible para los productos.',
    'campos'   => [
        'nombre'    => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'requerido' => true],
        'slug'      => ['etiqueta' => 'URL', 'tipo' => 'texto',
                        'ayuda' => 'Se calcula del nombre si se deja vacío.'],
        'orden'     => ['etiqueta' => 'Orden', 'tipo' => 'numero'],
        'logo'      => ['etiqueta' => 'Logo', 'tipo' => 'texto',
                        'placeholder' => 'img/marca/…',
                        'ayuda' => 'Ruta relativa a assets/. Sin logo se escribe el nombre, que también es información.'],
        'es_propia' => ['etiqueta' => 'Es la línea propia de Rastro', 'tipo' => 'casilla',
                        'ayuda' => 'Marcada, la marca NO aparece en la franja de vendedores oficiales de la home.'],
    ],
    'columnas' => ['nombre', 'slug', 'orden', 'es_propia'],
];

require RASTRO_VIEWS . '/admin/_abm.php';
