<?php
/**
 * sesion.php — sesión, autenticación y CSRF.
 *
 * Todo lo que tiene que ver con "¿quién es el que está del otro lado?"
 * pasa por acá. Ninguna vista toca `$_SESSION` directamente.
 *
 * LA SESIÓN ARRANCA EN TODOS LOS REQUESTS
 *
 * La abre `index.php` antes de resolver la ruta. Podría abrirse sólo
 * donde hace falta, pero la cabecera está en todas las páginas y tiene
 * que saber si mostrar "Cuenta" o el nombre de quien entró, así que
 * "donde hace falta" termina siendo en todos lados. Abrirla siempre es
 * una regla que no se olvida nadie.
 *
 * QUÉ SE GUARDA EN LA SESIÓN
 *
 * El id del usuario y nada más. El nombre, el rol y el correo se releen
 * de la base en cada request. Guardar el rol en la sesión significa que
 * bajarle los permisos a alguien no tiene efecto hasta que cierre
 * sesión, y eso es exactamente lo que no se quiere de un permiso.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* Después de este tiempo sin actividad, la sesión se cierra sola. Dos
   horas: suficiente para cargar productos en el panel sin que caduque a
   mitad de un formulario, poco para una máquina compartida. */
const SESION_INACTIVIDAD = 7200;

/* Límite de intentos de login. Se cuenta por correo y por IP en la misma
   ventana: por correo, para que no le prueben contraseñas a una cuenta
   concreta; por IP, para que no barran muchas cuentas desde un lugar. */
const LOGIN_VENTANA_SEG   = 900;
const LOGIN_MAX_POR_EMAIL = 8;
const LOGIN_MAX_POR_IP    = 25;

/* ==========================================================================
   Arranque
   ========================================================================== */

/**
 * Abre la sesión con la configuración endurecida. Idempotente.
 */
function sesion_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    /* El nombre por defecto, PHPSESSID, anuncia el stack. No es una
       defensa, pero tampoco cuesta nada. */
    session_name('rastro_sesion');

    session_set_cookie_params([
        'lifetime' => 0,            // se cierra al cerrar el navegador
        'path'     => '/',
        'httponly' => true,         // ningún script puede leer la cookie
        'secure'   => _sesion_es_https(),
        /* Lax y no Strict: con Strict, quien llega desde un enlace externo
           —un mail, WhatsApp— aparece deslogueado en el primer request y
           logueado al segundo, que se lee como un bug. Lax igual bloquea
           el POST cruzado, que es lo que importa. */
        'samesite' => 'Lax',
    ]);

    session_start();

    _sesion_controlar_inactividad();
}

function _sesion_es_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off') {
        return true;
    }

    // Hostinger termina TLS en un proxy y lo avisa por esta cabecera.
    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Cierra la sesión si pasó demasiado tiempo sin actividad.
 */
function _sesion_controlar_inactividad(): void
{
    $ultimo = (int) ($_SESSION['visto'] ?? 0);

    if ($ultimo > 0 && (time() - $ultimo) > SESION_INACTIVIDAD) {
        sesion_cerrar();
        session_start();
    }

    $_SESSION['visto'] = time();
}

/* ==========================================================================
   Quién está del otro lado
   ========================================================================== */

/**
 * El usuario logueado, o null. Se relee de la base una vez por request.
 *
 * Devuelve null también si la cuenta se desactivó mientras la sesión
 * seguía abierta: el permiso se comprueba contra la base, no contra lo
 * que se guardó el día del login.
 */
function sesion_usuario(): ?array
{
    static $usuario = null;
    static $resuelto = false;

    if ($resuelto) {
        return $usuario;
    }

    $resuelto = true;
    $id = (int) ($_SESSION['usuario_id'] ?? 0);

    if ($id <= 0) {
        return $usuario = null;
    }

    $encontrado = repo_user($id);

    if ($encontrado === null || !($encontrado['activo'] ?? false)) {
        sesion_cerrar();

        return $usuario = null;
    }

    return $usuario = $encontrado;
}

function sesion_hay_usuario(): bool
{
    return sesion_usuario() !== null;
}

/**
 * ¿El usuario de esta sesión puede entrar al panel?
 */
function sesion_es_admin(): bool
{
    return (sesion_usuario()['rol'] ?? '') === 'admin';
}

/* ==========================================================================
   Abrir y cerrar
   ========================================================================== */

/**
 * Abre la sesión de un usuario ya verificado por `repo_login()`.
 *
 * Regenera el id de sesión: si no, quien haya podido fijar la cookie
 * antes del login se queda con una sesión válida después. Es la
 * diferencia entre un login y un login seguro.
 */
function sesion_abrir(array $usuario): void
{
    sesion_iniciar();

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['visto']      = time();

    // El token viejo pertenecía a la sesión anónima.
    unset($_SESSION['csrf']);
}

/**
 * Cierra la sesión y borra la cookie.
 */
