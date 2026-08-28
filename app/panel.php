<?php
/**
 * panel.php — todo lo que el panel de administración necesita y el sitio
 * público no: sesión, CSRF, subida de imágenes y lectura de formularios.
 *
 * No hay lógica de datos acá. Guardar es tarea de `repository-escritura.php`;
 * esto es la puerta de calle y el control de lo que entra por ella.
 *
 * ---------------------------------------------------------------------
 * POR QUÉ EL PANEL TIENE SU PROPIO LOGIN Y NO EL DE /ingresar
 *
 * `/ingresar` es el login de clientes: hoy es mock y lo va a reemplazar el
 * backend con lo que decida. El panel escribe archivos en el servidor, así
 * que no puede depender de una pantalla que todavía no tiene auth real.
 * Son dos puertas distintas porque protegen dos cosas distintas.
 *
 * DE DÓNDE SALE EL ADMINISTRADOR
 *
 * De `app/config.php`, que NO se versiona (CLAUDE.md §7.1). Ahí van el mail
 * y el hash, y el hash se genera con:
 *
 *   php -r 'echo password_hash("la-clave", PASSWORD_DEFAULT), PHP_EOL;'
 *
 * Sin eso configurado el panel no deja entrar a nadie y explica cómo
 * configurarlo. Es a propósito: un panel que escribe archivos y arranca con
 * una clave por defecto conocida es un panel abierto, y en un hosting
 * compartido "todavía nadie sabe la URL" no es una medida de seguridad.
 *
 * Además de ese admin de arranque, `panel_ingresar()` acepta cualquier
 * usuario de `data/users.json` con `rol: "admin"`. Eso es lo que va a usar
 * el backend cuando haya usuarios de verdad; hoy no hay ninguno cargado.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

/** Cuánto dura la sesión sin actividad. Dos horas: es una jornada de carga. */
const PANEL_INACTIVIDAD = 7200;

/** Lo más grande que se acepta subir. */
const PANEL_MAX_BYTES = 6 * 1024 * 1024;

/** Dónde caen las imágenes que sube el cliente, relativo a assets/. */
const PANEL_CARPETA_SUBIDAS = 'img/subidas';

/* ==========================================================================
   Sesión
   ========================================================================== */

/**
 * Abre la sesión del panel, una sola vez por request.
 *
 * Las banderas de la cookie se ponen ANTES de session_start() porque después
 * no tienen efecto: es el error clásico de este bloque. `Secure` sale de si
 * el request llegó por HTTPS, así que en local por http sigue funcionando y
 * en Hostinger —que fuerza HTTPS— viaja protegida.
 */
function panel_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = ($_SERVER['HTTPS'] ?? '') !== ''
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => RASTRO_BASE === '' ? '/' : RASTRO_BASE . '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Strict',
    ]);

    session_name('rastro_panel');
    session_start();

    /* Caducidad por inactividad. La sesión de PHP puede vivir mucho más que
       esto según la configuración del hosting, que no controlamos: el plazo
       lo lleva la propia sesión. */
    $ahora  = time();
    $ultimo = (int) ($_SESSION['panel_ultimo'] ?? 0);

    if ($ultimo > 0 && ($ahora - $ultimo) > PANEL_INACTIVIDAD) {
        panel_cerrar_sesion();
        panel_sesion();

        return;
    }

    $_SESSION['panel_ultimo'] = $ahora;
}

/** El administrador de la sesión, o null si no hay nadie. */
function panel_usuario(): ?array
{
    panel_sesion();

    $usuario = $_SESSION['panel_usuario'] ?? null;

    return is_array($usuario) ? $usuario : null;
}

/**
 * Corta la vista y manda a la pantalla de ingreso si no hay sesión.
 * Es la primera línea de toda vista del panel, sin excepción.
 */
function panel_exigir_sesion(): void
{
    if (panel_usuario() !== null) {
        return;
    }

    /* A dónde quería ir. Después de identificarse vuelve ahí y no al
       tablero: si alguien abre el enlace de un pedido y le pide la clave,
       lo que espera ver después es ese pedido. */
    $_SESSION['panel_destino'] = RASTRO_RUTA;

    panel_ir('/admin/ingresar');
}

