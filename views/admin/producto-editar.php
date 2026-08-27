<?php
/**
 * admin/producto-editar.php — alta y edición de un producto.
 *
 * Sigue el frame "Admin · Producto · alta y edición".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=82-249
 *
 * Es la pantalla que el cliente va a usar más que ninguna, así que las
 * decisiones acá están tomadas para que cargar doscientos productos no
 * sea un castigo:
 *
 *   · La validación la hace `repo_producto_guardar()`, no esta vista. Si
 *     algo falla, el formulario se redibuja CON LO QUE LA PERSONA ESCRIBIÓ,
 *     no vacío. Perder veinte campos por un SKU repetido es la forma más
 *     rápida de que alguien deje de usar el panel.
 *   · La URL del producto no se recalcula al editar. Cambiar el slug de un
 *     producto publicado rompe el enlace que alguien mandó por WhatsApp,
 *     así que se toca a mano y sabiendo.
 *   · El descuento vacío significa "usá el global", que no es lo mismo que
 *     cero. El campo lo dice.
 *   · Las especificaciones y las fotos son listas que crecen. Sin
 *     JavaScript vienen tres filas fijas, que alcanzan para la mayoría.
 */

declare(strict_types=1);

$admin_seccion = 'productos';

/* El guard primero y la cabecera al final: un id que no existe tiene que
   poder devolver 404 antes de imprimir el primer byte. */
require RASTRO_VIEWS . '/admin/_guard.php';
require_once RASTRO_RAIZ . '/app/subidas.php';

$id = isset($params['id']) ? (int) $params['id'] : null;

$producto = $id !== null ? repo_admin_product($id) : null;

if ($id !== null && $producto === null) {
    router_404();
}

$es_alta = $producto === null;

/* --- Valores del formulario -----------------------------------------
   Arrancan con lo que hay en la base y los pisa lo que vino por POST, así
   un error de validación no borra lo que la persona escribió. */

$v = [
    'nombre'            => $producto['nombre'] ?? '',
    'sku'               => $producto['sku'] ?? '',
    'slug'              => $producto['slug'] ?? '',
    'categoria'         => $producto['categoria'] ?? '',
    'marca'             => $producto['marca'] ?? '',
    'descripcion_corta' => $producto['descripcion_corta'] ?? '',
    'descripcion'       => $producto['descripcion'] ?? '',
    'precio_lista'      => $producto['precio_lista'] ?? '',
    'precio_mayorista'  => $producto['precio_mayorista'] ?? '',
    'descuento_pct'     => $producto['descuento_pct'] ?? '',
    'stock'             => $producto['stock'] ?? 0,
    'peso_kg'           => $producto['peso_kg'] ?? '',
    'imagen'            => $producto['imagen'] ?? '',
    'destacado'         => $producto['destacado'] ?? false,
    'nuevo'             => $producto['nuevo'] ?? false,
    'activo'            => $producto['activo'] ?? true,
];

$imagenes = $producto['imagenes'] ?? [];
$especs   = $producto['especificaciones'] ?? [];
$errores  = [];

