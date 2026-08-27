<?php
/**
 * admin/_abm.php — la pantalla de lista corta con alta y edición.
 *
 * Categorías, marcas, clientes y banners son la misma pantalla: una lista
 * de pocas filas, ordenable, que el cliente edita a mano y donde el alta
 * y la edición pasan en la misma página. Cuatro archivos de doscientas
 * líneas casi idénticas se desincronizan solos; esto es uno solo con
 * cuatro configuraciones de veinte.
 *
 * NO ES UN GENERADOR DE ABM. Sólo cubre este caso —lista corta, campos
 * planos, sin relaciones— y por eso se puede leer entero. Productos y
 * pedidos NO usan esto: tienen su propia pantalla porque su formulario no
 * se parece a esto en nada.
 *
 * CÓMO SE USA
 *
 *   $abm = [
 *       'titulo'   => 'Categorías',
 *       'seccion'  => 'categorias',
 *       'singular' => 'la categoría',
 *       'listar'   => 'repo_categories',
 *       'guardar'  => 'repo_categoria_guardar',
 *       'borrar'   => 'repo_categoria_borrar',
 *       'nota'     => 'Las categorías arman el menú y el bento de la home.',
 *       'campos'   => [
 *           'nombre' => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'requerido' => true],
 *           …
 *       ],
 *       'columnas' => ['nombre', 'slug', 'orden'],   // qué se ve en la tabla
 *   ];
 *   require RASTRO_VIEWS . '/admin/_abm.php';
 *
 * Tipos de campo: texto | area | numero | casilla | select.
 */

declare(strict_types=1);

$admin_titulo  = (string) $abm['titulo'];
$admin_seccion = (string) $abm['seccion'];

require RASTRO_VIEWS . '/admin/_guard.php';

$abm_campos   = (array) $abm['campos'];
$abm_columnas = (array) $abm['columnas'];
$abm_singular = (string) ($abm['singular'] ?? 'el elemento');
$abm_ruta     = url('/admin/' . $abm['seccion']);

$abm_errores = [];
$abm_valores = [];

/* --- Guardar y borrar ------------------------------------------------ */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id     = (int) ($_POST['id'] ?? 0) ?: null;

    if ($accion === 'borrar' && $id !== null) {
        $resultado = ($abm['borrar'])($id);

        /* Se avisa cuántos productos quedaron sin categoría o sin marca.
           El borrado no los arrastra —la relación es ON DELETE SET NULL,
           así que siguen visibles y editables— pero quedan sueltos y
           alguien tiene que reasignarlos. */
        $sueltos = (int) ($resultado['sueltos'] ?? 0);

        admin_avisar(
            $sueltos > 0
                ? "Se borró. Quedaron $sueltos productos sin asignar: revisalos en Productos."
                : 'Se borró.'
        );

        header('Location: ' . $abm_ruta, true, 303);
        exit;
    }

    foreach ($abm_campos as $clave => $campo) {
        $abm_valores[$clave] = $campo['tipo'] === 'casilla'
            ? !empty($_POST[$clave])
            : trim((string) ($_POST[$clave] ?? ''));
    }

    $resultado = ($abm['guardar'])($abm_valores, $id);

    if ($resultado['ok']) {
        admin_avisar($id === null ? 'Se creó.' : 'Cambios guardados.');

        header('Location: ' . $abm_ruta, true, 303);
        exit;
    }

    $abm_errores = $resultado['errores'];
    $abm_editando = $id;
}

/* --- Qué se está editando -------------------------------------------- */

$abm_items = array_values(($abm['listar'])());

/* ?editar=N abre la fila N en el formulario. Sin JavaScript y sin una
   pantalla aparte: la lista es corta y el formulario está arriba. */
$abm_editando = $abm_editando ?? (param_int('editar') ?: null);
$abm_actual   = null;

if ($abm_editando !== null) {
    foreach ($abm_items as $item) {
        if ((int) ($item['id'] ?? 0) === $abm_editando) {
            $abm_actual = $item;
            break;
        }
    }

    if ($abm_actual === null) {
        $abm_editando = null;
    }
}

// Los valores del formulario: lo que vino por POST manda; si no, lo que
// se está editando; si no, vacío.
foreach ($abm_campos as $clave => $campo) {
    if (!array_key_exists($clave, $abm_valores)) {
        $abm_valores[$clave] = $abm_actual[$clave] ?? ($campo['tipo'] === 'casilla' ? false : '');
    }
}

/* La cabecera va recién acá, no arriba: el bloque de POST necesita poder
   redirigir, y un header() después del primer byte de HTML no sale. */
require RASTRO_VIEWS . '/admin/_cabecera.php';
?>

<?php if (!empty($abm['nota'])): ?>
    <p class="admin__conteo t-mono-texto"><?= e((string) $abm['nota']) ?></p>
<?php endif; ?>

<?php /* ============================================================
         Alta y edición
         ============================================================ */ ?>
