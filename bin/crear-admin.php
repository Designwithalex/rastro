<?php
/**
 * bin/crear-admin.php — crea o promueve un usuario del panel.
 *
 *   php bin/crear-admin.php ale@chichalabs.studio "Ale" "Gómez"
 *
 * Pide la contraseña por teclado y no la toma como argumento: un
 * argumento queda en el historial del shell y en la lista de procesos de
 * la máquina. Si el correo ya existe, lo promueve a admin y le cambia la
 * contraseña.
 *
 * ES LA ÚNICA FORMA DE CREAR UN ADMIN. No hay alta de admin desde el
 * panel ni desde el registro público: el rol lo decide el servidor, y
 * para eso hay que tener acceso al servidor.
 *
 * No se despliega: bin/ está excluido del deploy.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se corre por línea de comandos.\n");
}

require dirname(__DIR__) . '/app/db.php';

$email    = trim((string) ($argv[1] ?? ''));
$nombre   = trim((string) ($argv[2] ?? ''));
$apellido = trim((string) ($argv[3] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Uso: php bin/crear-admin.php correo@ejemplo.com \"Nombre\" \"Apellido\"\n";
    exit(1);
}

$email = mb_strtolower($email, 'UTF-8');

/**
 * Lee una contraseña sin mostrarla. En Windows no existe `stty -echo`, así
 * que se usa PowerShell; en Linux y macOS, stty.
 */
function leer_password(string $prompt): string
{
    echo $prompt;

    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        $ps = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
            . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $valor = shell_exec($ps);
    } else {
        shell_exec('stty -echo');
        $valor = fgets(STDIN);
        shell_exec('stty echo');
        echo PHP_EOL;
    }

    return trim((string) $valor);
}

$password = leer_password('Contraseña nueva: ');

if (mb_strlen($password) < 12) {
    // Más exigente que el registro público (8): esta cuenta edita el
    // catálogo, los precios y los pedidos de todo el sitio.
    echo "✗ La contraseña del panel necesita al menos 12 caracteres.\n";
    exit(1);
}

if ($password !== leer_password('Repetila: ')) {
    echo "✗ No coinciden.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$existente = db_q('SELECT id, nombre, apellido FROM usuarios WHERE email = ? LIMIT 1', [$email])->fetch();

if ($existente !== false) {
    db_q(
        'UPDATE usuarios SET password_hash = ?, rol = ?, activo = 1 WHERE id = ?',
        [$hash, 'admin', (int) $existente['id']]
    );

    echo "✓ {$email} ahora es admin (contraseña actualizada).\n";
    exit(0);
}

if ($nombre === '' || $apellido === '') {
    echo "✗ Para un usuario nuevo hacen falta nombre y apellido.\n";
    echo "  php bin/crear-admin.php $email \"Nombre\" \"Apellido\"\n";
    exit(1);
}

db_q(
    'INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, creado, activo)
     VALUES (?, ?, ?, ?, ?, ?, 1)',
    [$nombre, $apellido, $email, $hash, 'admin', date('Y-m-d')]
);

echo "✓ Admin creado: {$email}\n";
echo "  Entra por /ingresar y de ahí a /admin.\n";