/**
 * Valida credenciales. Devuelve el administrador o null.
 *
 * `password_verify` se corre SIEMPRE, aunque el mail no exista, contra un
 * hash de descarte. Si sólo se corriera cuando el usuario existe, la
 * diferencia de tiempo entre "mail que existe" y "mail que no" alcanza para
 * averiguar cuál es el mail del administrador sin acertar una sola clave.
 */
function panel_ingresar(string $email, string $clave): ?array
{
    $email = mb_strtolower(trim($email));

    // Hash de descarte: bcrypt de una cadena que no es la clave de nadie.
    $hash   = '$2y$12$0000000000000000000000000000000000000000000000000000u';
    $quien  = null;

    $admin_email = mb_strtolower(trim((string) config('panel_email', '')));
    $admin_hash  = (string) config('panel_password_hash', '');

    if ($admin_email !== '' && $admin_hash !== '' && hash_equals($admin_email, $email)) {
        $hash  = $admin_hash;
        $quien = [
            'id'     => 0,
            'nombre' => (string) config('panel_nombre', 'Administración'),
            'email'  => $admin_email,
            'origen' => 'config',
        ];
    } else {
        foreach (_repo_json('users') as $usuario) {
            if (($usuario['rol'] ?? '') !== 'admin' || ($usuario['activo'] ?? true) !== true) {
                continue;
            }

            if (hash_equals(mb_strtolower((string) ($usuario['email'] ?? '')), $email)) {
                $hash  = (string) ($usuario['password_hash'] ?? $hash);
                $quien = [
                    'id'     => (int) ($usuario['id'] ?? 0),
                    'nombre' => trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')),
                    'email'  => (string) ($usuario['email'] ?? ''),
                    'origen' => 'users',
                ];
                break;
            }
        }
    }

    if (!password_verify($clave, $hash) || $quien === null) {
        return null;
    }

    panel_sesion();

    /* Id de sesión nuevo al identificarse. Sin esto, un id que un atacante
       haya podido fijar de antemano queda válido ya con la sesión adentro
       (fijación de sesión). */
    session_regenerate_id(true);

    $_SESSION['panel_usuario'] = $quien;
    $_SESSION['panel_ultimo']  = time();

    return $quien;
}

function panel_cerrar_sesion(): void
{
    panel_sesion();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => true,
            'secure'   => $p['secure'],
            'samesite' => 'Strict',
        ]);
    }

    session_destroy();
}

/** ¿Hay panel configurado? Si no, no hay forma de entrar y hay que decirlo. */
function panel_configurado(): bool
{
    return trim((string) config('panel_email', '')) !== ''
        && trim((string) config('panel_password_hash', '')) !== '';
}

/* ==========================================================================
   CSRF
   ========================================================================== */

/**
 * El token de la sesión. Uno solo por sesión y no uno por formulario: con
 * varias pestañas abiertas, un token por formulario invalida el de las otras
 * y el cliente pierde lo que estaba cargando sin entender por qué.
 */
