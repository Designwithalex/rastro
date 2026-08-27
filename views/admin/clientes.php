<?php
/**
 * admin/clientes.php — los logos de "Confían en nosotros".
 *
 * No son los usuarios que compran: son los clubes, gimnasios y hoteles a los
 * que Rastro ya les vendió, y su logo es la prueba social de la portada
 * (CLAUDE.md §1). Un registro tiene nombre, logo y orden, y nada más.
 *
 * Es la lista más liviana del panel y por eso se edita en una tabla y no en
 * fichas: con veinte logos, una ficha por cada uno sería una pantalla de
 * scroll infinito para cambiar el orden de dos.
 */

declare(strict_types=1);

panel_exigir_sesion();

if (panel_es_post()) {
    panel_exigir_csrf();

    $accion = panel_texto('accion');
    $id     = panel_entero('id', 0) ?? 0;

    if ($accion === 'borrar') {
        /* El resultado se guarda en una variable y no se llama dos veces en
           el ternario: la segunda llamada volvería a escribir el archivo. */
        $borrado = repo_delete_client($id);

        panel_ir_con_aviso(
            '/admin/clientes',
            $borrado ? 'ok' : 'error',
            $borrado ? 'Logo borrado.' : 'No se pudo borrar.'
        );
    }

    $nombre = panel_texto('nombre');

    if ($nombre === '') {
        panel_ir_con_aviso('/admin/clientes', 'error', 'Poné el nombre del cliente.');
    }

    $logo = panel_texto('logo');

    if (($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $subida = panel_subir_imagen('archivo', 'cliente-' . $nombre);

        if (($subida['error'] ?? '') !== '') {
            panel_ir_con_aviso('/admin/clientes', 'error', $subida['error']);
        }

        $logo = (string) $subida['ruta'];
    }

    $guardado = repo_save_client([
        'id'     => $id,
        'nombre' => $nombre,
        'logo'   => $logo,
        'orden'  => panel_entero('orden', 99) ?? 99,
    ]);

    panel_ir_con_aviso(
        '/admin/clientes',
        $guardado !== null ? 'ok' : 'error',
        $guardado !== null
            ? ($id > 0 ? 'Cliente actualizado.' : 'Cliente agregado.')
            : 'No se pudo guardar.'
    );
}

$clientes = repo_clients();

$sin_logo = count(array_filter(
    $clientes,
    static fn (array $c): bool => (string) ($c['logo'] ?? '') === ''
));

$titulo = 'Clientes';
$bajada = 'Los logos de "Confían en nosotros", en la portada.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($sin_logo > 0): ?>
    <?php /* La franja de la portada escribe el nombre cuando no hay logo,
             así que la sección no se rompe. Igual conviene decirlo: un
             nombre suelto entre logos se lee como un hueco. */ ?>
    <p class="panel-nota">
        <?= e((string) $sin_logo) ?> cliente<?= $sin_logo === 1 ? '' : 's' ?> sin logo.
        En la portada se escribe el nombre en su lugar.
    </p>
<?php endif; ?>

<?php if ($clientes === []): ?>
    <p class="panel-vacio">Todavía no hay clientes cargados.</p>
<?php else: ?>

    <div class="panel-tabla-marco">
        <table class="panel-tabla">
            <thead>
                <tr>
                    <th scope="col"><span class="visualmente-oculto">Logo</span></th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Reemplazar el logo</th>
                    <th scope="col" class="panel-tabla__num">Orden</th>
                    <th scope="col"><span class="visualmente-oculto">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <?php
                    $cli_id = (int) ($cliente['id'] ?? 0);
                    $logo   = (string) ($cliente['logo'] ?? '');
                    ?>
                    <tr>
                        <td class="panel-tabla__miniatura">
                            <?php if ($logo !== ''): ?>
                                <img src="<?= e(asset($logo)) ?>" alt="" width="44" height="44"
                                     loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="panel-tabla__sin-foto" aria-hidden="true">—</span>
                            <?php endif; ?>
                        </td>

                        <td colspan="3">
                            <form class="panel-linea" method="post" enctype="multipart/form-data"
                                  action="<?= e(url('/admin/clientes')) ?>">
                                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                                <input type="hidden" name="accion" value="guardar">
                                <input type="hidden" name="id" value="<?= e((string) $cli_id) ?>">
                                <input type="hidden" name="logo" value="<?= e($logo) ?>">

                                <div class="campo-panel campo-panel--crece">
                                    <label class="campo-panel__rotulo visualmente-oculto"
                                           for="c-nombre-<?= e((string) $cli_id) ?>">Nombre</label>
                                    <input class="campo-panel__control" type="text"
                                           id="c-nombre-<?= e((string) $cli_id) ?>" name="nombre" required
                                           value="<?= e((string) ($cliente['nombre'] ?? '')) ?>">
                                </div>

                                <div class="campo-panel campo-panel--crece">
                                    <label class="campo-panel__rotulo visualmente-oculto"
                                           for="c-logo-<?= e((string) $cli_id) ?>">Logo</label>
                                    <input class="campo-panel__control" type="file"
                                           id="c-logo-<?= e((string) $cli_id) ?>" name="archivo"
                                           accept="image/jpeg,image/png,image/webp">
                                </div>

                                <div class="campo-panel campo-panel--angosto">
                                    <label class="campo-panel__rotulo visualmente-oculto"
                                           for="c-orden-<?= e((string) $cli_id) ?>">Orden</label>
                                    <input class="campo-panel__control" type="text" inputmode="numeric"
                                           id="c-orden-<?= e((string) $cli_id) ?>" name="orden"
                                           value="<?= e((string) (int) ($cliente['orden'] ?? 0)) ?>">
                                </div>

                                <button class="panel-boton panel-boton--chico" type="submit">Guardar</button>
                            </form>
                        </td>

                        <td class="panel-tabla__acciones">
                            <form method="post" action="<?= e(url('/admin/clientes')) ?>"
                                  data-confirmar="¿Borrar a «<?= e((string) ($cliente['nombre'] ?? '')) ?>» de la portada?">
                                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                                <input type="hidden" name="accion" value="borrar">
                                <input type="hidden" name="id" value="<?= e((string) $cli_id) ?>">
                                <button class="panel-enlace panel-enlace--peligro" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<section class="panel-alta" aria-labelledby="alta-cliente">
    <h2 class="panel-seccion__titulo" id="alta-cliente">Cliente nuevo</h2>

    <form class="panel-alta__formulario panel-linea" method="post" enctype="multipart/form-data"
          action="<?= e(url('/admin/clientes')) ?>">
        <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="0">

        <div class="campo-panel campo-panel--crece">
            <label class="campo-panel__rotulo" for="nuevo-cliente">Nombre</label>
            <input class="campo-panel__control" type="text" id="nuevo-cliente" name="nombre" required>
        </div>

        <div class="campo-panel campo-panel--crece">
            <label class="campo-panel__rotulo" for="nuevo-cliente-logo">Logo</label>
            <input class="campo-panel__control" type="file" id="nuevo-cliente-logo" name="archivo"
                   accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="campo-panel campo-panel--angosto">
            <label class="campo-panel__rotulo" for="nuevo-cliente-orden">Orden</label>
            <input class="campo-panel__control" type="text" inputmode="numeric"
                   id="nuevo-cliente-orden" name="orden" value="<?= e((string) (count($clientes) + 1)) ?>">
        </div>

        <button class="panel-boton panel-boton--acento" type="submit">Agregar</button>
    </form>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
