<?php
/**
 * admin/pedidos.php — el listado de pedidos.
 *
 * Sigue el frame "Admin · Pedidos · listado".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=83-135
 *
 * El estado se cambia desde acá sin abrir el detalle: procesar la tanda
 * del día es marcar cinco pedidos como enviados, y obligar a entrar y
 * salir de cada uno para eso son diez clics de más por pedido.
 */

declare(strict_types=1);

$admin_titulo  = 'Pedidos';
$admin_seccion = 'pedidos';

require RASTRO_VIEWS . '/admin/_guard.php';

$estados = repo_estados_pedido();

/* --- Cambio de estado ------------------------------------------------ */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $resultado = repo_pedido_estado(
        (string) ($_POST['codigo'] ?? ''),
        (string) ($_POST['estado'] ?? '')
    );

    admin_avisar(
        $resultado['ok'] ? 'Estado actualizado.' : (string) $resultado['error'],
        $resultado['ok'] ? 'ok' : 'error'
    );

    header('Location: ' . url('/admin/pedidos') . (($_POST['volver'] ?? '') !== '' ? '?' . $_POST['volver'] : ''), true, 303);
    exit;
}

/* --- Datos ----------------------------------------------------------- */

$filtros = [
    'q'      => param('q'),
    'estado' => param('estado', '', array_keys($estados)),
];

$pagina  = param_int('pagina', 1, 1) ?? 1;
$listado = repo_admin_pedidos($filtros, $pagina, 25);
$volver  = http_build_query(array_filter($filtros + ['pagina' => $pagina > 1 ? $pagina : null]));

/* La cabecera va recién acá, no arriba: el bloque de POST necesita poder
   redirigir, y un header() después del primer byte de HTML no sale. */
require RASTRO_VIEWS . '/admin/_cabecera.php';
?>

<form class="filtros-admin" method="get" action="<?= e(url('/admin/pedidos')) ?>">
    <p class="form-admin__campo">
        <label class="form-admin__etiqueta t-mono-label-sm" for="q">Buscar</label>
        <input class="campo t-mono-texto" type="search" id="q" name="q"
               value="<?= e($filtros['q']) ?>" placeholder="Código, nombre o correo">
    </p>

    <p class="form-admin__campo">
        <label class="form-admin__etiqueta t-mono-label-sm" for="estado">Estado</label>
        <select class="campo t-mono-texto" id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $valor => $etiqueta): ?>
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
    <?= $listado['total'] === 1 ? 'pedido' : 'pedidos' ?>
    <?php if (array_filter($filtros) !== []): ?>
        · <a href="<?= e(url('/admin/pedidos')) ?>">limpiar filtros</a>
    <?php endif; ?>
</p>

<?php if ($listado['items'] === []): ?>
    <div class="vacio-admin">
        <p class="t-display-s">No hay pedidos con esos filtros</p>
        <p class="vacio-admin__texto t-body-md">
            Cuando entre un pedido nuevo va a aparecer acá, y en el dashboard.
        </p>
    </div>
<?php else: ?>
    <div class="tabla-admin">
        <table>
            <thead>
                <tr>
                    <th class="t-mono-label-sm" scope="col">Pedido</th>
                    <th class="t-mono-label-sm" scope="col">Cliente</th>
                    <th class="t-mono-label-sm" scope="col">Fecha</th>
                    <th class="t-mono-label-sm" scope="col">Pago</th>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">Total</th>
                    <th class="t-mono-label-sm" scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listado['items'] as $pedido): ?>
                    <tr>
                        <th class="tabla-admin__principal t-mono-texto" scope="row">
                            <a href="<?= e(url('/admin/pedidos/' . $pedido['codigo'])) ?>">#<?= e($pedido['codigo']) ?></a>
                        </th>

                        <td class="t-mono-texto">
                            <?= e($pedido['cliente']) ?>
                            <?php if ($pedido['cliente_email'] !== ''): ?>
                                <span class="tabla-admin__sku t-mono-texto-sm"><?= e($pedido['cliente_email']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="t-mono-texto">
                            <time datetime="<?= e($pedido['fecha']) ?>">
                                <?= e(date('d/m/Y', strtotime($pedido['fecha']))) ?>
                            </time>
                        </td>

                        <td class="t-mono-texto">
                            <?= e($pedido['medio_pago'] === 'transferencia' ? 'Transferencia' : ($pedido['medio_pago'] === 'mercado_pago' ? 'Mercado Pago' : '—')) ?>
                        </td>

                        <td class="tabla-admin__num t-precio-xs"><?= e(moneda($pedido['total'])) ?></td>

                        <td>
                            <?php /* El select cambia el estado sin salir de la
                                     lista. El botón "Cambiar" existe porque sin
                                     JavaScript un select no envía nada solo. */ ?>
                            <form class="estado-form" method="post" action="<?= e(url('/admin/pedidos')) ?>">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="codigo" value="<?= e($pedido['codigo']) ?>">
                                <input type="hidden" name="volver" value="<?= e($volver) ?>">

                                <label class="visualmente-oculto" for="estado-<?= e($pedido['codigo']) ?>">
                                    Estado del pedido <?= e($pedido['codigo']) ?>
                                </label>
                                <select class="campo t-mono-texto" id="estado-<?= e($pedido['codigo']) ?>" name="estado">
                                    <?php foreach ($estados as $valor => $etiqueta): ?>
                                        <option value="<?= e($valor) ?>" <?= $pedido['estado'] === $valor ? 'selected' : '' ?>>
                                            <?= e($etiqueta) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="tabla-admin__accion t-mono-label-sm" type="submit">Cambiar</button>
                            </form>
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
                       href="<?= e(url('/admin/pedidos') . ($qs !== '' ? '?' . $qs : '')) ?>"><?= e((string) $p) ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
