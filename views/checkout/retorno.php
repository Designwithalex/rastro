<?php
/**
 * checkout/retorno.php — la página a la que se vuelve después de pagar.
 *
 * Es una sola página para los cuatro finales posibles: pago aprobado, pago
 * pendiente, pago rechazado y pedido por transferencia. Lo que cambia es el
 * texto; el trabajo es el mismo, porque en los tres primeros casos hay que ir
 * a preguntarle a la API en qué quedó la cosa.
 *
 * ============================================================================
 * LO MÁS IMPORTANTE DE ESTE ARCHIVO
 * ============================================================================
 *
 * EL ESTADO DEL PAGO NO SE LEE DE LA URL. NUNCA.
 *
 * Mercado Pago vuelve al sitio con ?status=approved&payment_id=123 colgando de
 * la dirección. Es cómodo y es exactamente lo que no hay que usar: esa URL la
 * escribe el navegador de quien compra, y cambiar `rejected` por `approved` en
 * la barra de direcciones es algo que sabe hacer cualquiera.
 *
 * Lo que se hace acá es tomar el `payment_id` de la URL —eso sí es un dato
 * útil, es un identificador— y preguntarle a la API de Mercado Pago, con el
 * access token que sólo tiene el servidor, en qué estado está ese pago. La
 * respuesta de la API es la única fuente de verdad.
 *
 * Y todavía falta un paso: se comprueba que el `external_reference` de ese
 * pago sea el del pedido que se está mirando. Sin esa comprobación, pegar en
 * la URL el id de un pago aprobado ajeno alcanzaría para marcar el pedido
 * propio como pagado. Eso lo hace checkout_sincronizar_pago().
 *
 * ADEMÁS, ESTA PÁGINA PUEDE NO EJECUTARSE NUNCA.
 *
 * Quien paga puede cerrar la pestaña antes de que Mercado Pago lo devuelva.
 * Por eso la confirmación de verdad es el webhook (views/webhooks/mercadopago.php),
 * que llega igual aunque no haya nadie mirando. Esta página es la cara visible
 * del mismo trabajo, no el mecanismo.
 *
 * Y al revés: el webhook puede llegar ANTES que el visitante. Las dos entradas
 * llaman a la misma función, que es idempotente a propósito.
 *
 * ============================================================================
 *
 * TODO(backend): el código del pedido viaja en la URL y no se valida contra
 * ninguna sesión, así que esta página se puede abrir con un código ajeno. Por
 * eso NO imprime la dirección de entrega, el teléfono ni el apellido: sólo el
 * código, las líneas y el estado. Cuando exista sesión hay que exigir que el
 * pedido sea del usuario logueado —o firmar el código— y ahí sí se puede
 * mostrar el detalle completo.
 */

declare(strict_types=1);

$codigo = param('pedido');
$pedido = $codigo !== '' ? repo_order_local($codigo) : null;

if ($pedido === null) {
    router_404();
}

$settings = repo_settings();

/* `estado` es lo que dice la URL y sólo sirve para elegir el título mientras
   se consulta la API. Para el pago manda $pago_real, más abajo. */
$estado_url = param('estado', 'pendiente', ['exito', 'pendiente', 'error', 'transferencia']);

/* Mercado Pago manda el id del pago en `payment_id` y, por compatibilidad con
   integraciones viejas, también en `collection_id`. Se acepta cualquiera de
   los dos y se descarta el literal "null", que es lo que llega cuando el pago
   ni se intentó. */
$payment_id = param('payment_id') !== '' ? param('payment_id') : param('collection_id');

if ($payment_id === 'null' || !preg_match('/^\d{1,20}$/', $payment_id)) {
    $payment_id = '';
}

/* Si no vino en la URL, puede ser que el webhook ya lo haya guardado: es el
   caso de quien cierra la pestaña y vuelve al rato desde el correo. */
if ($payment_id === '') {
    $payment_id = (string) ($pedido['pago']['payment_id'] ?? '');
}

$error_consulta = null;

if ($estado_url !== 'transferencia' && $payment_id !== '' && mp_activo()) {
    $sincronizado = checkout_sincronizar_pago($pedido, $payment_id);

    if ($sincronizado['pago'] !== null) {
        $pedido = $sincronizado['pedido'];
    } else {
        $error_consulta = $sincronizado['error'];
    }
}

$pago         = (array) ($pedido['pago'] ?? []);
$estado_pago  = (string) ($pago['estado'] ?? '');
$detalle_pago = (string) ($pago['detalle'] ?? '');
$estado_pedido = (string) ($pedido['estado'] ?? 'pendiente_pago');

/* --- Qué se le dice a la persona -----------------------------------------
   Se decide con el estado del PEDIDO, que ya se actualizó contra la API.
   Sólo si nunca hubo un pago que consultar se cae al estado de la URL. */
$situacion = match (true) {
    $estado_url === 'transferencia'      => 'transferencia',
    $estado_pedido === 'pagado'          => 'pagado',
    $estado_pedido === 'cancelado'       => 'rechazado',
    $estado_pedido === 'pendiente_pago'
        && $estado_pago !== ''           => 'pendiente',
    $estado_url === 'error'              => 'rechazado',
    default                              => 'sin_confirmar',
};

