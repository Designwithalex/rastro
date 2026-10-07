<?php
/**
 * pedido/consulta.php — ver un pedido sin tener cuenta.
 *
 * Quien compró sin registrarse no tiene "Mis pedidos". Lo que sí tiene es
 * el código del pedido —está en la pantalla de retorno y en el mail de
 * Mercado Pago como referencia— y el correo con el que compró. Con los dos
 * juntos se abre el detalle.
 *
 * QUÉ PROTEGE ESTO
 *
 * El detalle muestra nombre, dirección y teléfono. El código solo no
 * alcanza para verlo (es corto y se dicta por WhatsApp), el correo solo
 * tampoco (es público). `repo_guest_order()` exige los dos, y acá se
 * frena por IP con el mismo contador que /ingresar: ocho intentos
 * fallidos cada quince minutos.
 *
 * Nunca se dice cuál de los dos estuvo mal. "Ese código no existe" sería
 * una forma de enumerar pedidos.
 *
 * Si sale bien, el código queda anotado en la sesión y se redirige a
 * /pedido/{codigo}: así recargar la página no reenvía el formulario, y el
 * enlace se puede volver a abrir mientras dure la sesión.
 */

declare(strict_types=1);

$titulo      = 'Consultar un pedido';
$descripcion = 'Mirá el estado de tu pedido con el código y el correo con el que compraste.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

$enviado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// El código llega también por GET, desde el detalle cuando no hay permiso:
// así la persona sólo tiene que escribir el correo.
$codigo = strtoupper(trim((string) ($enviado ? ($_POST['codigo'] ?? '') : param('codigo'))));
$email  = $enviado ? trim((string) ($_POST['email'] ?? '')) : '';
$error  = null;

/* Con sesión, el correo ya se sabe. Se completa para no pedirlo dos veces. */
$usuario = sesion_usuario();

if (!$enviado && $usuario !== null) {
    $email = (string) ($usuario['email'] ?? '');
}

if ($enviado) {
    if (!sesion_csrf_valido()) {
        $error = 'El formulario venció. Volvé a intentar.';
    } elseif (sesion_bloqueado()) {
        $error = 'Demasiados intentos. Esperá unos minutos y volvé a probar.';
    } else {
        $pedido = repo_guest_order($codigo, $email);

        if ($pedido === null) {
            sesion_anotar_intento();
            $error = 'No encontramos un pedido con ese código y ese correo. Revisá los dos datos.';
        } else {
            sesion_desbloquear_pedido((string) $pedido['codigo']);

            header('Location: ' . url('/pedido/' . rawurlencode((string) $pedido['codigo'])), true, 303);

            exit;
        }
    }
}

// Antes del head: acuñar el token abre la sesión, y eso es una cabecera.
$csrf = sesion_csrf();

require RASTRO_VIEWS . '/layout/head.php';
?>

<main id="contenido" tabindex="-1">
    <div class="auth reticula">

        <figure class="auth__foto">
            <img src="<?= e(asset('img/ambiente/ambiente-discos-en-gimnasio.jpg')) ?>" alt=""
                 loading="lazy" decoding="async">
            <figcaption class="auth__claim">
                <span class="auth__claim-texto t-display-m">
                    Tu pedido, con o sin cuenta.
                </span>
                <span class="auth__pie t-mono-label-sm">Fig. 03 · Depósito Rastro</span>
            </figcaption>
        </figure>

        <div class="auth__panel">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <h1 class="auth__titulo t-display-l">Consultar un pedido</h1>
            <p class="auth__bajada t-body-md">
                Escribí el código del pedido —empieza con RF— y el correo con el que
                compraste.
            </p>

            <?php if ($error !== null): ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    <?= e($error) ?>
                </p>
            <?php endif; ?>

            <form class="formulario formulario--auth" method="post" action="<?= e(url('/pedido')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="codigo">Código de pedido</label>
                    <input class="campo t-mono-texto" type="text" id="codigo" name="codigo"
                           value="<?= e($codigo) ?>" placeholder="RF-2026-XXXX"
                           autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                </p>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                    <input class="campo t-mono-texto" type="email" id="email" name="email"
                           autocomplete="email" value="<?= e($email) ?>" required>
                </p>

                <button class="boton boton--acento formulario__enviar" type="submit">
                    <span class="t-mono-label">Ver el pedido</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>
            </form>

            <?php if ($usuario === null): ?>
                <p class="auth__alterna t-mono-texto">
                    ¿Tenés cuenta?
                    <a class="auth__enlace t-mono-label" href="<?= e(url('/ingresar')) ?>">Ingresá</a>
                    y vas a ver todos tus pedidos juntos.
                </p>
            <?php else: ?>
                <p class="auth__alterna t-mono-texto">
                    Los pedidos que hiciste con tu cuenta están en
                    <a class="auth__enlace t-mono-label" href="<?= e(url('/cuenta')) ?>">Mis pedidos</a>.
                </p>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
