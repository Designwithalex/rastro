<?php
/**
 * admin/marcas.php — las marcas.
 *
 * Dos cosas distintas conviven en esta tabla y la pantalla tiene que dejarlo
 * claro (`repository.php`, "marca propia vs. marca de terceros"):
 *
 *   · Marcas de terceros — Rastro es su vendedor oficial. Son las que salen
 *     en la franja "Vendedores oficiales" de la portada.
 *   · Línea propia — lo que Rastro fabrica o marca. Existe para que un
 *     producto pueda resolver el nombre de su marca, pero NO sale en esa
 *     franja: uno no es vendedor oficial de sí mismo.
 *
 * Por eso la casilla "es línea propia" tiene su explicación al lado y no un
 * rótulo suelto: es la que decide si el logo aparece en la portada.
 */

declare(strict_types=1);

panel_exigir_sesion();

if (panel_es_post()) {
    panel_exigir_csrf();

    $accion = panel_texto('accion');
    $id     = panel_entero('id', 0) ?? 0;

    if ($accion === 'borrar') {
        $resultado = repo_delete_brand($id);

        panel_ir_con_aviso(
            '/admin/marcas',
            $resultado['ok'] ? 'ok' : 'error',
            $resultado['ok'] ? 'Marca borrada.' : $resultado['motivo']
        );
    }

    $nombre = panel_texto('nombre');

    if ($nombre === '') {
        panel_ir_con_aviso('/admin/marcas', 'error', 'La marca necesita un nombre.');
    }

    // Mismo criterio que las categorías: el slug de lo que ya existe no se toca.
    $slug_actual = panel_texto('slug');
    $slug        = $slug_actual !== '' ? $slug_actual : slug($nombre);

    foreach (repo_brands(true) as $otra) {
        if ((int) ($otra['id'] ?? 0) !== $id && ($otra['slug'] ?? '') === $slug) {
            panel_ir_con_aviso('/admin/marcas', 'error', 'Ya hay una marca con ese identificador.');
        }
    }

    $logo = panel_texto('logo');

    if (($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $subida = panel_subir_imagen('archivo', 'marca-' . $nombre);

        if (($subida['error'] ?? '') !== '') {
            panel_ir_con_aviso('/admin/marcas', 'error', $subida['error']);
        }

        $logo = (string) $subida['ruta'];
    }

    $guardada = repo_save_brand([
        'id'        => $id,
        'slug'      => $slug,
        'nombre'    => $nombre,
        'logo'      => $logo,
        'orden'     => panel_entero('orden', 99) ?? 99,
        'es_propia' => panel_booleano('es_propia'),
    ]);

    panel_ir_con_aviso(
        '/admin/marcas',
        $guardada !== null ? 'ok' : 'error',
        $guardada !== null
            ? ($id > 0 ? 'Marca actualizada.' : 'Marca creada.')
            : 'No se pudo guardar.'
    );
}

/* Con las propias incluidas: el panel administra la tabla entera, no la
   franja de la portada. */
$marcas = repo_brands(true);

$titulo = 'Marcas';
$bajada = 'Las de terceros salen en "Vendedores oficiales" de la portada. La línea propia, no.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($marcas === []): ?>
    <p class="panel-vacio">Todavía no hay marcas cargadas.</p>
<?php else: ?>

    <ul class="panel-fichas">
        <?php foreach ($marcas as $marca): ?>
            <?php
            $marca_id = (int) ($marca['id'] ?? 0);
            $logo     = (string) ($marca['logo'] ?? '');
            $propia   = ($marca['es_propia'] ?? false) === true;
            ?>
            <li class="panel-ficha">
                <form class="panel-ficha__formulario" method="post" enctype="multipart/form-data"
                      action="<?= e(url('/admin/marcas')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= e((string) $marca_id) ?>">
                    <input type="hidden" name="slug" value="<?= e((string) ($marca['slug'] ?? '')) ?>">

                    <div class="panel-ficha__marca panel-ficha__marca--logo">
                        <?php if ($logo !== ''): ?>
                            <img src="<?= e(asset($logo)) ?>" alt="Logo de <?= e((string) ($marca['nombre'] ?? '')) ?>"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="panel-ficha__hueco">Sin logo</span>
                        <?php endif; ?>
                    </div>

                    <div class="panel-ficha__campos">
                        <div class="panel-fila">
                            <div class="campo-panel campo-panel--crece">
                                <label class="campo-panel__rotulo" for="m-nombre-<?= e((string) $marca_id) ?>">Nombre</label>
                                <input class="campo-panel__control" type="text"
                                       id="m-nombre-<?= e((string) $marca_id) ?>" name="nombre" required
                                       value="<?= e((string) ($marca['nombre'] ?? '')) ?>">
                            </div>

                            <div class="campo-panel campo-panel--angosto">
                                <label class="campo-panel__rotulo" for="m-orden-<?= e((string) $marca_id) ?>">Orden</label>
                                <input class="campo-panel__control" type="text" inputmode="numeric"
                                       id="m-orden-<?= e((string) $marca_id) ?>" name="orden"
                                       value="<?= e((string) (int) ($marca['orden'] ?? 0)) ?>">
                            </div>
                        </div>

                        <div class="campo-panel">
                            <label class="campo-panel__rotulo" for="m-logo-<?= e((string) $marca_id) ?>">Cambiar el logo</label>
                            <input class="campo-panel__control" type="file"
                                   id="m-logo-<?= e((string) $marca_id) ?>" name="archivo"
                                   accept="image/jpeg,image/png,image/webp">
                            <input type="hidden" name="logo" value="<?= e($logo) ?>">
                            <p class="campo-panel__ayuda">
                                PNG con fondo transparente. Sobre negro, un JPG con fondo blanco
                                deja un recuadro blanco en la franja.
                            </p>
                        </div>

                        <label class="campo-panel__casilla">
                            <input type="checkbox" name="es_propia" value="1" <?= $propia ? 'checked' : '' ?>>
                            Es línea propia de Rastro
                        </label>
                        <p class="campo-panel__ayuda">
                            Marcada, el logo NO aparece en "Vendedores oficiales" de la portada.
                        </p>
                    </div>

                    <div class="panel-ficha__acciones">
                        <button class="panel-boton panel-boton--chico" type="submit">Guardar</button>
                    </div>
                </form>

                <form class="panel-ficha__borrar" method="post" action="<?= e(url('/admin/marcas')) ?>"
                      data-confirmar="¿Borrar la marca «<?= e((string) ($marca['nombre'] ?? '')) ?>»?">
                    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                    <input type="hidden" name="accion" value="borrar">
                    <input type="hidden" name="id" value="<?= e((string) $marca_id) ?>">
                    <button class="panel-enlace panel-enlace--peligro" type="submit">Borrar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>

<section class="panel-alta" aria-labelledby="alta-marca">
    <h2 class="panel-seccion__titulo" id="alta-marca">Marca nueva</h2>

    <form class="panel-alta__formulario" method="post" enctype="multipart/form-data"
          action="<?= e(url('/admin/marcas')) ?>">
        <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="0">

        <div class="panel-fila">
            <div class="campo-panel campo-panel--crece">
                <label class="campo-panel__rotulo" for="nueva-marca">Nombre</label>
                <input class="campo-panel__control" type="text" id="nueva-marca" name="nombre" required>
            </div>

            <div class="campo-panel campo-panel--angosto">
                <label class="campo-panel__rotulo" for="nueva-marca-orden">Orden</label>
                <input class="campo-panel__control" type="text" inputmode="numeric"
                       id="nueva-marca-orden" name="orden" value="<?= e((string) (count($marcas) + 1)) ?>">
            </div>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="nueva-marca-logo">Logo</label>
            <input class="campo-panel__control" type="file" id="nueva-marca-logo" name="archivo"
                   accept="image/jpeg,image/png,image/webp">
        </div>

        <label class="campo-panel__casilla">
            <input type="checkbox" name="es_propia" value="1">
            Es línea propia de Rastro
        </label>

        <button class="panel-boton panel-boton--acento" type="submit">Crear marca</button>
    </form>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