/* --- Guardar --------------------------------------------------------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    foreach (array_keys($v) as $campo) {
        $v[$campo] = in_array($campo, ['destacado', 'nuevo', 'activo'], true)
            ? !empty($_POST[$campo])
            : trim((string) ($_POST[$campo] ?? ''));
    }

    $imagenes = array_values(array_filter(
        array_map('trim', (array) ($_POST['imagenes'] ?? [])),
        static fn (string $r): bool => $r !== ''
    ));

    $especs = [];
    foreach ((array) ($_POST['espec_label'] ?? []) as $i => $etiqueta) {
        $especs[] = [
            'label' => trim((string) $etiqueta),
            'valor' => trim((string) (($_POST['espec_valor'] ?? [])[$i] ?? '')),
        ];
    }

    /* La foto que se sube reemplaza a la principal y se suma a la galería.
       Se procesa ANTES de validar el resto para que, si el producto no se
       guarda por otro error, la imagen ya subida no se pierda: queda en el
       campo y se manda de nuevo con el siguiente intento. */
    if (!empty($_FILES['foto']['name'])) {
        $subida = subir_imagen($_FILES['foto'], 'productos', (string) ($v['sku'] ?: $v['nombre']));

        if ($subida['error'] !== null) {
            $errores['foto'] = $subida['error'];
        } elseif ($subida['ok']) {
            $v['imagen'] = $subida['ruta'];

            if (!in_array($subida['ruta'], $imagenes, true)) {
                array_unshift($imagenes, $subida['ruta']);
            }
        }
    }

    if ($errores === []) {
        $resultado = repo_producto_guardar(
            $v + [
                'imagenes'    => $imagenes,
                'espec_label' => array_column($especs, 'label'),
                'espec_valor' => array_column($especs, 'valor'),
            ],
            $id
        );

        if ($resultado['ok']) {
            admin_avisar($es_alta ? 'Producto creado.' : 'Cambios guardados.');

            header('Location: ' . url('/admin/productos/' . $resultado['id']), true, 303);
            exit;
        }

        $errores = $resultado['errores'];
    }
}

$admin_titulo = $es_alta ? 'Nuevo producto' : (string) $v['nombre'];

require RASTRO_VIEWS . '/admin/_cabecera.php';

$categorias = repo_categories();
$marcas     = repo_admin_marcas();

// Sin JavaScript hacen falta filas vacías de arranque; con JS se suman solas.
while (count($especs) < 3) {
    $especs[] = ['label' => '', 'valor' => ''];
}

while (count($imagenes) < 2) {
    $imagenes[] = '';
}
?>

<?php if ($errores !== []): ?>
    <p class="admin-aviso admin-aviso--error t-mono-texto" role="alert">
        <span class="admin-aviso__marca" aria-hidden="true">!</span>
        No se guardó: revisá los campos marcados.
    </p>
<?php endif; ?>