$textos = [
    'pagado' => [
        'indice'  => 'Pago aprobado',
        'titulo'  => 'Listo, recibimos tu pago',
        'bajada'  => 'Te mandamos el comprobante por correo. Nos comunicamos para coordinar la entrega.',
    ],
    'pendiente' => [
        'indice'  => 'Pago en proceso',
        'titulo'  => 'Estamos esperando la confirmación',
        'bajada'  => 'Mercado Pago todavía no confirmó el pago. Puede tardar unos minutos, o hasta dos días hábiles si elegiste efectivo. Te avisamos por correo apenas se acredite.',
    ],
    'rechazado' => [
        'indice'  => 'Pago rechazado',
        'titulo'  => 'El pago no se pudo completar',
        'bajada'  => 'No se hizo ningún cargo. Podés volver a intentar con otro medio o coordinar por transferencia.',
    ],
    'transferencia' => [
        'indice'  => 'Pedido registrado',
        'titulo'  => 'Anotamos tu pedido',
        'bajada'  => 'Escribinos por WhatsApp con el código del pedido y te pasamos los datos para transferir. El descuento ya está aplicado en el total.',
    ],
    'sin_confirmar' => [
        'indice'  => 'Pedido registrado',
        'titulo'  => 'Todavía no tenemos el pago',
        'bajada'  => 'El pedido quedó anotado pero no llegó ninguna confirmación de pago. Si ya pagaste, escribinos con el código y lo revisamos.',
    ],
];

$texto = $textos[$situacion];

$titulo      = $texto['titulo'];
$descripcion = 'Estado de tu pedido en Rastro Fitness.';
$clase_body  = 'pagina-checkout pagina-retorno';
$estilos     = ['componentes', 'catalogo', 'carrito', 'cuenta', 'checkout'];

/* El carrito se vacía SÓLO cuando el pago se aprobó o cuando el pedido quedó
   anotado para transferencia. Si el pago se rechazó, vaciar el carrito
   obligaría a cargar todo de nuevo para reintentar, que es la peor forma de
   perder una venta que ya estaba hecha. */
$vaciar_carrito = in_array($situacion, ['pagado', 'transferencia'], true);

$whatsapp = whatsapp_link(
    $settings,
    'Hola Rastro, te escribo por el pedido ' . (string) $pedido['codigo'] . '.'
);

$scripts = $vaciar_carrito ? ['checkout-retorno'] : [];

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1"
      class="retorno"
      <?= $vaciar_carrito ? 'data-vaciar-carrito' : '' ?>>

    <div class="contenedor">
        <div class="retorno__caja retorno--<?= e($situacion) ?>">

            <p class="retorno__indice t-mono-label">
                <span class="indice">01</span> <?= e($texto['indice']) ?>
            </p>

            <h1 class="retorno__titulo t-display-l"><?= e($texto['titulo']) ?></h1>

            <p class="retorno__bajada t-body-lg"><?= e($texto['bajada']) ?></p>

            <?php if ($situacion === 'rechazado' && $detalle_pago !== ''): ?>
                <?php /* El motivo concreto, no un "error" genérico: "no era el
                         código de seguridad" se arregla reintentando y
                         "fondos insuficientes" no. */ ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    <?= e(mp_motivo_rechazo($detalle_pago)) ?>
                </p>
            <?php endif; ?>

            <?php if ($error_consulta !== null): ?>
                <p class="mensaje mensaje--aviso t-mono-texto" role="status">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    No pudimos confirmar el estado del pago en este momento. El pedido
                    quedó registrado igual: si hubo un cargo, lo vas a ver reflejado.
                </p>
            <?php endif; ?>

            <dl class="retorno__datos">
                <div class="retorno__dato">
                    <dt class="t-mono-label-sm">Código de pedido</dt>
                    <dd class="t-mono-dato retorno__codigo"><?= e((string) $pedido['codigo']) ?></dd>
                </div>
                <div class="retorno__dato">
                    <dt class="t-mono-label-sm">Total</dt>
                    <dd class="plata t-precio-sm"><?= e(moneda((int) $pedido['total'])) ?></dd>
                </div>
                <div class="retorno__dato">
                    <dt class="t-mono-label-sm">Medio de pago</dt>
                    <dd class="t-mono-dato">
                        <?= e(checkout_medios()[(string) $pedido['medio_pago']]['nombre'] ?? '—') ?>
                    </dd>
                </div>
                <?php if ($pago['payment_id'] ?? '') : ?>
                    <div class="retorno__dato">
                        <dt class="t-mono-label-sm">Operación</dt>
                        <dd class="t-mono-dato"><?= e((string) $pago['payment_id']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <ul class="retorno__items">
                <?php foreach ((array) $pedido['items'] as $item): ?>
                    <li class="retorno__item">
                        <span class="t-body-sm"><?= e((string) $item['nombre']) ?></span>
                        <span class="t-mono-texto-sm">
                            <?= e((string) $item['cantidad']) ?> ×
                            <?= e(moneda((int) $item['precio_unitario'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="retorno__acciones">
                <?php if ($situacion === 'rechazado'): ?>
                    <a class="boton boton--acento" href="<?= e(url('/carrito')) ?>">
                        <span class="t-mono-label">Volver al carrito</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                <?php else: ?>
                    <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                        <span class="t-mono-label">Seguir comprando</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>

                <?php if ($whatsapp !== null): ?>
                    <a class="boton boton--fantasma" href="<?= e($whatsapp) ?>"
                       rel="noopener" target="_blank">
                        <span class="t-mono-label">
                            <?= $situacion === 'transferencia' ? 'Pedir los datos para transferir' : 'Escribirnos por WhatsApp' ?>
                        </span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (mp_es_prueba() && $pedido['medio_pago'] === 'mercado_pago'): ?>
                <p class="retorno__prueba t-mono-texto-sm">
                    Ambiente de prueba · no se cobró dinero real.
                </p>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
