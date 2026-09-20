<?php
/**
 * bin/verificar-correo.php — el cliente SMTP, contra un servidor de mentira.
 *
 *   php bin/verificar-correo.php
 *
 * Levanta `bin/smtp-falso.php`, le manda un mail de verdad por
 * `correo_enviar()` y comprueba la conversación comando por comando.
 *
 * Lo que deja demostrado:
 *
 *   · Los comandos salen en orden: EHLO, AUTH, MAIL FROM, RCPT TO, DATA, QUIT.
 *   · Entiende respuestas de varias líneas (250-STARTTLS / 250 HELP). Leer
 *     una sola desalinea todo lo que viene después.
 *   · Escapa el punto al principio de una línea. Sin eso, un cuerpo que
 *     empiece un renglón con "." corta el mensaje ahí.
 *   · El remitente es la casilla autenticada y no un no-reply@ inventado.
 *   · Sin usuario no exige STARTTLS; con usuario y sin STARTTLS, se planta
 *     antes de mandar la clave.
 *   · Una clave rechazada devuelve false con el motivo, no un true mudo.
 *
 * Lo que NO prueba: que el proveedor real acepte el mensaje. Eso se prueba
 * desde /admin/configuracion con el botón de probar el envío.
 *
 * CÓMO ESTÁ ARMADO
 *
 * La configuración del sitio vive en la constante RASTRO_CONFIG, que se
 * define una vez por proceso y no se puede cambiar. Como cada escenario
 * necesita la suya, el script se vuelve a ejecutar como hijo —uno por
 * escenario— pasándole los valores por el entorno. El padre sólo junta los
 * resultados.
 *
 * No se despliega: bin/ está excluido del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

/** Los tres escenarios: puerto, modo del servidor falso, y si hay credenciales. */
const ESCENARIOS = [
    'completo' => [
        'puerto'  => 2526,
        'modo'    => 'completo',
        'usuario' => 'ventas@rastrofitness.com',
        'titulo'  => 'Envío con usuario y clave',
    ],
    'simple' => [
        'puerto'  => 2527,
        'modo'    => 'simple',
        'usuario' => '',
        'titulo'  => 'Relay local sin usuario: no exige STARTTLS',
    ],
    'sin-tls' => [
        'puerto'  => 2528,
        'modo'    => 'simple',
        'usuario' => 'ventas@rastrofitness.com',
        'titulo'  => 'Con clave y sin STARTTLS: se planta antes de mandarla',
    ],
    'rechaza' => [
        'puerto'  => 2529,
        'modo'    => 'rechaza',
        'usuario' => 'ventas@rastrofitness.com',
        'titulo'  => 'Clave equivocada: false y con motivo',
    ],
];

/* ==========================================================================
   Padre: corre un hijo por escenario
   ========================================================================== */

if (getenv('RASTRO_ESCENARIO') === false) {
    $fallas = 0;

    /* Un certificado autofirmado para 127.0.0.1, con el que el servidor
       falso hace un STARTTLS de verdad. El cliente lo conoce por `smtp_ca`,
       así que el handshake se verifica igual que en producción: la prueba no
       apaga ninguna comprobación, sólo le presenta una autoridad conocida. */
    $cert  = sys_get_temp_dir() . '/rastro-smtp-cert-' . getmypid() . '.pem';
    $clave = $cert . '.key';

    exec(
        'openssl req -x509 -newkey rsa:2048 -nodes -days 1'
        . ' -keyout ' . escapeshellarg($clave)
        . ' -out ' . escapeshellarg($cert)
        . ' -subj "/CN=127.0.0.1"'
        . ' -addext "subjectAltName=IP:127.0.0.1" 2>/dev/null',
        $nada,
        $codigo_cert
    );

    if ($codigo_cert !== 0 || !is_file($cert)) {
        echo "✗ No se pudo generar el certificado de prueba con openssl.\n";
        exit(1);
    }

    // local_cert quiere el certificado y la clave en el mismo archivo.
    $combinado = $cert . '.full';
    file_put_contents($combinado, (string) file_get_contents($cert) . (string) file_get_contents($clave));

    foreach (ESCENARIOS as $nombre => $e) {
        echo $e['titulo'] . "\n";

        $salida = [];
        $codigo = 0;

        exec(
            'RASTRO_ESCENARIO=' . escapeshellarg($nombre)
            . ' RASTRO_SMTP_CERT=' . escapeshellarg($combinado)
            . ' RASTRO_SMTP_CA=' . escapeshellarg($cert) . ' '
            . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' 2>&1',
            $salida,
            $codigo
        );

        foreach ($salida as $linea) {
            echo '   ' . $linea . "\n";
        }

        $codigo === 0 || $fallas++;

        echo "\n";
    }

    foreach ([$cert, $clave, $combinado] as $f) {
        @unlink($f);
    }

    if ($fallas === 0) {
        echo "Cliente SMTP verificado: la conversación es correcta, el remitente es la\n";
        echo "casilla autenticada, y un fallo devuelve el motivo en vez de un true mudo.\n";
        exit(0);
    }

    echo "$fallas escenario(s) fallaron.\n";
    exit(1);
}

/* ==========================================================================
   Hijo: un escenario, con su propia configuración
   ========================================================================== */

$nombre = (string) getenv('RASTRO_ESCENARIO');
$e      = ESCENARIOS[$nombre] ?? null;

