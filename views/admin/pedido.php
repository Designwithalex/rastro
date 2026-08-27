<?php
/**
 * admin/pedido.php — un pedido, con lo único que se puede tocar: el estado.
 *
 * Los importes se muestran tal como se guardaron el día de la compra y NO se
 * recalculan contra el catálogo de hoy. Es una regla del contrato de datos:
 * el precio de hoy no es el precio al que se vendió en abril, y un
 * comprobante que cambia cuando cambia una lista de precios no es un
 * comprobante.
 */

declare(strict_types=1);

panel_exigir_sesion();

$codigo  = (string) ($params['codigo'] ?? '');
$pedido  = repo_order($codigo);
$estados = panel_estados_pedido();

if ($pedido === null) {
    router_404();
}

if (panel_es_post()) {
    panel_exigir_csrf();

    $nuevo = panel_texto('estado');

    if (!isset($estados[$nuevo])) {
        panel_ir_con_aviso('/admin/pedidos/' . $codigo, 'error', 'Ese estado no existe.');
    }

    if ($nuevo === ($pedido['estado'] ?? '')) {
        panel_ir_con_aviso('/admin/pedidos/' . $codigo, 'ok', 'El pedido ya estaba en ese estado.');
    }

    if (repo_save_order_status($codigo, $nuevo)) {
        panel_ir_con_aviso(
            '/admin/pedidos/' . $codigo,
            'ok',
            'El pedido pasó a "' . $estados[$nuevo] . '".'
        );
    }

    panel_ir_con_aviso('/admin/pedidos/' . $codigo, 'error', 'No se pudo cambiar el estado.');
}

$cliente = repo_user((int) ($pedido['usuario_id'] ?? 0));

$titulo = 'Pedido ' . $codigo;
$bajada = sprintf(
    '%s · %s',
    (string) ($pedido['fecha'] ?? ''),
    ucfirst((string) ($pedido['medio_pago'] ?? 'sin medio de pago'))
);
$volver = ['texto' => 'Todos los pedidos', 'href' => url('/admin/pedidos')];

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<div class="panel-columnas">

    <div class="panel-columnas__principal">

        <section class="panel-grupo" aria-labelledby="items-titulo">
            <h2 class="panel-grupo__titulo" id="items-titulo">Qué se llevó</h2>

            <div class="panel-tabla-marco">
                <table class="panel-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="panel-tabla__num">Cantidad</th>
                            <th scope="col" class="panel-tabla__num">Unitario</th>
                            <th scope="col" class="panel-tabla__num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($pedido['items'] ?? []) as $item): ?>
                            <?php $cantidad = (int) ($item['cantidad'] ?? 0); ?>
                            <tr>
                                <th scope="row" class="panel-tabla__principal">
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                    <span class="panel-tabla__sku"><?= e((string) ($item['sku'] ?? '')) ?></span>
                                </th>
                                <td class="panel-tabla__num"><?= e((string) $cantidad) ?></td>
                                <td class="panel-tabla__num"><?= e(moneda($item['precio_unitario'] ?? 0)) ?></td>
                                <td class="panel-tabla__num">
                                    <?= e(moneda(((int) ($item['precio_unitario'] ?? 0)) * $cantidad)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <dl class="panel-totales">
                <div>
                    <dt>Subtotal</dt>
                    <dd><?= e(moneda($pedido['subtotal'] ?? 0)) ?></dd>
                </div>
                <div>
                    <dt>Envío</dt>
                    <dd><?= (int) ($pedido['envio'] ?? 0) === 0 ? 'Sin cargo' : e(moneda($pedido['envio'])) ?></dd>
                </div>
                <?php if ((float) ($pedido['descuento_aplicado_pct'] ?? 0) > 0): ?>
                    <div>
                        <dt>Descuento aplicado</dt>
                        <dd>−<?= e(porcentaje($pedido['descuento_aplicado_pct'])) ?>%</dd>
                    </div>
                <?php endif; ?>
                <div class="panel-totales__final">
                    <dt>Total</dt>
                    <dd><?= e(moneda($pedido['total'] ?? 0)) ?></dd>
                </div>
            </dl>

            <?php /* El porcentaje que se muestra es el que quedó guardado en
                     el pedido, no el de settings. Si el cliente sube el
                     descuento general mañana, este pedido tiene que seguir
                     diciendo lo que se cobró. */ ?>
            <p class="panel-nota">
                Los importes son los del día de la compra y no cambian aunque se
                actualice la lista de precios.
            </p>
        </section>
    </div>

    <aside class="panel-columnas__lateral">

        <form class="panel-grupo" method="post" action="<?= e(url('/admin/pedidos/' . $codigo)) ?>">
            <h2 class="panel-grupo__titulo">Estado</h2>

            <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

            <div class="campo-panel">
                <label class="campo-panel__rotulo" for="estado">En qué anda</label>
                <select class="campo-panel__control" id="estado" name="estado">
                    <?php foreach ($estados as $clave => $rotulo): ?>
                        <option value="<?= e($clave) ?>" <?= ($pedido['estado'] ?? '') === $clave ? 'selected' : '' ?>>
                            <?= e($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="campo-panel__ayuda">
                    Es lo único editable de un pedido.
                </p>
            </div>

            <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                Cambiar estado
            </button>
        </form>

        <section class="panel-grupo" aria-labelledby="cliente-titulo">
            <h2 class="panel-grupo__titulo" id="cliente-titulo">Quién compró</h2>

            <?php if ($cliente === null): ?>
                <p class="panel-vacio panel-vacio--chico">
                    El usuario de este pedido ya no está en la base.
                </p>
            <?php else: ?>
                <dl class="panel-datos">
                    <div>
                        <dt>Nombre</dt>
                        <dd><?= e(trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''))) ?></dd>
                    </div>
                    <div>
                        <dt>Mail</dt>
                        <dd><a class="panel-enlace" href="mailto:<?= e((string) ($cliente['email'] ?? '')) ?>">
                            <?= e((string) ($cliente['email'] ?? '')) ?>
                        </a></dd>
                    </div>
                    <?php if (!empty($cliente['telefono'])): ?>
                        <div>
                            <dt>Teléfono</dt>
                            <dd><?= e((string) $cliente['telefono']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($cliente['empresa'])): ?>
                        <div>
                            <dt>Empresa</dt>
                            <dd><?= e((string) $cliente['empresa']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($cliente['direccion']) && is_array($cliente['direccion'])): ?>
                        <div>
                            <dt>Envío</dt>
                            <dd>
                                <?= e((string) ($cliente['direccion']['calle'] ?? '')) ?><br>
                                <?= e((string) ($cliente['direccion']['ciudad'] ?? '')) ?>,
                                <?= e((string) ($cliente['direccion']['provincia'] ?? '')) ?>
                                <?= e((string) ($cliente['direccion']['codigo_postal'] ?? '')) ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
