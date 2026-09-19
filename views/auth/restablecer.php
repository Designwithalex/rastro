<?php
/**
 * auth/restablecer.php — elegir la contraseña nueva, con el token del mail.
 *
 * El token llega en la URL. Eso es inevitable —es un enlace en un correo—
 * y por eso vale una hora, se usa una sola vez y se quema apenas la
 * contraseña cambia: una URL termina en el historial del navegador, en el
 * log de un proxy y a veces en el Referer de la página siguiente.
 *
 * Lo que se guarda del lado del servidor es el HASH del token, nunca el
 * token: durante esa hora es equivalente a la contraseña, y un archivo con
 * tokens en claro es un archivo con contraseñas en claro
 * (`repo_crear_recuperacion`).
 *
 * UN TOKEN INVÁLIDO Y UNO VENCIDO SE TRATAN IGUAL. No se dice cuál de los
 * dos fue: la diferencia sólo le sirve a quien está probando tokens.
 */

declare(strict_types=1);

$titulo      = 'Elegir contraseña';
$descripcion = 'Elegí una contraseña nueva para tu cuenta.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

$csrf  = sesion_csrf();
$token = (string) ($params['token'] ?? '');
$email = repo_email_de_recuperacion($token);

$enviado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$error   = null;

if ($email !== null && $enviado) {
    $clave  = (string) ($_POST['password'] ?? '');
    $repite = (string) ($_POST['password2'] ?? '');

    if (!sesion_csrf_valido()) {
        $error = 'El formulario venció. Volvé a abrir el enlace del mail.';
    } elseif (mb_strlen($clave) < 8) {
        $error = 'La contraseña tiene que tener al menos 8 caracteres.';
    } elseif ($clave !== $repite) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        $usuario = repo_cambiar_password($email, $clave);

        if ($usuario === null) {
            $error = 'No pudimos guardar la contraseña. Probá de nuevo en un momento.';
        } else {
            /* Se quema el token ANTES de dejar a nadie adentro: si algo
               fallara después, lo que no puede quedar vivo es el enlace. */
            repo_quemar_recuperacion($token);

            /* Y se limpia el freno de intentos: quien acaba de demostrar
               que tiene acceso al correo de la cuenta no es quien estaba
               probando contraseñas. */
            sesion_limpiar_intentos();

            sesion_entrar($usuario);

            header('Location: ' . url('/cuenta'), true, 303);

            exit;
        }
    }
}

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1">
    <div class="auth reticula">

        <figure class="auth__foto">
            <img src="<?= e(asset('img/ambiente/ambiente-discos-en-gimnasio.jpg')) ?>" alt=""
                 loading="lazy" decoding="async">
        </figure>

        <div class="auth__panel">

            <?php if ($email === null): ?>

                <h1 class="auth__titulo t-display-l">El enlace ya no sirve</h1>

                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    Este enlace venció o ya se usó. Los enlaces valen una hora
                    y sirven una sola vez.
                </p>

                <p class="auth__ayuda t-body-sm">
                    Pedí uno nuevo y te lo mandamos al correo de tu cuenta.
                </p>

                <p class="auth__alterna t-mono-texto">
                    <a class="auth__enlace" href="<?= e(url('/recuperar')) ?>">Pedir otro enlace</a>
                </p>

            <?php else: ?>

                <h1 class="auth__titulo t-display-l">Elegí tu contraseña</h1>

                <p class="auth__bajada t-body-md">
                    Para la cuenta <strong><?= e($email) ?></strong>.
                </p>

                <?php if ($error !== null): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        <?= e($error) ?>
                    </p>
                <?php endif; ?>

                <form class="formulario formulario--auth" method="post"
                      action="<?= e(url('/recuperar/' . $token)) ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="password">Contraseña nueva</label>
                        <input class="campo t-mono-texto" type="password" id="password" name="password"
                               autocomplete="new-password" minlength="8" required autofocus>
                        <span class="formulario__ayuda t-mono-texto-sm">Mínimo 8 caracteres.</span>
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="password2">Repetila</label>
                        <input class="campo t-mono-texto" type="password" id="password2" name="password2"
                               autocomplete="new-password" minlength="8" required>
                    </p>

                    <button class="boton boton--acento formulario__enviar" type="submit">
                        <span class="t-mono-label">Guardar y entrar</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
