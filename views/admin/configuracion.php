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

/* Ajustes de texto que se guardan tal cual. Se suman acá y no a mano en
   tres lugares (guardar, repintar con errores, dibujar). */
$ajustes_texto = [
    'envio_zona_nombre', 'envio_zona_cps', 'envio_cotizar_categorias',
    'envio_origen_cp', 'envio_origen_localidad',
    'transferencia_titular', 'transferencia_alias', 'transferencia_cvu', 'transferencia_banco',
    'avisos_email', 'meta_pixel_id', 'ga_id', 'data_fiscal_url',
];

$ajustes_enteros = ['envio_zona_costo', 'envio_cotizar_kg'];

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

        $via           = smtp_activo() ? 'SMTP (' . (string) config('smtp_host', '') . ')' : 'mail() del servidor';
        $correo_error  = null;

        $ok = correo_enviar(
            $destino,
            'Prueba de correo de rastrofitness.com',
            sprintf(
                "Este es un mail de prueba enviado desde el panel de Rastro Fitness.\n\n"
                . "Fecha: %s\nRemitente: %s\nEnviado por: %s\nServidor: %s\n\n"
                . "Si te llegó, el sitio puede avisar por mail: el enlace para cambiar\n"
                . "la contraseña y el aviso cuando alguien pide un arrepentimiento.\n",
                date('d/m/Y H:i'),
                correo_remitente(),
                $via,
                (string) ($_SERVER['SERVER_NAME'] ?? '')
            ),
            '',
            $correo_error
        );

        /* El motivo del fallo se muestra entero. Antes decía sólo "el
           servidor rechazó el envío", que no alcanza para arreglar nada:
           no es lo mismo una clave equivocada que un puerto cerrado. */
        panel_ir_con_aviso(
            '/admin/configuracion',
            $ok ? 'ok' : 'error',
            $ok
                ? 'Enviado por ' . $via . '. El servidor lo aceptó: revisá la bandeja de '
                  . $destino . ' y también spam, porque que lo acepte no garantiza que llegue.'
                : 'No se pudo enviar por ' . $via . '. Motivo: '
                  . ($correo_error ?? 'sin detalle') . '.'
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

    $avisos_email = panel_texto('avisos_email');

    if ($avisos_email !== '' && !filter_var($avisos_email, FILTER_VALIDATE_EMAIL)) {
        $errores['avisos_email'] = 'Ese mail no tiene forma de mail.';
    }

    $cvu = (string) preg_replace('/\D/', '', panel_texto('transferencia_cvu'));

    if ($cvu !== '' && strlen($cvu) !== 22) {
        $errores['transferencia_cvu'] = 'El CVU o CBU tiene 22 números.';
    }

    $data_fiscal = panel_texto('data_fiscal_url');

    if ($data_fiscal !== '' && !preg_match('#^https?://#', $data_fiscal)) {
        $errores['data_fiscal_url'] = 'Pegá el link completo del QR, empezando por http.';
    }

    foreach ($ajustes_enteros as $clave) {
        if ((panel_entero($clave, 0) ?? 0) < 0) {
            $errores[$clave] = 'No puede ser negativo.';
        }
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

            /* Las tres cifras de /mayoristas. Estaban escritas en la vista
               con `null`, así que la página mostraba [ DATO ] y no había
               forma de completarlas: ni desde el panel ni desde ningún
               lado. Se guardan como texto porque "48 a 72 h hábiles" no es
               un número. */
            'may_salas_equipadas'         => panel_texto('may_salas_equipadas'),
            'may_entrega_tipica'          => panel_texto('may_entrega_tipica'),
            'may_pedido_minimo'           => panel_texto('may_pedido_minimo'),
        ]
        + ['transferencia_cvu' => $cvu]
        + array_combine($ajustes_texto, array_map('panel_texto', $ajustes_texto))
        + array_combine($ajustes_enteros, array_map(
            static fn (string $c): int => panel_entero($c, 0) ?? 0,
            $ajustes_enteros
        )));

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
        'may_salas_equipadas'         => panel_texto('may_salas_equipadas'),
        'may_entrega_tipica'          => panel_texto('may_entrega_tipica'),
        'may_pedido_minimo'           => panel_texto('may_pedido_minimo'),
    ]
    + array_combine($ajustes_texto, array_map('panel_texto', $ajustes_texto)));
}

$envio_config = envio_config($settings);

/* Un campo de texto del panel con su error. Para los ajustes nuevos, que
   son todos iguales: rótulo, control y ayuda. */
