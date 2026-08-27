<?php
/**
 * auth/registro.php — el alta de cuenta.
 *
 * Sigue el frame "Registro · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=68-118
 *
 * `repo_register()` valida de verdad —campos vacíos, correo con forma de
 * correo, correo ya tomado, contraseña de menos de 8— y NO persiste. Así
 * que la maqueta muestra los errores reales por campo, que es lo que hay
 * que ver para saber si la pantalla funciona, y avisa que la cuenta
 * todavía no se guarda.
 *
 * EL SELECTOR "PARA UN GIMNASIO O EMPRESA"
 *
 * Está diseñado y marca el rol `mayorista` en el array que devuelve
 * `repo_register()`. Qué pasa después —si esa persona ve precios
 * distintos o si sólo dispara un aviso al equipo— no está definido
 * (PENDIENTES #39). Por eso los campos de empresa y CUIT se piden pero
 * no son obligatorios: hasta que el negocio decida, exigirlos sería
 * inventar una regla.
 */

declare(strict_types=1);

$titulo      = 'Crear cuenta';
$descripcion = 'Creá tu cuenta para seguir tus pedidos y comprar más rápido.';
$clase_body  = 'pagina-auth';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

if (sesion_hay_usuario()) {
    header('Location: ' . url(sesion_es_admin() ? '/admin' : '/cuenta'), true, 302);
    exit;
}

$enviado  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$errores  = [];
$creado   = null;
$valores  = [
    'nombre'     => '',
    'apellido'   => '',
    'email'      => '',
    'telefono'   => '',
    'empresa'    => '',
    'cuit'       => '',
    'es_empresa' => false,
];

if ($enviado) {
    csrf_exigir();

    foreach ($valores as $clave => $_) {
        $valores[$clave] = $clave === 'es_empresa'
            ? !empty($_POST['es_empresa'])
            : trim((string) ($_POST[$clave] ?? ''));
    }

    $resultado = repo_register($valores + ['password' => (string) ($_POST['password'] ?? '')]);
    $errores   = $resultado['errores'];

    if ($resultado['ok']) {
        /* Se entra directo: pedirle que vuelva a escribir el correo y la
           contraseña que acaba de elegir es un paso que no aporta nada.
           El rol lo puso repo_register() del lado del servidor, nunca el
           formulario. */
        sesion_abrir($resultado['usuario']);

        header('Location: ' . url('/cuenta'), true, 302);
        exit;
    }
}

require RASTRO_VIEWS . '/layout/head.php';

$campos = [
    ['nombre' => 'nombre',   'etiqueta' => 'Nombre',             'tipo' => 'text',  'auto' => 'given-name',  'requerido' => true],
    ['nombre' => 'apellido', 'etiqueta' => 'Apellido',           'tipo' => 'text',  'auto' => 'family-name', 'requerido' => true],
    ['nombre' => 'email',    'etiqueta' => 'Correo electrónico', 'tipo' => 'email', 'auto' => 'email',       'requerido' => true],
    ['nombre' => 'telefono', 'etiqueta' => 'Teléfono',           'tipo' => 'tel',   'auto' => 'tel',         'requerido' => false],
];
?>

