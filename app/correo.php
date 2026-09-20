<?php
/**
 * correo.php — mandar un mail desde el sitio.
 *
 * Una sola función y una capa finita sobre `mail()`. No hay SMTP, no hay
 * librería, no hay Composer: el proyecto no tiene build step (CLAUDE.md §3)
 * y hoy el sitio manda exactamente dos mails, los dos del mismo trámite.
 *
 * ---------------------------------------------------------------------
 * MANDAR UN MAIL PUEDE FALLAR, Y ACÁ FALLAR IMPORTA
 *
 * `mail()` devuelve true cuando el servidor ACEPTÓ el mensaje, no cuando
 * llegó. Puede rebotar después, puede caer en spam, puede que el hosting
 * no tenga el dominio configurado. Por eso `correo_enviar()` devuelve un
 * booleano y quien la llama TIENE que mirarlo: en el arrepentimiento, el
 * mail es la notificación, pero la constancia es el archivo. Si el mail
 * falla, el trámite igual existe y la persona tiene su número.
 *
 * DE QUÉ DIRECCIÓN SALE
 *
 * Del propio dominio del sitio, no del correo de contacto. Un servidor
 * que dice ser `gmail.com` sin serlo termina en spam o directamente
 * rechazado: el remitente tiene que ser un dominio que este servidor esté
 * autorizado a usar. El correo de contacto va en Reply-To, que es lo que
 * el cliente abre cuando aprieta "responder".
 *
 * TODO(backend): cuando haya volumen —confirmaciones de compra, avisos de
 * envío— esto pasa a una cola y a un proveedor con reputación (Resend,
 * Postmark, SES). `mail()` en un hosting compartido no sostiene eso.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

/**
 * Manda un mail de texto plano. Devuelve true si el servidor lo aceptó.
 *
 * @param string $para     Destinatario.
 * @param string $asunto   Sin saltos de línea.
 * @param string $cuerpo   Texto plano.
 * @param string $responder Dirección para Reply-To. Opcional.
 */
function correo_enviar(
    string $para,
    string $asunto,
    string $cuerpo,
    string $responder = '',
    ?string &$error = null
): bool {
    $error = null;
    $para  = trim($para);

    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        $error = 'el destinatario no es una dirección válida';
        error_log('correo: destinatario inválido: ' . $para);

        return false;
    }

    /* Inyección de cabeceras: un salto de línea en el asunto o en el
       Reply-To deja meter cabeceras propias —un Bcc a media agenda, por
       ejemplo—. Los dos campos salen de un formulario público, así que se
       limpian antes de tocar la cabecera. */
    $asunto = trim(str_replace(["\r", "\n"], ' ', $asunto));

    $remitente = correo_remitente();

    $cabeceras = [
        'From: Rastro Fitness <' . $remitente . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: rastro',
    ];

    $responder = trim(str_replace(["\r", "\n"], '', $responder));

    if ($responder !== '' && filter_var($responder, FILTER_VALIDATE_EMAIL)) {
        $cabeceras[] = 'Reply-To: ' . $responder;
    }

    /* El asunto se codifica porque lleva acentos: sin esto llega con los
       caracteres rotos en varios clientes. El cuerpo no lo necesita, va
       declarado como UTF-8 de 8 bits en las cabeceras. */
    $asunto_mime = '=?UTF-8?B?' . base64_encode($asunto) . '?=';

    // Las líneas de un mail se cortan a 70: más largo, algunos servidores lo parten mal.
    $cuerpo = wordwrap(str_replace("\r\n", "\n", $cuerpo), 70, "\n", false);

    /* Con SMTP configurado el mail sale de una casilla real y autenticada, y
       por eso pasa las verificaciones de Gmail. Sin configurar, `mail()`:
       entrega al servidor del hosting, que manda desde un dominio que nadie
       lo autorizó a usar. Ver app/smtp.php. */
    if (smtp_activo()) {
        return smtp_enviar($para, $asunto_mime, $cuerpo, $cabeceras, $remitente, $error);
    }

    $ok = @mail($para, $asunto_mime, $cuerpo, implode("\r\n", $cabeceras), '-f' . $remitente);

    if (!$ok) {
        $error = 'mail() del servidor rechazó el envío';
        error_log('correo: mail() falló al enviar a ' . $para . ' · asunto: ' . $asunto);
    }

    return (bool) $ok;
}

/**
 * La dirección desde la que sale el correo, derivada de `base_url`.
 *
 * Se deriva y no se escribe a mano para que el día que cambie el dominio
 * —ya pasó una vez— el remitente no quede apuntando al anterior y los
 * mails empiecen a rebotar sin que nadie entienda por qué.
 */
function correo_remitente(): string
{
    /* Con SMTP, el remitente es la casilla con la que nos autenticamos y no
       un no-reply@ inventado. La mayoría de los proveedores —Hostinger entre
       ellos— rechazan un MAIL FROM que no coincida con el usuario que
       inició sesión, y los que lo aceptan suelen marcarlo como sospechoso.
       Mandar como quien realmente sos es lo que hace que el mail llegue. */
    $usuario = trim((string) config('smtp_user', ''));

    if ($usuario !== '' && filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
        return $usuario;
    }

    $host = (string) parse_url((string) config('base_url', ''), PHP_URL_HOST);
    $host = preg_replace('/^www\./i', '', $host);

    return $host !== '' ? 'no-reply@' . $host : 'no-reply@localhost';
}
