<?php
/**
 * admin/configuracion.php — los ajustes del sitio.
 *
 * Sigue el frame "Admin · Configuración".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=84-550
 *
 * QUÉ SE PUEDE EDITAR lo decide `repo_settings_editables()`, no esta
 * vista: agregar un ajuste es agregarlo a esa lista y aparece solo acá.
 * Un POST con una clave que no esté en la lista no crea un ajuste nuevo.
 *
 * EL PORCENTAJE DE DESCUENTO ES EL CAMPO MÁS IMPORTANTE DEL PANEL.
 * Aparece en cada card, en cada ficha, en el carrito, en la banda de la
 * home y en los términos. Se escribe una sola vez y acá; por eso el
 * formulario lo dice y por eso los banners usan {descuento} en vez del
 * número.
 */

declare(strict_types=1);

$admin_titulo  = 'Configuración';
$admin_seccion = 'configuracion';

require RASTRO_VIEWS . '/admin/_guard.php';

$editables = repo_settings_editables();
$errores   = [];
$valores   = repo_settings();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    $enviados = [];
    foreach (array_keys($editables) as $clave) {
        $enviados[$clave] = trim((string) ($_POST[$clave] ?? ''));
    }

    $resultado = repo_settings_guardar($enviados);
    $errores   = $resultado['errores'];

    if ($resultado['ok']) {
        admin_avisar('Configuración guardada. Los cambios ya se ven en el sitio.');

        header('Location: ' . url('/admin/configuracion'), true, 303);
        exit;
    }

    // Con errores se redibuja con lo que la persona escribió, no con lo
    // que hay en la base.
    $valores = $enviados + $valores;
}

/* La cabecera va recién acá, no arriba: el bloque de POST necesita poder
   redirigir, y un header() después del primer byte de HTML no sale. */
require RASTRO_VIEWS . '/admin/_cabecera.php';
?>

<form class="form-admin" method="post" action="<?= e(url('/admin/configuracion')) ?>" data-avisar-cambios>
    <?= csrf_campo() ?>

    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Precios y envíos</legend>

        <div class="form-admin__grilla">
            <?php foreach (['descuento_transferencia_pct', 'envio_gratis_desde'] as $clave): ?>
                <?php
                $campo = $editables[$clave];
                $error = $errores[$clave] ?? null;
                ?>
                <p class="form-admin__campo">
                    <label class="form-admin__etiqueta t-mono-label-sm" for="<?= e($clave) ?>">
                        <?= e($campo['etiqueta']) ?>
                        <?php if (!empty($campo['sufijo'])): ?>
                            <span class="form-admin__ayuda">(<?= e($campo['sufijo']) ?>)</span>
                        <?php endif; ?>
                    </label>
                    <input class="campo t-mono-texto <?= $error ? 'es-error' : '' ?>"
                           type="text" inputmode="decimal" id="<?= e($clave) ?>" name="<?= e($clave) ?>"
                           value="<?= e((string) ($valores[$clave] ?? '')) ?>">
                    <?php if ($error): ?>
                        <span class="form-admin__error t-mono-texto-sm"><span aria-hidden="true">!</span> <?= e($error) ?></span>
                    <?php elseif (!empty($campo['ayuda'])): ?>
                        <span class="form-admin__ayuda t-mono-texto-sm"><?= e($campo['ayuda']) ?></span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>

        <?php /* Se muestra la cuenta hecha con un precio de ejemplo. Un
                 porcentaje suelto es abstracto; ver que 100.000 pasa a
                 85.000 no lo es, y evita el error de cargar 0,15. */ ?>
        <p class="form-admin__ayuda t-mono-texto-sm">
            Con el valor actual, un producto de <?= e(moneda(100000)) ?> se muestra a
            <?= e(moneda((int) round(100000 * (100 - numero_decimal($valores['descuento_transferencia_pct'] ?? 0)) / 100))) ?>
            por transferencia.
        </p>
    </fieldset>

    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Contacto</legend>

        <div class="form-admin__grilla">
            <?php foreach (['whatsapp', 'email', 'horario', 'instagram'] as $clave): ?>
                <?php $campo = $editables[$clave]; ?>
                <p class="form-admin__campo">
                    <label class="form-admin__etiqueta t-mono-label-sm" for="<?= e($clave) ?>">
                        <?= e($campo['etiqueta']) ?>
                    </label>
                    <input class="campo t-mono-texto" type="text" id="<?= e($clave) ?>" name="<?= e($clave) ?>"
                           value="<?= e((string) ($valores[$clave] ?? '')) ?>">
                    <?php if (!empty($campo['ayuda'])): ?>
                        <span class="form-admin__ayuda t-mono-texto-sm"><?= e($campo['ayuda']) ?></span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="whatsapp_mensaje">
                    <?= e($editables['whatsapp_mensaje']['etiqueta']) ?>
                </label>
                <input class="campo t-mono-texto" type="text" id="whatsapp_mensaje" name="whatsapp_mensaje"
                       value="<?= e((string) ($valores['whatsapp_mensaje'] ?? '')) ?>">
                <span class="form-admin__ayuda t-mono-texto-sm"><?= e($editables['whatsapp_mensaje']['ayuda']) ?></span>
            </p>
        </div>

        <?php if (trim((string) ($valores['whatsapp'] ?? '')) === ''): ?>
            <p class="form-admin__error t-mono-texto-sm">
                <span aria-hidden="true">!</span>
                Sin número cargado, el sitio esconde todos los botones de WhatsApp.
                Es a propósito: un enlace a wa.me sin destinatario lleva a la home de
                WhatsApp, que es peor que no ofrecer el botón.
            </p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Datos fiscales</legend>

        <div class="form-admin__grilla">
            <?php foreach (['razon_social', 'cuit'] as $clave): ?>
                <?php $campo = $editables[$clave]; ?>
                <p class="form-admin__campo">
                    <label class="form-admin__etiqueta t-mono-label-sm" for="<?= e($clave) ?>">
                        <?= e($campo['etiqueta']) ?>
                    </label>
                    <input class="campo t-mono-texto" type="text" id="<?= e($clave) ?>" name="<?= e($clave) ?>"
                           value="<?= e((string) ($valores[$clave] ?? '')) ?>">
                    <?php if (!empty($campo['ayuda'])): ?>
                        <span class="form-admin__ayuda t-mono-texto-sm"><?= e($campo['ayuda']) ?></span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="form-admin__acciones">
        <button class="boton boton--acento" type="submit">
            <span class="t-mono-label">Guardar configuración</span>
            <span class="boton__flecha" aria-hidden="true">→</span>
        </button>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