<form class="form-admin" method="post" enctype="multipart/form-data" data-avisar-cambios
      action="<?= e($es_alta ? url('/admin/productos/nuevo') : url('/admin/productos/' . $id)) ?>">
    <?= csrf_campo() ?>

    <?php /* ============================================================
             Identidad
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Identidad</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="nombre">Nombre</label>
                <input class="campo t-mono-texto <?= isset($errores['nombre']) ? 'es-error' : '' ?>"
                       type="text" id="nombre" name="nombre" value="<?= e((string) $v['nombre']) ?>" required>
                <?php if (isset($errores['nombre'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['nombre']) ?></span>
                <?php endif; ?>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="sku">Código (SKU)</label>
                <input class="campo t-mono-texto <?= isset($errores['sku']) ? 'es-error' : '' ?>"
                       type="text" id="sku" name="sku" value="<?= e((string) $v['sku']) ?>" required>
                <?php if (isset($errores['sku'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['sku']) ?></span>
                <?php else: ?>
                    <span class="form-admin__ayuda t-mono-texto-sm">Se ve en la foto de la card y en la ficha.</span>
                <?php endif; ?>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="slug">URL</label>
                <input class="campo t-mono-texto <?= isset($errores['slug']) ? 'es-error' : '' ?>"
                       type="text" id="slug" name="slug" value="<?= e((string) $v['slug']) ?>"
                       placeholder="<?= $es_alta ? 'se calcula del nombre' : '' ?>">
                <?php if (isset($errores['slug'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['slug']) ?></span>
                <?php elseif (!$es_alta): ?>
                    <span class="form-admin__ayuda t-mono-texto-sm">
                        Cambiarla rompe los enlaces que ya se compartieron de este producto.
                    </span>
                <?php endif; ?>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="categoria">Categoría</label>
                <select class="campo t-mono-texto" id="categoria" name="categoria">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= e($c['slug']) ?>" <?= $v['categoria'] === $c['slug'] ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="marca">Marca</label>
                <select class="campo t-mono-texto" id="marca" name="marca">
                    <option value="">Sin marca</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= e($m['slug']) ?>" <?= $v['marca'] === $m['slug'] ? 'selected' : '' ?>>
                            <?= e($m['nombre']) ?><?= $m['es_propia'] ? ' (línea propia)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
    </fieldset>

    <?php /* ============================================================
             Precio y stock
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Precio y stock</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="precio_lista">Precio publicado</label>
                <input class="campo t-mono-texto <?= isset($errores['precio_lista']) ? 'es-error' : '' ?>"
                       type="text" inputmode="numeric" id="precio_lista" name="precio_lista"
                       value="<?= e((string) $v['precio_lista']) ?>" required>
                <?php if (isset($errores['precio_lista'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['precio_lista']) ?></span>
                <?php else: ?>
                    <span class="form-admin__ayuda t-mono-texto-sm">Es el precio pagando con Mercado Pago.</span>
                <?php endif; ?>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="precio_mayorista">Precio mayorista</label>
                <input class="campo t-mono-texto" type="text" inputmode="numeric"
                       id="precio_mayorista" name="precio_mayorista" value="<?= e((string) $v['precio_mayorista']) ?>">
                <span class="form-admin__ayuda t-mono-texto-sm">No se publica. Es para cotizar.</span>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="descuento_pct">Descuento propio</label>
                <input class="campo t-mono-texto <?= isset($errores['descuento_pct']) ? 'es-error' : '' ?>"
                       type="text" inputmode="decimal" id="descuento_pct" name="descuento_pct"
                       value="<?= e((string) $v['descuento_pct']) ?>" placeholder="usa el global">
                <?php if (isset($errores['descuento_pct'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['descuento_pct']) ?></span>
                <?php else: ?>
                    <span class="form-admin__ayuda t-mono-texto-sm">
                        Vacío = usa el <?= e(descuento_global($settings ?? repo_settings())) ?>% general. Escribir 0 es "sin descuento".
                    </span>
                <?php endif; ?>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="stock">Stock</label>
                <input class="campo t-mono-texto" type="number" id="stock" name="stock"
                       value="<?= e((string) $v['stock']) ?>" step="1">
                <span class="form-admin__ayuda t-mono-texto-sm">En 0 el producto se ve pero no se puede agregar al carrito.</span>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="peso_kg">Peso (kg)</label>
                <input class="campo t-mono-texto" type="text" inputmode="decimal"
                       id="peso_kg" name="peso_kg" value="<?= e((string) $v['peso_kg']) ?>">
                <span class="form-admin__ayuda t-mono-texto-sm">Es la medida que se muestra en la card.</span>
            </p>
        </div>
    </fieldset>

    <?php /* ============================================================
             Fotos
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Fotos</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="foto">Subir una foto</label>
                <input class="campo t-mono-texto <?= isset($errores['foto']) ? 'es-error' : '' ?>"
                       type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                <?php if (isset($errores['foto'])): ?>
                    <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($errores['foto']) ?></span>
                <?php else: ?>
                    <span class="form-admin__ayuda t-mono-texto-sm">
                        JPG, PNG o WebP, hasta 6 MB. Pasa a ser la foto principal.
                    </span>
                <?php endif; ?>
            </p>

            <?php if ((string) $v['imagen'] !== ''): ?>
                <p class="form-admin__campo">
                    <span class="form-admin__etiqueta t-mono-label-sm">Foto principal actual</span>
                    <img class="form-admin__vista" src="<?= e(asset((string) $v['imagen'])) ?>" alt=""
                         width="120" height="120" loading="lazy" decoding="async">
                </p>
            <?php endif; ?>
        </div>

        <input type="hidden" name="imagen" value="<?= e((string) $v['imagen']) ?>">

        <div class="form-admin__campo">
            <span class="form-admin__etiqueta t-mono-label-sm">Galería</span>
            <span class="form-admin__ayuda t-mono-texto-sm">
                Rutas relativas a <code>assets/</code>. La primera es la que se ve grande en la ficha.
            </span>

            <div class="repetible" id="lista-imagenes">
                <?php foreach ($imagenes as $i => $ruta): ?>
                    <div class="repetible__fila repetible__fila--simple">
                        <input class="campo t-mono-texto" type="text" name="imagenes[<?= e((string) $i) ?>]"
                               value="<?= e($ruta) ?>" placeholder="img/productos/…">
                        <button class="repetible__quitar" type="button" data-repetible-quitar
                                aria-label="Quitar esta foto">×</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="boton boton--fantasma repetible__sumar" type="button" data-repetible-sumar="lista-imagenes">
                <span class="t-mono-label">Sumar foto</span>
            </button>
        </div>
    </fieldset>

    <?php /* ============================================================
             Textos y especificaciones
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Textos</legend>

        <p class="form-admin__campo">
            <label class="form-admin__etiqueta t-mono-label-sm" for="descripcion_corta">Descripción corta</label>
            <input class="campo t-mono-texto" type="text" id="descripcion_corta" name="descripcion_corta"
                   value="<?= e((string) $v['descripcion_corta']) ?>" maxlength="400">
            <span class="form-admin__ayuda t-mono-texto-sm">Una línea. Es lo que se usa en buscadores y redes.</span>
        </p>

        <p class="form-admin__campo">
            <label class="form-admin__etiqueta t-mono-label-sm" for="descripcion">Descripción</label>
            <textarea class="campo campo--area t-mono-texto" id="descripcion" name="descripcion"
                      rows="6"><?= e((string) $v['descripcion']) ?></textarea>
            <span class="form-admin__ayuda t-mono-texto-sm">Un renglón en blanco separa párrafos.</span>
        </p>

        <div class="form-admin__campo">
            <span class="form-admin__etiqueta t-mono-label-sm">Especificaciones</span>
            <span class="form-admin__ayuda t-mono-texto-sm">
                Se dibujan en la tabla de la ficha, en este orden. Las filas a medio llenar no se guardan.
            </span>

            <div class="repetible" id="lista-especs">
                <?php foreach ($especs as $i => $espec): ?>
                    <div class="repetible__fila">
                        <input class="campo t-mono-texto" type="text" name="espec_label[<?= e((string) $i) ?>]"
                               value="<?= e($espec['label']) ?>" placeholder="Material">
                        <input class="campo t-mono-texto" type="text" name="espec_valor[<?= e((string) $i) ?>]"
                               value="<?= e($espec['valor']) ?>" placeholder="Caucho virgen de alta densidad">
                        <button class="repetible__quitar" type="button" data-repetible-quitar
                                aria-label="Quitar esta especificación">×</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="boton boton--fantasma repetible__sumar" type="button" data-repetible-sumar="lista-especs">
                <span class="t-mono-label">Sumar especificación</span>
            </button>
        </div>
    </fieldset>

    <?php /* ============================================================
             Publicación
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Publicación</legend>

        <label class="opcion-admin">
            <input type="checkbox" name="activo" value="1" <?= $v['activo'] ? 'checked' : '' ?>>
            <span class="t-mono-texto">Publicado en el catálogo</span>
        </label>

        <label class="opcion-admin">
            <input type="checkbox" name="destacado" value="1" <?= $v['destacado'] ? 'checked' : '' ?>>
            <span class="t-mono-texto">Destacado — aparece en la home</span>
        </label>

        <label class="opcion-admin">
            <input type="checkbox" name="nuevo" value="1" <?= $v['nuevo'] ? 'checked' : '' ?>>
            <span class="t-mono-texto">Nuevo — sube en el orden por relevancia</span>
        </label>
    </fieldset>

    <div class="form-admin__pie">
        <div class="form-admin__acciones">
            <button class="boton boton--acento" type="submit">
                <span class="t-mono-label"><?= $es_alta ? 'Crear producto' : 'Guardar cambios' ?></span>
                <span class="boton__flecha" aria-hidden="true">→</span>
            </button>

            <a class="boton boton--fantasma" href="<?= e(url('/admin/productos')) ?>">
                <span class="t-mono-label">Cancelar</span>
            </a>
        </div>

        <?php if (!$es_alta && $v['activo']): ?>
            <a class="tabla-admin__accion t-mono-label-sm" target="_blank" rel="noopener"
               href="<?= e(url('/producto/' . $producto['slug'])) ?>">
                Ver en el sitio ↗
            </a>
        <?php endif; ?>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