<form class="form-admin form-admin--abm" method="post" action="<?= e($abm_ruta) ?>" data-avisar-cambios>
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="guardar">
    <?php if ($abm_editando !== null): ?>
        <input type="hidden" name="id" value="<?= e((string) $abm_editando) ?>">
    <?php endif; ?>

    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">
            <?= $abm_editando !== null ? 'Editar ' . e($abm_singular) : 'Agregar ' . e($abm_singular) ?>
        </legend>

        <?php if ($abm_errores !== []): ?>
            <p class="form-admin__error t-mono-texto">
                <span aria-hidden="true">!</span> No se guardó: revisá los campos marcados.
            </p>
        <?php endif; ?>

        <div class="form-admin__grilla">
            <?php foreach ($abm_campos as $clave => $campo): ?>
                <?php $error = $abm_errores[$clave] ?? null; ?>

                <?php if ($campo['tipo'] === 'casilla'): ?>
                    <div class="form-admin__campo form-admin__campo--ancho">
                        <label class="opcion-admin">
                            <input type="checkbox" name="<?= e($clave) ?>" value="1"
                                   <?= $abm_valores[$clave] ? 'checked' : '' ?>>
                            <span class="t-mono-texto"><?= e($campo['etiqueta']) ?></span>
                        </label>
                        <?php if (!empty($campo['ayuda'])): ?>
                            <span class="form-admin__ayuda t-mono-texto-sm"><?= e($campo['ayuda']) ?></span>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <p class="form-admin__campo <?= !empty($campo['ancho']) ? 'form-admin__campo--ancho' : '' ?>">
                        <label class="form-admin__etiqueta t-mono-label-sm" for="c-<?= e($clave) ?>">
                            <?= e($campo['etiqueta']) ?>
                        </label>

                        <?php if ($campo['tipo'] === 'select'): ?>
                            <select class="campo t-mono-texto <?= $error ? 'es-error' : '' ?>"
                                    id="c-<?= e($clave) ?>" name="<?= e($clave) ?>">
                                <?php foreach ((array) $campo['opciones'] as $valor => $etiqueta): ?>
                                    <option value="<?= e((string) $valor) ?>"
                                            <?= (string) $abm_valores[$clave] === (string) $valor ? 'selected' : '' ?>>
                                        <?= e((string) $etiqueta) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($campo['tipo'] === 'area'): ?>
                            <textarea class="campo campo--area t-mono-texto <?= $error ? 'es-error' : '' ?>"
                                      id="c-<?= e($clave) ?>" name="<?= e($clave) ?>"
                                      rows="3"><?= e((string) $abm_valores[$clave]) ?></textarea>

                        <?php else: ?>
                            <input class="campo t-mono-texto <?= $error ? 'es-error' : '' ?>"
                                   type="<?= $campo['tipo'] === 'numero' ? 'number' : 'text' ?>"
                                   id="c-<?= e($clave) ?>" name="<?= e($clave) ?>"
                                   value="<?= e((string) $abm_valores[$clave]) ?>"
                                   <?= !empty($campo['requerido']) ? 'required' : '' ?>
                                   <?= !empty($campo['placeholder']) ? 'placeholder="' . e($campo['placeholder']) . '"' : '' ?>>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($error) ?></span>
                        <?php elseif (!empty($campo['ayuda'])): ?>
                            <span class="form-admin__ayuda t-mono-texto-sm"><?= e($campo['ayuda']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="form-admin__acciones">
            <button class="boton boton--acento" type="submit">
                <span class="t-mono-label"><?= $abm_editando !== null ? 'Guardar cambios' : 'Agregar' ?></span>
                <span class="boton__flecha" aria-hidden="true">→</span>
            </button>

            <?php if ($abm_editando !== null): ?>
                <a class="boton boton--fantasma" href="<?= e($abm_ruta) ?>">
                    <span class="t-mono-label">Cancelar</span>
                </a>
            <?php endif; ?>
        </div>
    </fieldset>
</form>

<?php /* ============================================================
         La lista
         ============================================================ */ ?>
<?php if ($abm_items === []): ?>
    <div class="vacio-admin">
        <p class="t-display-s">Todavía no hay nada cargado</p>
        <p class="vacio-admin__texto t-body-md">Usá el formulario de arriba para agregar <?= e($abm_singular) ?>.</p>
    </div>
<?php else: ?>
    <div class="tabla-admin">
        <table>
            <thead>
                <tr>
                    <?php foreach ($abm_columnas as $clave): ?>
                        <th class="t-mono-label-sm" scope="col"><?= e($abm_campos[$clave]['etiqueta'] ?? $clave) ?></th>
                    <?php endforeach; ?>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">
                        <span class="visualmente-oculto">Acciones</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abm_items as $item): ?>
                    <?php $item_id = (int) ($item['id'] ?? 0); ?>
                    <tr class="<?= $item_id === $abm_editando ? 'es-editando' : '' ?>">
                        <?php foreach ($abm_columnas as $i => $clave): ?>
                            <?php
                            $valor = $item[$clave] ?? '';
                            $tipo  = $abm_campos[$clave]['tipo'] ?? 'texto';

                            if ($tipo === 'casilla') {
                                $valor = $valor ? 'Sí' : 'No';
                            } elseif ($tipo === 'select') {
                                $valor = $abm_campos[$clave]['opciones'][$valor] ?? $valor;
                            }
                            ?>
                            <?php if ($i === 0): ?>
                                <th class="tabla-admin__principal t-mono-texto" scope="row"><?= e((string) $valor) ?></th>
                            <?php else: ?>
                                <td class="t-mono-texto"><?= e((string) $valor) !== '' ? e((string) $valor) : '—' ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <td>
                            <div class="tabla-admin__acciones">
                                <a class="tabla-admin__accion t-mono-label-sm"
                                   href="<?= e($abm_ruta . '?editar=' . $item_id) ?>">Editar</a>

                                <form method="post" action="<?= e($abm_ruta) ?>"
                                      data-confirmar="¿Borrar «<?= e((string) ($item[$abm_columnas[0]] ?? '')) ?>»? No se puede deshacer.">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="borrar">
                                    <input type="hidden" name="id" value="<?= e((string) $item_id) ?>">
                                    <button class="tabla-admin__accion tabla-admin__accion--peligro t-mono-label-sm"
                                            type="submit">Borrar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
