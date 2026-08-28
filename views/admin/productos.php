<?php
/**
 * admin/productos.php — la lista del catálogo.
 *
 * Muestra TODO junto, sin paginar. Son 30 productos y el techo realista de
 * este negocio son un par de cientos: paginar de a 20 obligaría a saltar de
 * página para encontrar un SKU, que es justo lo que se viene a hacer acá.
 * El filtro de arriba es la herramienta de búsqueda, no la paginación.
 *
 * El borrado vive en esta pantalla y no en el formulario a propósito: se
 * borra desde la lista, que es donde se ve qué se está borrando y qué queda.
 */

declare(strict_types=1);

panel_exigir_sesion();

if (panel_es_post()) {
    panel_exigir_csrf();

    $id = panel_entero('id', 0) ?? 0;

    if ($id > 0 && repo_delete_product($id)) {
        panel_ir_con_aviso('/admin/productos', 'ok', 'Producto borrado.');
    }

    panel_ir_con_aviso('/admin/productos', 'error', 'No se pudo borrar el producto.');
}

$productos  = repo_all_products();
$categorias = repo_categories();
$settings   = repo_settings();

/* Índice slug → nombre para mostrar "Discos" y no "discos" en la columna.
   Se arma una vez y no por fila: dentro del foreach serían 30 recorridas
   de la tabla de categorías para resolver 30 nombres. */
$nombres_categoria = [];
foreach ($categorias as $categoria) {
    $nombres_categoria[(string) ($categoria['slug'] ?? '')] = (string) ($categoria['nombre'] ?? '');
}

/* Filtros. Son GET y no POST para que la lista filtrada se pueda guardar en
   favoritos y compartir por WhatsApp: "mirá, estos son los que están sin
   stock". Un POST no deja hacer eso. */
$busqueda = trim(param('q'));
$filtro_categoria = param('categoria');
$solo_sin_stock   = param_bool('sin_stock');

$visibles = array_values(array_filter($productos, static function (array $p) use ($busqueda, $filtro_categoria, $solo_sin_stock): bool {
    if ($filtro_categoria !== '' && ($p['categoria'] ?? '') !== $filtro_categoria) {
        return false;
    }

    if ($solo_sin_stock && (int) ($p['stock'] ?? 0) > 0) {
        return false;
    }

    if ($busqueda === '') {
        return true;
    }

    // El SKU se busca igual que el nombre: es lo que el cliente tiene a mano.
    $aguja = _repo_normalizar($busqueda);

    return str_contains(_repo_normalizar((string) ($p['nombre'] ?? '')), $aguja)
        || str_contains(_repo_normalizar((string) ($p['sku'] ?? '')), $aguja);
}));