function sesion_cerrar(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/* ==========================================================================
   Guardas
   ========================================================================== */

/**
 * Exige sesión. Si no hay, manda a /ingresar y se acuerda a dónde volver.
 */
function sesion_exigir(): array
{
    $usuario = sesion_usuario();

    if ($usuario !== null) {
        return $usuario;
    }

    $_SESSION['volver_a'] = ruta_actual();

    header('Location: ' . url('/ingresar'), true, 302);
    exit;
}

/**
 * Exige rol admin.
 *
 * Quien está logueado pero no es admin recibe un 404, no un 403: un 403
 * confirma que la ruta existe. Para alguien que no tiene por qué saber
 * que hay un panel, esa ruta no existe y punto.
 */
function sesion_exigir_admin(): array
{
    $usuario = sesion_exigir();

    if (($usuario['rol'] ?? '') !== 'admin') {
        router_404();
    }

    return $usuario;
}

/**
 * A dónde volver después de entrar. Sólo rutas internas: si el destino
 * viniera de la URL sin filtrar, sería un redirect abierto listo para
 * usar en un correo de phishing.
 */
function sesion_destino_post_login(): string
{
    $destino = (string) ($_SESSION['volver_a'] ?? '');
    unset($_SESSION['volver_a']);

    if ($destino === '' || !str_starts_with($destino, '/') || str_starts_with($destino, '//')) {
        return url('/cuenta');
    }

    return url($destino);
}

/* ==========================================================================
   CSRF
   ========================================================================== */

/**
 * El token de esta sesión. Uno por sesión, no uno por formulario: con
 * varias pestañas abiertas, un token por formulario invalida el de la
 * pestaña de al lado y la persona pierde lo que escribió.
 */
function csrf_token(): string
{
    sesion_iniciar();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf'];
}

/**
 * El campo oculto que va en todos los formularios POST del sitio.
 */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * ¿El token del POST es el de esta sesión?
 *
 * hash_equals y no ===: la comparación de strings de PHP corta en la
 * primera diferencia, y eso se puede medir.
 */
function csrf_valido(): bool
{
    $enviado = (string) ($_POST['_csrf'] ?? '');
    $guardado = (string) ($_SESSION['csrf'] ?? '');

    return $enviado !== '' && $guardado !== '' && hash_equals($guardado, $enviado);
}

/**
 * Corta el request si el token no es válido. La usan las vistas que
 * reciben POST, antes de mirar cualquier otro campo.
 */
function csrf_exigir(): void
{
    if (csrf_valido()) {
        return;
    }

    http_response_code(419);
    error_log('CSRF inválido en ' . ruta_actual() . ' desde ' . _sesion_ip_texto());

    exit('La sesión expiró o el formulario no es válido. Volvé atrás y probá de nuevo.');
}

/* ==========================================================================
   Límite de intentos de login

   Vive en la base y no en la sesión: quien prueba contraseñas no manda
   la cookie de sesión, así que contarlo ahí no cuenta nada.
   ========================================================================== */

function _sesion_ip_binaria(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $bin = @inet_pton($ip);

    return $bin === false ? "\0" : $bin;
}

function _sesion_ip_texto(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
}

/**
 * ¿Este correo o esta IP se pasaron de intentos fallidos?
 *
 * LA VENTANA LA CALCULA MYSQL, no PHP.
 *
 * `momento` lo escribe MySQL con CURRENT_TIMESTAMP, o sea con el reloj y
 * la zona horaria del servidor de base. Si acá se comparara contra un
 * `date()` de PHP, se estarían mezclando dos relojes: en esta máquina PHP
 * corre en UTC y MySQL en hora local, tres horas de diferencia, y la
 * condición `momento > hace 15 minutos` no se cumplía nunca. Resultado:
 * el contador devolvía siempre 0 y el límite de intentos no bloqueaba a
 * nadie, en silencio.
 *
 * Con NOW() los dos extremos de la comparación salen del mismo reloj y da
 * igual cómo esté configurada cada máquina.
 */
function login_bloqueado(string $email): bool
{
    $porEmail = (int) db_q(
        'SELECT COUNT(*) FROM intentos_login
         WHERE email = ? AND momento > NOW() - INTERVAL ? SECOND',
        [mb_strtolower(trim($email), 'UTF-8'), LOGIN_VENTANA_SEG]
    )->fetchColumn();

    if ($porEmail >= LOGIN_MAX_POR_EMAIL) {
        return true;
    }

    $porIp = (int) db_q(
        'SELECT COUNT(*) FROM intentos_login
         WHERE ip = ? AND momento > NOW() - INTERVAL ? SECOND',
        [_sesion_ip_binaria(), LOGIN_VENTANA_SEG]
    )->fetchColumn();

    return $porIp >= LOGIN_MAX_POR_IP;
}

/**
 * Anota un intento fallido.
 */
function login_anotar_fallo(string $email): void
{
    db_q(
        'INSERT INTO intentos_login (email, ip) VALUES (?, ?)',
        [mb_strtolower(trim($email), 'UTF-8'), _sesion_ip_binaria()]
    );

    /* Limpieza oportunista: sin esto la tabla crece para siempre. Se hace
       de vez en cuando y no en cada fallo para no pagar un DELETE por
       intento. La ventana también la calcula MySQL, por lo mismo. */
    if (random_int(1, 50) === 1) {
        db_q(
            'DELETE FROM intentos_login WHERE momento < NOW() - INTERVAL ? SECOND',
            [LOGIN_VENTANA_SEG * 4]
        );
    }
}

/**
 * Borra los intentos de un correo. Se llama cuando entra bien: si no,
 * alguien que se equivocó siete veces y acertó a la octava sigue con el
 * contador cargado.
 */
function login_limpiar(string $email): void
{
    db_q(
        'DELETE FROM intentos_login WHERE email = ?',
        [mb_strtolower(trim($email), 'UTF-8')]
    );
}
