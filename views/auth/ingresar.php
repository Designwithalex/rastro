<?php
/**
 * auth/ingresar.php — el login.
 *
 * Sigue el frame "Login · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=68-3
 *
 * DE ACÁ SE SALE LOGUEADO. El POST verifica contra `repo_login()`, abre
 * la sesión con `sesion_abrir()` —que regenera el id— y redirige. Si
 * alguien llegó acá desde una página que exigía sesión, vuelve a esa
 * página; si no, va a /cuenta. Y si es admin, al panel.
 *
 * TRES DEFENSAS EN ESTE FORMULARIO
 *
 * 1. Token CSRF. Sin él, un formulario en otro sitio puede loguear a
 *    alguien con una cuenta ajena y hacerle creer que es la suya.
 * 2. Límite de intentos, por correo y por IP (app/sesion.php).
 * 3. El mensaje de error NUNCA dice cuál de los dos campos estuvo mal.
 *    "Ese correo no existe" le confirma a cualquiera qué direcciones
 *    tienen cuenta acá.
 *
 * Se responde con el mismo texto y en el mismo tiempo esté mal el correo
 * o la contraseña: `repo_login()` calcula un hash igual cuando el correo
 * no existe, para que no se note por la demora.
 */

declare(strict_types=1);

/* Quien ya entró no tiene nada que hacer acá. */
if (sesion_hay_usuario()) {
    header('Location: ' . url(sesion_es_admin() ? '/admin' : '/cuenta'), true, 302);
    exit;
}

$titulo      = 'Ingresar';
$descripcion = 'Entrá a tu cuenta para ver tus pedidos y comprar más rápido.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

$email = '';
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $email = trim((string) ($_POST['email'] ?? ''));

    if (login_bloqueado($email)) {
        /* Se dice que está bloqueado y no "contraseña incorrecta": quien
           se equivocó de verdad necesita saber por qué dejó de andar, y a
           quien está probando contraseñas el aviso no le sirve de nada,
           porque ya no puede seguir. */
        $error = 'Demasiados intentos. Esperá unos minutos y probá de nuevo.';
    } else {
        $usuario = repo_login($email, (string) ($_POST['password'] ?? ''));

        if ($usuario === null) {
            login_anotar_fallo($email);
            $error = 'No pudimos entrar con esos datos. Revisá el correo y la contraseña.';
        } else {
            login_limpiar($email);
            sesion_abrir($usuario);

            /* Un admin entra al panel; el resto, a donde venía o a su
               cuenta. sesion_destino_post_login() sólo acepta rutas
               internas: si el destino saliera de la URL sin filtrar,
               sería un redirect abierto listo para un correo falso. */
            $destino = ($usuario['rol'] ?? '') === 'admin'
                ? url('/admin')
                : sesion_destino_post_login();

            header('Location: ' . $destino, true, 302);
            exit;
        }
    }
}

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1">
    <div class="auth reticula">

        <?php /* La foto es decorativa y en celular no se dibuja: ocupa media
                 pantalla y lo que la persona vino a hacer es entrar. */ ?>
        <figure class="auth__foto">
            <img src="<?= e(asset('img/ambiente/ambiente-discos-en-gimnasio.jpg')) ?>" alt=""
                 loading="lazy" decoding="async">
            <figcaption class="auth__claim">
                <span class="auth__claim-texto t-display-m">
                    Tus pedidos, tus datos y tu historial en un solo lugar.
                </span>
                <span class="auth__pie t-mono-label-sm">Fig. 03 · Depósito Rastro</span>
            </figcaption>
        </figure>

        <div class="auth__panel">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <h1 class="auth__titulo t-display-l">Ingresar</h1>
            <p class="auth__bajada t-body-md">
                Entrá con tu correo para ver tus pedidos y comprar más rápido.
            </p>

            <?php if ($error !== null): ?>
                <?php /* Ícono + texto, nunca sólo color: el bordeaux del sitio
                         también es promoción (CLAUDE.md §5.4). */ ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    <?= e($error) ?>
                </p>
            <?php endif; ?>

            <form class="formulario formulario--auth" method="post" action="<?= e(url('/ingresar')) ?>">
                <?= csrf_campo() ?>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                    <input class="campo t-mono-texto" type="email" id="email" name="email"
                           autocomplete="email" value="<?= e($email) ?>" required autofocus>
                </p>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="password">Contraseña</label>
                    <input class="campo t-mono-texto" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </p>

                <?php /* TODO(backend): la recuperación por mail todavía no
                         existe. Se agrega junto con el envío de correo. */ ?>
                <p class="auth__olvide">
                    <span class="t-mono-label-sm">Olvidé mi contraseña</span>
                </p>

                <button class="boton boton--acento formulario__enviar" type="submit">
                    <span class="t-mono-label">Ingresar</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth__alterna t-mono-texto">
                ¿No tenés cuenta?
                <a class="auth__enlace t-mono-label" href="<?= e(url('/registro')) ?>">Crear una</a>
            </p>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
