<?php
/**
 * admin/importar.php — precios y productos desde una planilla.
 *
 * Pedidos del cliente (22/10):
 *   · "Actualizar precios en masa subiendo un Excel con SKU, precio de lista
 *     y mayorista (la lista cambia cada 15 días)."
 *   · "Importar productos desde una planilla (tenemos más de 450)."
 *
 * Las dos cosas son la misma pantalla: se sube un .xlsx o .csv y se cruza
 * por SKU. Con "Sólo precios" se tocan precio de lista y mayorista y nada
 * más; con "Productos" se crean los SKU nuevos y se actualizan las columnas
 * que vengan con dato en los existentes.
 *
 * SIEMPRE SE PREVISUALIZA PRIMERO
 *
 * Un Excel con la columna corrida pone el precio mayorista en el stock de
 * 450 productos. Por eso el primer envío no guarda nada: muestra qué va a
 * cambiar, y recién con la casilla "aplicar" se escribe.
 */

declare(strict_types=1);

panel_exigir_sesion();

require_once dirname(__DIR__, 2) . '/app/planilla.php';

$tipo      = panel_es_post() ? (panel_texto('tipo') === 'productos' ? 'productos' : 'precios') : 'precios';
$resultado = null;
$error     = null;

if (panel_es_post()) {
    panel_exigir_csrf();

    $archivo = $_FILES['planilla'] ?? null;

    if (!is_array($archivo) || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) $archivo['tmp_name'])) {
        $error = 'Elegí la planilla (.xlsx o .csv) para subir.';
    } else {
        $leida = planilla_leer((string) $archivo['tmp_name'], (string) $archivo['name']);

        if ($leida['error'] !== null) {
            $error = $leida['error'];
        } elseif (!in_array('sku', $leida['encabezados'], true) && !in_array('codigo', $leida['encabezados'], true)) {
            $error = 'La planilla necesita una columna SKU (o "Código"). Encontré: '
                   . implode(', ', $leida['encabezados']) . '.';
        } else {
            $resultado = importar_analizar($leida['filas'], $tipo);

            if (panel_booleano('aplicar') && $resultado['cambios'] !== []) {
                $ok = repo_save_products(array_column($resultado['cambios'], 'fila'));

                panel_ir_con_aviso(
                    '/admin/importar',
                    $ok ? 'ok' : 'error',
                    $ok
                        ? sprintf('Listo: %d productos actualizados y %d nuevos.', $resultado['actualizados'], $resultado['nuevos'])
                        : 'No se pudo guardar. No se cambió nada.'
                );
            }
        }
    }
}

/**
 * Cruza las filas de la planilla con el catálogo y arma la lista de cambios.
 *
 * @return array{cambios:list<array>, avisos:list<string>, actualizados:int, nuevos:int, sin_cambios:int}
 */
