<?php
/**
 * admin/pedido-detalle.php — la ficha de un pedido.
 *
 * Sigue el frame "Admin · Pedido · detalle".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=83-294
 *
 * LOS IMPORTES NO SE RECALCULAN. Cada línea muestra el `precio_unitario`
 * con el que se vendió y el pedido su `descuento_aplicado_pct`, no los de
 * hoy. Un pedido de abril con un producto que en agosto subió de precio
 * tiene que seguir diciendo lo que se cobró en abril: es el comprobante
 * de una operación, no una vista del catálogo.
 *
 * Por eso también el nombre y el SKU salen de `pedido_items` y no de la
 * tabla de productos, aunque el producto todavía exista.
 */

declare(strict_types=1);

$admin_seccion = 'pedidos';

require RASTRO_VIEWS . '/admin/_guard.php';

$codigo = (string) ($params['codigo'] ?? '');
$pedido = repo_admin_pedido($codigo);

if ($pedido === null) {
    router_404();
}

$estados = repo_estados_pedido();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $resultado = repo_pedido_estado($codigo, (string) ($_POST['estado'] ?? ''));

    admin_avisar(
        $resultado['ok'] ? 'Estado actualizado.' : (string) $resultado['error'],
        $resultado['ok'] ? 'ok' : 'error'
    );

    header('Location: ' . url('/admin/pedidos/' . $codigo), true, 303);
    exit;
}

$admin_titulo = 'Pedido #' . $pedido['codigo'];

require RASTRO_VIEWS . '/admin/_cabecera.php';

/* El subtotal se recompone sumando las líneas tal como se guardaron, y se
   compara con el que quedó grabado en el pedido. Si no coinciden, algo se
   guardó mal en su momento y hay que verlo, no taparlo. */
$suma_lineas = 0;
foreach ($pedido['items'] as $item) {
    $suma_lineas += $item['precio_unitario'] * $item['cantidad'];
}

$descuadre = $suma_lineas !== $pedido['subtotal'];
?>

