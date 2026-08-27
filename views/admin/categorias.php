<?php
/**
 * admin/categorias.php — las categorías del catálogo.
 *
 * Lista y formulario en la misma pantalla, con una fila por categoría que es
 * su propio formulario. Son seis registros de cuatro campos: mandar a otra
 * pantalla para cambiar un nombre sería tres clics para editar una palabra.
 *
 * `productos_count` no se edita: lo recalcula el repository en cada alta y
 * cada baja de producto. Un contador que se escribe a mano miente el primer
 * día (`repo_recount_categories()`).
 */

declare(strict_types=1);

panel_exigir_sesion();

if (panel_es_post()) {
    panel_exigir_csrf();

    $accion = panel_texto('accion');
    $id     = panel_entero('id', 0) ?? 0;

    if ($accion === 'borrar') {
        $resultado = repo_delete_category($id);

        panel_ir_con_aviso(
            '/admin/categorias',
            $resultado['ok'] ? 'ok' : 'error',
            $resultado['ok'] ? 'Categoría borrada.' : $resultado['motivo']
        );
    }

    $nombre = panel_texto('nombre');

    if ($nombre === '') {
        panel_ir_con_aviso('/admin/categorias', 'error', 'La categoría necesita un nombre.');
    }

    /* El slug de una categoría que ya existe no se toca: es la URL
       /catalogo/discos y la referencia que guarda cada producto en su campo
       `categoria`. Regenerarlo al corregir el nombre dejaría a todos los
       productos apuntando a una categoría que ya no existe. */
    $slug_actual = panel_texto('slug');
    $slug        = $slug_actual !== '' ? $slug_actual : slug($nombre);

    if ($slug === '') {
        panel_ir_con_aviso('/admin/categorias', 'error', 'No se pudo armar la dirección web de esa categoría.');
    }

    foreach (repo_categories() as $otra) {
        if ((int) ($otra['id'] ?? 0) !== $id && ($otra['slug'] ?? '') === $slug) {
            panel_ir_con_aviso('/admin/categorias', 'error', 'Ya hay una categoría con esa dirección web.');
        }
    }

    $guardada = repo_save_category([
        'id'          => $id,
        'slug'        => $slug,
        'nombre'      => $nombre,
        'descripcion' => panel_texto('descripcion'),
        'pictograma'  => panel_texto('pictograma'),
        'orden'       => panel_entero('orden', 99) ?? 99,
    ]);

    panel_ir_con_aviso(
        '/admin/categorias',
        $guardada !== null ? 'ok' : 'error',
        $guardada !== null
            ? ($id > 0 ? 'Categoría actualizada.' : 'Categoría creada.')
            : 'No se pudo guardar.'
    );
}

$categorias = repo_categories();

$titulo = 'Categorías';
$bajada = 'El orden es el que se ve en la portada y en el menú de productos.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($categorias === []): ?>
    <p class="panel-vacio">Todavía no hay categorías. Cargá la primera abajo.</p>
