<?php
/**
 * admin/productos.php — el listado del panel.
 *
 * Sigue el frame "Admin · Productos · listado".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=82-41
 *
 * A DIFERENCIA DEL CATÁLOGO, ESTA LISTA VE TODO: también lo que está dado
 * de baja, porque el panel es justamente donde se lo vuelve a dar de alta.
 * Las filas de baja van atenuadas para que se distingan de un vistazo.
 *
 * Las acciones destructivas —dar de baja— son POST con token y confirman
 * antes. Un enlace no puede cambiar el estado de un producto: bastaría con
 * que un buscador lo siguiera para vaciar el catálogo.
 */

declare(strict_types=1);

$admin_titulo  = 'Productos';
$admin_seccion = 'productos';
$admin_accion  = ['texto' => 'Nuevo producto', 'href' => url('/admin/productos/nuevo')];

require RASTRO_VIEWS . '/admin/_guard.php';

/* --- Acciones ------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $id     = (int) ($_POST['id'] ?? 0);
    $activo = ($_POST['accion'] ?? '') === 'alta';

    if ($id > 0) {
        repo_producto_activo($id, $activo);
        admin_avisar($activo ? 'Producto dado de alta.' : 'Producto dado de baja. Sigue en la lista y se puede volver a activar.');
    }

    /* Redirect después del POST: recargar no tiene que repetir la acción.
       Se conservan los filtros para volver a la misma vista de la lista. */
    header('Location: ' . url('/admin/productos') . (($_POST['volver'] ?? '') !== '' ? '?' . $_POST['volver'] : ''), true, 303);
    exit;
}

/* --- Datos ---------------------------------------------------------- */

$estados_lista = [
    ''          => 'Todos',
    'activos'   => 'Publicados',
    'bajas'     => 'Dados de baja',
    'sin_stock' => 'Sin stock',
];

$filtros = [
    'q'         => param('q'),
    'categoria' => param('categoria'),
    'estado'    => param('estado', '', array_keys($estados_lista)),
];

$pagina  = param_int('pagina', 1, 1) ?? 1;
$listado = repo_admin_products($filtros, $pagina, 25);

$categorias = repo_categories();

// Lo que hay que conservar al volver de un POST.
$volver = http_build_query(array_filter($filtros + ['pagina' => $pagina > 1 ? $pagina : null]));

/* La cabecera va recién acá, no arriba: el bloque de POST necesita poder
   redirigir, y un header() después del primer byte de HTML no sale. */
require RASTRO_VIEWS . '/admin/_cabecera.php';
?>

