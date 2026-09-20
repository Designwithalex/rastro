<?php
/**
 * ============================================================================
 * smtp.php — mandar un mail por SMTP autenticado, sin librerías.
 * ============================================================================
 *
 * POR QUÉ EXISTE
 *
 * `mail()` entrega el mensaje al servidor del hosting, que lo manda desde
 * `no-reply@rastrofitness.com` sin que nadie lo haya autorizado a usar ese
 * dominio. Gmail y Outlook miran el SPF del dominio, no encuentran a ese
 * servidor en la lista, y el mail termina en spam o directamente rechazado.
 * Por eso el enlace de recuperación de contraseña no llegaba.
 *
 * Con SMTP el mail sale de una casilla real, con su usuario y su clave. El
 * proveedor que la emite ya está autorizado a mandar por ese dominio, así
 * que el mensaje pasa las verificaciones.
 *
 * CÓMO SE PRENDE
 *
 * Con `smtp_host` cargado en `app/config.php`. Vacío, el sitio sigue usando
 * `mail()` como hasta ahora. Es el mismo interruptor que la base de datos:
 * una credencial presente o ausente, nada más que acordarse de cambiar.
 *
 * NO HAY COMPOSER
 *
 * El proyecto no tiene build step (CLAUDE.md §3), así que esto habla el
 * protocolo a mano sobre un socket. Son ~200 líneas y cubren lo que el sitio
 * necesita: texto plano, un destinatario, STARTTLS o TLS directo, AUTH LOGIN
 * o PLAIN. No cubre adjuntos ni HTML, y no tiene por qué: el sitio manda
 * tres mails y los tres son texto.
 */

declare(strict_types=1);

/** Cuánto se espera cada respuesta del servidor, en segundos. */
const SMTP_TIMEOUT = 15;

/**
 * ¿Hay un SMTP configurado?
 *
 * Como `db_activa()`: mira la configuración y no se conecta a nada.
 */
function smtp_activo(): bool
{
    return trim((string) config('smtp_host', '')) !== '';
}

/**
 * Manda un mail por SMTP. Devuelve true si el servidor lo aceptó.
 *
 * `$error` recibe el motivo cuando devuelve false, para que la prueba de
 * correo del panel pueda mostrar algo más útil que "no se pudo".
 *
 * @param list<string> $cabeceras Cabeceras ya armadas, sin el From.
 */
function smtp_enviar(
    string $para,
    string $asunto_mime,
    string $cuerpo,
    array $cabeceras,
    string $remitente,
    ?string &$error = null
): bool {
    $host   = trim((string) config('smtp_host', ''));
    $puerto = (int) config('smtp_port', 587);
    $user   = (string) config('smtp_user', '');
    $clave  = (string) config('smtp_pass', '');

    /* 465 es TLS desde el primer byte (SMTPS); 587 arranca en claro y sube a
       TLS con STARTTLS. Es la diferencia que más cuesta encontrar cuando un
       envío falla, así que se deduce del puerto en vez de pedir otro ajuste
       que alguien va a dejar mal. */
    $tls_directo = (string) config('smtp_seguridad', '') === 'tls'
        || $puerto === 465;

    $destino = ($tls_directo ? 'ssl://' : '') . $host . ':' . $puerto;

    /* El certificado del servidor se verifica siempre: son los valores por
       defecto de PHP y no se tocan. `smtp_ca` existe para el caso en que el
       proveedor use una autoridad que no está en el bundle del sistema —pasa
       en servidores de correo internos—, y es un archivo que se suma a las
       de confianza, no un permiso para saltear la verificación. */
    $contexto = stream_context_create();
    $ca       = trim((string) config('smtp_ca', ''));

    if ($ca !== '' && is_file($ca)) {
        stream_context_set_option($contexto, 'ssl', 'cafile', $ca);
    }

    $socket = @stream_socket_client(
        $destino,
        $errno,
        $errstr,
        SMTP_TIMEOUT,
        STREAM_CLIENT_CONNECT,
        $contexto
    );

    if ($socket === false) {
        $error = sprintf('no se pudo conectar a %s (%s)', $destino, $errstr !== '' ? $errstr : "error $errno");
        error_log('smtp: ' . $error);

        return false;
    }

    stream_set_timeout($socket, SMTP_TIMEOUT);

    try {
        // El saludo del servidor llega solo, antes de que mandemos nada.
        _smtp_esperar($socket, [220]);

        $saludo = (string) (parse_url((string) config('base_url', ''), PHP_URL_HOST) ?: 'localhost');

        _smtp_decir($socket, 'EHLO ' . $saludo);
        $capacidades = _smtp_esperar($socket, [250]);

        /* TLS hace falta cuando hay una clave que proteger. Sin usuario no
           hay nada secreto que mandar, y exigirlo igual rompería el caso
           legítimo de un relay en la misma máquina —127.0.0.1, sin
           credenciales— que es justo donde TLS no aporta nada.

           Con usuario, en cambio, no se negocia: AUTH manda la clave en
           base64, que no es cifrado. Si el servidor no ofrece STARTTLS se
           corta antes de decir nada, porque es preferible no mandar el mail
           a mandar la clave en claro por la red. */
        $hay_secreto = $user !== '';

        if (!$tls_directo && $hay_secreto) {
            if (stripos($capacidades, 'STARTTLS') === false) {
                throw new RuntimeException(
                    'el servidor no ofrece STARTTLS y la clave viajaría en claro'
                );
            }

            _smtp_decir($socket, 'STARTTLS');
            _smtp_esperar($socket, [220]);

            $ok = @stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($ok !== true) {
                throw new RuntimeException('falló el handshake TLS');
            }

            // Después de STARTTLS hay que volver a saludar: lo de antes no cuenta.
            _smtp_decir($socket, 'EHLO ' . $saludo);
            $capacidades = _smtp_esperar($socket, [250]);
        }

        if ($user !== '') {
            _smtp_autenticar($socket, $capacidades, $user, $clave);
        }

        _smtp_decir($socket, 'MAIL FROM:<' . $remitente . '>');
        _smtp_esperar($socket, [250]);

        _smtp_decir($socket, 'RCPT TO:<' . $para . '>');
        _smtp_esperar($socket, [250, 251]);

        _smtp_decir($socket, 'DATA');
        _smtp_esperar($socket, [354]);

        $mensaje = implode("\r\n", array_merge(
            ['To: ' . $para, 'Subject: ' . $asunto_mime],
            $cabeceras,
            ['Date: ' . date('r'), '']
        ));

        /* Un punto solo al principio de una línea termina el mensaje, así que
           se duplica: es la regla de transparencia del protocolo. Sin esto,
           un cuerpo que arranque una línea con "." se corta ahí. */
        $cuerpo_seguro = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $cuerpo));

        _smtp_escribir($socket, $mensaje . "\r\n" . $cuerpo_seguro . "\r\n.\r\n");
        _smtp_esperar($socket, [250]);

        _smtp_decir($socket, 'QUIT');

        fclose($socket);

        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        error_log('smtp: ' . $error);

        @fclose($socket);

        return false;
    }
}

