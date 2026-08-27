<?php
/**
 * cuenta/index.php — Mi cuenta.
 *
 * Sigue los frames "Mi cuenta · Pedidos" y "Mi cuenta · Datos".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=71-320
 *
 * NO HAY SESIÓN TODAVÍA, así que esta pantalla muestra SIEMPRE el usuario
 * de prueba id 1. Está dicho en pantalla con la nota de maqueta: una
 * cuenta que muestra los pedidos de alguien sin preguntar quién sos es
 * exactamente lo que no puede pasar cuando esto sea real.
 *
 * TODO(backend) — y es el importante de toda la Fase 2:
 *   1. Exigir sesión. Sin sesión, esta ruta redirige a /ingresar.
 *   2. El usuario sale de la sesión, no de una constante.
 *   3. `repo_order()` hoy no valida quién pide el pedido y el código es
 *      adivinable (RF-año-ddmm). Antes de exponer el detalle hay que
 *      comparar `usuario_id` contra el usuario logueado. Está anotado en
 *      el propio repository.php.
 *
 * Las secciones se eligen por `?seccion=`, no por ruta, porque el router
 * tiene una sola entrada para /cuenta y las tres son la misma página con
 * distinto panel. Direcciones todavía no está diseñada (PENDIENTES #38).
 */

declare(strict_types=1);

/* TODO(backend): esto sale de la sesión. */
const CUENTA_USUARIO_DEMO = 1;

$usuario = repo_user(CUENTA_USUARIO_DEMO);

if ($usuario === null) {
    router_404();
}

$seccion = param('seccion', 'pedidos', ['pedidos', 'datos']);
$pedidos = repo_orders((int) $usuario['id']);

$titulo      = 'Mi cuenta';
$descripcion = 'Tus pedidos y tus datos.';
$clase_body  = 'pagina-cuenta';
$estilos     = ['componentes', 'catalogo', 'cuenta'];

require RASTRO_VIEWS . '/layout/head.php';

$secciones = [
    'pedidos' => 'Pedidos',
    'datos'   => 'Mis datos',
];

/* Los estados son los que hay en el mock. Confirmar cuáles maneja el
   negocio de verdad: si aparecen más, se suma una variante del chip y la
   tabla no se toca (PENDIENTES #36). */
$estados = [
    'en_camino' => ['texto' => 'En camino', 'clase' => 'chip-estado--camino'],
    'entregado' => ['texto' => 'Entregado', 'clase' => 'chip-estado--entregado'],
    'cancelado' => ['texto' => 'Cancelado', 'clase' => 'chip-estado--cancelado'],
];
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
                    <?php /* TODO(backend): cerrar sesión es un POST, no un enlace:
                             un GET lo puede disparar cualquier imagen remota. Se
                             deja como botón deshabilitado hasta que exista. */ ?>
                    <button class="cuenta__item cuenta__item--salir" type="button" disabled>
                        <span class="cuenta__numero t-mono-label-sm"><?= e(sprintf('%02d', $n + 1)) ?></span>
                        <span class="t-mono-label">Cerrar sesión</span>
                    </button>
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
                                        <th class="t-mono-texto" scope="row">#<?= e($pedido['codigo'] ?? '') ?></th>
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
                    $nota_maqueta = 'Los pedidos salen de repo_orders(). El detalle de cada uno '
                                  . 'todavía no tiene pantalla: repo_order() no valida quién lo pide '
                                  . 'y el código es adivinable, así que no se expone hasta que haya '
                                  . 'sesión. El estado es un campo del pedido: si aparecen estados '
                                  . 'nuevos se suma una variante del chip y la tabla no se toca.';
                    require RASTRO_VIEWS . '/partials/nota-maqueta.php';
                    ?>
                <?php endif; ?>

            <?php else: ?>
                <h2 class="cuenta__titulo t-display-m" id="panel-titulo">Mis datos</h2>
                <p class="cuenta__bajada t-body-md">Lo que usamos para facturarte y para entregarte.</p>

                <dl class="ficha-datos reticula reticula--superficie cuenta__datos">
                    <div class="ficha-datos__fila">
                        <dt class="t-mono-label-sm">Nombre</dt>
                        <dd class="t-mono-texto"><?= e($usuario['nombre'] . ' ' . $usuario['apellido']) ?></dd>
                    </div>
                    <div class="ficha-datos__fila">
                        <dt class="t-mono-label-sm">Correo</dt>
                        <dd class="t-mono-texto"><?= e($usuario['email'] ?? '') ?></dd>
                    </div>
                    <div class="ficha-datos__fila">
                        <dt class="t-mono-label-sm">Teléfono</dt>
                        <dd class="t-mono-texto"><?= e($usuario['telefono'] ?? '—') ?></dd>
                    </div>
                    <div class="ficha-datos__fila">
                        <dt class="t-mono-label-sm">Dirección</dt>
                        <dd class="t-mono-texto">
                            <?php if (!empty($usuario['direccion'])): ?>
                                <?= e(implode(', ', array_filter([
                                    $usuario['direccion']['calle'] ?? null,
                                    $usuario['direccion']['ciudad'] ?? null,
                                    $usuario['direccion']['provincia'] ?? null,
                                    $usuario['direccion']['codigo_postal'] ?? null,
                                ]))) ?>
                            <?php else: ?>
                                <span class="marcador t-mono-label-sm">[ sin cargar ]</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if (!empty($usuario['empresa'])): ?>
                        <div class="ficha-datos__fila">
                            <dt class="t-mono-label-sm">Empresa</dt>
                            <dd class="t-mono-texto">
                                <?= e($usuario['empresa']) ?>
                                <?php if (!empty($usuario['cuit'])): ?> · CUIT <?= e($usuario['cuit']) ?><?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php
                $nota_maqueta = 'Los datos salen de repo_user(). Falta el formulario de edición '
                              . 'y que el backend lo guarde.';
                require RASTRO_VIEWS . '/partials/nota-maqueta.php';
                ?>
            <?php endif; ?>

            <?php
            $nota_maqueta = 'No hay sesión todavía: esta pantalla muestra siempre el usuario de '
                          . 'prueba (demo@rastrofitness.com.ar). Cuando entre la sesión, /cuenta '
                          . 'tiene que redirigir a /ingresar si no hay nadie logueado.';
            require RASTRO_VIEWS . '/partials/nota-maqueta.php';
            ?>
        </section>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
