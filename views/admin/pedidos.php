<?php
/**
 * admin/pedidos.php — la lista de pedidos.
 *
 * Los pedidos no se crean ni se borran desde el panel: los crea el checkout
 * y son el comprobante de una operación. Acá sólo se miran y se les cambia
 * el estado. Un pedido editable después de cobrado no sirve como respaldo
 * de nada, ni para el cliente ni para Rastro.
 *
 * LA LISTA MEZCLA DOS ORÍGENES. `repo_all_orders()` junta las compras que
 * escribe el checkout con las tres del mock, y cada fila dice de cuál viene.
 * Sin esa marca, los pedidos de ejemplo se leen como ventas y alguien va a
 * intentar despachar uno.
 */

declare(strict_types=1);

panel_exigir_sesion();

$pedidos = repo_all_orders();
$estados = panel_estados_pedido();

/* Los nombres de los medios de pago salen de `checkout_medios()`, que es
   donde el checkout los define. Escribirlos de nuevo acá haría que el día
   que entre "efectivo en el local" el panel siga mostrando la clave cruda. */
$medios = checkout_medios();

$filtro_estado = param('estado', '', array_keys($estados));

$visibles = $filtro_estado === ''
    ? $pedidos
    : array_values(array_filter(
        $pedidos,
        static fn (array $p): bool => ($p['estado'] ?? '') === $filtro_estado
    ));

/* Cuántos hay de cada estado, para los números de las pestañas. Se cuenta
   sobre la lista completa y no sobre la filtrada, si no todas las pestañas
   mostrarían el conteo de la que está abierta. */
$conteo = [];
foreach ($pedidos as $pedido) {
    $clave = (string) ($pedido['estado'] ?? '');
    $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
}

$titulo = 'Pedidos';
$bajada = sprintf('%d en total', count($pedidos));

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<nav class="panel-pestanas" aria-label="Filtrar por estado">
    <a class="panel-pestana<?= $filtro_estado === '' ? ' es-activa' : '' ?>"
       href="<?= e(url('/admin/pedidos')) ?>"
       <?= $filtro_estado === '' ? 'aria-current="true"' : '' ?>>
        Todos <span class="panel-pestana__cuenta"><?= e((string) count($pedidos)) ?></span>
    </a>

    <?php foreach ($estados as $clave => $rotulo): ?>
        <?php if (($conteo[$clave] ?? 0) === 0) { continue; } ?>
        <a class="panel-pestana<?= $filtro_estado === $clave ? ' es-activa' : '' ?>"
           href="<?= e(url('/admin/pedidos?estado=' . $clave)) ?>"
           <?= $filtro_estado === $clave ? 'aria-current="true"' : '' ?>>
            <?= e($rotulo) ?>
            <span class="panel-pestana__cuenta"><?= e((string) $conteo[$clave]) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($visibles === []): ?>

    <p class="panel-vacio">
        <?= $pedidos === []
            ? 'Todavía no entró ningún pedido. Van a aparecer acá apenas el checkout esté conectado.'
            : 'No hay pedidos en ese estado.' ?>
    </p>

<?php else: ?>

    <div class="panel-tabla-marco">
        <table class="panel-tabla">
            <thead>
                <tr>
                    <th scope="col">Código</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Pago</th>
                    <th scope="col">Origen</th>
                    <th scope="col" class="panel-tabla__num">Artículos</th>
                    <th scope="col" class="panel-tabla__num">Total</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visibles as $pedido): ?>
                    <?php
                    $codigo   = (string) ($pedido['codigo'] ?? '');
                    $articulos = array_sum(array_map(
                        static fn (array $i): int => (int) ($i['cantidad'] ?? 0),
                        (array) ($pedido['items'] ?? [])
                    ));
                    ?>
                    <tr>
                        <th scope="row">
                            <a class="panel-enlace" href="<?= e(url('/admin/pedidos/' . $codigo)) ?>">
                                <?= e($codigo) ?>
                            </a>
                        </th>
                        <td><?= e((string) ($pedido['fecha'] ?? '')) ?></td>
                        <td>
                            <?= e($medios[$pedido['medio_pago'] ?? '']['nombre']
                                  ?? (string) ($pedido['medio_pago'] ?? '—')) ?>
                        </td>
                        <td>
                            <?php if (($pedido['origen'] ?? '') === 'mock'): ?>
                                <span class="panel-pastilla panel-pastilla--mock">Ejemplo</span>
                            <?php else: ?>
                                <span class="panel-pastilla">Venta</span>
                            <?php endif; ?>
                        </td>
                        <td class="panel-tabla__num"><?= e((string) $articulos) ?></td>
                        <td class="panel-tabla__num"><?= e(moneda($pedido['total'] ?? 0)) ?></td>
                        <td>
                            <span class="panel-pastilla panel-pastilla--<?= e((string) ($pedido['estado'] ?? '')) ?>">
                                <?= e($estados[$pedido['estado'] ?? ''] ?? (string) ($pedido['estado'] ?? '')) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
