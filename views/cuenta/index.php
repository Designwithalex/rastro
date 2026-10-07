<?php
/**
 * cuenta/index.php — Mi cuenta.
 *
 * Sigue los frames "Mi cuenta · Pedidos" y "Mi cuenta · Datos".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=71-320
 *
 * Exige sesión: sin nadie identificado, la ruta manda a /ingresar y vuelve
 * acá después de entrar. El usuario sale de la sesión, no de una constante.
 *
 * PEDIDOS
 *
 * La lista sale de repo_orders(), que junta el mock y lo que compró con el
 * checkout. Cada código lleva a /pedido/{codigo}, que vuelve a comprobar
 * que el pedido sea de quien lo pide (repo_user_order()).
 *
 * MIS DATOS
 *
 * Se editan acá mismo y se guardan con repo_save_user(). El formulario se
 * manda a sí mismo, como /ingresar: si algo está mal, los errores se pintan
 * al lado del campo sin perder lo escrito. Si sale bien, se redirige (PRG)
 * para que recargar no lo reenvíe.
 *
 * Cambiar el correo o la contraseña pide la contraseña actual. Son las dos
 * cosas con las que se entra a la cuenta: sin ese control, alguien que
 * encuentre una sesión abierta en una computadora prestada se queda con la
 * cuenta para siempre. El resto de los datos se cambia sin pedirla.
 *
 * Las secciones se eligen por `?seccion=`, no por ruta, porque el router
 * tiene una sola entrada para /cuenta y las dos son la misma página con
 * distinto panel. Direcciones todavía no está diseñada (PENDIENTES #38).
 */

declare(strict_types=1);

sesion_exigir_usuario();

$usuario = sesion_usuario();

if ($usuario === null) {
    // sesion_exigir_usuario() ya redirigió; esto es para el analizador.
    router_404();
}

$seccion = param('seccion', 'pedidos', ['pedidos', 'datos']);
$pedidos = repo_orders((int) $usuario['id']);

/* ==========================================================================
   Mis datos — guardar
   ========================================================================== */

$direccion_actual = is_array($usuario['direccion'] ?? null) ? $usuario['direccion'] : [];

$datos = [
    'nombre'        => (string) ($usuario['nombre'] ?? ''),
    'apellido'      => (string) ($usuario['apellido'] ?? ''),
    'email'         => (string) ($usuario['email'] ?? ''),
    'telefono'      => (string) ($usuario['telefono'] ?? ''),
    'calle'         => (string) ($direccion_actual['calle'] ?? ''),
    'ciudad'        => (string) ($direccion_actual['ciudad'] ?? ''),
    'provincia'     => (string) ($direccion_actual['provincia'] ?? ''),
    'codigo_postal' => (string) ($direccion_actual['codigo_postal'] ?? ''),
    'empresa'       => (string) ($usuario['empresa'] ?? ''),
    'cuit'          => (string) ($usuario['cuit'] ?? ''),
];

