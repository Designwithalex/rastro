<?php
/**
 * admin/banners.php — el fondo del hero, las placas del carrusel y las
 * franjas de texto.
 *
 * ---------------------------------------------------------------------
 * LAS CUATRO POSICIONES NO SON CUATRO VARIANTES DE LO MISMO
 *
 *   hero_fondo   la foto apaisada detrás del hero. Una sola, decorativa,
 *                sin enlace y sin texto: el titular ya dice lo que hay
 *                que decir.
 *   hero         las placas 4:5 que rotan en el carrusel de la portada.
 *                Con una sola no rota ni muestra controles.
 *   mayorista    la foto de la franja mayorista.
 *   franja       texto de la barra superior. No lleva imagen.
 *
 * Por eso la pantalla las agrupa y le explica a cada grupo qué formato
 * necesita, en vez de dar una lista plana con un desplegable de posición.
 * Cargar una foto apaisada como placa del carrusel no da ningún error: sale
 * recortada, y el cliente descubre por qué recién cuando mira la portada.
 *
 * EL TÍTULO DE UNA PLACA ES SU TEXTO ALTERNATIVO. La placa lleva el mensaje
 * adentro de la imagen: si el título viene vacío, esa promoción no existe
 * para quien usa un lector de pantalla. Por eso el campo se rotula "Qué dice
 * la placa" y es obligatorio en `hero` (PENDIENTES #67).
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

panel_exigir_sesion();

/** Qué es cada posición, para los encabezados y las ayudas de la pantalla. */
$posiciones = [
    'hero_fondo' => [
        'titulo' => 'Fondo de la portada',
        'texto'  => 'La foto apaisada detrás del titular. Se usa la primera activa.',
        'foto'   => 'Apaisada, 1600 px de lado largo. Oscura: encima va texto blanco.',
        'imagen' => true,
        'enlace' => false,
        'alt'    => false,
    ],
    'hero' => [
        'titulo' => 'Placas del carrusel',
        'texto'  => 'Las piezas de promoción que rotan en la portada. Con una sola no rota.',
        'foto'   => 'Vertical 4:5 —las mismas de Instagram—, 1200 px de lado largo.',
        'imagen' => true,
        'enlace' => true,
        'alt'    => true,
    ],
    'mayorista' => [
        'titulo' => 'Franja mayorista',
        'texto'  => 'La foto de la sección de venta mayorista.',
        'foto'   => 'Apaisada.',
        'imagen' => true,
        'enlace' => true,
        'alt'    => false,
    ],
    'franja' => [
        'titulo' => 'Barra de texto',
        // No lleva enlace: la franja es decorativa (aria-hidden) y un enlace
        // adentro recibiría el foco sin anunciarse. Ver layout/marquee.php.
        'texto'  => 'La franja bordeaux que corre arriba de todo. Si hay varias activas, '
                  . 'se alternan en el orden indicado. Sin ninguna activa, dice "Equipamiento profesional".',
        'foto'   => '',
        'imagen' => false,
        'enlace' => false,
        'alt'    => false,
    ],
];

