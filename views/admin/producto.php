<?php
/**
 * admin/producto.php — alta y edición de un producto.
 *
 * Una sola vista para las dos cosas. Un alta es una edición con el registro
 * vacío: separarlas duplicaría un formulario de veinte campos y garantizaría
 * que un día se agregue un campo en uno y no en el otro.
 *
 * ---------------------------------------------------------------------
 * QUÉ NO HACE ESTA PANTALLA
 *
 * No calcula el precio con descuento. Ese número sale de un solo lugar en
 * todo el proyecto (`precio_con_descuento()`, CLAUDE.md §4.3) y acá se
 * muestra a modo de vista previa, leyendo el mismo helper que usa el sitio.
 * Si el panel lo calculara por su cuenta habría dos fuentes de verdad para
 * el dato más importante del negocio.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

panel_exigir_sesion();

$categorias = repo_categories();
$marcas     = repo_brands(true);   // con la línea propia: un producto puede ser de Rastro
$settings   = repo_settings();

$id = isset($params['id']) ? (int) $params['id'] : 0;

/* El producto que se está editando, tal como está en el archivo. Se busca
   por id sobre la lista cruda y no con repo_product(), que resuelve por
   slug y deja afuera los despublicados: el panel tiene que poder editar
   justamente los que el sitio no muestra. */
$producto = null;

if ($id > 0) {
    foreach (repo_all_products() as $fila) {
        if ((int) ($fila['id'] ?? 0) === $id) {
            $producto = $fila;
            break;
        }
    }

    if ($producto === null) {
        router_404();
    }
}

$errores = [];

/* --- Guardar ------------------------------------------------------------ */