<?php else: ?>

    <ul class="panel-fichas">
        <?php foreach ($categorias as $categoria): ?>
            <?php
            $cat_id    = (int) ($categoria['id'] ?? 0);
            $cuenta    = (int) ($categoria['productos_count'] ?? 0);
            $picto     = (string) ($categoria['pictograma'] ?? '');
            ?>
            <li class="panel-ficha">
                <form class="panel-ficha__formulario" method="post" action="<?= e(url('/admin/categorias')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= e((string) $cat_id) ?>">
                    <input type="hidden" name="slug" value="<?= e((string) ($categoria['slug'] ?? '')) ?>">

                    <div class="panel-ficha__marca">
                        <?php if ($picto !== ''): ?>
                            <img src="<?= e(asset($picto)) ?>" alt="" width="32" height="32"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="panel-ficha__hueco" aria-hidden="true">—</span>
                        <?php endif; ?>
                    </div>

                    <div class="panel-ficha__campos">
                        <div class="panel-fila">
                            <div class="campo-panel campo-panel--crece">
                                <label class="campo-panel__rotulo" for="nombre-<?= e((string) $cat_id) ?>">Nombre</label>
                                <input class="campo-panel__control" type="text"
                                       id="nombre-<?= e((string) $cat_id) ?>" name="nombre" required
                                       value="<?= e((string) ($categoria['nombre'] ?? '')) ?>">
                            </div>

                            <div class="campo-panel campo-panel--angosto">
                                <label class="campo-panel__rotulo" for="orden-<?= e((string) $cat_id) ?>">Orden</label>
                                <input class="campo-panel__control" type="text" inputmode="numeric"
                                       id="orden-<?= e((string) $cat_id) ?>" name="orden"
                                       value="<?= e((string) (int) ($categoria['orden'] ?? 0)) ?>">
                            </div>
                        </div>

                        <div class="campo-panel">
                            <label class="campo-panel__rotulo" for="desc-<?= e((string) $cat_id) ?>">Descripción</label>
                            <input class="campo-panel__control" type="text"
                                   id="desc-<?= e((string) $cat_id) ?>" name="descripcion"
                                   value="<?= e((string) ($categoria['descripcion'] ?? '')) ?>">
                        </div>

                        <div class="campo-panel">
                            <label class="campo-panel__rotulo" for="picto-<?= e((string) $cat_id) ?>">Pictograma</label>
                            <input class="campo-panel__control campo-panel__control--mono" type="text"
                                   id="picto-<?= e((string) $cat_id) ?>" name="pictograma"
                                   value="<?= e($picto) ?>">
                            <p class="campo-panel__ayuda">
                                Ruta relativa a <code>assets/</code>, de los íconos en
                                <code>img/iconos/</code>.
                            </p>
                        </div>

                        <p class="panel-ficha__dato">
                            <code>/catalogo/<?= e((string) ($categoria['slug'] ?? '')) ?></code>
                            · <?= e((string) $cuenta) ?> producto<?= $cuenta === 1 ? '' : 's' ?>
                        </p>
                    </div>

                    <div class="panel-ficha__acciones">
                        <button class="panel-boton panel-boton--chico" type="submit">Guardar</button>
                    </div>
                </form>

                <?php /* El borrado va en su propio formulario y no como un
                         segundo botón del de arriba: dos submits en un mismo
                         form comparten los campos, y un Enter en el nombre
                         dispararía el primero que encuentre el navegador. */ ?>
                <form class="panel-ficha__borrar" method="post" action="<?= e(url('/admin/categorias')) ?>"
                      data-confirmar="¿Borrar la categoría «<?= e((string) ($categoria['nombre'] ?? '')) ?>»?">
                    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= e((string) $cat_id) ?>">
                    <button class="panel-enlace panel-enlace--peligro" type="submit"
                            <?= $cuenta > 0 ? 'disabled' : '' ?>>
                        Borrar
                    </button>
                    <?php if ($cuenta > 0): ?>
                        <span class="panel-ficha__nota">Tiene productos</span>
                    <?php endif; ?>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>

<section class="panel-alta" aria-labelledby="alta-titulo">
    <h2 class="panel-seccion__titulo" id="alta-titulo">Categoría nueva</h2>

    <form class="panel-alta__formulario" method="post" action="<?= e(url('/admin/categorias')) ?>">
        <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="0">

        <div class="panel-fila">
            <div class="campo-panel campo-panel--crece">
                <label class="campo-panel__rotulo" for="nueva-nombre">Nombre</label>
                <input class="campo-panel__control" type="text" id="nueva-nombre" name="nombre" required>
            </div>

            <div class="campo-panel campo-panel--angosto">
                <label class="campo-panel__rotulo" for="nueva-orden">Orden</label>
                <input class="campo-panel__control" type="text" inputmode="numeric"
                       id="nueva-orden" name="orden" value="<?= e((string) (count($categorias) + 1)) ?>">
            </div>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="nueva-desc">Descripción</label>
            <input class="campo-panel__control" type="text" id="nueva-desc" name="descripcion">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="nueva-picto">Pictograma</label>
            <input class="campo-panel__control campo-panel__control--mono" type="text"
                   id="nueva-picto" name="pictograma" placeholder="img/iconos/icono-disco-blanco.png">
        </div>

        <button class="panel-boton panel-boton--acento" type="submit">Crear categoría</button>
    </form>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