if ($e === null) {
    exit("escenario desconocido: $nombre\n");
}

define('RASTRO_CONFIG', [
    'base_url'       => 'https://rastrofitness.com',
    'smtp_host'      => '127.0.0.1',
    'smtp_port'      => $e['puerto'],
    'smtp_user'      => $e['usuario'],
    'smtp_pass'      => 'clave-de-prueba',
    'smtp_seguridad' => '',
    'smtp_ca'        => (string) getenv('RASTRO_SMTP_CA'),
]);

require $raiz . '/app/helpers.php';
require $raiz . '/app/smtp.php';
require $raiz . '/app/correo.php';

$archivo = sys_get_temp_dir() . '/rastro-smtp-' . getmypid() . '.txt';

@unlink($archivo);
@unlink($archivo . '.listo');

$proc = proc_open(
    [
        PHP_BINARY,
        $raiz . '/bin/smtp-falso.php',
        (string) $e['puerto'],
        $archivo,
        $e['modo'],
        (string) getenv('RASTRO_SMTP_CERT'),
    ],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $tuberias
);

// Se espera al archivo de señal en vez de dormir un rato fijo.
for ($i = 0; $i < 200 && !is_file($archivo . '.listo'); $i++) {
    usleep(25000);
}

/* El cuerpo arranca un renglón con un punto a propósito: es el carácter que
   corta el mensaje si el cliente no lo escapa. */
$cuerpo = "Hola,\n\nEntrá acá para cambiar tu contraseña:\n\n"
        . "https://rastrofitness.com/recuperar/abc\n.punto al principio\n\nRastro\n";

$error = null;
$ok    = correo_enviar('cliente@example.com', 'Cambiar tu contraseña', $cuerpo, '', $error);

// Se espera a que el servidor falso termine de escribir la transcripción.
for ($i = 0; $i < 200; $i++) {
    $estado = proc_get_status($proc);

    if (!($estado['running'] ?? false)) {
        break;
    }

    usleep(25000);
}

if (proc_get_status($proc)['running'] ?? false) {
    proc_terminate($proc);
}

proc_close($proc);

$t = is_file($archivo) ? (string) file_get_contents($archivo) : '';

@unlink($archivo);
@unlink($archivo . '.listo');

$checks = match ($nombre) {
    'completo' => [
        'devolvió true'                => $ok === true,
        'sin error'                    => $error === null,
        'saludó con EHLO'              => str_contains($t, 'EHLO rastrofitness.com'),
        'pidió STARTTLS'               => str_contains($t, 'STARTTLS'),
        'volvió a saludar tras el TLS' => substr_count($t, 'EHLO rastrofitness.com') === 2,
        'se autenticó con AUTH LOGIN'  => str_contains($t, 'AUTH LOGIN'),
        'mandó el usuario en base64'   => str_contains($t, base64_encode('ventas@rastrofitness.com')),
        'mandó la clave en base64'     => str_contains($t, base64_encode('clave-de-prueba')),
        'MAIL FROM es la casilla real' => str_contains($t, 'MAIL FROM:<ventas@rastrofitness.com>'),
        'RCPT TO al destinatario'      => str_contains($t, 'RCPT TO:<cliente@example.com>'),
        'abrió DATA'                   => str_contains($t, "DATA\r\n"),
        'puso el destinatario'         => str_contains($t, 'To: cliente@example.com'),
        'codificó el asunto en UTF-8'  => str_contains($t, '=?UTF-8?B?'),
        'mandó el enlace'              => str_contains($t, 'https://rastrofitness.com/recuperar/abc'),
        'escapó el punto inicial'      => str_contains($t, "\r\n..punto al principio"),
        'cerró el cuerpo con un punto' => str_contains($t, "\r\n.\r\n"),
        'cerró con QUIT'               => str_contains($t, 'QUIT'),
    ],

    'simple' => [
        'devolvió true sin exigir TLS' => $ok === true,
        'no intentó AUTH'              => !str_contains($t, 'AUTH'),
        'no intentó STARTTLS'          => !str_contains($t, 'STARTTLS'),
        'remitente no-reply del dominio' => str_contains($t, 'MAIL FROM:<no-reply@rastrofitness.com>'),
        'entregó el mensaje'           => str_contains($t, "\r\n.\r\n"),
    ],

    'sin-tls' => [
        'devolvió false'             => $ok === false,
        'dice que falta STARTTLS'    => is_string($error) && str_contains($error, 'STARTTLS'),
        'NO mandó la clave'          => !str_contains($t, base64_encode('clave-de-prueba')),
        'NO mandó AUTH'              => !str_contains($t, 'AUTH'),
        'NO mandó el mensaje'        => !str_contains($t, 'MAIL FROM'),
    ],

    'rechaza' => [
        'devolvió false'        => $ok === false,
        'dice el motivo'        => is_string($error) && $error !== '',
        'el motivo cita el 535' => is_string($error) && str_contains($error, '535'),
        'no siguió a MAIL FROM' => !str_contains($t, 'MAIL FROM'),
    ],

    default => [],
};

$malas = 0;

foreach ($checks as $que => $bien) {
    echo ($bien ? '✓' : '✗') . " $que\n";
    $bien || $malas++;
}

if ($nombre !== 'completo' && $nombre !== 'simple' && $error !== null) {
    echo '  (motivo que vería el panel: ' . $error . ")\n";
}

exit($malas === 0 ? 0 : 1);
