<?php
/**
 * admin/tablero.php — la primera pantalla del panel.
 *
 * No es un tablero de métricas: es una lista de cosas para hacer. Quien abre
 * esto no viene a mirar gráficos, viene a cargar un producto o a despachar un
 * pedido. Por eso arriba van los avisos —lo que está mal y hay que arreglar—
 * y recién después los números.
 */

declare(strict_types=1);

panel_exigir_sesion();

$productos  = repo_all_products();
$pedidos    = repo_all_orders();
$categorias = repo_categories();
$banners    = repo_all_banners();
$settings   = repo_settings();

$sin_stock = array_values(array_filter(
    $productos,
    static fn (array $p): bool => (int) ($p['stock'] ?? 0) <= 0
));

$sin_foto = array_values(array_filter(
    $productos,
    static fn (array $p): bool => ($p['imagen'] ?? '') === ''
        || str_contains((string) ($p['imagen'] ?? ''), 'sin-foto')
));

$pendientes = array_values(array_filter(
    $pedidos,
    static fn (array $p): bool => in_array($p['estado'] ?? '', ['pendiente', 'pagado', 'preparando'], true)
));

$banners_apagados = count(array_filter(
    $banners,
    static fn (array $b): bool => ($b['activo'] ?? false) !== true
));

$placas_hero = count(array_filter(
    $banners,
    static fn (array $b): bool => ($b['posicion'] ?? '') === 'hero' && ($b['activo'] ?? false) === true
));

/* Los avisos se arman como lista de datos y no como bloques de marcado
   sueltos: así el orden y el "no hay nada pendiente" se resuelven una sola
   vez, y sumar un aviso nuevo es sumar un elemento. */
$avisos = [];

if ($pendientes !== []) {
    $avisos[] = [
        'texto'  => sprintf(
            '%d pedido%s sin despachar.',
            count($pendientes),
            count($pendientes) === 1 ? '' : 's'
        ),
        'enlace' => '/admin/pedidos',
        'accion' => 'Ver pedidos',
    ];
}

if ($sin_stock !== []) {
    $avisos[] = [
        'texto'  => sprintf(
            '%d producto%s sin stock. Siguen publicados y se pueden agregar al carrito.',
            count($sin_stock),
            count($sin_stock) === 1 ? '' : 's'
        ),
        'enlace' => '/admin/productos',
        'accion' => 'Revisar',
    ];
}

if ($sin_foto !== []) {
    $avisos[] = [
        'texto'  => sprintf(
            '%d producto%s sin foto propia. En el catálogo se ven con el marcador gris.',
            count($sin_foto),
            count($sin_foto) === 1 ? '' : 's'
        ),
        'enlace' => '/admin/productos',
        'accion' => 'Cargar fotos',
    ];
}

if ($placas_hero < 2) {
    $avisos[] = [
        'texto'  => 'El carrusel de la portada tiene una sola placa: con una no rota ni muestra los controles.',
        'enlace' => '/admin/banners',
        'accion' => 'Cargar placas',
    ];
}

$titulo = 'Tablero';
$bajada = 'Qué hay para hacer hoy.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php /* ============================================================
         Avisos — lo que hay que atender
         ============================================================ */ ?>
<section class="tablero__avisos" aria-labelledby="avisos-titulo">
    <h2 class="panel-seccion__titulo" id="avisos-titulo">Pendientes</h2>

    <?php if ($avisos === []): ?>
        <p class="panel-vacio">
            No hay nada pendiente. El catálogo está completo y los pedidos, al día.
        </p>
    <?php else: ?>
        <ul class="tablero__lista-avisos">
            <?php foreach ($avisos as $aviso): ?>
                <li class="tablero__aviso">
                    <span class="tablero__aviso-texto"><?= e($aviso['texto']) ?></span>
                    <a class="panel-boton panel-boton--chico" href="<?= e(url($aviso['enlace'])) ?>">
                        <?= e($aviso['accion']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php /* ============================================================
         Cifras
         ============================================================ */ ?>
<section class="tablero__cifras" aria-labelledby="cifras-titulo">
    <h2 class="panel-seccion__titulo" id="cifras-titulo">De un vistazo</h2>

    <ul class="tablero__grilla">
        <li class="tablero__cifra">
            <span class="tablero__cifra-valor"><?= e((string) count($productos)) ?></span>
            <span class="tablero__cifra-rotulo">Productos</span>
        </li>
        <li class="tablero__cifra">
            <span class="tablero__cifra-valor"><?= e((string) count($categorias)) ?></span>
            <span class="tablero__cifra-rotulo">Categorías</span>
        </li>
        <li class="tablero__cifra">
            <span class="tablero__cifra-valor"><?= e((string) count($pedidos)) ?></span>
            <span class="tablero__cifra-rotulo">Pedidos</span>
        </li>
        <li class="tablero__cifra<?= $sin_stock !== [] ? ' tablero__cifra--alerta' : '' ?>">
            <span class="tablero__cifra-valor"><?= e((string) count($sin_stock)) ?></span>
            <span class="tablero__cifra-rotulo">Sin stock</span>
        </li>

        <?php /* El porcentaje de descuento es el dato más importante del
                 sitio: aparece en cada card, en cada ficha y en el carrito
                 (CLAUDE.md §4.3). Va en el tablero para que se vea de entrada
                 cuál está publicado y no haya que entrar a Configuración
                 para acordarse. */ ?>
        <li class="tablero__cifra tablero__cifra--acento">
            <span class="tablero__cifra-valor"><?= e(descuento_global($settings)) ?>%</span>
            <span class="tablero__cifra-rotulo">Descuento por transferencia</span>
            <a class="tablero__cifra-enlace" href="<?= e(url('/admin/configuracion')) ?>">Cambiar</a>
        </li>

        <li class="tablero__cifra">
            <span class="tablero__cifra-valor"><?= e((string) count($banners)) ?></span>
            <span class="tablero__cifra-rotulo">
                Banners<?= $banners_apagados > 0 ? ' · ' . $banners_apagados . ' apagado' . ($banners_apagados === 1 ? '' : 's') : '' ?>
            </span>
        </li>
    </ul>
</section>

<?php /* ============================================================
         Últimos pedidos
         ============================================================ */ ?>
<section class="tablero__pedidos" aria-labelledby="ultimos-titulo">
    <div class="panel-seccion__encabezado">
        <h2 class="panel-seccion__titulo" id="ultimos-titulo">Últimos pedidos</h2>
        <a class="panel-enlace" href="<?= e(url('/admin/pedidos')) ?>">Ver todos</a>
    </div>

    <?php if ($pedidos === []): ?>
        <p class="panel-vacio">Todavía no entró ningún pedido.</p>
    <?php else: ?>
        <div class="panel-tabla-marco">
            <table class="panel-tabla">
                <thead>
                    <tr>
                        <th scope="col">Código</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="panel-tabla__num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($pedidos, 0, 5) as $pedido): ?>
                        <tr>
                            <th scope="row">
                                <a class="panel-enlace" href="<?= e(url('/admin/pedidos/' . ($pedido['codigo'] ?? ''))) ?>">
                                    <?= e((string) ($pedido['codigo'] ?? '')) ?>
                                </a>
                            </th>
                            <td><?= e((string) ($pedido['fecha'] ?? '')) ?></td>
                            <td>
                                <span class="panel-pastilla panel-pastilla--<?= e((string) ($pedido['estado'] ?? 'pendiente')) ?>">
                                    <?= e(panel_estados_pedido()[$pedido['estado'] ?? ''] ?? (string) ($pedido['estado'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="panel-tabla__num"><?= e(moneda($pedido['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
