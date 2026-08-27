<?php
/**
 * auth/ingresar.php — el login.
 *
 * Sigue el frame "Login · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=68-3
 *
 * QUÉ HACE Y QUÉ NO
 *
 * El POST llega hasta `repo_login()`, que verifica el hash de verdad, y
 * la vista dibuja el resultado. Lo que NO hace es abrir sesión: no hay
 * `session_start()` en ningún lado del proyecto todavía. Un login que
 * dice "listo" y no deja a nadie adentro sería peor que uno que dice qué
 * le falta, así que lo dice.
 *
 * TODO(backend): abrir la sesión, regenerar el id de sesión, sumar un
 * token CSRF al formulario y límite de intentos por IP y por correo.
 * `repo_login()` ya gasta el mismo tiempo cuando el correo no existe,
 * para no filtrar qué direcciones están registradas.
 *
 * NUNCA se dice cuál de los dos campos estuvo mal. "El correo no existe"
 * le confirma a cualquiera qué direcciones tienen cuenta acá.
 */

declare(strict_types=1);

$titulo      = 'Ingresar';
$descripcion = 'Entrá a tu cuenta para ver tus pedidos y comprar más rápido.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

$enviado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$email   = $enviado ? trim((string) ($_POST['email'] ?? '')) : '';
$usuario = null;
$error   = null;

if ($enviado) {
    $usuario = repo_login($email, (string) ($_POST['password'] ?? ''));

    if ($usuario === null) {
        $error = 'No pudimos entrar con esos datos. Revisá el correo y la contraseña.';
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
            <?php elseif ($usuario !== null): ?>
                <p class="mensaje mensaje--ok t-mono-texto" role="status">
                    <span class="mensaje__marca" aria-hidden="true">✓</span>
                    Los datos son correctos, <?= e($usuario['nombre']) ?>. Falta que el
                    backend abra la sesión: por eso todavía no entrás a tu cuenta.
                </p>
            <?php endif; ?>

            <form class="formulario formulario--auth" method="post" action="<?= e(url('/ingresar')) ?>">
                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                    <input class="campo t-mono-texto" type="email" id="email" name="email"
                           autocomplete="email" value="<?= e($email) ?>" required>
                </p>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="password">Contraseña</label>
                    <input class="campo t-mono-texto" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </p>

                <?php /* TODO(backend): esta ruta todavía no existe. Se agrega al
                         router junto con el envío del mail de recuperación. */ ?>
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

            <?php
            $nota_maqueta = 'El backend conecta la sesión, el token CSRF y el límite de '
                          . 'intentos. La verificación de la contraseña ya funciona contra '
                          . 'data/users.json. Los estados de error del campo están resueltos '
                          . 'en el componente Input de Figma.';
            require RASTRO_VIEWS . '/partials/nota-maqueta.php';
            ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
