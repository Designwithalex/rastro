<?php
/**
 * bin/smtp-falso.php — un servidor SMTP de mentira, para probar el cliente.
 *
 *   php bin/smtp-falso.php <puerto> <archivo-transcripcion> [modo]
 *
 * Atiende UNA conexión, habla el protocolo lo justo, y escribe en el archivo
 * todo lo que el cliente le dijo. La prueba después lee esa transcripción y
 * comprueba que la conversación haya sido la correcta.
 *
 * Existe porque un cliente SMTP sin probar es código que uno cree que anda.
 * Lo único que se puede afirmar de `smtp_enviar()` es lo que este archivo
 * deja demostrado: que manda los comandos en orden, que entiende respuestas
 * de varias líneas, y que escapa el punto al principio de una línea.
 *
 * Modos:
 *   completo  ofrece STARTTLS y AUTH (el servidor de verdad)
 *   simple    no ofrece ninguno de los dos (relay local sin credenciales)
 *   rechaza   contesta 535 al AUTH (clave equivocada)
 *
 * No se despliega: bin/ está excluido del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$puerto  = (int) ($argv[1] ?? 0);
$destino = (string) ($argv[2] ?? '');
$modo    = (string) ($argv[3] ?? 'completo');
$cert    = (string) ($argv[4] ?? '');

if ($puerto === 0 || $destino === '') {
    exit("Uso: php bin/smtp-falso.php <puerto> <archivo> [modo]\n");
}

$servidor = @stream_socket_server("tcp://127.0.0.1:$puerto", $errno, $errstr);

if ($servidor === false) {
    file_put_contents($destino, "ERROR: no se pudo escuchar en $puerto: $errstr\n");
    exit(1);
}

// La prueba espera este archivo para saber que ya puede conectarse.
file_put_contents($destino . '.listo', '1');

$cliente = @stream_socket_accept($servidor, 20);

if ($cliente === false) {
    file_put_contents($destino, "ERROR: nadie se conectó\n");
    exit(1);
}

stream_set_timeout($cliente, 10);

$transcripcion = '';

function decir($cliente, string $linea): void
{
    fwrite($cliente, $linea . "\r\n");
}

decir($cliente, '220 smtp-falso listo');

$en_datos = false;

while (($linea = fgets($cliente, 2048)) !== false) {
    $transcripcion .= $linea;

    if ($en_datos) {
        // Un punto solo cierra el cuerpo.
        if (rtrim($linea, "\r\n") === '.') {
            $en_datos = false;
            decir($cliente, '250 2.0.0 aceptado');
        }

        continue;
    }

    $comando = strtoupper(strtok(trim($linea), ' ') ?: '');

    switch ($comando) {
        case 'EHLO':
            /* Respuesta de varias líneas a propósito: es donde se rompe un
               cliente que lee una sola. Las intermedias llevan guión. */
            if ($modo === 'simple') {
                decir($cliente, '250-smtp-falso');
                decir($cliente, '250 HELP');
                break;
            }

            decir($cliente, '250-smtp-falso');
            decir($cliente, '250-STARTTLS');
            decir($cliente, '250-AUTH LOGIN PLAIN');
            decir($cliente, '250 HELP');
            break;

        case 'STARTTLS':
            if ($cert === '' || !is_file($cert)) {
                decir($cliente, '454 4.7.0 no hay certificado');
                break;
            }

            decir($cliente, '220 2.0.0 adelante con TLS');

            /* El handshake de verdad, con un certificado autofirmado que el
               cliente conoce por `smtp_ca`. Sin esto la prueba no diría nada
               sobre el camino que se usa en producción, que es justamente el
               que lleva STARTTLS. */
            stream_context_set_option($cliente, 'ssl', 'local_cert', $cert);
            stream_context_set_option($cliente, 'ssl', 'allow_self_signed', true);

            $listo_tls = @stream_socket_enable_crypto(
                $cliente,
                true,
                STREAM_CRYPTO_METHOD_TLS_SERVER
            );

            if ($listo_tls !== true) {
                file_put_contents($destino, $transcripcion . "\nERROR: falló el TLS del servidor\n");
                exit(1);
            }

            break;

        case 'AUTH':
            if ($modo === 'rechaza') {
                decir($cliente, '535 5.7.8 usuario o clave incorrectos');
                break;
            }

            // AUTH LOGIN: usuario, después clave, las dos en base64.
            decir($cliente, '334 VXNlcm5hbWU6');
            $transcripcion .= (string) fgets($cliente, 1024);
            decir($cliente, '334 UGFzc3dvcmQ6');
            $transcripcion .= (string) fgets($cliente, 1024);
            decir($cliente, '235 2.7.0 autenticado');
            break;

        case 'MAIL':
        case 'RCPT':
            decir($cliente, '250 2.1.0 ok');
            break;

        case 'DATA':
            $en_datos = true;
            decir($cliente, '354 mandá el mensaje, terminá con un punto solo');
            break;

        case 'QUIT':
            decir($cliente, '221 2.0.0 chau');
            break 2;

        default:
            decir($cliente, '502 5.5.2 no entiendo');
    }
}

file_put_contents($destino, $transcripcion);

fclose($cliente);
fclose($servidor);
@unlink($destino . '.listo');