function panel_csrf(): string
{
    panel_sesion();

    if (empty($_SESSION['panel_csrf'])) {
        $_SESSION['panel_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['panel_csrf'];
}

/**
 * Corta el request si el token no viene o no coincide.
 *
 * Se llama al principio de CADA rama que escribe. No hay excepciones: la
 * pantalla que "no cambia nada importante" es justamente la que se olvida.
 */
function panel_exigir_csrf(): void
{
    $enviado = (string) ($_POST['csrf'] ?? '');

    if ($enviado !== '' && hash_equals(panel_csrf(), $enviado)) {
        return;
    }

    http_response_code(400);
    error_log('panel: CSRF inválido en ' . RASTRO_RUTA);

    echo '<!doctype html><meta charset="utf-8"><title>Sesión vencida</title>'
       . '<p>La sesión venció o el formulario se envió dos veces. '
       . '<a href="' . e(url('/admin')) . '">Volver al panel</a>.</p>';

    exit;
}

/* ==========================================================================
   Navegación y avisos
   ========================================================================== */

function panel_es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Redirige y termina.
 *
 * Después de un POST que guardó SIEMPRE se redirige, aunque se pudiera
 * dibujar la página directamente: si no, recargar reenvía el formulario y
 * el cliente termina con el mismo producto cargado tres veces.
 */
function panel_ir(string $ruta): never
{
    header('Location: ' . url($ruta), true, 303);

    exit;
}

/**
 * Deja un aviso para la pantalla siguiente y redirige.
 * `$tipo` es 'ok' o 'error'.
 */
function panel_ir_con_aviso(string $ruta, string $tipo, string $texto): never
{
    panel_sesion();

    $_SESSION['panel_aviso'] = ['tipo' => $tipo, 'texto' => $texto];

    panel_ir($ruta);
}

/** Lee el aviso pendiente y lo consume: se muestra una vez y no vuelve. */
function panel_aviso(): ?array
{
    panel_sesion();

    $aviso = $_SESSION['panel_aviso'] ?? null;
    unset($_SESSION['panel_aviso']);

    return is_array($aviso) ? $aviso : null;
}

/* ==========================================================================
   Lectura de formularios

   $_POST siempre trae texto. Estas funciones lo dejan en el tipo que va al
   JSON, para que el archivo no termine con "24" en un campo que la vista
   suma. Un stock guardado como cadena rompe la comparación de stock bajo
   sin dar ningún error.
   ========================================================================== */

function panel_texto(string $campo, string $defecto = ''): string
{
    $valor = $_POST[$campo] ?? $defecto;

    if (!is_string($valor)) {
        return $defecto;
    }

    // \r\n del textarea a \n: el JSON queda igual lo mande quien lo mande.
    return trim(str_replace("\r\n", "\n", $valor));
}

function panel_entero(string $campo, ?int $defecto = null): ?int
{
    $valor = trim((string) ($_POST[$campo] ?? ''));

    if ($valor === '') {
        return $defecto;
    }

    /* El cliente escribe precios como los lee: "74.900" o "74 900". Sacar
       los separadores es lo que evita que un disco de 74.900 se guarde
       como 74 pesos. */
    $valor = str_replace(['.', ' ', ','], '', $valor);

    return is_numeric($valor) ? (int) $valor : $defecto;
}

function panel_booleano(string $campo): bool
{
    return isset($_POST[$campo]) && $_POST[$campo] !== '' && $_POST[$campo] !== '0';
}

/**
 * Una lista escrita como líneas en un textarea.
 * Es la forma más simple de editar un array corto sin inventar un widget.
 */
function panel_lineas(string $campo): array
{
    $texto = panel_texto($campo);

    if ($texto === '') {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', explode("\n", $texto)),
        static fn (string $linea): bool => $linea !== ''
    ));
}

/**
 * Pares "Rótulo: valor" por línea, para especificaciones técnicas.
 * Una línea sin dos puntos se guarda entera como rótulo, sin valor: es más
 * útil que descartarla en silencio.
 */
function panel_pares(string $campo): array
{
    $pares = [];

    foreach (panel_lineas($campo) as $linea) {
        $partes = explode(':', $linea, 2);

        $pares[] = [
            'label' => trim($partes[0]),
            'valor' => isset($partes[1]) ? trim($partes[1]) : '',
        ];
    }

    return $pares;
}

/* ==========================================================================
   Subida de imágenes
   ========================================================================== */

/**
 * Guarda una imagen subida y devuelve su ruta relativa a assets/, lista
 * para meter en el JSON. Devuelve ['ruta' => …] o ['error' => …].
 *
 * No se confía en el nombre ni en el `type` que manda el navegador: los dos
 * los escribe el cliente. El tipo sale de `getimagesize()`, que mira el
 * contenido, y la extensión se deriva de ahí. Un .php renombrado a .jpg no
 * pasa `getimagesize()`, y aunque pasara, la carpeta de subidas tiene su
 * propio .htaccess que apaga la ejecución de PHP.
 */
function panel_subir_imagen(string $campo, string $prefijo = 'imagen'): array
{
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return ['error' => 'No llegó ningún archivo.'];
    }

    $archivo = $_FILES[$campo];
    $codigo  = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($codigo === UPLOAD_ERR_NO_FILE) {
        return ['error' => ''];  // no subió nada, y no tenía por qué
    }

    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'La imagen es más grande de lo que acepta el servidor.'];
    }

    if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file((string) $archivo['tmp_name'])) {
        return ['error' => 'No se pudo recibir la imagen. Probá de nuevo.'];
    }

    if ((int) $archivo['size'] > PANEL_MAX_BYTES) {
        return ['error' => sprintf(
            'La imagen pesa %s y el máximo es %s.',
            panel_pesar((int) $archivo['size']),
            panel_pesar(PANEL_MAX_BYTES)
        )];
    }

    $medidas = @getimagesize((string) $archivo['tmp_name']);

    if ($medidas === false) {
        return ['error' => 'Ese archivo no es una imagen.'];
    }

    $extensiones = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    $tipo = (int) ($medidas[2] ?? 0);

    if (!isset($extensiones[$tipo])) {
        return ['error' => 'Formato no admitido. Subí un JPG, un PNG o un WebP.'];
    }

    $carpeta = dirname(__DIR__) . '/assets/' . PANEL_CARPETA_SUBIDAS;

    if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        return ['error' => 'No se pudo crear la carpeta de imágenes en el servidor.'];
    }

    /* Nombre nuevo siempre. Conservar el del cliente trae acentos, espacios
       y, sobre todo, colisiones: dos "foto.jpg" de dos productos distintos
       y el segundo pisa al primero. */
    $nombre = sprintf(
        '%s-%s.%s',
        slug($prefijo) !== '' ? slug($prefijo) : 'imagen',
        bin2hex(random_bytes(4)),
        $extensiones[$tipo]
    );

    if (!@move_uploaded_file((string) $archivo['tmp_name'], $carpeta . '/' . $nombre)) {
        return ['error' => 'No se pudo guardar la imagen. Revisá los permisos de assets/img/subidas.'];
    }

    @chmod($carpeta . '/' . $nombre, 0664);

    return ['ruta' => PANEL_CARPETA_SUBIDAS . '/' . $nombre];
}

