<?php
/**
 * webhooks/mercadopago.php — la notificación de pago.
 *
 * ESTO NO ES UNA PÁGINA. Es un endpoint que llama Mercado Pago, desde sus
 * servidores, cada vez que un pago cambia de estado. No lo abre ninguna
 * persona, no imprime HTML y no incluye el layout. Está en views/ nada más
 * que porque el router del proyecto resuelve rutas a archivos de views/.
 *
 * POR QUÉ ES LA PIEZA QUE SOSTIENE TODO
 *
 * La página de retorno depende de que quien compró vuelva al sitio, y mucha
 * gente cierra la pestaña apenas ve "pago aprobado". Peor: un pago en
 * efectivo con cupón se acredita dos días después, cuando ya no hay ninguna
 * pestaña abierta. El webhook llega igual. Es el único camino por el que un
 * pedido se entera de que se pagó sin depender de nadie.
 *
 * LAS TRES REGLAS DE UN WEBHOOK
 *
 * 1. VALIDAR LA FIRMA ANTES DE CREER NADA.
 *    Esta URL es pública: cualquiera puede hacerle POST diciendo "el pago 123
 *    se aprobó". La firma HMAC es lo único que separa una notificación de
 *    Mercado Pago de una inventada. Se valida ANTES de tocar un pedido.
 *
 * 2. NO CREERLE AL CUERPO, NI SIQUIERA FIRMADO.
 *    La notificación trae un id y nada más. El estado del pago se consulta
 *    por API con el access token. El cuerpo dice QUÉ mirar, no QUÉ pasó.
 *
 * 3. CONTESTAR 200 RÁPIDO.
 *    Mercado Pago espera 22 segundos. Si no contestás 200 o 201, reintenta:
 *    enseguida, a los 15 minutos, a los 30, a las 6 horas, a las 48 y a las
 *    96. Todo lo que sea lento —mandar un mail, generar un PDF— va en una
 *    cola, no acá adentro.
 *
 * Como consecuencia de la 3, este archivo contesta 200 en casi todos los
 * casos, incluso cuando no hizo nada. Un 500 porque una notificación no nos
 * interesa haría que Mercado Pago la reintente ocho veces al pedo. Las únicas
 * excepciones son 401 —firma inválida, que sí queremos que quede registrada
 * de los dos lados— y 405 si alguien entra con el navegador.
 *
 * CÓMO PROBARLO
 *   docs/MERCADOPAGO.md §"Probar el webhook". El panel de Mercado Pago tiene
 *   un simulador que dispara una notificación firmada contra la URL.
 *
 * TODO(backend):
 *   · Mandar el mail de confirmación cuando el pago pasa a aprobado.
 *   · Descontar stock. Hoy el webhook cambia el estado del pedido y nada más.
 *   · Atender `merchant_order` además de `payment`, para cerrar el caso de
 *     alguien que reintenta y deja dos pagos colgando de una preferencia.
 */

declare(strict_types=1);

/* Nada de HTML: index.php ya mandó un Content-Type de página. Se pisa acá,
   antes de imprimir una sola letra. */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

/** Cierra el request con un código y una palabra. Nada de detalles: lo que
 *  se escriba acá lo lee cualquiera que golpee la URL. */
$responder = static function (int $codigo, string $palabra): never {
    http_response_code($codigo);
    echo $palabra;
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Alguien abrió la URL con el navegador. No es un error del sistema.
    $responder(405, 'metodo no permitido');
}

/* --- Lo que llegó --------------------------------------------------------- */

$crudo  = (string) file_get_contents('php://input');
$cuerpo = json_decode($crudo, true);
$cuerpo = is_array($cuerpo) ? $cuerpo : [];

$aviso = mp_leer_notificacion($_GET, $cuerpo);

$firma      = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
$request_id = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');

