<?php
/**
 * auth/recuperar.php — pedir un enlace para cambiar la contraseña.
 *
 * ---------------------------------------------------------------------
 * CONTESTA LO MISMO EXISTA O NO LA CUENTA
 *
 * Es lo primero que hay que entender de esta pantalla. Si dijera "no
 * encontramos ese correo", el formulario dejaría de servir para recuperar
 * una contraseña y pasaría a servir para averiguar quién tiene cuenta acá:
 * se prueban mil direcciones y las que no dan error están registradas.
 *
 * Así que el mensaje es siempre "si esa dirección tiene una cuenta, te
 * llega un mail". Es menos cómodo para quien se equivocó de correo y es la
 * única forma de no filtrar la lista de clientes.
 *
 * Por lo mismo, el freno de intentos cuenta también los pedidos que no
 * encontraron cuenta: si sólo contara los que sí, la diferencia de
 * comportamiento volvería a delatar cuáles existen.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

$titulo      = 'Recuperar contraseña';
$descripcion = 'Te mandamos un enlace para elegir una contraseña nueva.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

if (sesion_hay_usuario()) {
    header('Location: ' . url('/cuenta'), true, 303);

    exit;
}

$csrf = sesion_csrf();

$enviado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$email   = $enviado ? trim((string) ($_POST['email'] ?? '')) : '';
$listo   = false;
$error   = null;

if ($enviado) {
    if (!sesion_csrf_valido()) {
        $error = 'El formulario venció. Volvé a intentar.';
    } elseif (sesion_bloqueado()) {
        $error = 'Demasiados intentos. Esperá unos minutos y volvé a probar.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribí un correo válido.';
    } else {
        /* Se cuenta el intento SIEMPRE, encuentre cuenta o no. Ver el
           comentario del encabezado: contar sólo los aciertos delata
           cuáles direcciones existen. */
        sesion_anotar_intento();

        $usuario = null;

        foreach (repo_all_users() as $u) {
            if (mb_strtolower((string) ($u['email'] ?? '')) === mb_strtolower($email)) {
                $usuario = $u;
                break;
            }
        }

        if ($usuario !== null) {
            $token = repo_crear_recuperacion($email);

            if ($token !== null) {
                $enlace = rtrim((string) config('base_url', ''), '/')
                        . url('/recuperar/' . $token);

                correo_enviar(
                    $email,
                    'Cambiar tu contraseña en Rastro Fitness',
                    sprintf(
                        "Hola %s,\n\nPediste cambiar tu contraseña. Entrá acá y elegí una nueva:\n\n%s\n\n"
                        . "El enlace vale una hora y se usa una sola vez.\n\n"
                        . "Si no lo pediste vos, ignorá este mensaje: tu contraseña no cambió\n"
                        . "y nadie puede cambiarla sin este enlace.\n\nRastro Fitness\n",
                        (string) ($usuario['nombre'] ?? ''),
                        $enlace
                    )
                );
            }
        }

        /* Se dice que salió bien pase lo que pase: exista la cuenta, no
           exista, o haya fallado el mail. Cualquier diferencia acá es
           información sobre quién está registrado. */
        $listo = true;
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

            <h1 class="auth__titulo t-display-l">Recuperar contraseña</h1>

            <?php if ($listo): ?>

                <p class="mensaje mensaje--ok t-mono-texto" role="status">
                    <span class="mensaje__marca" aria-hidden="true">✓</span>
                    Si <strong><?= e($email) ?></strong> tiene una cuenta, te mandamos un
                    enlace para elegir una contraseña nueva. Vale una hora.
                </p>

                <p class="auth__ayuda t-body-sm">
                    Revisá también la carpeta de spam. Si no te llega, escribinos
                    y lo resolvemos a mano.
                </p>

                <p class="auth__alterna t-mono-texto">
                    <a class="auth__enlace" href="<?= e(url('/ingresar')) ?>">Volver a ingresar</a>
                </p>

            <?php else: ?>

                <p class="auth__bajada t-body-md">
                    Escribí el correo de tu cuenta y te mandamos un enlace para
                    elegir una contraseña nueva.
                </p>

                <?php if ($error !== null): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        <?= e($error) ?>
                    </p>
                <?php endif; ?>

                <form class="formulario formulario--auth" method="post" action="<?= e(url('/recuperar')) ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                        <input class="campo t-mono-texto" type="email" id="email" name="email"
                               autocomplete="email" required autofocus value="<?= e($email) ?>">
                    </p>

                    <button class="boton boton--acento formulario__enviar" type="submit">
                        <span class="t-mono-label">Mandarme el enlace</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="auth__alterna t-mono-texto">
                    <a class="auth__enlace" href="<?= e(url('/ingresar')) ?>">Volver a ingresar</a>
                </p>

            <?php endif; ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