<form class="filtros-admin" method="get" action="<?= e(url('/admin/productos')) ?>">
    <p class="form-admin__campo">
        <label class="form-admin__etiqueta t-mono-label-sm" for="q">Buscar</label>
        <input class="campo t-mono-texto" type="search" id="q" name="q"
               value="<?= e($filtros['q']) ?>" placeholder="Nombre o código">
    </p>

    <p class="form-admin__campo">
        <label class="form-admin__etiqueta t-mono-label-sm" for="categoria">Categoría</label>
        <select class="campo t-mono-texto" id="categoria" name="categoria">
            <option value="">Todas</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?= e($categoria['slug']) ?>" <?= $filtros['categoria'] === $categoria['slug'] ? 'selected' : '' ?>>
                    <?= e($categoria['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p class="form-admin__campo">
        <label class="form-admin__etiqueta t-mono-label-sm" for="estado">Estado</label>
        <select class="campo t-mono-texto" id="estado" name="estado">
            <?php foreach ($estados_lista as $valor => $etiqueta): ?>
                <option value="<?= e($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>>
                    <?= e($etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <button class="boton boton--fantasma" type="submit"><span class="t-mono-label">Filtrar</span></button>
</form>

<p class="admin__conteo t-mono-texto">
    <?= e((string) $listado['total']) ?>
    <?= $listado['total'] === 1 ? 'producto' : 'productos' ?>
    <?php if (array_filter($filtros) !== []): ?>
        · <a href="<?= e(url('/admin/productos')) ?>">limpiar filtros</a>
    <?php endif; ?>
</p>

<?php if ($listado['items'] === []): ?>
    <div class="vacio-admin">
        <p class="t-display-s">No hay productos con esos filtros</p>
        <p class="vacio-admin__texto t-body-md">
            Probá sacando alguno, o cargá un producto nuevo.
        </p>
        <a class="boton boton--acento" href="<?= e(url('/admin/productos/nuevo')) ?>">
            <span class="t-mono-label">Nuevo producto</span>
            <span class="boton__flecha" aria-hidden="true">+</span>
        </a>
    </div>
<?php else: ?>
    <div class="tabla-admin">
        <table>
            <thead>
                <tr>
                    <th class="t-mono-label-sm" scope="col"><span class="visualmente-oculto">Foto</span></th>
                    <th class="t-mono-label-sm" scope="col">Producto</th>
                    <th class="t-mono-label-sm" scope="col">Categoría</th>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">Precio</th>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">Stock</th>
                    <th class="t-mono-label-sm" scope="col">Estado</th>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">
                        <span class="visualmente-oculto">Acciones</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listado['items'] as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'es-inactivo' ?>">
                        <td>
                            <?php if ($p['imagen'] !== ''): ?>
                                <img class="tabla-admin__foto" src="<?= e(asset($p['imagen'])) ?>" alt=""
                                     loading="lazy" decoding="async" width="48" height="48">
                            <?php endif; ?>
                        </td>

                        <th class="tabla-admin__principal t-mono-texto" scope="row">
                            <a href="<?= e(url('/admin/productos/' . $p['id'])) ?>"><?= e($p['nombre']) ?></a>
                            <span class="tabla-admin__sku t-mono-texto-sm"><?= e($p['sku']) ?></span>
                        </th>

                        <td class="t-mono-texto"><?= e($p['categoria_nombre']) ?></td>
                        <td class="tabla-admin__num t-mono-texto"><?= e(moneda($p['precio_lista'])) ?></td>
                        <td class="tabla-admin__num t-mono-texto"><?= e((string) $p['stock']) ?></td>

                        <td>
                            <?php if (!$p['activo']): ?>
                                <span class="estado-admin estado-admin--cancelado t-mono-label-sm">De baja</span>
                            <?php elseif ($p['stock'] <= 0): ?>
                                <span class="estado-admin estado-admin--pendiente t-mono-label-sm">Sin stock</span>
                            <?php elseif ($p['destacado']): ?>
                                <span class="estado-admin estado-admin--en_camino t-mono-label-sm">Destacado</span>
                            <?php else: ?>
                                <span class="estado-admin estado-admin--entregado t-mono-label-sm">Publicado</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="tabla-admin__acciones">
                                <a class="tabla-admin__accion t-mono-label-sm"
                                   href="<?= e(url('/admin/productos/' . $p['id'])) ?>">Editar</a>

                                <?php /* POST y no enlace: un enlace que cambia el
                                         estado lo dispara cualquier cosa que siga
                                         URLs, incluido un buscador. */ ?>
                                <form method="post" action="<?= e(url('/admin/productos')) ?>"
                                      <?php if ($p['activo']): ?>
                                          data-confirmar="¿Dar de baja «<?= e($p['nombre']) ?>»? Sale del catálogo pero no se borra: se puede volver a activar."
                                      <?php endif; ?>>
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $p['id']) ?>">
                                    <input type="hidden" name="accion" value="<?= $p['activo'] ? 'baja' : 'alta' ?>">
                                    <input type="hidden" name="volver" value="<?= e($volver) ?>">
                                    <button class="tabla-admin__accion <?= $p['activo'] ? 'tabla-admin__accion--peligro' : '' ?> t-mono-label-sm"
                                            type="submit">
                                        <?= $p['activo'] ? 'Dar de baja' : 'Dar de alta' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($listado['paginas'] > 1): ?>
        <nav class="paginacion-admin" aria-label="Paginación">
            <?php for ($p = 1; $p <= $listado['paginas']; $p++): ?>
                <?php $qs = http_build_query(array_filter($filtros + ['pagina' => $p > 1 ? $p : null])); ?>
                <?php if ($p === $listado['pagina']): ?>
                    <span class="paginacion__paso es-activo t-mono-label" aria-current="page"><?= e((string) $p) ?></span>
                <?php else: ?>
                    <a class="paginacion__paso t-mono-label"
                       href="<?= e(url('/admin/productos') . ($qs !== '' ? '?' . $qs : '')) ?>"><?= e((string) $p) ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
