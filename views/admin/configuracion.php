<?php
/**
 * admin/configuracion.php — los datos que atraviesan todo el sitio.
 *
 * El campo más importante del panel está en esta pantalla: el porcentaje de
 * descuento por transferencia. Aparece en cada card del catálogo, en cada
 * ficha, en el carrito y en la franja de la portada, y sale de un solo lugar
 * (CLAUDE.md §4.3). Cambiarlo acá lo cambia en todo el sitio de una; por eso
 * la pantalla muestra un ejemplo con un precio real antes de guardar, para
 * que nadie descubra un 50% mal tipeado mirando la portada.
 */

declare(strict_types=1);

panel_exigir_sesion();

$errores = [];

$prueba_correo = null;

if (panel_es_post()) {
    panel_exigir_csrf();

    /* La prueba de correo comparte pantalla con la configuración pero es
       otra acción: no guarda nada. Se distingue por el nombre del botón y
       corta acá, antes de tocar settings. */
    if (panel_texto('accion') === 'probar_correo') {
        $destino = panel_texto('destino_prueba');

        if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            panel_ir_con_aviso('/admin/configuracion', 'error', 'Escribí un correo válido para la prueba.');
        }

        $ok = correo_enviar(
            $destino,
            'Prueba de correo de rastrofitness.com',
            sprintf(
                "Este es un mail de prueba enviado desde el panel de Rastro Fitness.\n\n"
                . "Fecha: %s\nRemitente: %s\nServidor: %s\n\n"
                . "Si te llegó, el sitio puede avisar por mail cuando alguien pide un\n"
                . "arrepentimiento. Si llegó a spam, falta configurar DKIM en hPanel.\n",
                date('d/m/Y H:i'),
                correo_remitente(),
                (string) ($_SERVER['SERVER_NAME'] ?? '')
            )
        );

        panel_ir_con_aviso(
            '/admin/configuracion',
            $ok ? 'ok' : 'error',
            $ok
                ? 'El servidor aceptó el mensaje. Revisá la bandeja de ' . $destino
                  . ' y también la carpeta de spam: que lo acepte no garantiza que llegue.'
                : 'El servidor rechazó el envío. El sitio no puede mandar mails así como está.'
        );
    }

    $descuento = panel_entero('descuento_transferencia_pct', 0) ?? 0;

    if ($descuento < 0 || $descuento > 90) {
        $errores['descuento_transferencia_pct'] = 'El descuento va entre 0 y 90. Un 100 regalaría la mercadería.';
    }

    $envio = panel_entero('envio_gratis_desde', 0) ?? 0;

    if ($envio < 0) {
        $errores['envio_gratis_desde'] = 'No puede ser negativo.';
    }

    $email = panel_texto('email');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'Ese mail no tiene forma de mail.';
    }

    if ($errores === []) {
        $guardado = repo_save_settings([
            'descuento_transferencia_pct' => $descuento,
            'whatsapp'                    => panel_texto('whatsapp'),
            'whatsapp_mensaje'            => panel_texto('whatsapp_mensaje'),
            'email'                       => $email,
            'horario'                     => panel_texto('horario'),
            'envio_gratis_desde'          => $envio,
            'razon_social'                => panel_texto('razon_social'),
            'cuit'                        => panel_texto('cuit'),
            'instagram'                   => panel_texto('instagram'),
        ]);

        panel_ir_con_aviso(
            '/admin/configuracion',
            $guardado !== null ? 'ok' : 'error',
            $guardado !== null
                ? 'Configuración guardada. Los cambios ya se ven en el sitio.'
                : 'No se pudo guardar.'
        );
    }
}

$settings = repo_settings();

/* Con errores gana lo que mandaron, para no perder la carga. */
if ($errores !== []) {
    $settings = array_replace($settings, [
        'descuento_transferencia_pct' => panel_entero('descuento_transferencia_pct', 0) ?? 0,
        'whatsapp'                    => panel_texto('whatsapp'),
        'whatsapp_mensaje'            => panel_texto('whatsapp_mensaje'),
        'email'                       => panel_texto('email'),
        'horario'                     => panel_texto('horario'),
        'envio_gratis_desde'          => panel_entero('envio_gratis_desde', 0) ?? 0,
        'razon_social'                => panel_texto('razon_social'),
        'cuit'                        => panel_texto('cuit'),
        'instagram'                   => panel_texto('instagram'),
    ]);
}