$errores  = [];
$falla    = null;
$guardado = param('guardado') === '1';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $seccion = 'datos';

    foreach (array_keys($datos) as $campo) {
        $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $clave_actual = (string) ($_POST['password_actual'] ?? '');
    $clave_nueva  = (string) ($_POST['password_nueva'] ?? '');

    $cambia_email = mb_strtolower($datos['email']) !== mb_strtolower((string) $usuario['email']);
    $cambia_clave = $clave_nueva !== '';

    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'Escribí tu nombre.';
    }

    if ($datos['apellido'] === '') {
        $errores['apellido'] = 'Escribí tu apellido.';
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Revisá el correo: no parece una dirección válida.';
    } elseif ($cambia_email) {
        foreach (repo_all_users() as $otro) {
            if ((int) $otro['id'] !== (int) $usuario['id']
                && mb_strtolower(trim((string) ($otro['email'] ?? ''))) === mb_strtolower($datos['email'])) {
                $errores['email'] = 'Ya hay otra cuenta con este correo.';
                break;
            }
        }
    }

    if ($datos['telefono'] !== '' && strlen(preg_replace('/\D+/', '', $datos['telefono']) ?? '') < 8) {
        $errores['telefono'] = 'Dejanos un teléfono con característica.';
    }

    if ($datos['provincia'] !== '' && !in_array($datos['provincia'], provincias(), true)) {
        $errores['provincia'] = 'Elegí una provincia de la lista.';
    }

    if ($cambia_clave && mb_strlen($clave_nueva) < 8) {
        $errores['password_nueva'] = 'La contraseña necesita al menos 8 caracteres.';
    }

    if (!sesion_csrf_valido()) {
        $falla = 'El formulario venció. Volvé a intentar.';
    } elseif (($cambia_email || $cambia_clave) && $errores === []) {
        if (sesion_bloqueado()) {
            $falla = 'Demasiados intentos. Esperá unos minutos y volvé a probar.';
        } elseif (repo_login((string) $usuario['email'], $clave_actual) === null) {
            sesion_anotar_intento();
            $errores['password_actual'] = 'Para cambiar el correo o la contraseña, escribí tu contraseña actual.';
        }
    }

    if ($falla === null && $errores === []) {
        $direccion = [
            'calle'         => $datos['calle'],
            'ciudad'        => $datos['ciudad'],
            'provincia'     => $datos['provincia'],
            'codigo_postal' => $datos['codigo_postal'],
        ];

        $cambios = [
            'id'        => (int) $usuario['id'],
            'nombre'    => $datos['nombre'],
            'apellido'  => $datos['apellido'],
            'email'     => $datos['email'],
            'telefono'  => $datos['telefono'],
            // Sin ningún dato de dirección se guarda null, como en el alta:
            // así "Mis datos" vuelve a decir "sin cargar" y no ", , ,".
            'direccion' => implode('', $direccion) === '' ? null : $direccion,
            'empresa'   => $datos['empresa'] !== '' ? $datos['empresa'] : null,
            'cuit'      => $datos['cuit'] !== '' ? $datos['cuit'] : null,
        ];

        if ($cambia_clave) {
            $cambios['password'] = $clave_nueva;
        }

        if (repo_save_user($cambios) === null) {
            $falla = 'No pudimos guardar los cambios. Probá de nuevo en un minuto.';
        } else {
            header('Location: ' . url('/cuenta') . '?seccion=datos&guardado=1', true, 303);

            exit;
        }
    }
}

/** El mensaje de error de un campo, con el mismo marcado que el checkout. */
$error_de = static function (string $campo) use (&$errores): string {
    return isset($errores[$campo])
        ? '<span class="campo__error t-mono-texto-sm" id="error-' . e($campo) . '">'
        . '<span class="mensaje__marca" aria-hidden="true">!</span> ' . e($errores[$campo]) . '</span>'
        : '';
};

/** Los atributos de un campo con error. */
$invalido = static function (string $campo) use (&$errores): string {
    return isset($errores[$campo])
        ? 'aria-invalid="true" aria-describedby="error-' . e($campo) . '"'
        : '';
};

// Antes del head: el token se acuña con la sesión ya abierta, pero igual.
$csrf = sesion_csrf();

$titulo      = 'Mi cuenta';
$descripcion = 'Tus pedidos y tus datos.';
$clase_body  = 'pagina-cuenta';
$estilos     = ['componentes', 'catalogo', 'cuenta', 'checkout'];

require RASTRO_VIEWS . '/layout/head.php';

$secciones = [
    'pedidos' => 'Pedidos',
    'datos'   => 'Mis datos',
];

/* La lista vive en helpers.php porque el panel también la necesita, para
   ofrecer los estados que se pueden elegir. Duplicada, el panel termina
   ofreciendo estados que esta página no sabe dibujar (PENDIENTES #36). */
$estados = estados_pedido();
?>