function importar_analizar(array $filas, string $tipo): array
{
    $por_sku = [];
    $slugs   = [];
    foreach (repo_all_products() as $p) {
        $por_sku[mb_strtoupper(trim((string) ($p['sku'] ?? '')))] = $p;
        $slugs[(string) ($p['slug'] ?? '')] = true;
    }

    $categorias = [];
    foreach (repo_categories() as $c) {
        $categorias[(string) $c['slug']] = (string) $c['slug'];
        $categorias[slug((string) ($c['nombre'] ?? ''))] = (string) $c['slug'];
    }

    $marcas = [];
    foreach (repo_brands(true) as $m) {
        $marcas[(string) $m['slug']] = (string) $m['slug'];
        $marcas[slug((string) ($m['nombre'] ?? ''))] = (string) $m['slug'];
    }

    $cambios = [];
    $avisos  = [];
    $actualizados = $nuevos = $sin_cambios = 0;

    foreach ($filas as $n => $f) {
        $linea = $n + 2; // +1 por el encabezado, +1 porque Excel cuenta desde 1
        $sku   = mb_strtoupper(trim((string) planilla_valor($f, ['sku', 'codigo', 'cod'])));

        if ($sku === '') {
            $avisos[] = "Fila $linea: sin SKU, se saltea.";
            continue;
        }

        $actual = $por_sku[$sku] ?? null;
        $datos  = [];

        $precio = planilla_pesos(planilla_valor($f, ['precio_de_lista', 'precio_lista', 'lista', 'precio', 'pvp', 'precio_minorista']));
        $mayor  = planilla_pesos(planilla_valor($f, ['precio_mayorista', 'mayorista', 'precio_mayor']));

        if ($precio !== null) {
            $datos['precio_lista'] = $precio;
        }
        if ($mayor !== null) {
            $datos['precio_mayorista'] = $mayor;
        }

        if ($tipo === 'productos') {
            $texto = static fn (array $nombres): ?string => planilla_valor($f, $nombres);

            if (($v = $texto(['nombre', 'producto', 'titulo'])) !== null) {
                $datos['nombre'] = $v;
            }
            if (($v = $texto(['descripcion_corta', 'bajada', 'resumen'])) !== null) {
                $datos['descripcion_corta'] = $v;
            }
            if (($v = $texto(['descripcion', 'detalle'])) !== null) {
                $datos['descripcion'] = $v;
            }
            if (($v = $texto(['stock', 'cantidad'])) !== null) {
                $datos['stock'] = max(0, (int) planilla_pesos($v));
            }
            if (($v = $texto(['peso_kg', 'peso', 'kg'])) !== null) {
                $datos['peso_kg'] = (float) str_replace(',', '.', (string) preg_replace('/[^\d.,]/', '', $v));
            }
            if (($v = $texto(['imagen', 'foto'])) !== null) {
                $datos['imagen'] = ltrim(preg_replace('#^/?assets/#', '', $v) ?? $v, '/');
            }
            if (($v = $texto(['grupo', 'variante_grupo', 'familia'])) !== null) {
                $datos['variante_grupo'] = slug($v);
            }
            if (($v = $texto(['variante', 'variante_nombre', 'medida'])) !== null) {
                $datos['variante_nombre'] = $v;
            }
            if (($v = $texto(['activo', 'publicado', 'visible'])) !== null) {
                $datos['activo'] = !in_array(mb_strtolower($v), ['no', '0', 'falso', 'false', 'n'], true);
            }
            if (($v = $texto(['categoria', 'rubro'])) !== null) {
                if (isset($categorias[slug($v)])) {
                    $datos['categoria'] = $categorias[slug($v)];
                } else {
                    $avisos[] = "Fila $linea ($sku): la categoría «{$v}» no existe. Creala en Categorías y volvé a subir.";
                }
            }
            if (($v = $texto(['marca'])) !== null) {
                if (isset($marcas[slug($v)])) {
                    $datos['marca'] = $marcas[slug($v)];
                } else {
                    $avisos[] = "Fila $linea ($sku): la marca «{$v}» no existe. Creala en Marcas y volvé a subir.";
                }
            }
        }

        if ($actual === null) {
            if ($tipo === 'precios') {
                $avisos[] = "Fila $linea: el SKU $sku no está en el catálogo. En «Sólo precios» no se crean productos.";
                continue;
            }

            if (empty($datos['nombre'])) {
                $avisos[] = "Fila $linea: el SKU $sku es nuevo y no tiene nombre, se saltea.";
                continue;
            }

            $base = slug($datos['nombre']);
            $slug = isset($slugs[$base]) ? $base . '-' . slug($sku) : $base;
            $slugs[$slug] = true;

            $fila = $datos + [
                'slug' => $slug, 'sku' => $sku, 'categoria' => '', 'marca' => '',
                'descripcion_corta' => '', 'descripcion' => '', 'precio_lista' => 0,
                'precio_mayorista' => 0, 'descuento_pct' => null, 'stock' => 0,
                'destacado' => false, 'nuevo' => false, 'imagen' => '', 'imagenes' => [],
                'especificaciones' => [], 'peso_kg' => null, 'activo' => true,
            ];

            if ($fila['imagen'] !== '') {
                $fila['imagenes'] = [$fila['imagen']];
            }

            $cambios[] = ['tipo' => 'nuevo', 'sku' => $sku, 'nombre' => $fila['nombre'], 'detalle' => importar_resumen([], $fila), 'fila' => $fila];
            $por_sku[$sku] = $fila;
            $nuevos++;
            continue;
        }

        // Sólo lo que cambia de verdad: subir la misma lista dos veces no
        // tiene que listar 450 "cambios".
        $diferencias = array_filter(
            $datos,
            static fn ($v, $k): bool => ($actual[$k] ?? null) != $v,
            ARRAY_FILTER_USE_BOTH
        );

        if ($diferencias === []) {
            $sin_cambios++;
            continue;
        }

        $cambios[] = [
            'tipo'    => 'actualiza',
            'sku'     => $sku,
            'nombre'  => (string) ($actual['nombre'] ?? ''),
            'detalle' => importar_resumen($actual, $diferencias),
            'fila'    => ['id' => (int) $actual['id']] + $diferencias,
        ];
        $actualizados++;
    }

    return compact('cambios', 'avisos', 'actualizados', 'nuevos', 'sin_cambios');
}

/** "Precio: $80.000 → $82.500 · Stock: 3 → 10". */
function importar_resumen(array $antes, array $cambios): string
{
    $rotulos = [
        'precio_lista' => 'Precio', 'precio_mayorista' => 'Mayorista', 'stock' => 'Stock',
        'nombre' => 'Nombre', 'categoria' => 'Categoría', 'marca' => 'Marca', 'peso_kg' => 'Peso',
        'activo' => 'Publicado', 'variante_grupo' => 'Grupo', 'variante_nombre' => 'Variante',
        'imagen' => 'Foto', 'descripcion_corta' => 'Bajada', 'descripcion' => 'Descripción',
    ];

    $partes = [];
    foreach ($rotulos as $clave => $rotulo) {
        if (!array_key_exists($clave, $cambios)) {
            continue;
        }

        $ver = static function ($v) use ($clave): string {
            if (in_array($clave, ['precio_lista', 'precio_mayorista'], true)) {
                return moneda((int) $v);
            }
            if (is_bool($v)) {
                return $v ? 'sí' : 'no';
            }
            $v = (string) $v;
            return mb_strlen($v) > 40 ? mb_substr($v, 0, 40) . '…' : ($v === '' ? '—' : $v);
        };

        $partes[] = $antes === [] || !array_key_exists($clave, $antes)
            ? $rotulo . ': ' . $ver($cambios[$clave])
            : $rotulo . ': ' . $ver($antes[$clave]) . ' → ' . $ver($cambios[$clave]);
    }

    return implode(' · ', $partes);
}