if (panel_es_post()) {
    panel_exigir_csrf();

    $nombre = panel_texto('nombre');
    $sku    = panel_texto('sku');

    /* El slug se deriva del nombre pero se puede escribir a mano, y una vez
       que el producto existe NO se regenera solo: es su URL pública, y una
       URL que cambia sola cuando alguien corrige una tilde del nombre rompe
       el enlace que el cliente ya mandó por WhatsApp. */
    $slug_pedido = panel_texto('slug');
    $slug        = $slug_pedido !== '' ? slug($slug_pedido) : slug($nombre);

    if ($nombre === '') {
        $errores['nombre'] = 'El nombre no puede quedar vacío.';
    }

    if ($sku === '') {
        $errores['sku'] = 'El SKU es lo que identifica al producto en el depósito.';
    }

    if ($slug === '') {
        $errores['slug'] = 'No se pudo armar la dirección web con ese nombre. Escribila a mano.';
    }

    /* Slug y SKU repetidos. Dos productos con el mismo slug hacen que uno de
       los dos sea inalcanzable —repo_product() devuelve el primero que
       encuentra— y no da ningún error: simplemente hay una ficha que nunca
       se ve. */
    foreach (repo_all_products() as $otro) {
        if ((int) ($otro['id'] ?? 0) === $id) {
            continue;
        }

        if (($otro['slug'] ?? '') === $slug) {
            $errores['slug'] = 'Ya hay otro producto con esa dirección web: ' . (string) ($otro['nombre'] ?? '');
        }

        if ($sku !== '' && strcasecmp((string) ($otro['sku'] ?? ''), $sku) === 0) {
            $errores['sku'] = 'Ese SKU ya lo usa: ' . (string) ($otro['nombre'] ?? '');
        }
    }

    $precio = panel_entero('precio_lista', 0) ?? 0;

    if ($precio <= 0) {
        $errores['precio_lista'] = 'El precio tiene que ser mayor que cero.';
    }

    $descuento = panel_entero('descuento_pct');

    if ($descuento !== null && ($descuento < 0 || $descuento > 90)) {
        $errores['descuento_pct'] = 'El descuento va entre 0 y 90.';
    }

    /* La foto principal. Si suben una nueva reemplaza a la que había; si no
       suben nada se conserva. Un formulario sin archivo NO significa "sacale
       la foto": para eso está la casilla de quitar. */
    $imagen = (string) ($producto['imagen'] ?? '');

    if (($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $subida = panel_subir_imagen('foto', $nombre !== '' ? $nombre : 'producto');

        if (($subida['error'] ?? '') !== '') {
            $errores['foto'] = $subida['error'];
        } else {
            $imagen = (string) $subida['ruta'];
        }
    } elseif (panel_booleano('quitar_foto')) {
        $imagen = '';
    }

    if ($errores === []) {
        /* `imagenes` arranca por la principal: es la que abre la galería de
           la ficha. Las demás se escriben una por línea. */
        $galeria = panel_lineas('imagenes');

        if ($imagen !== '') {
            array_unshift($galeria, $imagen);
        }

        $datos = [
            'id'                => $id,
            'slug'              => $slug,
            'sku'               => $sku,
            'nombre'            => $nombre,
            'categoria'         => panel_texto('categoria'),
            'marca'             => panel_texto('marca'),
            'descripcion_corta' => panel_texto('descripcion_corta'),
            'descripcion'       => panel_texto('descripcion'),
            'precio_lista'      => $precio,
            'precio_mayorista'  => panel_entero('precio_mayorista', 0) ?? 0,
            'descuento_pct'     => $descuento,
            'stock'             => max(0, panel_entero('stock', 0) ?? 0),
            'peso_kg'           => panel_entero('peso_kg'),
            'destacado'         => panel_booleano('destacado'),
            'nuevo'             => panel_booleano('nuevo'),
            'activo'            => panel_booleano('activo'),
            'imagen'            => $imagen,
            'imagenes'          => array_values(array_unique($galeria)),
            'especificaciones'  => panel_pares('especificaciones'),
        ];

        $guardado = repo_save_product($datos);

        if ($guardado !== null) {
            panel_ir_con_aviso(
                '/admin/productos',
                'ok',
                sprintf('"%s" %s.', $nombre, $id > 0 ? 'quedó actualizado' : 'se agregó al catálogo')
            );
        }

        $errores['general'] = 'No se pudo guardar. Revisá los permisos de la carpeta data/.';
    }

    /* Con errores se vuelve a dibujar el formulario con LO QUE MANDARON, no
       con lo que estaba guardado: perder veinte campos por una tilde en el
       SKU es la forma más rápida de que nadie quiera usar el panel. */
    $producto = array_replace((array) $producto, [
        'id'                => $id,
        'slug'              => $slug_pedido !== '' ? $slug_pedido : $slug,
        'sku'               => $sku,
        'nombre'            => $nombre,
        'categoria'         => panel_texto('categoria'),
        'marca'             => panel_texto('marca'),
        'descripcion_corta' => panel_texto('descripcion_corta'),
        'descripcion'       => panel_texto('descripcion'),
        'precio_lista'      => $precio,
        'precio_mayorista'  => panel_entero('precio_mayorista', 0) ?? 0,
        'descuento_pct'     => $descuento,
        'stock'             => panel_entero('stock', 0) ?? 0,
        'peso_kg'           => panel_entero('peso_kg'),
        'destacado'         => panel_booleano('destacado'),
        'nuevo'             => panel_booleano('nuevo'),
        'activo'            => panel_booleano('activo'),
        'imagen'            => $imagen,
    ]);
}

/* --- Valores del formulario --------------------------------------------- */

/**
 * El valor de un campo, con el defecto del alta.
 *
 * `array_key_exists` y no `??`: `descuento_pct` vale null a propósito —
 * significa "usá el descuento general"— y `??` trata null igual que ausente,
 * así que devolvía el defecto `''` y el campo mostraba un 0 que nadie
 * escribió. Un 0 en ese campo no es lo mismo que vacío: dice "este producto
 * no tiene descuento", que es otra cosa.
 */
$v = static function (string $clave, mixed $defecto = '') use ($producto): mixed {
    return is_array($producto) && array_key_exists($clave, $producto)
        ? $producto[$clave]
        : $defecto;
};

$es_alta = $id === 0;

/* Un producto nuevo arranca publicado. Cargarlo y que no aparezca en el
   sitio hasta descubrir una casilla escondida es la primera frustración
   posible con el panel. */
$activo = $es_alta ? true : (($producto['activo'] ?? true) === true);

$galeria_extra = array_values(array_filter(
    (array) $v('imagenes', []),
    static fn ($ruta): bool => is_string($ruta) && $ruta !== '' && $ruta !== ($producto['imagen'] ?? null)
));

$especificaciones_texto = implode("\n", array_map(
    static fn (array $par): string => trim((string) ($par['label'] ?? '')) . ': ' . trim((string) ($par['valor'] ?? '')),
    array_filter((array) $v('especificaciones', []), 'is_array')
));

$titulo = $es_alta ? 'Nuevo producto' : (string) $v('nombre', 'Producto');
$bajada = $es_alta ? 'Los campos con * son los únicos obligatorios.' : 'SKU ' . (string) $v('sku', '');
$volver = ['texto' => 'Todos los productos', 'href' => url('/admin/productos')];

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if (isset($errores['general'])): ?>
    <p class="panel-aviso panel-aviso--error" role="alert">
        <span class="panel-aviso__icono" aria-hidden="true">!</span>
        <?= e($errores['general']) ?>
    </p>
<?php elseif ($errores !== []): ?>
    <p class="panel-aviso panel-aviso--error" role="alert">
        <span class="panel-aviso__icono" aria-hidden="true">!</span>
        No se guardó: revisá los campos marcados abajo.
    </p>
<?php endif; ?>

<?php /* enctype multipart porque el formulario sube la foto. Sin eso el
         archivo no llega y $_FILES viene vacío sin ningún error. */ ?>
<form class="panel-formulario" method="post" enctype="multipart/form-data"
      action="<?= e(url($es_alta ? '/admin/productos/nuevo' : '/admin/productos/' . $id)) ?>">

    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

    <div class="panel-columnas">

        <div class="panel-columnas__principal">

            <?php /* --- Identificación --- */ ?>
            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Qué es</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="nombre">Nombre *</label>
                    <input class="campo-panel__control<?= isset($errores['nombre']) ? ' es-error' : '' ?>"
                           type="text" id="nombre" name="nombre" required
                           value="<?= e((string) $v('nombre')) ?>">
                    <?php if (isset($errores['nombre'])): ?>
                        <p class="campo-panel__error"><?= e($errores['nombre']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="panel-fila">
                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="sku">SKU *</label>
                        <input class="campo-panel__control<?= isset($errores['sku']) ? ' es-error' : '' ?>"
                               type="text" id="sku" name="sku" required
                               value="<?= e((string) $v('sku')) ?>">
                        <?php if (isset($errores['sku'])): ?>
                            <p class="campo-panel__error"><?= e($errores['sku']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="slug">Dirección web</label>
                        <input class="campo-panel__control<?= isset($errores['slug']) ? ' es-error' : '' ?>"
                               type="text" id="slug" name="slug"
                               value="<?= e((string) $v('slug')) ?>"
                               placeholder="se arma sola con el nombre">
                        <p class="campo-panel__ayuda">
                            <?= e(rtrim((string) config('base_url', ''), '/')) ?>/producto/<span data-eco-slug><?= e((string) $v('slug', '…')) ?></span>
                            <?php if (!$es_alta): ?>
                                — cambiarla rompe los enlaces que ya se compartieron.
                            <?php endif; ?>
                        </p>
                        <?php if (isset($errores['slug'])): ?>
                            <p class="campo-panel__error"><?= e($errores['slug']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-fila">
                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="categoria">Categoría</label>
                        <select class="campo-panel__control" id="categoria" name="categoria">
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= e((string) ($categoria['slug'] ?? '')) ?>"
                                    <?= $v('categoria') === ($categoria['slug'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string) ($categoria['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="marca">Marca</label>
                        <select class="campo-panel__control" id="marca" name="marca">
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?= e((string) ($marca['slug'] ?? '')) ?>"
                                    <?= $v('marca') === ($marca['slug'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string) ($marca['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <?php /* --- Textos --- */ ?>
            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Textos</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="descripcion_corta">Bajada</label>
                    <textarea class="campo-panel__control" id="descripcion_corta" name="descripcion_corta"
                              rows="2" maxlength="200"><?= e((string) $v('descripcion_corta')) ?></textarea>
                    <p class="campo-panel__ayuda">
                        Una línea. Es lo que se lee abajo del nombre en la grilla del catálogo.
                    </p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="descripcion">Descripción</label>
                    <textarea class="campo-panel__control" id="descripcion" name="descripcion"
                              rows="8"><?= e((string) $v('descripcion')) ?></textarea>
                    <p class="campo-panel__ayuda">
                        El texto largo de la ficha. Contá el material, la medida y para qué uso sirve:
                        es lo que decide una compra de un club.
                    </p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="especificaciones">Ficha técnica</label>
                    <textarea class="campo-panel__control campo-panel__control--mono" id="especificaciones"
                              name="especificaciones" rows="7"><?= e($especificaciones_texto) ?></textarea>
                    <p class="campo-panel__ayuda">
                        Una por línea, con dos puntos: <code>Material: caucho virgen</code>.
                    </p>
                </div>
            </fieldset>

            <?php /* --- Imágenes --- */ ?>
            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Fotos</legend>

                <div class="panel-foto">
                    <?php if ((string) $v('imagen') !== ''): ?>
                        <img class="panel-foto__vista" src="<?= e(asset((string) $v('imagen'))) ?>"
                             alt="Foto actual de <?= e((string) $v('nombre', 'el producto')) ?>"
                             width="120" height="120" decoding="async">
                    <?php else: ?>
                        <span class="panel-foto__hueco" aria-hidden="true">Sin foto</span>
                    <?php endif; ?>

                    <div class="panel-foto__campos">
                        <div class="campo-panel">
                            <label class="campo-panel__rotulo" for="foto">Cambiar la foto principal</label>
                            <input class="campo-panel__control<?= isset($errores['foto']) ? ' es-error' : '' ?>"
                                   type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                            <p class="campo-panel__ayuda">
                                JPG, PNG o WebP, hasta <?= e(panel_pesar(PANEL_MAX_BYTES)) ?>.
                                Lo ideal es sobre fondo blanco y de 1000 px de lado.
                            </p>
                            <?php if (isset($errores['foto'])): ?>
                                <p class="campo-panel__error"><?= e($errores['foto']) ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ((string) $v('imagen') !== ''): ?>
                            <label class="campo-panel__casilla">
                                <input type="checkbox" name="quitar_foto" value="1">
                                Quitar la foto actual
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="imagenes">Otras fotos</label>
                    <textarea class="campo-panel__control campo-panel__control--mono" id="imagenes"
                              name="imagenes" rows="4"><?= e(implode("\n", $galeria_extra)) ?></textarea>
                    <p class="campo-panel__ayuda">
                        Una ruta por línea, relativa a <code>assets/</code>. Ejemplo:
                        <code>img/productos/disco-alt.jpg</code>. La principal se agrega sola.
                    </p>
                </div>
            </fieldset>
        </div>

        <?php /* --- Barra lateral: lo que decide si se vende y a cuánto --- */ ?>
        <aside class="panel-columnas__lateral">

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Precio</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="precio_lista">Precio de lista *</label>
                    <input class="campo-panel__control<?= isset($errores['precio_lista']) ? ' es-error' : '' ?>"
                           type="text" inputmode="numeric" id="precio_lista" name="precio_lista" required
                           value="<?= e((string) (int) $v('precio_lista', 0)) ?>">
                    <p class="campo-panel__ayuda">
                        En pesos, sin puntos ni centavos. Es el precio pagando con Mercado Pago.
                    </p>
                    <?php if (isset($errores['precio_lista'])): ?>
                        <p class="campo-panel__error"><?= e($errores['precio_lista']) ?></p>
                    <?php endif; ?>
                </div>

                <?php /* Vista previa del precio con descuento, calculada con
                         el mismo helper que el sitio. Es el número que el
                         cliente va a ver publicado y conviene que quien carga
                         lo vea antes de guardar. */ ?>
                <?php if ((int) $v('precio_lista', 0) > 0): ?>
                    <?php $vista = precio_con_descuento((array) $producto, $settings); ?>
                    <p class="panel-previa">
                        <?php if (($vista['tiene_descuento'] ?? false) === true): ?>
                            Por transferencia se publica
                            <strong><?= e(moneda($vista['con_descuento'])) ?></strong>
                            <span>−<?= e(porcentaje($vista['porcentaje'])) ?>%, ahorro de <?= e(moneda($vista['ahorro'])) ?></span>
                        <?php else: ?>
                            <strong>Sin descuento por transferencia.</strong>
                            <span>El general está en <?= e(descuento_global($settings)) ?>%.</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="precio_mayorista">Precio mayorista</label>
                    <input class="campo-panel__control" type="text" inputmode="numeric"
                           id="precio_mayorista" name="precio_mayorista"
                           value="<?= e((string) (int) $v('precio_mayorista', 0)) ?>">
                    <p class="campo-panel__ayuda">No se publica: es de referencia para cotizar.</p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="descuento_pct">Descuento propio (%)</label>
                    <input class="campo-panel__control<?= isset($errores['descuento_pct']) ? ' es-error' : '' ?>"
                           type="text" inputmode="numeric" id="descuento_pct" name="descuento_pct"
                           value="<?= $v('descuento_pct') === null ? '' : e((string) (int) $v('descuento_pct')) ?>">
                    <p class="campo-panel__ayuda">
                        Vacío = usa el <?= e(descuento_global($settings)) ?>% general.
                        Sólo se completa para pisar el general en este producto.
                    </p>
                    <?php if (isset($errores['descuento_pct'])): ?>
                        <p class="campo-panel__error"><?= e($errores['descuento_pct']) ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Depósito</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="stock">Stock</label>
                    <input class="campo-panel__control" type="text" inputmode="numeric"
                           id="stock" name="stock" value="<?= e((string) (int) $v('stock', 0)) ?>">
                    <p class="campo-panel__ayuda">En cero se sigue mostrando, marcado como sin stock.</p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="peso_kg">Peso (kg)</label>
                    <input class="campo-panel__control" type="text" inputmode="numeric"
                           id="peso_kg" name="peso_kg"
                           value="<?= $v('peso_kg') === null ? '' : e((string) (int) $v('peso_kg')) ?>">
                </div>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Dónde aparece</legend>

                <label class="campo-panel__casilla">
                    <input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
                    Publicado en el sitio
                </label>

                <label class="campo-panel__casilla">
                    <input type="checkbox" name="destacado" value="1" <?= $v('destacado') ? 'checked' : '' ?>>
                    Destacado en la portada
                </label>

                <label class="campo-panel__casilla">
                    <input type="checkbox" name="nuevo" value="1" <?= $v('nuevo') ? 'checked' : '' ?>>
                    Marcar como novedad
                </label>
            </fieldset>

            <div class="panel-acciones">
                <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                    <?= $es_alta ? 'Crear producto' : 'Guardar cambios' ?>
                </button>

                <?php if (!$es_alta && (string) $v('slug') !== ''): ?>
                    <a class="panel-enlace" target="_blank" rel="noopener"
                       href="<?= e(url('/producto/' . (string) $v('slug'))) ?>">
                        Ver en el sitio
                        <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
                    </a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