$campo = static function (string $clave, string $rotulo, string $ayuda = '', string $placeholder = '', string $tipo = 'text') use ($settings, $errores): void {
    ?>
    <div class="campo-panel campo-panel--crece">
        <label class="campo-panel__rotulo" for="<?= e($clave) ?>"><?= e($rotulo) ?></label>
        <input class="campo-panel__control<?= isset($errores[$clave]) ? ' es-error' : '' ?>"
               type="<?= e($tipo) ?>" id="<?= e($clave) ?>" name="<?= e($clave) ?>"
               placeholder="<?= e($placeholder) ?>"
               value="<?= e((string) ($settings[$clave] ?? '')) ?>">
        <?php if ($ayuda !== ''): ?>
            <p class="campo-panel__ayuda"><?= e($ayuda) ?></p>
        <?php endif; ?>
        <?php if (isset($errores[$clave])): ?>
            <p class="campo-panel__error"><?= e($errores[$clave]) ?></p>
        <?php endif; ?>
    </div>
    <?php
};

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

            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Envíos</legend>

                <?php $campo('envio_zona_nombre', 'Nombre de la zona', '', 'CABA y alrededores'); ?>

                <div class="campo-panel">
                    <label class="campo-panel__rotulo" for="envio_zona_cps">Códigos postales de la zona</label>
                    <textarea class="campo-panel__control campo-panel__control--mono" rows="2"
                              id="envio_zona_cps" name="envio_zona_cps"
                              placeholder="1000-1499, 1600-1899"><?= e((string) ($settings['envio_zona_cps'] ?? '')) ?></textarea>
                    <p class="campo-panel__ayuda">
                        Rangos separados por coma. CABA es 1000-1499.
                        <?php if ($envio_config['zona_rangos'] === []): ?>
                            <strong>Sin códigos cargados no hay zona: todos los envíos se cotizan.</strong>
                        <?php else: ?>
                            Se entiende como
                            <?= e(implode(', ', array_map(
                                static fn (array $r): string => $r[0] === $r[1] ? (string) $r[0] : $r[0] . ' a ' . $r[1],
                                $envio_config['zona_rangos']
                            ))) ?>.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="panel-fila">
                    <div class="campo-panel campo-panel--crece">
                        <label class="campo-panel__rotulo" for="envio_zona_costo">Tarifa en la zona</label>
                        <input class="campo-panel__control<?= isset($errores['envio_zona_costo']) ? ' es-error' : '' ?>"
                               type="text" inputmode="numeric" id="envio_zona_costo" name="envio_zona_costo"
                               value="<?= e((string) (int) ($settings['envio_zona_costo'] ?? 0)) ?>">
                        <p class="campo-panel__ayuda">En pesos. Se suma al pago de Mercado Pago. En 0, se cotiza.</p>
                    </div>

                    <div class="campo-panel campo-panel--crece">
                        <label class="campo-panel__rotulo" for="envio_gratis_desde">Gratis en la zona desde</label>
                        <input class="campo-panel__control<?= isset($errores['envio_gratis_desde']) ? ' es-error' : '' ?>"
                               type="text" inputmode="numeric" id="envio_gratis_desde" name="envio_gratis_desde"
                               value="<?= e((string) (int) ($settings['envio_gratis_desde'] ?? 0)) ?>">
                        <p class="campo-panel__ayuda">
                            En pesos, y sólo dentro de la zona. <strong>En 0 está apagado.</strong>
                        </p>
                        <?php if (isset($errores['envio_gratis_desde'])): ?>
                            <p class="campo-panel__error"><?= e($errores['envio_gratis_desde']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-fila">
                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="envio_cotizar_kg">Se cotiza desde (kg por bulto)</label>
                        <input class="campo-panel__control" type="text" inputmode="numeric"
                               id="envio_cotizar_kg" name="envio_cotizar_kg"
                               value="<?= e((string) (int) $envio_config['cotizar_kg']) ?>">
                    </div>
                    <div class="campo-panel campo-panel--crece">
                        <label class="campo-panel__rotulo" for="envio_cotizar_categorias">Categorías que siempre se cotizan</label>
                        <input class="campo-panel__control campo-panel__control--mono" type="text"
                               id="envio_cotizar_categorias" name="envio_cotizar_categorias"
                               value="<?= e(implode(', ', $envio_config['cotizar_categorias'])) ?>">
                    </div>
                </div>
                <p class="campo-panel__ayuda">
                    Racks, jaulas y bancos generan el pedido con envío «a cotizar» y les llega el aviso.
                    Un producto suelto también se marca desde su ficha en Productos. Fuera de la zona
                    el envío también figura «a cotizar» hasta que conectemos el agregador.
                </p>

                <div class="panel-fila">
                    <?php $campo('envio_origen_cp', 'CP de origen', '', '2930'); ?>
                    <?php $campo('envio_origen_localidad', 'Localidad de origen', 'Desde dónde sale el envío. El retiro por depósito está apagado.', 'San Pedro, Buenos Aires'); ?>
                </div>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Transferencia</legend>

                <div class="panel-fila">
                    <?php $campo('transferencia_alias', 'Alias', '', 'rastro.fitness.mp'); ?>
                    <?php $campo('transferencia_cvu', 'CVU o CBU', '', '22 números'); ?>
                </div>
                <div class="panel-fila">
                    <?php $campo('transferencia_titular', 'Titular'); ?>
                    <?php $campo('transferencia_banco', 'Banco o billetera', '', 'Mercado Pago'); ?>
                </div>
                <p class="campo-panel__ayuda">
                    Se muestran al terminar un pedido por transferencia, con el número de pedido para
                    el concepto. El pedido queda «Esperando transferencia» hasta que lo marquen como
                    pagado desde Pedidos.
                </p>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Avisos de pedidos</legend>

                <?php $campo('avisos_email', 'Mandar cada pedido a', 'Llega un mail por cada pedido pagado, por cada transferencia y por cada envío a cotizar. Vacío: se usa el mail de ventas.', (string) ($settings['email'] ?? ''), 'email'); ?>
            </fieldset>

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Medición y legales</legend>

                <div class="panel-fila">
                    <?php $campo('meta_pixel_id', 'Meta Pixel (ID)', '', '123456789012345'); ?>
                    <?php $campo('ga_id', 'Google Analytics (ID)', '', 'G-XXXXXXXXXX'); ?>
                </div>
                <p class="campo-panel__ayuda">
                    Con los IDs cargados, el sitio registra las visitas y el evento de compra cuando un pago se aprueba.
                </p>

                <?php $campo('data_fiscal_url', 'Link del QR de Data Fiscal (ARCA)', 'El que da ARCA al adherir el sitio, del estilo http://qr.afip.gob.ar/?qr=… Con el link cargado, el QR aparece en el pie.', 'http://qr.afip.gob.ar/?qr=', 'url'); ?>
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
                    <?php
                    /* El 22/10 la web decía .com.ar y los avisos salían desde
                       .com. Se avisa acá en vez de descubrirlo en un reclamo. */
                    $dominio_ventas = strtolower((string) substr(strrchr((string) ($settings['email'] ?? ''), '@') ?: '', 1));
                    $dominio_envio  = strtolower((string) substr(strrchr(correo_remitente(), '@') ?: '', 1));
                    ?>
                    <?php if ($dominio_ventas !== '' && $dominio_ventas !== $dominio_envio): ?>
                        <p class="campo-panel__ayuda">
                            <strong>Ojo: los mails del sitio salen desde <?= e(correo_remitente()) ?>.</strong>
                            Usen el mismo dominio en los dos para tener un solo mail.
                        </p>
                    <?php endif; ?>
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

            <fieldset class="panel-grupo">
                <legend class="panel-grupo__titulo">Cifras de Mayoristas</legend>

                <div class="panel-fila">
                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="may_salas_equipadas">Salas equipadas</label>
                        <input class="campo-panel__control" type="text"
                               id="may_salas_equipadas" name="may_salas_equipadas"
                               placeholder="40"
                               value="<?= e((string) ($settings['may_salas_equipadas'] ?? '')) ?>">
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="may_entrega_tipica">Entrega típica</label>
                        <input class="campo-panel__control" type="text"
                               id="may_entrega_tipica" name="may_entrega_tipica"
                               placeholder="48 a 72 h hábiles"
                               value="<?= e((string) ($settings['may_entrega_tipica'] ?? '')) ?>">
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="may_pedido_minimo">Pedido mínimo</label>
                        <input class="campo-panel__control" type="text"
                               id="may_pedido_minimo" name="may_pedido_minimo"
                               placeholder="$ 500.000"
                               value="<?= e((string) ($settings['may_pedido_minimo'] ?? '')) ?>">
                    </div>
                </div>

                <p class="campo-panel__ayuda">
                    Son las tres cifras del encabezado de /mayoristas. Un campo vacío se
                    dibuja como <code>[ DATO ]</code> en la página, a propósito: en la
                    revisión se ve qué falta en vez de leerse como si estuviera resuelto.
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