$titulo = 'Productos';
$bajada = sprintf(
    '%d en el catálogo%s',
    count($productos),
    count($visibles) !== count($productos) ? ' · ' . count($visibles) . ' con estos filtros' : ''
);
$accion = ['texto' => 'Nuevo producto', 'href' => url('/admin/productos/nuevo')];

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<form class="panel-filtros" method="get" action="<?= e(url('/admin/productos')) ?>">
    <div class="campo-panel campo-panel--crece">
        <label class="campo-panel__rotulo" for="q">Buscar por nombre o SKU</label>
        <input class="campo-panel__control" type="search" id="q" name="q" value="<?= e($busqueda) ?>">
    </div>

    <div class="campo-panel">
        <label class="campo-panel__rotulo" for="categoria">Categoría</label>
        <select class="campo-panel__control" id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= e((string) ($categoria['slug'] ?? '')) ?>"
                    <?= $filtro_categoria === ($categoria['slug'] ?? '') ? 'selected' : '' ?>>
                    <?= e((string) ($categoria['nombre'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <label class="campo-panel__casilla">
        <input type="checkbox" name="sin_stock" value="1" <?= $solo_sin_stock ? 'checked' : '' ?>>
        Sólo sin stock
    </label>

    <button class="panel-boton" type="submit">Filtrar</button>

    <?php if ($busqueda !== '' || $filtro_categoria !== '' || $solo_sin_stock): ?>
        <a class="panel-enlace" href="<?= e(url('/admin/productos')) ?>">Limpiar</a>
    <?php endif; ?>
</form>

<?php if ($visibles === []): ?>

    <p class="panel-vacio">
        <?= $productos === []
            ? 'Todavía no hay productos cargados.'
            : 'Ningún producto coincide con esos filtros.' ?>
    </p>

<?php else: ?>

    <div class="panel-tabla-marco">
        <table class="panel-tabla panel-tabla--productos">
            <thead>
                <tr>
                    <th scope="col"><span class="visualmente-oculto">Foto</span></th>
                    <th scope="col">Producto</th>
                    <th scope="col">Categoría</th>
                    <th scope="col" class="panel-tabla__num">Precio</th>
                    <th scope="col" class="panel-tabla__num">Stock</th>
                    <th scope="col">Estado</th>
                    <th scope="col"><span class="visualmente-oculto">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visibles as $producto): ?>
                    <?php
                    $sin_stock = (int) ($producto['stock'] ?? 0) <= 0;
                    $foto      = (string) ($producto['imagen'] ?? '');
                    $editar    = url('/admin/productos/' . (int) ($producto['id'] ?? 0));
                    ?>
                    <tr>
                        <td class="panel-tabla__miniatura">
                            <?php if ($foto !== ''): ?>
                                <img src="<?= e(asset($foto)) ?>" alt="" width="44" height="44"
                                     loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="panel-tabla__sin-foto" aria-hidden="true">—</span>
                            <?php endif; ?>
                        </td>

                        <th scope="row" class="panel-tabla__principal">
                            <a class="panel-enlace" href="<?= e($editar) ?>">
                                <?= e((string) ($producto['nombre'] ?? '')) ?>
                            </a>
                            <span class="panel-tabla__sku"><?= e((string) ($producto['sku'] ?? '')) ?></span>
                        </th>

                        <td><?= e($nombres_categoria[$producto['categoria'] ?? ''] ?? (string) ($producto['categoria'] ?? '')) ?></td>

                        <td class="panel-tabla__num"><?= e(moneda($producto['precio_lista'] ?? 0)) ?></td>

                        <td class="panel-tabla__num<?= $sin_stock ? ' panel-tabla__num--alerta' : '' ?>">
                            <?= e((string) (int) ($producto['stock'] ?? 0)) ?>
                        </td>

                        <td>
                            <?php /* Tres estados posibles y ninguno se dice
                                     sólo con color: cada pastilla lleva su
                                     texto (CLAUDE.md §5.4). */ ?>
                            <?php if (($producto['activo'] ?? true) !== true): ?>
                                <span class="panel-pastilla panel-pastilla--apagado">Despublicado</span>
                            <?php elseif ($sin_stock): ?>
                                <span class="panel-pastilla panel-pastilla--alerta">Sin stock</span>
                            <?php else: ?>
                                <span class="panel-pastilla panel-pastilla--ok">Publicado</span>
                            <?php endif; ?>

                            <?php if (($producto['destacado'] ?? false) === true): ?>
                                <span class="panel-pastilla panel-pastilla--nota">Destacado</span>
                            <?php endif; ?>
                        </td>

                        <td class="panel-tabla__acciones">
                            <a class="panel-enlace" href="<?= e($editar) ?>">Editar</a>

                            <?php /* Borrar es un formulario porque cambia el
                                     estado del servidor, y lleva confirmación
                                     en el JS. Sin JS el submit pasa directo:
                                     es un borrado explícito de una fila que
                                     se está mirando, no un enlace que se puede
                                     apretar sin querer. */ ?>
                            <form method="post" action="<?= e(url('/admin/productos')) ?>"
                                  data-confirmar="¿Borrar «<?= e((string) ($producto['nombre'] ?? '')) ?>»? No se puede deshacer.">
                                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) (int) ($producto['id'] ?? 0)) ?>">
                                <button class="panel-enlace panel-enlace--peligro" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
