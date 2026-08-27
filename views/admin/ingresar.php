<?php
/**
 * admin/ingresar.php — la puerta del panel.
 *
 * Es la única pantalla del panel que no exige sesión, por razones obvias.
 */

declare(strict_types=1);

$error = '';

/* Ya identificado: acá no tiene nada que hacer. Sin esto, volver atrás
   después de entrar muestra el formulario de nuevo y da a entender que la
   sesión se perdió. */
if (panel_usuario() !== null) {
    panel_ir('/admin');
}

if (panel_es_post()) {
    /* CSRF también en el login. Sin token, un sitio ajeno puede identificar
       a alguien con una cuenta que el atacante controla y hacerle cargar
       cosas ahí sin que se dé cuenta de que cambió de cuenta. */
    panel_exigir_csrf();

    $email = panel_texto('email');
    $clave = (string) ($_POST['clave'] ?? '');

    if ($email === '' || $clave === '') {
        $error = 'Completá el mail y la contraseña.';
    } elseif (!panel_configurado()) {
        $error = 'El panel todavía no está configurado en este servidor.';
    } elseif (panel_ingresar($email, $clave) === null) {
        /* Un solo mensaje para "no existe" y para "clave equivocada". Decir
           cuál de las dos falló le confirma a quien prueba mails cuál es el
           del administrador. */
        $error = 'Mail o contraseña incorrectos.';
        error_log('panel: intento de ingreso fallido para ' . $email);
    } else {
        $destino = (string) ($_SESSION['panel_destino'] ?? '/admin');
        unset($_SESSION['panel_destino']);

        /* Sólo se vuelve a una ruta del propio panel. `panel_destino` sale
           de la sesión y hoy no lo escribe nadie más, pero un "volver a
           donde ibas" que acepta cualquier destino es una redirección
           abierta esperando a que alguien encuentre cómo sembrarlo. */
        panel_ir(str_starts_with($destino, '/admin') ? $destino : '/admin');
    }
}

$titulo       = 'Ingresar';
$panel_pelado = true;

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<main class="ingreso" id="panel-contenido">
    <div class="ingreso__caja">

        <img class="ingreso__marca"
             src="<?= e(asset('img/marca/rastro-completo-plata-sobre-blanco.png')) ?>"
             alt="Rastro Fitness" width="170" height="60">

        <h1 class="ingreso__titulo">Panel de administración</h1>

        <?php if (!panel_configurado()): ?>

            <?php /* Falta config.php. Se explica cómo resolverlo en vez de
                     mostrar un formulario que no puede funcionar: el que
                     está mirando esto es quien despliega, no un visitante. */ ?>
            <div class="panel-aviso panel-aviso--error" role="alert">
                <p><strong>El panel no está configurado en este servidor.</strong></p>
                <p>
                    Falta <code>app/config.php</code> con <code>panel_email</code> y
                    <code>panel_password_hash</code>. Se copia de
                    <code>app/config.example.php</code>, que explica cómo generar el hash.
                </p>
            </div>

        <?php else: ?>

            <?php if ($error !== ''): ?>
                <p class="panel-aviso panel-aviso--error" role="alert">
                    <span class="panel-aviso__icono" aria-hidden="true">!</span>
                    <?= e($error) ?>
                </p>
            <?php endif; ?>

            <form class="ingreso__formulario" method="post" action="<?= e(url('/admin/ingresar')) ?>">
                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="email">Mail</label>
                    <input class="campo-panel__control" type="email" id="email" name="email"
                           autocomplete="username" required autofocus
                           value="<?= e(panel_texto('email')) ?>">
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="clave">Contraseña</label>
                    <input class="campo-panel__control" type="password" id="clave" name="clave"
                           autocomplete="current-password" required>
                </div>

                <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                    Entrar
                </button>
            </form>

        <?php endif; ?>

        <p class="ingreso__pie">
            <a href="<?= e(url('/')) ?>">Volver al sitio</a>
        </p>

    </div>
</main>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