if (panel_es_post()) {
    panel_exigir_csrf();

    $accion = panel_texto('accion');
    $id     = panel_entero('id', 0) ?? 0;

    if ($accion === 'borrar') {
        $borrado = repo_delete_banner($id);

        panel_ir_con_aviso(
            '/admin/banners',
            $borrado ? 'ok' : 'error',
            $borrado ? 'Banner borrado.' : 'No se pudo borrar.'
        );
    }

    $posicion = panel_texto('posicion');

    if (!isset($posiciones[$posicion])) {
        panel_ir_con_aviso('/admin/banners', 'error', 'Esa posición no existe.');
    }

    $regla  = $posiciones[$posicion];
    $titulo_banner = panel_texto('titulo');

    if ($regla['alt'] && $titulo_banner === '') {
        panel_ir_con_aviso(
            '/admin/banners',
            'error',
            'Escribí qué dice la placa: es el texto que lee un lector de pantalla en lugar de la imagen.'
        );
    }

    $imagen = panel_texto('imagen');

    if (($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $subida = panel_subir_imagen('archivo', 'banner-' . ($titulo_banner !== '' ? $titulo_banner : $posicion));

        if (($subida['error'] ?? '') !== '') {
            panel_ir_con_aviso('/admin/banners', 'error', $subida['error']);
        }

        $imagen = (string) $subida['ruta'];
    }

    if ($regla['imagen'] && $imagen === '') {
        panel_ir_con_aviso('/admin/banners', 'error', 'Esta posición necesita una imagen.');
    }

    $guardado = repo_save_banner([
        'id'       => $id,
        'titulo'   => $titulo_banner,
        'imagen'   => $regla['imagen'] ? $imagen : '',
        'enlace'   => $regla['enlace'] ? panel_texto('enlace') : '',
        'posicion' => $posicion,
        'activo'   => panel_booleano('activo'),
        'orden'    => panel_entero('orden', 99) ?? 99,
    ]);

    panel_ir_con_aviso(
        '/admin/banners',
        $guardado !== null ? 'ok' : 'error',
        $guardado !== null
            ? ($id > 0 ? 'Banner actualizado.' : 'Banner creado.')
            : 'No se pudo guardar.'
    );
}

$banners = repo_all_banners();

/* Agrupados por posición para dibujarlos bajo su propio encabezado. */
$por_posicion = array_fill_keys(array_keys($posiciones), []);

foreach ($banners as $banner) {
    $clave = (string) ($banner['posicion'] ?? '');

    if (isset($por_posicion[$clave])) {
        $por_posicion[$clave][] = $banner;
    }
}

$titulo = 'Banners';
$bajada = 'El fondo de la portada, las placas del carrusel y las franjas de texto.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php foreach ($posiciones as $clave => $regla): ?>
    <?php $grupo = $por_posicion[$clave]; ?>

    <section class="panel-bloque" aria-labelledby="pos-<?= e($clave) ?>">

        <div class="panel-seccion__encabezado">
            <div>
                <h2 class="panel-seccion__titulo" id="pos-<?= e($clave) ?>"><?= e($regla['titulo']) ?></h2>
                <p class="panel-seccion__bajada"><?= e($regla['texto']) ?></p>
            </div>
            <span class="panel-seccion__cuenta">
                <?= e((string) count($grupo)) ?> cargado<?= count($grupo) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($clave === 'hero' && count(array_filter($grupo, static fn ($b) => ($b['activo'] ?? false) === true)) < 2): ?>
            <p class="panel-nota">
                Con menos de dos placas activas el carrusel no rota ni dibuja los controles:
                se ve una sola imagen fija.
            </p>
        <?php endif; ?>

        <?php if ($grupo === []): ?>
            <p class="panel-vacio panel-vacio--chico">Nada cargado en esta posición.</p>
        <?php else: ?>

            <ul class="panel-fichas">
                <?php foreach ($grupo as $banner): ?>
                    <?php
                    $ban_id = (int) ($banner['id'] ?? 0);
                    $imagen = (string) ($banner['imagen'] ?? '');
                    $activo = ($banner['activo'] ?? false) === true;
                    ?>
                    <li class="panel-ficha<?= $activo ? '' : ' panel-ficha--apagada' ?>">
                        <?php /* Sin imagen no hay primera columna: sin el modificador,
                                 los campos caían en el hueco de 120 px de la miniatura. */ ?>
                        <form class="panel-ficha__formulario<?= $regla['imagen'] ? '' : ' panel-ficha__formulario--sin-marca' ?>"
                              method="post" enctype="multipart/form-data"
                              action="<?= e(url('/admin/banners')) ?>">
                            <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                            <input type="hidden" name="accion" value="guardar">
                            <input type="hidden" name="id" value="<?= e((string) $ban_id) ?>">
                            <input type="hidden" name="posicion" value="<?= e($clave) ?>">
                            <input type="hidden" name="imagen" value="<?= e($imagen) ?>">

                            <?php if ($regla['imagen']): ?>
                                <div class="panel-ficha__marca panel-ficha__marca--banner">
                                    <?php if ($imagen !== ''): ?>
                                        <img src="<?= e(asset($imagen)) ?>"
                                             alt="<?= e((string) ($banner['titulo'] ?? '')) ?>"
                                             loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="panel-ficha__hueco">Sin imagen</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="panel-ficha__campos">
                                <div class="campo-panel">
                                    <label class="campo-panel__rotulo" for="b-titulo-<?= e((string) $ban_id) ?>">
                                        <?= $regla['alt'] ? 'Qué dice la placa' : 'Título' ?>
                                        <?= $regla['alt'] ? '*' : '' ?>
                                    </label>
                                    <input class="campo-panel__control" type="text"
                                           id="b-titulo-<?= e((string) $ban_id) ?>" name="titulo"
                                           <?= $regla['alt'] ? 'required' : '' ?>
                                           value="<?= e((string) ($banner['titulo'] ?? '')) ?>">
                                    <?php if ($regla['alt']): ?>
                                        <p class="campo-panel__ayuda">
                                            Es el texto alternativo de la imagen: lo que se lee en voz alta
                                            cuando la placa no se ve.
                                        </p>
                                    <?php elseif ($clave === 'franja'): ?>
                                        <p class="campo-panel__ayuda">
                                            Se puede escribir <code>{descuento}</code> y sale el porcentaje
                                            que esté en Configuración. Nunca poner el número a mano.
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($regla['imagen']): ?>
                                    <div class="campo-panel">
                                        <label class="campo-panel__rotulo" for="b-archivo-<?= e((string) $ban_id) ?>">
                                            Reemplazar la imagen
                                        </label>
                                        <input class="campo-panel__control" type="file"
                                               id="b-archivo-<?= e((string) $ban_id) ?>" name="archivo"
                                               accept="image/jpeg,image/png,image/webp">
                                        <p class="campo-panel__ayuda"><?= e($regla['foto']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="panel-fila">
                                    <?php if ($regla['enlace']): ?>
                                        <div class="campo-panel campo-panel--crece">
                                            <label class="campo-panel__rotulo" for="b-enlace-<?= e((string) $ban_id) ?>">
                                                A dónde lleva
                                            </label>
                                            <input class="campo-panel__control campo-panel__control--mono" type="text"
                                                   id="b-enlace-<?= e((string) $ban_id) ?>" name="enlace"
                                                   value="<?= e((string) ($banner['enlace'] ?? '')) ?>"
                                                   placeholder="/catalogo">
                                        </div>
                                    <?php endif; ?>

                                    <div class="campo-panel campo-panel--angosto">
                                        <label class="campo-panel__rotulo" for="b-orden-<?= e((string) $ban_id) ?>">Orden</label>
                                        <input class="campo-panel__control" type="text" inputmode="numeric"
                                               id="b-orden-<?= e((string) $ban_id) ?>" name="orden"
                                               value="<?= e((string) (int) ($banner['orden'] ?? 0)) ?>">
                                    </div>
                                </div>

                                <label class="campo-panel__casilla">
                                    <input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
                                    Se muestra en el sitio
                                </label>
                            </div>

                            <div class="panel-ficha__acciones">
                                <button class="panel-boton panel-boton--chico" type="submit">Guardar</button>
                            </div>
                        </form>

                        <form class="panel-ficha__borrar" method="post" action="<?= e(url('/admin/banners')) ?>"
                              data-confirmar="¿Borrar este banner? Si sólo lo querés sacar de la portada, destildá «Se muestra en el sitio».">
                            <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="id" value="<?= e((string) $ban_id) ?>">
                            <button class="panel-enlace panel-enlace--peligro" type="submit">Borrar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php endif; ?>

        <?php /* El alta de cada posición va acá abajo y no en un formulario
                 general con desplegable: así el campo de archivo puede decir
                 exactamente qué formato necesita ESTA posición. */ ?>
        <details class="panel-alta panel-alta--plegable">
            <summary class="panel-alta__resumen">Agregar en <?= e(mb_strtolower($regla['titulo'])) ?></summary>

            <form class="panel-alta__formulario" method="post" enctype="multipart/form-data"
                  action="<?= e(url('/admin/banners')) ?>">
                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="posicion" value="<?= e($clave) ?>">

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="nuevo-titulo-<?= e($clave) ?>">
                        <?= $regla['alt'] ? 'Qué dice la placa *' : 'Título' ?>
                    </label>
                    <input class="campo-panel__control" type="text"
                           id="nuevo-titulo-<?= e($clave) ?>" name="titulo"
                           <?= $regla['alt'] ? 'required' : '' ?>>
                </div>

                <?php if ($regla['imagen']): ?>
                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="nuevo-archivo-<?= e($clave) ?>">Imagen *</label>
                        <input class="campo-panel__control" type="file"
                               id="nuevo-archivo-<?= e($clave) ?>" name="archivo" required
                               accept="image/jpeg,image/png,image/webp">
                        <p class="campo-panel__ayuda"><?= e($regla['foto']) ?></p>
                    </div>
                <?php endif; ?>

                <div class="panel-fila">
                    <?php if ($regla['enlace']): ?>
                        <div class="campo-panel campo-panel--crece">
                            <label class="campo-panel__rotulo" for="nuevo-enlace-<?= e($clave) ?>">A dónde lleva</label>
                            <input class="campo-panel__control campo-panel__control--mono" type="text"
                                   id="nuevo-enlace-<?= e($clave) ?>" name="enlace" placeholder="/catalogo">
                        </div>
                    <?php endif; ?>

                    <div class="campo-panel campo-panel--angosto">
                        <label class="campo-panel__rotulo" for="nuevo-orden-<?= e($clave) ?>">Orden</label>
                        <input class="campo-panel__control" type="text" inputmode="numeric"
                               id="nuevo-orden-<?= e($clave) ?>" name="orden"
                               value="<?= e((string) (count($grupo) + 1)) ?>">
                    </div>
                </div>

                <label class="campo-panel__casilla">
                    <input type="checkbox" name="activo" value="1" checked>
                    Se muestra en el sitio
                </label>

                <button class="panel-boton panel-boton--acento" type="submit">Agregar</button>
            </form>
        </details>
    </section>
<?php endforeach; ?>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