/* El ejemplo se calcula con el mismo helper que el sitio, sobre un precio
   redondo. No es un número inventado para la pantalla: es exactamente la
   cuenta que va a ver el visitante. */
$ejemplo = precio_con_descuento(['precio_lista' => 100000], $settings);

$whatsapp_link = whatsapp_link($settings);

$titulo = 'Configuración';
$bajada = 'Datos que aparecen en todo el sitio.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($errores !== []): ?>
    <p class="panel-aviso panel-aviso--error" role="alert">
        <span class="panel-aviso__icono" aria-hidden="true">!</span>
        No se guardó: revisá los campos marcados.
    </p>
<?php endif; ?>

<form class="panel-formulario" method="post" action="<?= e(url('/admin/configuracion')) ?>">
    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

    <div class="panel-columnas">
        <div class="panel-columnas__principal">

            <fieldset class="panel-grupo panel-grupo--destacado">
                <legend class="panel-grupo__titulo">Precio y descuento</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="descuento_transferencia_pct">
                        Descuento por transferencia o efectivo (%)
                    </label>
                    <input class="campo-panel__control campo-panel__control--grande<?= isset($errores['descuento_transferencia_pct']) ? ' es-error' : '' ?>"
                           type="text" inputmode="numeric"
                           id="descuento_transferencia_pct" name="descuento_transferencia_pct"
                           value="<?= e((string) (int) ($settings['descuento_transferencia_pct'] ?? 0)) ?>">
                    <?php if (isset($errores['descuento_transferencia_pct'])): ?>
                        <p class="campo-panel__error"><?= e($errores['descuento_transferencia_pct']) ?></p>
                    <?php endif; ?>
                </div>

                <p class="panel-previa panel-previa--grande">
                    Con este número, un producto de <strong><?= e(moneda(100000)) ?></strong>
                    se publica a <strong><?= e(moneda($ejemplo['con_descuento'])) ?></strong>
                    pagando por transferencia.
                </p>

                <p class="campo-panel__ayuda">
                    El precio que se carga en cada producto es el de Mercado Pago. Este porcentaje
                    es el que se le descuenta y se muestra al lado, en todo el sitio.
                    Un producto puede tener su propio descuento y pisar este.
                </p>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="envio_gratis_desde">Envío sin cargo desde</label>
                    <input class="campo-panel__control<?= isset($errores['envio_gratis_desde']) ? ' es-error' : '' ?>"
                           type="text" inputmode="numeric" id="envio_gratis_desde" name="envio_gratis_desde"
                           value="<?= e((string) (int) ($settings['envio_gratis_desde'] ?? 0)) ?>">
                    <p class="campo-panel__ayuda">
                        En pesos. Hoy: <?= e(moneda($settings['envio_gratis_desde'] ?? 0)) ?>.
                    </p>
                    <?php if (isset($errores['envio_gratis_desde'])): ?>
                        <p class="campo-panel__error"><?= e($errores['envio_gratis_desde']) ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Contacto</legend>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="whatsapp">WhatsApp</label>
                    <input class="campo-panel__control" type="text" id="whatsapp" name="whatsapp"
                           value="<?= e((string) ($settings['whatsapp'] ?? '')) ?>"
                           placeholder="+54 9 351 000 0000">
                    <p class="campo-panel__ayuda">
                        <?php /* Sin número cargado, el sitio directamente NO
                                 dibuja los botones de WhatsApp: un wa.me sin
                                 destinatario lleva a la home de WhatsApp, que
                                 es peor que no ofrecerlo (helpers.php). */ ?>
                        <?php if ($whatsapp_link === null): ?>
                            <strong>Sin un número válido no se muestran los botones de WhatsApp en el sitio.</strong>
                        <?php else: ?>
                            Los botones del sitio apuntan a este número.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="whatsapp_mensaje">Mensaje que viene escrito</label>
                    <input class="campo-panel__control" type="text" id="whatsapp_mensaje" name="whatsapp_mensaje"
                           value="<?= e((string) ($settings['whatsapp_mensaje'] ?? '')) ?>">
                    <p class="campo-panel__ayuda">
                        Es lo que aparece ya escrito cuando alguien abre el chat desde el sitio.
                    </p>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="email">Mail de ventas</label>
                    <input class="campo-panel__control<?= isset($errores['email']) ? ' es-error' : '' ?>"
                           type="email" id="email" name="email"
                           value="<?= e((string) ($settings['email'] ?? '')) ?>">
                    <?php if (isset($errores['email'])): ?>
                        <p class="campo-panel__error"><?= e($errores['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="horario">Horario de atención</label>
                    <input class="campo-panel__control" type="text" id="horario" name="horario"
                           value="<?= e((string) ($settings['horario'] ?? '')) ?>">
                </div>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="instagram">Instagram</label>
                    <input class="campo-panel__control" type="url" id="instagram" name="instagram"
                           value="<?= e((string) ($settings['instagram'] ?? '')) ?>">
                </div>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Datos fiscales</legend>

                <div class="panel-fila">
                    <div class="campo-panel campo-panel--crece">
                        <label class="campo-panel__rotulo" for="razon_social">Razón social</label>
                        <input class="campo-panel__control" type="text" id="razon_social" name="razon_social"
                               value="<?= e((string) ($settings['razon_social'] ?? '')) ?>">
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="cuit">CUIT</label>
                        <input class="campo-panel__control campo-panel__control--mono" type="text"
                               id="cuit" name="cuit"
                               value="<?= e((string) ($settings['cuit'] ?? '')) ?>">
                    </div>
                </div>

                <p class="campo-panel__ayuda">
                    Salen en el pie y en las páginas legales, que son obligatorias por la ley
                    de defensa del consumidor.
                </p>
            </fieldset>
        </div>

        <aside class="panel-columnas__lateral">
            <div class="panel-acciones panel-acciones--pegada">
                <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                    Guardar configuración
                </button>
                <p class="panel-nota">
                    Los cambios se ven en el sitio apenas se guardan. No hay que publicar nada.
                </p>
            </div>
        </aside>
    </div>
</form>

<?php /* ============================================================
         Prueba de correo

         Va en un formulario APARTE del de configuración: no se pueden
         anidar formularios, y además esto no guarda nada, manda un mail.

         Existe porque `mail()` miente a medias: devuelve true cuando el
         servidor ACEPTÓ el mensaje, no cuando llegó. La única forma de
         saber si el sitio puede avisar por mail es mandarse uno y mirar
         la bandeja — y hasta ahora eso requería entrar por SSH.
         ============================================================ */ ?>
<section class="panel-bloque" aria-labelledby="correo-titulo">
    <div class="panel-seccion__encabezado">
        <div>
            <h2 class="panel-seccion__titulo" id="correo-titulo">Probar el correo</h2>
            <p class="panel-seccion__bajada">
                El sitio avisa por mail cuando alguien pide un arrepentimiento.
                Acá se comprueba que pueda hacerlo.
            </p>
        </div>
    </div>

    <form class="panel-alta__formulario panel-linea" method="post"
          action="<?= e(url('/admin/configuracion')) ?>">
        <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
        <input type="hidden" name="accion" value="probar_correo">

        <div class="campo-panel campo-panel--crece">
            <label class="campo-panel__rotulo" for="destino_prueba">Mandar una prueba a</label>
            <input class="campo-panel__control" type="email" id="destino_prueba"
                   name="destino_prueba" required
                   value="<?= e((string) ($settings['email'] ?? '')) ?>">
        </div>

        <button class="panel-boton" type="submit">Enviar prueba</button>
    </form>

    <p class="campo-panel__ayuda">
        Sale desde <code><?= e(correo_remitente()) ?></code>.
        Si el mensaje llega a spam, falta <strong>DKIM</strong>: se activa en
        hPanel, en Correos → (el dominio) → Configuración DNS.
    </p>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
