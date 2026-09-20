<?php
/**
 * bin/verificar-formularios.php — lo que el sitio escribe cuando alguien
 * completa un formulario: arrepentimientos, recuperación de contraseña e
 * intentos de login.
 *
 *   php bin/verificar-formularios.php
 *
 * Son los tres almacenes que NO pasan por `_repo_json()` —tenían cada uno
 * su propio archivo— y los tres tienen algo que perder: una constancia
 * legal con plazo de 10 días, un enlace que da acceso a una cuenta, y el
 * freno contra la fuerza bruta.
 *
 * ESCRIBE EN LA BASE: usa códigos y correos de prueba y los borra al
 * terminar, pase lo que pase.
 *
 * No se despliega: bin/ está excluido del workflow de deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

require $raiz . '/app/helpers.php';
require $raiz . '/app/db.php';
require $raiz . '/app/repository-mysql.php';
require $raiz . '/app/repository.php';
require $raiz . '/app/repository-escritura.php';

// Por SESION_VENTANA y SESION_MAX_INTENTOS. Cargarlo no arranca ninguna
// sesión: sesion.php sólo define funciones y constantes.
require $raiz . '/app/sesion.php';

if (!db_activa()) {
    exit("✗ No hay base configurada en app/config.php.\n");
}

const PRUEBA_EMAIL = 'prueba-automatica@example.invalid';
const PRUEBA_IP    = 'ffffffffffffffff';

$fallas = 0;

function limpiar(): void
{
    try {
        db_q('DELETE FROM arrepentimientos WHERE email = ?', [PRUEBA_EMAIL]);
        db_q('DELETE FROM recuperaciones WHERE email = ?', [PRUEBA_EMAIL]);
        db_q('DELETE FROM intentos_login WHERE ip = ?', [PRUEBA_IP]);
    } catch (Throwable $e) {
        echo '  (no se pudo limpiar: ' . $e->getMessage() . ")\n";
    }
}

register_shutdown_function('limpiar');
limpiar();

/** @param array<string,bool> $checks */
function informar(array $checks): int
{
    $malas = 0;

    foreach ($checks as $que => $ok) {
        echo '   ' . ($ok ? '✓' : '✗') . " $que\n";
        $ok || $malas++;
    }

    return $malas;
}

/* --- 1. Arrepentimientos -------------------------------------------- */

echo "1. Arrepentimiento: alta, lectura y cambio de estado\n";

$alta = repo_save_arrepentimiento([
    'pedido'  => 'RF-2026-TEST',
    'nombre'  => 'Prueba Automática',
    'email'   => PRUEBA_EMAIL,
    'detalle' => 'Constancia de prueba. Se borra sola.',
]);

$codigo = (string) ($alta['codigo'] ?? '');
$todos  = repo_arrepentimientos();
$mio    = null;

foreach ($todos as $a) {
    if (($a['codigo'] ?? '') === $codigo) {
        $mio = $a;
        break;
    }
}

$fallas += informar([
    'devolvió el registro'   => $alta !== null,
    'le puso código ARR-'    => str_starts_with($codigo, 'ARR-'),
    'estado inicial recibido' => ($alta['estado'] ?? null) === 'recibido',
    'aparece al leer'        => $mio !== null,
    'conserva el detalle'    => ($mio['detalle'] ?? null) === 'Constancia de prueba. Se borra sola.',
    'conserva el pedido'     => ($mio['pedido'] ?? null) === 'RF-2026-TEST',
    'sin `actualizado` aún'  => !array_key_exists('actualizado', $mio ?? []),
]);

$movido = repo_save_arrepentimiento_estado($codigo, 'resuelto');

$relectura = null;
foreach (repo_arrepentimientos() as $a) {
    if (($a['codigo'] ?? '') === $codigo) {
        $relectura = $a;
        break;
    }
}