/* --- 1. ¿Nos interesa? ----------------------------------------------------

   Mercado Pago manda varios tipos por la misma URL. Hoy sólo se atiende
   `payment`; el resto se acepta con un 200 para que no reintente.

   VA ANTES DE VALIDAR LA FIRMA, y el orden importa. Los avisos de
   `merchant_order` se firman distinto que los de `payment`, así que nunca
   pasan esta validación: si se los mide con la misma vara, contestan 401 y
   Mercado Pago los reintenta durante horas. Se vio en el primer pago de
   prueba real —11/09/2026— con dos reintentos del mismo merchant_order
   desde IPs distintas.

   No afloja nada. Un tipo que no se atiende no lee ni escribe nada: se
   contesta "recibido" y se corta. La firma sigue siendo obligatoria para
   `payment`, que es el único camino que toca un pedido. */

if ($aviso['tipo'] !== 'payment' || $aviso['id'] === '') {
    repo_log_pago('webhook.ignorado', ['tipo' => $aviso['tipo'], 'id' => $aviso['id']]);

    $responder(200, 'ignorado');
}

/* --- 2. La firma ---------------------------------------------------------- */

if (!mp_firma_valida($firma, $request_id, $aviso['id'])) {
    /* Se registra, porque un webhook que empieza a recibir firmas inválidas
       es alguien probando la puerta, y eso se quiere ver en el log. No se
       guarda la firma entera: es material de un intento de ataque, no una
       credencial, pero tampoco hace falta tenerla en claro. */
    repo_log_pago('webhook.firma_invalida', [
        'tipo'   => $aviso['tipo'],
        'id'     => $aviso['id'],
        'origen' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'motivo' => mp_config()['webhook_secret'] === ''
            ? 'no hay mp_webhook_secret configurado'
            : 'la firma no coincide',
    ]);

    $responder(401, 'firma invalida');
}

/* --- 3. Qué pasó de verdad ------------------------------------------------ */

$respuesta = mp_obtener_pago($aviso['id']);

if (!$respuesta['ok']) {
    repo_log_pago('webhook.consulta_fallida', [
        'pago'   => $aviso['id'],
        'estado' => $respuesta['estado'],
        'error'  => $respuesta['error'],
    ]);

    /* Acá SÍ conviene un 500: la notificación era buena y no la pudimos
       procesar. El reintento de Mercado Pago es exactamente lo que queremos.
       El 404 es la excepción: un pago que no existe no va a existir en seis
       horas, y reintentarlo ocho veces no arregla nada. */
    $responder($respuesta['estado'] === 404 ? 200 : 500, 'reintentar');
}

$pago       = $respuesta['datos'];
$referencia = (string) ($pago['external_reference'] ?? '');
$pedido     = repo_order_by_reference($referencia);

if ($pedido === null) {
    /* Un pago de esta cuenta que no corresponde a ningún pedido nuestro. Pasa
       cuando se comparte la cuenta de Mercado Pago con otra integración, o
       cuando se prueba con credenciales de producción sobre pedidos que
       vivían en otro servidor. Se registra y se acepta: reintentar no lo va
       a hacer aparecer. */
    repo_log_pago('webhook.pedido_no_encontrado', [
        'pago'       => (string) ($pago['id'] ?? $aviso['id']),
        'referencia' => $referencia,
    ]);

    $responder(200, 'sin pedido');
}

/* --- 4. Guardar ----------------------------------------------------------- */

$resultado = checkout_sincronizar_pago($pedido, (string) ($pago['id'] ?? $aviso['id']));

repo_log_pago('webhook.procesado', [
    'pedido' => (string) $pedido['codigo'],
    'pago'   => (string) ($pago['id'] ?? $aviso['id']),
    'accion' => $aviso['accion'],
    'estado' => (string) ($pago['status'] ?? ''),
    'ok'     => $resultado['ok'],
]);

/* Si no se pudo guardar —el archivo de pedidos no era escribible, por
   ejemplo— se pide el reintento: la notificación era buena y el estado del
   pedido quedó viejo. */
$responder($resultado['ok'] ? 200 : 500, $resultado['ok'] ? 'ok' : 'reintentar');