$titulo = 'Importar';
$bajada = 'Precios y productos desde una planilla de Excel.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($error !== null): ?>
    <p class="panel-aviso panel-aviso--error" role="alert">
        <span class="panel-aviso__icono" aria-hidden="true">!</span>
        <?= e($error) ?>
    </p>
<?php endif; ?>

<form class="panel-formulario" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/importar')) ?>">
    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

    <div class="panel-columnas">
        <div class="panel-columnas__principal">
            <fieldset class="panel-grupo panel-grupo--destacado">
                <legend class="panel-grupo__titulo">Qué querés hacer</legend>

                <label class="campo-panel__casilla">
                    <input type="radio" name="tipo" value="precios" <?= $tipo === 'precios' ? 'checked' : '' ?>>
                    Sólo precios — la lista de cada 15 días
                </label>
                <p class="campo-panel__ayuda">
                    Columnas: <code>SKU</code>, <code>Precio de lista</code> y, si querés, <code>Mayorista</code>.
                    Toca esos dos números y nada más. Los SKU que no estén en el catálogo se avisan y se saltean.
                </p>

                <label class="campo-panel__casilla">
                    <input type="radio" name="tipo" value="productos" <?= $tipo === 'productos' ? 'checked' : '' ?>>
                    Productos — crear los nuevos y actualizar los que ya están
                </label>
                <p class="campo-panel__ayuda">
                    Columnas: <code>SKU</code> (obligatoria), <code>Nombre</code>, <code>Categoría</code>,
                    <code>Marca</code>, <code>Precio de lista</code>, <code>Mayorista</code>, <code>Stock</code>,
                    <code>Peso</code>, <code>Bajada</code>, <code>Descripción</code>, <code>Foto</code>,
                    <code>Publicado</code> (sí/no), <code>Grupo</code> y <code>Variante</code>.
                    Una celda vacía no borra nada: deja el dato que ya tenía.
                    Para que varias mancuernas salgan como un solo producto con selector de peso,
                    poneles el mismo <code>Grupo</code> (ej. «mancuerna hexagonal») y en
                    <code>Variante</code> el peso (ej. «2,5 kg»).
                </p>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">La planilla</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="planilla">Archivo .xlsx o .csv</label>
                    <input class="campo-panel__control" type="file" id="planilla" name="planilla"
                           accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                    <p class="campo-panel__ayuda">Se lee la primera hoja. La primera fila son los títulos de las columnas.</p>
                </div>

                <label class="campo-panel__casilla">
                    <input type="checkbox" name="aplicar" value="1">
                    Aplicar los cambios (sin tildar, sólo muestra qué va a cambiar)
                </label>
            </fieldset>
        </div>

        <aside class="panel-columnas__lateral">
            <div class="panel-acciones panel-acciones--pegada">
                <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                    Subir planilla
                </button>
                <p class="panel-nota">
                    Primero subila sin tildar «Aplicar» y revisá la lista. Si está bien, volvé a subirla tildada.
                </p>
            </div>
        </aside>
    </div>
</form>

<?php if ($resultado !== null): ?>
    <section class="panel-bloque" aria-labelledby="previa-titulo">
        <div class="panel-seccion__encabezado">
            <div>
                <h2 class="panel-seccion__titulo" id="previa-titulo">Vista previa — todavía no se guardó nada</h2>
                <p class="panel-seccion__bajada">
                    <?= e(sprintf(
                        '%d para actualizar · %d nuevos · %d sin cambios · %d avisos',
                        $resultado['actualizados'],
                        $resultado['nuevos'],
                        $resultado['sin_cambios'],
                        count($resultado['avisos'])
                    )) ?>
                </p>
            </div>
        </div>

        <?php if ($resultado['avisos'] !== []): ?>
            <ul class="panel-lista-avisos">
                <?php foreach ($resultado['avisos'] as $aviso): ?>
                    <li class="campo-panel__error"><?= e($aviso) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($resultado['cambios'] !== []): ?>
            <table class="panel-tabla">
                <thead>
                    <tr><th scope="col">SKU</th><th scope="col">Producto</th><th scope="col">Qué cambia</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($resultado['cambios'] as $c): ?>
                        <tr>
                            <td><code><?= e($c['sku']) ?></code><?= $c['tipo'] === 'nuevo' ? ' <span class="panel-pastilla">Nuevo</span>' : '' ?></td>
                            <td><?= e($c['nombre']) ?></td>
                            <td><?= e($c['detalle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
