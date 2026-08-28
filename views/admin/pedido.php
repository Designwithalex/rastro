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
$pedido  = repo_any_order($codigo);
$estados = panel_estados_pedido();
$medios  = checkout_medios();

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

/* Quién compró sale de dos lados distintos según de dónde venga el pedido.
   Los del mock apuntan a un usuario con `usuario_id`; los del checkout
   guardan los datos del comprador ADENTRO del pedido, porque hoy se puede
   comprar sin cuenta y `usuario_id` queda en 0. Se normalizan acá a la misma
   forma para que la pantalla dibuje una sola cosa. */
$es_del_sitio = ($pedido['origen'] ?? '') === 'sitio';

if ($es_del_sitio) {
    $comprador = (array) ($pedido['comprador'] ?? []);
    $entrega   = (array) ($pedido['entrega'] ?? []);

    $cliente = $comprador === [] ? null : [
        'nombre'    => (string) ($comprador['nombre'] ?? ''),
        'apellido'  => (string) ($comprador['apellido'] ?? ''),
        'email'     => (string) ($comprador['email'] ?? ''),
        'telefono'  => (string) ($comprador['telefono'] ?? ''),
        'empresa'   => null,
        'direccion' => $entrega === [] ? null : [
            'calle'         => (string) ($entrega['calle'] ?? ''),
            'ciudad'        => (string) ($entrega['localidad'] ?? ''),
            'provincia'     => (string) ($entrega['provincia'] ?? ''),
            'codigo_postal' => (string) ($entrega['codigo_postal'] ?? ''),
        ],
    ];
} else {
    $cliente = repo_user((int) ($pedido['usuario_id'] ?? 0));
}

$titulo = 'Pedido ' . $codigo;
$bajada = sprintf(
    '%s · %s',
    (string) ($pedido['fecha'] ?? ''),
    $medios[$pedido['medio_pago'] ?? '']['nombre'] ?? 'sin medio de pago'
);
$volver = ['texto' => 'Todos los pedidos', 'href' => url('/admin/pedidos')];

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if (!$es_del_sitio): ?>
    <p class="panel-nota">
        <strong>Es un pedido de ejemplo</strong>, de los tres que vienen cargados para
        que la pantalla tenga algo que mostrar. No es una venta: no lo despaches.
    </p>
<?php endif; ?>

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

        <?php /* ============================================================
                 El pago, tal como lo contestó Mercado Pago.

                 Sólo existe en los pedidos que creó el checkout, y sólo
                 después de que vuelva la notificación. Es lo primero que se
                 mira cuando alguien escribe "pagué y no me llegó nada": el
                 `payment_id` es lo que se busca en el panel de Mercado Pago.
                 ============================================================ */ ?>
        <?php if (!empty($pedido['pago']) && is_array($pedido['pago'])): ?>
            <?php $pago = $pedido['pago']; ?>
            <section class="panel-grupo" aria-labelledby="pago-titulo">
                <h2 class="panel-grupo__titulo" id="pago-titulo">El pago</h2>

                <dl class="panel-datos">
                    <div>
                        <dt>Número</dt>
                        <dd><code><?= e((string) ($pago['payment_id'] ?? '—')) ?></code></dd>
                    </div>
                    <div>
                        <dt>Dice Mercado Pago</dt>
                        <dd><?= e((string) ($pago['estado'] ?? '—')) ?></dd>
                    </div>
                    <?php if (!empty($pago['detalle'])): ?>
                        <div>
                            <dt>Detalle</dt>
                            <dd><?= e(mp_motivo_rechazo((string) $pago['detalle'])) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pago['metodo'])): ?>
                        <div>
                            <dt>Medio</dt>
                            <dd>
                                <?= e((string) $pago['metodo']) ?>
                                <?php if ((int) ($pago['cuotas'] ?? 0) > 1): ?>
                                    · <?= e((string) (int) $pago['cuotas']) ?> cuotas
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pago['monto'])): ?>
                        <div>
                            <dt>Cobrado</dt>
                            <dd><?= e(moneda($pago['monto'])) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php /* El monto que cobró Mercado Pago tiene que coincidir
                         con el total del pedido. Si no coincide, algo se
                         tocó entre que se armó el pedido y se pagó, y es lo
                         primero que hay que mirar antes de despachar. */ ?>
                <?php if (!empty($pago['monto']) && (int) round((float) $pago['monto']) !== (int) ($pedido['total'] ?? 0)): ?>
                    <p class="panel-aviso panel-aviso--error" role="alert">
                        <span class="panel-aviso__icono" aria-hidden="true">!</span>
                        Lo cobrado no coincide con el total del pedido. Revisalo antes de despachar.
                    </p>
                <?php endif; ?>
            </section>
        <?php elseif ($es_del_sitio): ?>
            <section class="panel-grupo">
                <h2 class="panel-grupo__titulo">El pago</h2>
                <p class="panel-vacio panel-vacio--chico">
                    Todavía no llegó la confirmación de Mercado Pago.
                </p>
            </section>
        <?php endif; ?>

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