<div class="pedido">

    <div class="pedido__principal">

        <?php if ($descuadre): ?>
            <p class="admin-aviso admin-aviso--error t-mono-texto" role="note">
                <span class="admin-aviso__marca" aria-hidden="true">!</span>
                El subtotal guardado (<?= e(moneda($pedido['subtotal'])) ?>) no coincide con la suma
                de las líneas (<?= e(moneda($suma_lineas)) ?>). No se corrige solo a propósito:
                es un comprobante y hay que revisar qué pasó.
            </p>
        <?php endif; ?>

        <h2 class="admin__seccion t-display-m">Qué se compró</h2>

        <div class="tabla-admin">
            <table>
                <thead>
                    <tr>
                        <th class="t-mono-label-sm" scope="col">Producto</th>
                        <th class="t-mono-label-sm tabla-admin__num" scope="col">Cantidad</th>
                        <th class="t-mono-label-sm tabla-admin__num" scope="col">Unitario</th>
                        <th class="t-mono-label-sm tabla-admin__num" scope="col">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedido['items'] as $item): ?>
                        <tr>
                            <th class="tabla-admin__principal t-mono-texto" scope="row">
                                <?= e($item['nombre']) ?>
                                <?php if ($item['sku'] !== ''): ?>
                                    <span class="tabla-admin__sku t-mono-texto-sm"><?= e($item['sku']) ?></span>
                                <?php endif; ?>
                            </th>
                            <td class="tabla-admin__num t-mono-texto"><?= e((string) $item['cantidad']) ?></td>
                            <td class="tabla-admin__num t-mono-texto"><?= e(moneda($item['precio_unitario'])) ?></td>
                            <td class="tabla-admin__num t-mono-texto">
                                <?= e(moneda($item['precio_unitario'] * $item['cantidad'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <dl class="pedido__totales">
            <div class="pedido__total">
                <dt class="t-mono-texto">Subtotal</dt>
                <dd class="t-mono-texto"><?= e(moneda($pedido['subtotal'])) ?></dd>
            </div>
            <div class="pedido__total">
                <dt class="t-mono-texto">Envío</dt>
                <dd class="t-mono-texto">
                    <?= $pedido['envio'] > 0 ? e(moneda($pedido['envio'])) : 'Sin cargo' ?>
                </dd>
            </div>
            <?php if ($pedido['descuento_aplicado_pct'] > 0): ?>
                <div class="pedido__total">
                    <dt class="t-mono-texto">
                        Descuento aplicado
                        <span class="form-admin__ayuda t-mono-texto-sm">
                            el del día de la compra, no el de hoy
                        </span>
                    </dt>
                    <dd class="t-mono-texto"><?= e(porcentaje($pedido['descuento_aplicado_pct'])) ?>%</dd>
                </div>
            <?php endif; ?>
            <div class="pedido__total pedido__total--final">
                <dt class="t-display-s">Total</dt>
                <dd class="t-precio-md"><?= e(moneda($pedido['total'])) ?></dd>
            </div>
        </dl>
    </div>

    <aside class="pedido__lado">

        <div class="form-admin__bloque">
            <h2 class="form-admin__leyenda t-display-s">Estado</h2>

            <form method="post" action="<?= e(url('/admin/pedidos/' . $pedido['codigo'])) ?>">
                <?= csrf_campo() ?>

                <p class="form-admin__campo">
                    <label class="form-admin__etiqueta t-mono-label-sm" for="estado">Estado del pedido</label>
                    <select class="campo t-mono-texto" id="estado" name="estado">
                        <?php foreach ($estados as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= $pedido['estado'] === $valor ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <button class="boton boton--acento" type="submit">
                    <span class="t-mono-label">Actualizar</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>
            </form>
        </div>

        <div class="form-admin__bloque">
            <h2 class="form-admin__leyenda t-display-s">Datos</h2>

            <dl class="pedido__datos">
                <div>
                    <dt class="t-mono-label-sm">Fecha</dt>
                    <dd class="t-mono-texto">
                        <time datetime="<?= e($pedido['fecha']) ?>"><?= e(date('d/m/Y', strtotime($pedido['fecha']))) ?></time>
                    </dd>
                </div>
                <div>
                    <dt class="t-mono-label-sm">Medio de pago</dt>
                    <dd class="t-mono-texto">
                        <?= e($pedido['medio_pago'] === 'transferencia' ? 'Transferencia o efectivo'
                             : ($pedido['medio_pago'] === 'mercado_pago' ? 'Mercado Pago' : '—')) ?>
                    </dd>
                </div>

                <?php if ($pedido['cliente'] !== null): ?>
                    <div>
                        <dt class="t-mono-label-sm">Cliente</dt>
                        <dd class="t-mono-texto">
                            <?= e($pedido['cliente']['nombre'] . ' ' . $pedido['cliente']['apellido']) ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="t-mono-label-sm">Correo</dt>
                        <dd class="t-mono-texto">
                            <a href="mailto:<?= e($pedido['cliente']['email']) ?>"><?= e($pedido['cliente']['email']) ?></a>
                        </dd>
                    </div>
                    <?php if (!empty($pedido['cliente']['telefono'])): ?>
                        <div>
                            <dt class="t-mono-label-sm">Teléfono</dt>
                            <dd class="t-mono-texto"><?= e($pedido['cliente']['telefono']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['cliente']['direccion'])): ?>
                        <div>
                            <dt class="t-mono-label-sm">Dirección</dt>
                            <dd class="t-mono-texto">
                                <?= e(implode(', ', array_filter([
                                    $pedido['cliente']['direccion']['calle'] ?? null,
                                    $pedido['cliente']['direccion']['ciudad'] ?? null,
                                    $pedido['cliente']['direccion']['provincia'] ?? null,
                                    $pedido['cliente']['direccion']['codigo_postal'] ?? null,
                                ]))) ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div>
                        <dt class="t-mono-label-sm">Cliente</dt>
                        <dd class="t-mono-texto">
                            <?php /* El pedido sobrevive al borrado de la cuenta:
                                     la relación es ON DELETE SET NULL. */ ?>
                            <span class="marcador t-mono-label-sm">[ cuenta eliminada ]</span>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <a class="tabla-admin__accion t-mono-label-sm" href="<?= e(url('/admin/pedidos')) ?>">← Volver a pedidos</a>
    </aside>
</div>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
