<?php
/**
 * sesion.php — la sesión de los CLIENTES del sitio.
 *
 * No confundir con `app/panel.php`, que es la sesión de administración.
 * Son dos puertas distintas porque protegen dos cosas distintas: una deja
 * ver los pedidos propios, la otra deja editar los precios del negocio.
 * Tienen cookies con nombres distintos a propósito, así entrar a una no
 * tiene nada que ver con la otra y cerrar una no cierra la otra.
 *
 * ---------------------------------------------------------------------
 * NO SE ABRE UNA SESIÓN EN CADA VISITA
 *
 * La cabecera está en todas las páginas y necesita saber si hay alguien
 * identificado para decir "Mi cuenta" o "Ingresar". Hacer `session_start()`
 * en cada request para eso significa un archivo de sesión por cada
 * visitante anónimo que pasa por la home, y no guardar nada adentro.
 *
 * `sesion_abrir()` sin forzar arranca la sesión SÓLO si el navegador ya
 * trae la cookie. Quien nunca se identificó no paga nada. Quien sí, la
 * tiene. Las pantallas que necesitan escribir en la sesión —ingresar y
 * registro— llaman con `true` y la fuerzan.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

/** Cuánto dura sin actividad. Más larga que la del panel: acá no se edita nada. */
const SESION_INACTIVIDAD = 1209600;   // 14 días

/** Intentos fallidos de ingreso tolerados por IP antes de frenar. */
const SESION_MAX_INTENTOS = 8;

/** Ventana del contador de intentos. */
const SESION_VENTANA = 900;           // 15 minutos

/* ==========================================================================
   Abrir y cerrar
   ========================================================================== */

/**
 * Arranca la sesión del cliente.
 *
 * Con `$forzar = false` —el caso normal— sólo la arranca si ya existe la
 * cookie. Ver el comentario del encabezado.
 */
function sesion_abrir(bool $forzar = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (!$forzar && !isset($_COOKIE['rastro_cliente'])) {
        return;
    }

    /* Red de contención. Arrancar una sesión manda un Set-Cookie, y una
       cabecera no se puede mandar con medio HTML afuera: PHP no la inicia y
       escupe tres warnings ADENTRO de la página, a la vista del visitante y
       con rutas del servidor. El lugar correcto es index.php, antes de
       despachar la vista; esto es para que un descuido futuro rompa en el
       log y no en pantalla. */
    if (headers_sent($archivo, $linea)) {
        error_log(sprintf(
            'sesion: no se pudo abrir, la salida ya había empezado en %s:%d',
            $archivo,
            $linea
        ));

        return;
    }

    $https = ($_SERVER['HTTPS'] ?? '') !== ''
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    /* Las banderas van ANTES de session_start(): después no tienen efecto.
       SameSite=Lax y no Strict como el panel: con Strict, alguien que
       vuelve de Mercado Pago —una navegación que empieza en otro sitio—
       llegaría sin cookie y se vería deslogueado justo al terminar de
       comprar. Lax manda la cookie en las navegaciones de nivel superior,
       que es exactamente ese caso, y sigue sin mandarla en peticiones de
       terceros. */
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => RASTRO_BASE === '' ? '/' : RASTRO_BASE . '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);

    session_name('rastro_cliente');
    session_start();

    $ahora  = time();
    $ultimo = (int) ($_SESSION['cliente_ultimo'] ?? 0);

    if ($ultimo > 0 && ($ahora - $ultimo) > SESION_INACTIVIDAD) {
        sesion_salir();
        sesion_abrir(true);

        return;
    }

    $_SESSION['cliente_ultimo'] = $ahora;
}

/**
 * Deja a alguien identificado.
 *
 * Guarda el id y nada más. Los datos se releen del repository en cada
 * página: si el usuario cambia su teléfono, no queda una copia vieja
 * viviendo en la sesión y contradiciendo a la base.
 */
function sesion_entrar(array $usuario): void
{
    sesion_abrir(true);

    /* Id de sesión nuevo al identificarse: sin esto, un id que un atacante
       haya podido fijar de antemano queda válido ya con la sesión adentro. */
    session_regenerate_id(true);

    $_SESSION['cliente_id']     = (int) ($usuario['id'] ?? 0);
    $_SESSION['cliente_ultimo'] = time();
}