$fallas += informar([
    'el cambio de estado dio true' => $movido === true,
    'quedó resuelto'               => ($relectura['estado'] ?? null) === 'resuelto',
    'quedó `actualizado`'          => array_key_exists('actualizado', $relectura ?? []),
    'un código inexistente da false' => repo_save_arrepentimiento_estado('ARR-NO-EXISTE', 'resuelto') === false,
]);

/* --- 2. Recuperación de contraseña ---------------------------------- */

echo "\n2. Recuperación: el enlace sirve una vez y sólo uno por correo\n";

$token = repo_crear_recuperacion(PRUEBA_EMAIL);

$guardado = db_q(
    'SELECT token_hash FROM recuperaciones WHERE email = ?',
    [PRUEBA_EMAIL]
)->fetchColumn();

$fallas += informar([
    'devolvió un token'        => is_string($token) && strlen((string) $token) === 64,
    'resuelve al correo'       => repo_email_de_recuperacion((string) $token) === PRUEBA_EMAIL,
    'el token NO está en la base' => $guardado !== false && $guardado !== $token,
    'lo guardado es su sha256' => $guardado === hash('sha256', (string) $token),
    'un token inventado no resuelve' => repo_email_de_recuperacion(str_repeat('a', 64)) === null,
]);

// Pedir uno nuevo tiene que invalidar el anterior.
$segundo = repo_crear_recuperacion(PRUEBA_EMAIL);

$vivos = (int) db_q(
    'SELECT COUNT(*) FROM recuperaciones WHERE email = ?',
    [PRUEBA_EMAIL]
)->fetchColumn();

$fallas += informar([
    'el segundo enlace sirve'   => repo_email_de_recuperacion((string) $segundo) === PRUEBA_EMAIL,
    'el primero ya no sirve'    => repo_email_de_recuperacion((string) $token) === null,
    'queda uno solo por correo' => $vivos === 1,
]);

repo_quemar_recuperacion((string) $segundo);

$fallas += informar([
    'quemado deja de servir' => repo_email_de_recuperacion((string) $segundo) === null,
    'y no queda la fila'     => (int) db_q('SELECT COUNT(*) FROM recuperaciones WHERE email = ?',
                                    [PRUEBA_EMAIL])->fetchColumn() === 0,
]);

/* --- 3. Intentos de login ------------------------------------------- */

echo "\n3. Intentos de login: el freno cuenta bien\n";

$antes = _repo_my_intentos_contar(PRUEBA_IP, time() - SESION_VENTANA);

_repo_my_intento_anotar(PRUEBA_IP, SESION_VENTANA);
_repo_my_intento_anotar(PRUEBA_IP, SESION_VENTANA);
_repo_my_intento_anotar(PRUEBA_IP, SESION_VENTANA);

$despues = _repo_my_intentos_contar(PRUEBA_IP, time() - SESION_VENTANA);

// Uno viejo, fuera de la ventana, no tiene que contar.
db_q(
    'INSERT INTO intentos_login (email, ip, momento) VALUES (?, ?, ?)',
    ['', PRUEBA_IP, date('Y-m-d H:i:s', time() - SESION_VENTANA - 600)]
);

$conViejo = _repo_my_intentos_contar(PRUEBA_IP, time() - SESION_VENTANA);

$fallas += informar([
    'arranca en cero'            => $antes === 0,
    'cuenta los tres'            => $despues === 3,
    'uno vencido no cuenta'      => $conViejo === 3,
    'otra IP no se ve afectada'  => _repo_my_intentos_contar('0000000000000000', time() - SESION_VENTANA) === 0,
]);

echo "\n";

limpiar();

echo "Datos de prueba borrados.\n\n";

if ($fallas === 0) {
    echo "Formularios verificados: el arrepentimiento queda con su constancia, el\n";
    echo "enlace de recuperación sirve una sola vez y el freno de login cuenta bien.\n";
    exit(0);
}

echo "$fallas comprobación(es) fallaron.\n";
exit(1);