<main id="contenido" tabindex="-1">

    <div class="contenedor barra-pagina barra-pagina--carrito">
        <div class="barra-pagina__titulo">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span></p>
            <div class="barra-pagina__linea">
                <h1 class="barra-pagina__nombre t-display-l">Mi cuenta</h1>
                <p class="barra-pagina__conteo t-mono-texto">Hola, <?= e($usuario['nombre']) ?></p>
            </div>
        </div>
    </div>

    <div class="cuenta reticula">

        <nav class="cuenta__nav" aria-label="Secciones de la cuenta">
            <ul>
                <?php $n = 0; foreach ($secciones as $clave => $etiqueta): $n++; ?>
                    <li>
                        <a class="cuenta__item <?= $seccion === $clave ? 'es-activo' : '' ?>"
                           href="<?= e(url('/cuenta') . '?seccion=' . $clave) ?>"
                           <?= $seccion === $clave ? 'aria-current="page"' : '' ?>>
                            <span class="cuenta__numero t-mono-label-sm"><?= e(sprintf('%02d', $n)) ?></span>
                            <span class="t-mono-label"><?= e($etiqueta) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php /* Direcciones está en el menú del frame pero no está
                         diseñada, y depende de si un cliente puede tener más de
                         un lugar de entrega (PENDIENTES #38). Hasta que se
                         defina no se dibuja un ítem que no lleva a ningún lado. */ ?>

                <li>
                    <?php /* Un POST y no un enlace: un GET que cierra sesión lo
                             dispara cualquier cosa que precargue enlaces, desde
                             el navegador hasta una imagen remota. Estuvo apagado
                             mientras la ruta no existía; ya existe.

                             El <form> se ve igual que los <a> de arriba: el
                             visitante no tiene por qué notar la diferencia de
                             mecanismo. El CSS está en .cuenta__salir. */ ?>
                    <form class="cuenta__salir" method="post" action="<?= e(url('/salir')) ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <button class="cuenta__item cuenta__item--salir" type="submit">
                            <span class="cuenta__numero t-mono-label-sm"><?= e(sprintf('%02d', $n + 1)) ?></span>
                            <span class="t-mono-label">Cerrar sesión</span>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>

        <section class="cuenta__panel" aria-labelledby="panel-titulo">

            <?php if ($seccion === 'pedidos'): ?>
                <h2 class="cuenta__titulo t-display-m" id="panel-titulo">Pedidos</h2>
                <p class="cuenta__bajada t-body-md">Todo lo que compraste, con su estado de envío.</p>

                <?php if ($pedidos === []): ?>
                    <div class="vacio">
                        <p class="vacio__indice t-mono-label">Sin pedidos</p>
                        <h3 class="t-display-s">Todavía no compraste nada</h3>
                        <p class="vacio__texto t-body-md">Cuando hagas tu primer pedido va a aparecer acá.</p>
                        <div class="vacio__acciones">
                            <a class="boton boton--acento" href="<?= e(url('/catalogo')) ?>">
                                <span class="t-mono-label">Ver el catálogo</span>
                                <span class="boton__flecha" aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="tabla-envoltorio">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th class="t-mono-label-sm" scope="col">Pedido</th>
                                    <th class="t-mono-label-sm" scope="col">Fecha</th>
                                    <th class="t-mono-label-sm" scope="col">Estado</th>
                                    <th class="t-mono-label-sm tabla__num" scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php
                                    $clave  = (string) ($pedido['estado'] ?? '');
                                    $estado = $estados[$clave] ?? ['texto' => $clave, 'clase' => ''];
                                    ?>
                                    <tr>
                                        <th class="t-mono-texto" scope="row">
                                            <a class="cuenta__pedido" href="<?= e(url('/pedido/' . rawurlencode((string) ($pedido['codigo'] ?? '')))) ?>">
                                                #<?= e($pedido['codigo'] ?? '') ?>
                                                <span class="visualmente-oculto">— ver el detalle</span>
                                            </a>
                                        </th>
                                        <td class="t-mono-texto">
                                            <?php /* <time> con la fecha en ISO además de la
                                                     legible: la tabla se puede leer y la fecha
                                                     se puede procesar. */ ?>
                                            <time datetime="<?= e($pedido['fecha'] ?? '') ?>">
                                                <?= e(date('d/m/Y', strtotime((string) ($pedido['fecha'] ?? 'now')))) ?>
                                            </time>
                                        </td>
                                        <td>
                                            <span class="chip-estado <?= e($estado['clase']) ?> t-mono-label-sm">
                                                <?= e($estado['texto']) ?>
                                            </span>
                                        </td>
                                        <td class="tabla__num">
                                            <span class="plata t-precio-sm"><?= e(moneda($pedido['total'] ?? 0)) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php
                    ?>
                <?php endif; ?>

            <?php else: ?>
                <h2 class="cuenta__titulo t-display-m" id="panel-titulo">Mis datos</h2>
                <p class="cuenta__bajada t-body-md">
                    Lo que usamos para facturarte y para entregarte. Con esto completamos
                    el checkout cuando comprás.
                </p>

                <?php if ($guardado): ?>
                    <p class="mensaje mensaje--ok t-mono-texto" role="status">
                        <span class="mensaje__marca" aria-hidden="true">✓</span>
                        Guardamos tus datos.
                    </p>
                <?php endif; ?>

                <?php if ($falla !== null): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        <?= e($falla) ?>
                    </p>
                <?php elseif ($errores !== []): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        Revisá los campos marcados y volvé a intentar.
                    </p>
                <?php endif; ?>

                <form class="formulario formulario--checkout cuenta__formulario" method="post"
                      action="<?= e(url('/cuenta') . '?seccion=datos') ?>" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <fieldset class="formulario__grupo">
                        <legend class="formulario__leyenda t-mono-label">
                            <span class="indice">01</span> Contacto
                        </legend>

                        <div class="formulario__fila">
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="nombre">Nombre</label>
                                <input class="campo t-mono-texto" type="text" id="nombre" name="nombre"
                                       autocomplete="given-name" value="<?= e($datos['nombre']) ?>" <?= $invalido('nombre') ?>>
                                <?= $error_de('nombre') ?>
                            </p>
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="apellido">Apellido</label>
                                <input class="campo t-mono-texto" type="text" id="apellido" name="apellido"
                                       autocomplete="family-name" value="<?= e($datos['apellido']) ?>" <?= $invalido('apellido') ?>>
                                <?= $error_de('apellido') ?>
                            </p>
                        </div>

                        <div class="formulario__fila">
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="email">Correo electrónico</label>
                                <input class="campo t-mono-texto" type="email" id="email" name="email"
                                       autocomplete="email" value="<?= e($datos['email']) ?>" <?= $invalido('email') ?>>
                                <?= $error_de('email') ?>
                                <span class="campo__ayuda t-mono-texto-sm">Es con el que entrás a la cuenta.</span>
                            </p>
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="telefono">Teléfono</label>
                                <input class="campo t-mono-texto" type="tel" id="telefono" name="telefono"
                                       autocomplete="tel" placeholder="11 5555 5555"
                                       value="<?= e($datos['telefono']) ?>" <?= $invalido('telefono') ?>>
                                <?= $error_de('telefono') ?>
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="formulario__grupo">
                        <legend class="formulario__leyenda t-mono-label">
                            <span class="indice">02</span> Dirección de entrega
                        </legend>

                        <p class="formulario__campo">
                            <label class="formulario__etiqueta t-mono-label-sm" for="calle">Calle y número</label>
                            <input class="campo t-mono-texto" type="text" id="calle" name="calle"
                                   autocomplete="street-address" value="<?= e($datos['calle']) ?>">
                        </p>

                        <div class="formulario__fila">
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="ciudad">Localidad</label>
                                <input class="campo t-mono-texto" type="text" id="ciudad" name="ciudad"
                                       autocomplete="address-level2" value="<?= e($datos['ciudad']) ?>">
                            </p>
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="provincia">Provincia</label>
                                <select class="campo t-mono-texto" id="provincia" name="provincia"
                                        autocomplete="address-level1" <?= $invalido('provincia') ?>>
                                    <option value="">Elegí una</option>
                                    <?php foreach (provincias() as $p): ?>
                                        <option value="<?= e($p) ?>" <?= $datos['provincia'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $error_de('provincia') ?>
                            </p>
                            <p class="formulario__campo formulario__campo--corto">
                                <label class="formulario__etiqueta t-mono-label-sm" for="codigo_postal">Código postal</label>
                                <input class="campo t-mono-texto" type="text" id="codigo_postal" name="codigo_postal"
                                       autocomplete="postal-code" inputmode="numeric" value="<?= e($datos['codigo_postal']) ?>">
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="formulario__grupo">
                        <legend class="formulario__leyenda t-mono-label">
                            <span class="indice">03</span> Facturación <span class="campo__opcional">(opcional)</span>
                        </legend>

                        <div class="formulario__fila">
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="empresa">Empresa</label>
                                <input class="campo t-mono-texto" type="text" id="empresa" name="empresa"
                                       autocomplete="organization" value="<?= e($datos['empresa']) ?>">
                            </p>
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="cuit">CUIT</label>
                                <input class="campo t-mono-texto" type="text" id="cuit" name="cuit"
                                       inputmode="numeric" value="<?= e($datos['cuit']) ?>">
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="formulario__grupo">
                        <legend class="formulario__leyenda t-mono-label">
                            <span class="indice">04</span> Contraseña
                        </legend>

                        <p class="formulario__nota t-mono-texto-sm">
                            Dejala vacía si no la querés cambiar. Para cambiar la contraseña o
                            el correo te pedimos la actual.
                        </p>

                        <div class="formulario__fila">
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="password_nueva">
                                    Contraseña nueva <span class="campo__opcional">(opcional)</span>
                                </label>
                                <input class="campo t-mono-texto" type="password" id="password_nueva" name="password_nueva"
                                       autocomplete="new-password" minlength="8" <?= $invalido('password_nueva') ?>>
                                <?= $error_de('password_nueva') ?>
                            </p>
                            <p class="formulario__campo">
                                <label class="formulario__etiqueta t-mono-label-sm" for="password_actual">Contraseña actual</label>
                                <input class="campo t-mono-texto" type="password" id="password_actual" name="password_actual"
                                       autocomplete="current-password" <?= $invalido('password_actual') ?>>
                                <?= $error_de('password_actual') ?>
                            </p>
                        </div>
                    </fieldset>

                    <button class="boton boton--acento cuenta__guardar" type="submit">
                        <span class="t-mono-label">Guardar cambios</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php
            ?>
        </section>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