function sesion_salir(): void
{
    sesion_abrir(true);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => true,
            'secure'   => $p['secure'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

/* ==========================================================================
   Quién es
   ========================================================================== */

/**
 * El usuario identificado, o null.
 *
 * Se resuelve UNA vez por request y se recuerda: la cabecera lo pregunta,
 * la vista lo pregunta y el pie lo pregunta, y no tiene sentido ir tres
 * veces al repository por lo mismo.
 *
 * Si el usuario fue dado de baja mientras tenía la sesión abierta, la
 * sesión se cierra sola: `repo_user()` ya no lo devuelve.
 */
function sesion_usuario(): ?array
{
    static $cache = false;

    if ($cache !== false) {
        return $cache;
    }

    sesion_abrir();

    $id = (int) ($_SESSION['cliente_id'] ?? 0);

    if ($id <= 0) {
        return $cache = null;
    }

    $usuario = repo_user($id);

    if ($usuario === null) {
        sesion_salir();

        return $cache = null;
    }

    return $cache = $usuario;
}

/** ¿Hay alguien identificado? Para la cabecera, que sólo necesita el sí o el no. */
function sesion_hay_usuario(): bool
{
    return sesion_usuario() !== null;
}

/**
 * Corta la vista y manda a identificarse si no hay nadie.
 * Guarda a dónde iba, para volver ahí y no al inicio.
 */
function sesion_exigir_usuario(): void
{
    if (sesion_usuario() !== null) {
        return;
    }

    sesion_abrir(true);
    $_SESSION['cliente_destino'] = RASTRO_RUTA;

    header('Location: ' . url('/ingresar'), true, 303);

    exit;
}

/**
 * A dónde volver después de identificarse, y se consume.
 *
 * Sólo se aceptan rutas del propio sitio que empiecen con `/`. Un "volver
 * a donde ibas" que acepta cualquier destino es una redirección abierta
 * esperando a que alguien encuentre cómo sembrarla. Se descarta también
 * `//otro.com`, que un navegador lee como una URL absoluta.
 */
function sesion_destino(string $defecto = '/cuenta'): string
{
    sesion_abrir();

    $destino = (string) ($_SESSION['cliente_destino'] ?? '');
    unset($_SESSION['cliente_destino']);

    if ($destino === '' || $destino[0] !== '/' || str_starts_with($destino, '//')) {
        return $defecto;
    }

    // Nadie vuelve a la pantalla de ingresar después de ingresar.
    if (str_starts_with($destino, '/ingresar') || str_starts_with($destino, '/registro')) {
        return $defecto;
    }

    return $destino;
}

/* ==========================================================================
   CSRF
   ========================================================================== */

/**
 * El token de la sesión. Uno por sesión y no uno por formulario: con varias
 * pestañas abiertas, un token por formulario invalida el de las otras.
 *
 * Fuerza la sesión porque los formularios que lo usan —ingresar, registro—
 * todavía no tienen una: el token ES lo que la abre.
 */
function sesion_csrf(): string
{
    sesion_abrir(true);

    if (empty($_SESSION['cliente_csrf'])) {
        $_SESSION['cliente_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['cliente_csrf'];
}

/** ¿El token que vino con el POST es el de esta sesión? */
function sesion_csrf_valido(): bool
{
    $enviado = (string) ($_POST['csrf'] ?? '');

    return $enviado !== '' && hash_equals(sesion_csrf(), $enviado);
}

/* ==========================================================================
   Freno de intentos

   Contra la fuerza bruta sobre una contraseña. Se cuenta por IP y no por
   mail: contar por mail deja que alguien bloquee la cuenta de otro a
   propósito, que es un ataque en sí mismo.

   Vive en un archivo y no en la sesión, porque la sesión la controla quien
   ataca: le alcanza con tirar la cookie para empezar de cero.

   TODO(backend): con MySQL esto es una tabla con un índice por ip y fecha,
   y se limpia con un DELETE programado en vez de en cada escritura.
   ========================================================================== */

function _sesion_intentos_ruta(): string
{
    return dirname(__DIR__) . '/data/intentos.json';
}

/** Cuántos intentos fallidos lleva esta IP dentro de la ventana. */
function sesion_intentos(): int
{
    if (db_activa()) {
        return _repo_my_intentos_contar(_sesion_clave_ip(), time() - SESION_VENTANA);
    }

    $ruta = _sesion_intentos_ruta();

    if (!is_file($ruta)) {
        return 0;
    }

    $datos = json_decode((string) @file_get_contents($ruta), true);
    $clave = _sesion_clave_ip();
    $desde = time() - SESION_VENTANA;

    if (!is_array($datos) || !isset($datos[$clave]) || !is_array($datos[$clave])) {
        return 0;
    }

    return count(array_filter(
        $datos[$clave],
        static fn ($t): bool => (int) $t >= $desde
    ));
}

function sesion_bloqueado(): bool
{
    return sesion_intentos() >= SESION_MAX_INTENTOS;
}

/** Anota un intento fallido. */
function sesion_anotar_intento(): void
{
    if (db_activa()) {
        _repo_my_intento_anotar(_sesion_clave_ip(), SESION_VENTANA);

        return;
    }

    $ruta  = _sesion_intentos_ruta();
    $clave = _sesion_clave_ip();
    $desde = time() - SESION_VENTANA;

    $datos = is_file($ruta)
        ? json_decode((string) @file_get_contents($ruta), true)
        : [];

    $datos = is_array($datos) ? $datos : [];

    /* Se limpia TODO el archivo, no sólo esta IP: si no, las entradas de
       quien probó una vez y se fue quedan para siempre y el archivo sólo
       crece. Son pocos registros y se recorren una vez cada intento
       fallido, que no es un camino caliente. */
    foreach ($datos as $k => $marcas) {
        $vivas = array_values(array_filter(
            is_array($marcas) ? $marcas : [],
            static fn ($t): bool => (int) $t >= $desde
        ));

        if ($vivas === []) {
            unset($datos[$k]);
        } else {
            $datos[$k] = $vivas;
        }
    }

    $datos[$clave][] = time();

    @file_put_contents(
        $ruta,
        json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

/** Borra los intentos de esta IP. Se llama cuando alguien entra bien. */
function sesion_limpiar_intentos(): void
{
    $ruta = _sesion_intentos_ruta();

    if (!is_file($ruta)) {
        return;
    }

    $datos = json_decode((string) @file_get_contents($ruta), true);

    if (!is_array($datos)) {
        return;
    }

    unset($datos[_sesion_clave_ip()]);

    @file_put_contents(
        $ruta,
        json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

/**
 * La IP, en forma de clave.
 *
 * Se guarda un hash y no la IP en claro: el archivo termina siendo un
 * registro de quién intentó entrar, y eso es un dato personal que no hace
 * falta conservar legible para contar hasta ocho.
 */
function _sesion_clave_ip(): string
{
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');

    return substr(hash('sha256', $ip), 0, 16);
}