/** Bytes en algo legible, para los mensajes de error. */
function panel_pesar(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    return number_format($bytes / 1024, 0, ',', '.') . ' KB';
}

/* ==========================================================================
   Presentación
   ========================================================================== */

/** Las nueve secciones del panel, en el orden en que van en la barra. */
function panel_secciones(): array
{
    return [
        ['ruta' => '/admin',               'titulo' => 'Tablero',       'exacta' => true],
        ['ruta' => '/admin/productos',     'titulo' => 'Productos',     'exacta' => false],
        ['ruta' => '/admin/pedidos',       'titulo' => 'Pedidos',       'exacta' => false],
        ['ruta' => '/admin/categorias',    'titulo' => 'Categorías',    'exacta' => false],
        ['ruta' => '/admin/marcas',        'titulo' => 'Marcas',        'exacta' => false],
        ['ruta' => '/admin/clientes',      'titulo' => 'Clientes',      'exacta' => false],
        ['ruta' => '/admin/banners',       'titulo' => 'Banners',       'exacta' => false],
        ['ruta' => '/admin/nosotros',      'titulo' => 'Nosotros',      'exacta' => false],
        ['ruta' => '/admin/configuracion', 'titulo' => 'Configuración', 'exacta' => false],
    ];
}

/**
 * Los estados de un pedido, como `clave => rótulo`, para los desplegables
 * y las pastillas del panel.
 *
 * NO define la lista: la lee de `estados_pedido()` en helpers.php, que es la
 * misma que dibuja `/cuenta`. Un estado que el panel deja elegir y la página
 * del cliente no sabe mostrar es un pedido que dice `en_camino` en crudo.
 */
function panel_estados_pedido(): array
{
    return array_map(
        static fn (array $estado): string => $estado['texto'],
        estados_pedido()
    );
}
