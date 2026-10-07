<?php
/**
 * pedido/detalle.php — un pedido, para quien lo hizo.
 *
 * Una sola pantalla para quien tiene cuenta y para quien compró sin cuenta.
 * Lo que cambia es cómo se demuestra que el pedido es suyo:
 *
 *   · con sesión: el pedido tiene que ser de ese usuario (repo_user_order()).
 *   · sin sesión, o un pedido hecho sin cuenta: este navegador tiene que
 *     haberlo desbloqueado, comprándolo o consultándolo en /pedido con el
 *     código y el correo (sesion_pedido_desbloqueado()).
 *
 * Si no se cumple ninguna de las dos, se manda a /pedido con el código ya
 * escrito: falta el correo. NO se muestra un 404 ni un "no es tuyo": la
 * respuesta es la misma exista o no el pedido, y así esta URL no sirve para
 * averiguar qué códigos existen.
 *
 * Muestra la dirección y el teléfono, que la pantalla de retorno del
 * checkout no muestra porque esa sí se abre con el código solo.
 */

declare(strict_types=1);

$codigo  = strtoupper((string) ($params['codigo'] ?? ''));
$usuario = sesion_usuario();
$pedido  = null;

if ($usuario !== null) {
    $pedido = repo_user_order($codigo, (int) $usuario['id']);
}

if ($pedido === null && sesion_pedido_desbloqueado($codigo)) {
    $pedido = repo_order_local($codigo);
}

if ($pedido === null) {
    header('Location: ' . url('/pedido') . '?codigo=' . rawurlencode($codigo), true, 303);

    exit;
}

$settings = repo_settings();
$estados  = estados_pedido();
$medios   = checkout_medios();

$clave_estado = (string) ($pedido['estado'] ?? '');
$estado       = $estados[$clave_estado] ?? ['texto' => $clave_estado, 'clase' => ''];
$medio        = $medios[(string) ($pedido['medio_pago'] ?? '')]['nombre'] ?? null;

$comprador = is_array($pedido['comprador'] ?? null) ? $pedido['comprador'] : [];
$entrega   = is_array($pedido['entrega'] ?? null) ? $pedido['entrega'] : [];

/* Los pedidos del mock no tienen bloque `entrega`, tienen la dirección de
   la cuenta. Se cae a esa para que la pantalla no quede vacía en la demo. */
if ($entrega === [] && $usuario !== null && is_array($usuario['direccion'] ?? null)) {
    $entrega = [
        'calle'         => $usuario['direccion']['calle'] ?? '',
        'localidad'     => $usuario['direccion']['ciudad'] ?? '',
        'provincia'     => $usuario['direccion']['provincia'] ?? '',
        'codigo_postal' => $usuario['direccion']['codigo_postal'] ?? '',
    ];
}

$direccion = implode(', ', array_filter([
    (string) ($entrega['calle'] ?? ''),
    (string) ($entrega['localidad'] ?? ''),
    trim((string) ($entrega['provincia'] ?? '') . ' ' . (string) ($entrega['codigo_postal'] ?? '')),
], static fn (string $v): bool => $v !== ''));

$whatsapp = whatsapp_link(
    $settings,
    'Hola Rastro, te escribo por el pedido ' . (string) $pedido['codigo'] . '.'
);

$fecha = strtotime((string) ($pedido['fecha'] ?? '')) ?: null;