<main id="contenido" tabindex="-1">
    <div class="auth reticula">

        <figure class="auth__foto">
            <img src="<?= e(asset('img/ambiente/ambiente-barra-en-rack.jpg')) ?>" alt=""
                 loading="lazy" decoding="async">
            <figcaption class="auth__claim">
                <span class="auth__claim-texto t-display-m">
                    Comprá una vez y la próxima ya sabemos a dónde entregarlo.
                </span>
                <span class="auth__pie t-mono-label-sm">Fig. 04 · Sala equipada</span>
            </figcaption>
        </figure>

        <div class="auth__panel">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <h1 class="auth__titulo t-display-l">Crear cuenta</h1>
            <p class="auth__bajada t-body-md">
                Con una cuenta seguís tus pedidos y no volvés a cargar tus datos.
            </p>

            <?php if ($errores !== []): ?>
                <p class="mensaje mensaje--error t-mono-texto" role="alert">
                    <span class="mensaje__marca" aria-hidden="true">!</span>
                    Revisá los campos marcados.
                </p>
            <?php endif; ?>

            <form class="formulario formulario--auth" method="post" action="<?= e(url('/registro')) ?>" novalidate>
                <?= csrf_campo() ?>


                <?php foreach ($campos as $campo): ?>
                    <?php $tiene_error = isset($errores[$campo['nombre']]); ?>
                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="<?= e($campo['nombre']) ?>">
                            <?= e($campo['etiqueta']) ?>
                            <?php if (!$campo['requerido']): ?>
                                <span class="formulario__opcional">(opcional)</span>
                            <?php endif; ?>
                        </label>
                        <input class="campo t-mono-texto <?= $tiene_error ? 'es-error' : '' ?>"
                               type="<?= e($campo['tipo']) ?>"
                               id="<?= e($campo['nombre']) ?>"
                               name="<?= e($campo['nombre']) ?>"
                               autocomplete="<?= e($campo['auto']) ?>"
                               value="<?= e($valores[$campo['nombre']]) ?>"
                               <?= $tiene_error ? 'aria-invalid="true" aria-describedby="error-' . e($campo['nombre']) . '"' : '' ?>
                               <?= $campo['requerido'] ? 'required' : '' ?>>
                        <?php if ($tiene_error): ?>
                            <span class="formulario__error t-mono-texto-sm" id="error-<?= e($campo['nombre']) ?>">
                                <span aria-hidden="true">!</span> <?= e($errores[$campo['nombre']]) ?>
                            </span>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="password">Contraseña</label>
                    <input class="campo t-mono-texto <?= isset($errores['password']) ? 'es-error' : '' ?>"
                           type="password" id="password" name="password" autocomplete="new-password"
                           minlength="8"
                           <?= isset($errores['password']) ? 'aria-invalid="true" aria-describedby="error-password"' : 'aria-describedby="ayuda-password"' ?>
                           required>
                    <?php if (isset($errores['password'])): ?>
                        <span class="formulario__error t-mono-texto-sm" id="error-password">
                            <span aria-hidden="true">!</span> <?= e($errores['password']) ?>
                        </span>
                    <?php else: ?>
                        <span class="formulario__ayuda t-mono-texto-sm" id="ayuda-password">Al menos 8 caracteres.</span>
                    <?php endif; ?>
                </p>

                <?php /* El selector abre dos campos más. Sin JavaScript se ven
                         siempre, que es lo correcto: opcionales y visibles es
                         mejor que escondidos detrás de una casilla que no
                         funciona. */ ?>
                <fieldset class="formulario__grupo">
                    <legend class="formulario__etiqueta t-mono-label-sm">Tipo de cuenta</legend>

                    <label class="opcion">
                        <input type="checkbox" name="es_empresa" value="1"
                               <?= $valores['es_empresa'] ? 'checked' : '' ?>>
                        <span class="opcion__texto t-mono-texto">Es para un gimnasio o empresa</span>
                    </label>

                    <p class="formulario__ayuda t-mono-texto-sm">
                        Si la marcás, te contactamos por el canal mayorista. Los datos de
                        abajo nos ayudan a preparar la cotización.
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="empresa">
                            Empresa o institución <span class="formulario__opcional">(opcional)</span>
                        </label>
                        <input class="campo t-mono-texto" type="text" id="empresa" name="empresa"
                               autocomplete="organization" value="<?= e($valores['empresa']) ?>">
                    </p>

                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="cuit">
                            CUIT <span class="formulario__opcional">(opcional)</span>
                        </label>
                        <input class="campo t-mono-texto" type="text" id="cuit" name="cuit"
                               inputmode="numeric" value="<?= e($valores['cuit']) ?>">
                    </p>
                </fieldset>

                <button class="boton boton--acento formulario__enviar" type="submit">
                    <span class="t-mono-label">Crear cuenta</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth__alterna t-mono-texto">
                ¿Ya tenés cuenta?
                <a class="auth__enlace t-mono-label" href="<?= e(url('/ingresar')) ?>">Ingresar</a>
            </p>

            <?php
            /* Lo único que sigue abierto de esta pantalla: qué pasa después
               con una cuenta marcada como mayorista. El alta ya guarda y ya
               abre sesión. */
            $nota_maqueta = 'La cuenta se crea y se entra al toque. Lo que falta definir es '
                          . 'qué pasa con una marcada como "para un gimnasio o empresa": si '
                          . 've precios distintos o si sólo dispara un aviso al equipo '
                          . '(PENDIENTES #39). Falta también el mail de bienvenida.';
            require RASTRO_VIEWS . '/partials/nota-maqueta.php';
            ?>
        </div>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