/**
 * AUTH, con el método que el servidor diga que acepta.
 *
 * LOGIN primero porque es el que ofrecen Hostinger, Gmail y Zoho. PLAIN
 * queda de alternativa: son los dos que se ven en la práctica y los dos
 * mandan la clave en base64, que NO es cifrado —por eso arriba no se
 * autentica nunca sin TLS—.
 */
function _smtp_autenticar($socket, string $capacidades, string $user, string $clave): void
{
    if (stripos($capacidades, 'AUTH') === false) {
        throw new RuntimeException('el servidor no ofrece AUTH pero hay un usuario configurado');
    }

    if (stripos($capacidades, 'LOGIN') !== false) {
        _smtp_decir($socket, 'AUTH LOGIN');
        _smtp_esperar($socket, [334]);

        _smtp_decir($socket, base64_encode($user));
        _smtp_esperar($socket, [334]);

        _smtp_decir($socket, base64_encode($clave));
        _smtp_esperar($socket, [235]);

        return;
    }

    if (stripos($capacidades, 'PLAIN') !== false) {
        _smtp_decir($socket, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $clave));
        _smtp_esperar($socket, [235]);

        return;
    }

    throw new RuntimeException('el servidor no acepta AUTH LOGIN ni AUTH PLAIN');
}

/** Manda una línea de comando. */
function _smtp_decir($socket, string $linea): void
{
    _smtp_escribir($socket, $linea . "\r\n");
}

function _smtp_escribir($socket, string $datos): void
{
    if (@fwrite($socket, $datos) === false) {
        throw new RuntimeException('se cortó la conexión al escribir');
    }
}

/**
 * Lee una respuesta entera y comprueba el código.
 *
 * Una respuesta puede venir en varias líneas: las intermedias llevan un
 * guión después del número (`250-STARTTLS`) y la última un espacio
 * (`250 HELP`). Leer una sola línea deja el resto en el socket y descoloca
 * todo lo que venga después.
 */
function _smtp_esperar($socket, array $esperados): string
{
    $respuesta = '';

    while (true) {
        $linea = @fgets($socket, 1024);

        if ($linea === false) {
            $info = stream_get_meta_data($socket);

            throw new RuntimeException(
                ($info['timed_out'] ?? false)
                    ? 'el servidor no contestó a tiempo'
                    : 'se cortó la conexión al leer'
            );
        }

        $respuesta .= $linea;

        // Última línea: el cuarto carácter es un espacio y no un guión.
        if (strlen($linea) >= 4 && $linea[3] === ' ') {
            break;
        }
    }

    $codigo = (int) substr($respuesta, 0, 3);

    if (!in_array($codigo, $esperados, true)) {
        throw new RuntimeException(
            'el servidor contestó: ' . trim(str_replace(["\r", "\n"], ' ', $respuesta))
        );
    }

    return $respuesta;
}