$titulo      = 'Pedido ' . (string) $pedido['codigo'];
$descripcion = 'Estado y detalle de tu pedido.';
$clase_body  = 'pagina-pedido';
// checkout.css por .mensaje--aviso, que vive ahí.
$estilos     = ['componentes', 'catalogo', 'carrito', 'cuenta', 'checkout'];

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1">

    <?php if ($usuario !== null): ?>
        <div class="contenedor pedido__volver">
            <a class="lineas__seguir t-mono-label" href="<?= e(url('/cuenta')) ?>">
                <span aria-hidden="true">←</span> Mis pedidos
            </a>
        </div>
    <?php endif; ?>

    <div class="contenedor barra-pagina barra-pagina--carrito">
        <div class="barra-pagina__titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <div class="barra-pagina__linea">
                <h1 class="barra-pagina__nombre t-display-l">Pedido <?= e((string) $pedido['codigo']) ?></h1>
                <p class="barra-pagina__conteo t-mono-texto">
                    <span class="chip-estado <?= e($estado['clase']) ?> t-mono-label-sm"><?= e($estado['texto']) ?></span>
                </p>
            </div>
        </div>
    </div>

    <div class="pedido reticula">

        <section class="pedido__principal" aria-labelledby="pedido-items">
            <h2 class="pedido__titulo t-mono-label" id="pedido-items">Qué compraste</h2>

            <div class="tabla-envoltorio" tabindex="0">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th class="t-mono-label-sm" scope="col">Producto</th>
                            <th class="t-mono-label-sm tabla__num" scope="col">Cantidad</th>
                            <th class="t-mono-label-sm tabla__num" scope="col">Unitario</th>
                            <th class="t-mono-label-sm tabla__num" scope="col">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($pedido['items'] ?? []) as $item): ?>
                            <tr>
                                <th class="t-body-sm" scope="row">
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                    <span class="pedido__sku t-mono-texto-sm"><?= e((string) ($item['sku'] ?? '')) ?></span>
                                </th>
                                <td class="t-mono-texto tabla__num"><?= e((string) (int) ($item['cantidad'] ?? 0)) ?></td>
                                <td class="t-mono-texto tabla__num"><?= e(moneda($item['precio_unitario'] ?? 0)) ?></td>
                                <td class="t-mono-texto tabla__num">
                                    <?= e(moneda((int) ($item['precio_unitario'] ?? 0) * (int) ($item['cantidad'] ?? 0))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <dl class="resumen__filas pedido__totales">
                <div class="resumen__fila">
                    <dt class="t-mono-texto">Subtotal</dt>
                    <dd class="t-mono-texto"><?= e(moneda($pedido['subtotal'] ?? 0)) ?></dd>
                </div>
                <div class="resumen__fila">
                    <dt class="t-mono-texto">Envío</dt>
                    <dd class="t-mono-texto">
                        <?= (int) ($pedido['envio'] ?? 0) > 0 ? e(moneda($pedido['envio'])) : 'Sin cargo' ?>
                    </dd>
                </div>
                <?php if ((float) ($pedido['descuento_aplicado_pct'] ?? 0) > 0): ?>
                    <div class="resumen__fila">
                        <dt class="t-mono-texto">Descuento transferencia</dt>
                        <dd class="t-mono-texto resumen__ahorro"><?= e(porcentaje((float) $pedido['descuento_aplicado_pct'])) ?>%</dd>
                    </div>
                <?php endif; ?>
            </dl>

            <div class="resumen__total">
                <p class="resumen__publicado">
                    <span class="t-mono-label-sm">Total</span>
                    <span class="plata t-precio-lg"><?= e(moneda($pedido['total'] ?? 0)) ?></span>
                </p>
                <p class="resumen__nota t-mono-texto-sm">
                    Los importes son los del día de la compra.
                </p>
            </div>
        </section>

        <aside class="pedido__lateral" aria-label="Datos del pedido">
            <h2 class="pedido__titulo t-mono-label">El pedido</h2>

            <dl class="pedido__datos">
                <div class="pedido__dato">
                    <dt class="t-mono-label-sm">Fecha</dt>
                    <dd class="t-mono-texto">
                        <?php if ($fecha !== null): ?>
                            <time datetime="<?= e(date('Y-m-d', $fecha)) ?>"><?= e(date('d/m/Y', $fecha)) ?></time>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if ($medio !== null): ?>
                    <div class="pedido__dato">
                        <dt class="t-mono-label-sm">Pago</dt>
                        <dd class="t-mono-texto"><?= e($medio) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pedido['pago']['payment_id'])): ?>
                    <div class="pedido__dato">
                        <dt class="t-mono-label-sm">Operación</dt>
                        <dd class="t-mono-texto"><?= e((string) $pedido['pago']['payment_id']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($comprador !== []): ?>
                    <div class="pedido__dato">
                        <dt class="t-mono-label-sm">A nombre de</dt>
                        <dd class="t-mono-texto">
                            <?= e(trim((string) ($comprador['nombre'] ?? '') . ' ' . (string) ($comprador['apellido'] ?? ''))) ?>
                        </dd>
                    </div>
                <?php endif; ?>
                <?php if ($direccion !== ''): ?>
                    <div class="pedido__dato">
                        <dt class="t-mono-label-sm">Entrega</dt>
                        <dd class="t-mono-texto"><?= e($direccion) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($entrega['notas'])): ?>
                    <div class="pedido__dato">
                        <dt class="t-mono-label-sm">Aclaraciones</dt>
                        <dd class="t-mono-texto"><?= e((string) $entrega['notas']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($clave_estado === 'pendiente_transferencia'): ?>
                <p class="mensaje mensaje--aviso t-mono-texto" role="status">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    Esperamos tu transferencia. Escribinos con el código del pedido y te
                    pasamos los datos.
                </p>
            <?php endif; ?>

            <?php if ($whatsapp !== null): ?>
                <a class="boton boton--fantasma pedido__contacto" href="<?= e($whatsapp) ?>"
                   rel="noopener" target="_blank">
                    <span class="t-mono-label">Consultar por este pedido</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                    <span class="visualmente-oculto">(abre WhatsApp en una pestaña nueva)</span>
                </a>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
