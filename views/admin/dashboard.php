<?php
/**
 * admin/dashboard.php — la portada del panel.
 *
 * Sigue el frame "Admin · Dashboard".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=80-43
 *
 * Cuatro cifras y los últimos pedidos. Nada más, a propósito: es la
 * pantalla que se mira de reojo diez veces por día, y todo lo que se
 * agregue acá compite con lo que de verdad importa —cuánto se vendió y
 * qué pedidos hay sin procesar—.
 *
 * "Sin stock" se destaca porque es la única de las cuatro que pide una
 * acción; las otras tres son informativas.
 */

declare(strict_types=1);

$admin_titulo  = 'Dashboard';
$admin_seccion = 'dashboard';

require RASTRO_VIEWS . '/admin/_cabecera.php';

$metricas = repo_admin_metricas();
$ultimos  = repo_admin_pedidos([], 1, 5);
$estados  = repo_estados_pedido();
?>

<div class="cifras-admin">

    <div class="cifra-admin">
        <p class="cifra-admin__rotulo t-mono-label-sm">Ventas del mes</p>
        <p class="cifra-admin__valor t-precio-lg"><?= e(moneda($metricas['ventas_mes'])) ?></p>
        <p class="cifra-admin__pie t-mono-texto-sm">
            <?= e((string) $metricas['pedidos_mes']) ?>
            <?= $metricas['pedidos_mes'] === 1 ? 'pedido' : 'pedidos' ?>, sin contar cancelados
        </p>
    </div>

    <div class="cifra-admin<?= $metricas['pedidos_nuevos'] > 0 ? ' cifra-admin--atencion' : '' ?>">
        <p class="cifra-admin__rotulo t-mono-label-sm">Pedidos nuevos</p>
        <p class="cifra-admin__valor t-precio-lg"><?= e((string) $metricas['pedidos_nuevos']) ?></p>
        <p class="cifra-admin__pie t-mono-texto-sm">
            <?php if ($metricas['pedidos_nuevos'] > 0): ?>
                <a href="<?= e(url('/admin/pedidos?estado=pendiente')) ?>">sin procesar</a>
            <?php else: ?>
                todo procesado
            <?php endif; ?>
        </p>
    </div>

    <div class="cifra-admin">
        <p class="cifra-admin__rotulo t-mono-label-sm">Productos activos</p>
        <p class="cifra-admin__valor t-precio-lg"><?= e((string) $metricas['productos_activos']) ?></p>
        <p class="cifra-admin__pie t-mono-texto-sm">de <?= e((string) $metricas['productos_total']) ?> cargados</p>
    </div>

    <div class="cifra-admin<?= $metricas['sin_stock'] > 0 ? ' cifra-admin--atencion' : '' ?>">
        <p class="cifra-admin__rotulo t-mono-label-sm">Sin stock</p>
        <p class="cifra-admin__valor t-precio-lg"><?= e((string) $metricas['sin_stock']) ?></p>
        <p class="cifra-admin__pie t-mono-texto-sm">
            <?php if ($metricas['sin_stock'] > 0): ?>
                <a href="<?= e(url('/admin/productos?estado=sin_stock')) ?>">revisar</a>
            <?php else: ?>
                todo con stock
            <?php endif; ?>
        </p>
    </div>
</div>

<h2 class="admin__seccion t-display-m">Últimos pedidos</h2>

<?php if ($ultimos['items'] === []): ?>
    <div class="vacio-admin">
        <p class="t-display-s">Todavía no hay pedidos</p>
        <p class="vacio-admin__texto t-body-md">
            Cuando entre el primero va a aparecer acá, y en la sección Pedidos con
            su detalle completo.
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
                    <th class="t-mono-label-sm" scope="col">Estado</th>
                    <th class="t-mono-label-sm tabla-admin__num" scope="col">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimos['items'] as $pedido): ?>
                    <tr>
                        <th class="tabla-admin__principal t-mono-texto" scope="row">
                            <a href="<?= e(url('/admin/pedidos/' . $pedido['codigo'])) ?>">
                                #<?= e($pedido['codigo']) ?>
                            </a>
                        </th>
                        <td class="t-mono-texto"><?= e($pedido['cliente']) ?></td>
                        <td class="t-mono-texto">
                            <time datetime="<?= e($pedido['fecha']) ?>">
                                <?= e(date('d/m/Y', strtotime($pedido['fecha']))) ?>
                            </time>
                        </td>
                        <td>
                            <span class="estado-admin estado-admin--<?= e($pedido['estado']) ?> t-mono-label-sm">
                                <?= e($estados[$pedido['estado']] ?? $pedido['estado']) ?>
                            </span>
                        </td>
                        <td class="tabla-admin__num t-precio-xs"><?= e(moneda($pedido['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
