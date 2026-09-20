<?php
/**
 * auth/ingresar.php — el login.
 *
 * Sigue el frame "Login · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=68-3
 *
 * QUÉ HACE
 *
 * El POST llega a `repo_login()`, que verifica el hash, y si está bien
 * abre la sesión y manda a donde la persona quería ir. Todo eso vive en
 * `app/sesion.php`.
 *
 * LAS TRES DEFENSAS, Y POR QUÉ CADA UNA
 *
 * · Token CSRF. Sin él, un sitio ajeno puede identificar a alguien con una
 *   cuenta que controla el atacante y hacerle comprar ahí sin que note que
 *   cambió de cuenta.
 * · Id de sesión nuevo al entrar (`session_regenerate_id`), contra la
 *   fijación de sesión.
 * · Freno por IP: ocho intentos fallidos cada quince minutos. Por IP y no
 *   por correo a propósito: contar por correo deja que cualquiera bloquee
 *   la cuenta de otro, que es un ataque en sí mismo.
 *
 * NUNCA se dice cuál de los dos campos estuvo mal. "El correo no existe"
 * le confirma a cualquiera qué direcciones tienen cuenta acá. Por la misma
 * razón `repo_login()` gasta el mismo tiempo cuando el correo no existe.
 */

declare(strict_types=1);

$titulo      = 'Ingresar';
$descripcion = 'Entrá a tu cuenta para ver tus pedidos y comprar más rápido.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

/* Quien ya entró no tiene nada que hacer acá. Sin esto, volver atrás
   después de ingresar muestra el formulario otra vez y parece que la
   sesión se perdió. */
if (sesion_hay_usuario()) {
    header('Location: ' . url('/cuenta'), true, 303);

    exit;
}

$enviado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$email   = $enviado ? trim((string) ($_POST['email'] ?? '')) : '';
$usuario = null;
$error   = null;

if ($enviado) {
    if (!sesion_csrf_valido()) {
        // No se dice "CSRF": no le sirve a quien lo lee ni a quien lo intenta.
        $error = 'El formulario venció. Volvé a intentar.';
    } elseif (sesion_bloqueado()) {
        $error = 'Demasiados intentos. Esperá unos minutos y volvé a probar.';
    } else {
        $usuario = repo_login($email, (string) ($_POST['password'] ?? ''));

        if ($usuario === null) {
            sesion_anotar_intento();
            $error = 'No pudimos entrar con esos datos. Revisá el correo y la contraseña.';
        } else {
            sesion_limpiar_intentos();
            sesion_entrar($usuario);

            header('Location: ' . url(sesion_destino()), true, 303);

            exit;
        }
    }
}

/* El token CSRF se acuña ACÁ y no en el formulario: acuñarlo crea la sesión,
   crear la sesión manda una cabecera, y para cuando el formulario se dibuja
   ya salió medio HTML. Se pide antes de abrir el documento y la vista lo
   imprime después. */
$csrf = sesion_csrf();

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
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
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

                <button class="boton boton--acento formulario__enviar" type="submit">
                    <span class="t-mono-label">Ingresar</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>

                <p class="auth__alterna t-mono-texto">
                    <a class="auth__enlace" href="<?= e(url('/recuperar')) ?>">Olvidé mi contraseña</a>
                </p>
            </form>

            <p class="auth__alterna t-mono-texto">
                ¿No tenés cuenta?
                <a class="auth__enlace t-mono-label" href="<?= e(url('/registro')) ?>">Crear una</a>
            </p>

            <?php
            ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
