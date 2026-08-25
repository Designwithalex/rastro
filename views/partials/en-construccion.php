<?php
/**
 * partials/en-construccion.php — ANDAMIO DE LA FASE 2.
 *
 * La ruta ya está resuelta pero la vista todavía no existe. En vez de un
 * error en blanco, muestra la cabecera y el pie reales y dice qué archivo
 * falta. Este archivo y el bloque .andamio de layout.css se borran cuando
 * entren todas las páginas.
 */

declare(strict_types=1);

$vista_pendiente = $vista_pendiente ?? 'desconocida';

$titulo      = 'Página en construcción';
$descripcion = 'Esta sección todavía se está maquetando.';
$clase_body  = 'pagina-andamio';

require RASTRO_VIEWS . '/layout/head.php';
?>

<main class="andamio" id="contenido">
    <div class="contenedor andamio__caja">
        <p class="andamio__indice t-mono-label">[ Fase 2 · en construcción ]</p>
        <h1 class="andamio__titulo t-display-l">La base ya está</h1>
        <p class="andamio__texto t-body-md">
            La ruta <code class="t-mono-dato"><?= e(ruta_actual()) ?></code> está resuelta por el router,
            pero la vista todavía no se maquetó.
        </p>
        <dl class="andamio__ficha reticula">
            <div class="andamio__fila">
                <dt class="t-mono-label">Vista esperada</dt>
                <dd class="t-mono-texto">views/<?= e($vista_pendiente) ?>.php</dd>
            </div>
            <div class="andamio__fila">
                <dt class="t-mono-label">Ruta</dt>
                <dd class="t-mono-texto"><?= e(ruta_actual()) ?></dd>
            </div>
            <div class="andamio__fila">
                <dt class="t-mono-label">Estado</dt>
                <dd class="t-mono-texto"><?= e((string) http_response_code()) ?></dd>
            </div>
        </dl>
        <a class="andamio__volver t-mono-label" href="<?= e(url('/')) ?>">Volver al inicio</a>
    </div>
</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
