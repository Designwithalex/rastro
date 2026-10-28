<?php
/**
 * mayoristas.php — la landing del canal mayorista.
 *
 * Sigue el frame "Mayoristas · Desktop 1440".
 * https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=74-3
 *
 * ACÁ NO SE MUESTRAN PRECIOS. Es la regla del canal (CLAUDE.md §1): un
 * club o un gimnasio cotiza el proyecto completo, no compra por unidad.
 * `precio_mayorista` existe en los datos y no sale a pantalla
 * (PENDIENTES #11).
 *
 * EL FORMULARIO LLEGA POR MAIL (desde el 28/10)
 *
 * Hasta octubre no enviaba nada, y el cliente preguntó a dónde llegaba
 * (pedido del 22/10, punto 23). Llega a `avisos_email` de Configuración, o
 * al mail de ventas si no hay uno aparte, con responder-a del que consulta.
 *
 * Contra envíos automáticos: un campo trampa que una persona no ve
 * (`sitio_web`) y la comprobación de origen del checkout.
 */

declare(strict_types=1);

/* --- Envío del formulario ------------------------------------------- */
$consulta = ['nombre' => '', 'empresa' => '', 'email' => '', 'telefono' => '', 'mensaje' => ''];
$consulta_estado = null; // null | 'ok' | 'error'
$consulta_error  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($consulta as $clave => $_) {
        $consulta[$clave] = trim(mb_substr((string) ($_POST[$clave] ?? ''), 0, $clave === 'mensaje' ? 3000 : 200));
    }

    $ajustes_may = repo_settings();
    $destino_may = trim((string) ($ajustes_may['avisos_email'] ?? '')) ?: trim((string) ($ajustes_may['email'] ?? ''));

    if (trim((string) ($_POST['sitio_web'] ?? '')) !== '' || !checkout_mismo_origen()) {
        // Un robot: se le contesta como si hubiera salido bien.
        $consulta_estado = 'ok';
    } elseif ($consulta['nombre'] === '' || $consulta['mensaje'] === ''
        || !filter_var($consulta['email'], FILTER_VALIDATE_EMAIL)
        || strlen(preg_replace('/\D+/', '', $consulta['telefono']) ?? '') < 8) {
        $consulta_estado = 'error';
        $consulta_error  = 'Revisá nombre, correo, teléfono y qué querés equipar: son los datos que necesitamos para responderte.';
    } else {
        $enviado_may = $destino_may !== '' && correo_enviar(
            $destino_may,
            'Consulta mayorista · ' . $consulta['nombre'] . ($consulta['empresa'] !== '' ? ' · ' . $consulta['empresa'] : ''),
            "Consulta desde /mayoristas\n\n"
            . 'Nombre: ' . $consulta['nombre'] . "\n"
            . 'Empresa: ' . ($consulta['empresa'] ?: '—') . "\n"
            . 'Correo: ' . $consulta['email'] . "\n"
            . 'Teléfono: ' . $consulta['telefono'] . "\n\n"
            . "Qué quiere equipar:\n" . $consulta['mensaje'] . "\n",
            $consulta['email']
        );

        $consulta_estado = $enviado_may ? 'ok' : 'error';
        $consulta_error  = 'No pudimos enviar la consulta en este momento. Escribinos por WhatsApp y te respondemos.';

        if ($enviado_may) {
            $consulta = array_map(static fn (): string => '', $consulta);
        }
    }
}

$titulo      = 'Venta mayorista';
$descripcion = 'Equipamos gimnasios, clubes, hoteles y barrios cerrados. '
             . 'Cotizamos el proyecto completo: pedinos una propuesta cerrada.';
$clase_body  = 'pagina-mayoristas';
$estilos     = ['componentes', 'catalogo', 'cuenta', 'mayoristas'];

require RASTRO_VIEWS . '/layout/head.php';

$whatsapp = whatsapp_link(
    $settings,
    'Hola Rastro, estoy equipando un espacio y quiero pedir una cotización.'
);

/* Copy de diseño. Cuando el panel edite esta página, esto pasa a salir del
   repository igual que /nosotros (PENDIENTES #28). */
$publicos = [
    ['titulo' => 'Clubes',              'texto' => 'Salas de musculación y espacios de entrenamiento para socios.'],
    ['titulo' => 'Gimnasios',           'texto' => 'Desde el que abre hasta el que suma una sala nueva.'],
    ['titulo' => 'Hoteles y resorts',   'texto' => 'Gimnasios de huéspedes: equipamiento compacto y de bajo mantenimiento.'],
    ['titulo' => 'Desarrolladoras',     'texto' => 'Amenities de edificios en pozo, con entrega coordinada a la obra.'],
    ['titulo' => 'Barrios cerrados',    'texto' => 'SUM y salas comunes, con instalación por administración.'],
    ['titulo' => 'Empresas con gym',    'texto' => 'Espacios de bienestar para empleados, con factura.'],
];

$pasos = [
    ['titulo' => 'Nos contás el espacio',   'texto' => 'Metros cuadrados, cuánta gente entrena y qué presupuesto manejás. Con eso alcanza para empezar.'],
    ['titulo' => 'Armamos la propuesta',    'texto' => 'Te mandamos el listado con cantidades, precios y plazo de entrega. Si algo no cierra, lo ajustamos.'],
    ['titulo' => 'Coordinamos la entrega',  'texto' => 'Cotizamos el envío a la obra o al local y lo coordinamos con vos.'],
];

/* Las tres cifras de la barra son datos del negocio que el cliente todavía
   no pasó. Se dibujan como hueco visible, igual que en /nosotros: una celda
   escondida no la reclama nadie (PENDIENTES #50). */
/* Las tres cifras salen de la configuración, que se edita en
   /admin/configuracion. Estaban escritas acá con `null` fijo, así que la
   página mostraba [ DATO ] y no había forma de completarlas desde ningún
   lado: ni el cliente ni nadie.

   Un valor vacío sigue dibujándose como [ DATO ] visible, que es la regla
   del proyecto para un hueco declarado: en la revisión se ve qué falta en
   vez de leerse como si estuviera resuelto. */
$cifra_o_hueco = static function (mixed $v): ?string {
    $v = trim((string) ($v ?? ''));

    return $v === '' ? null : $v;
};

$cifras_mayorista = [
    ['rotulo' => 'Salas equipadas', 'valor' => $cifra_o_hueco($settings['may_salas_equipadas'] ?? null)],
    ['rotulo' => 'Entrega típica',  'valor' => $cifra_o_hueco($settings['may_entrega_tipica'] ?? null)],
    ['rotulo' => 'Pedido mínimo',   'valor' => $cifra_o_hueco($settings['may_pedido_minimo'] ?? null)],
];

$campos = [
    ['nombre' => 'nombre',   'etiqueta' => 'Nombre y apellido',     'tipo' => 'text',  'auto' => 'name',         'requerido' => true],
    ['nombre' => 'empresa',  'etiqueta' => 'Empresa o institución', 'tipo' => 'text',  'auto' => 'organization', 'requerido' => false],
    ['nombre' => 'email',    'etiqueta' => 'Correo electrónico',    'tipo' => 'email', 'auto' => 'email',        'requerido' => true],
    ['nombre' => 'telefono', 'etiqueta' => 'Teléfono',              'tipo' => 'tel',   'auto' => 'tel',          'requerido' => true],
];
?>

<main id="contenido" tabindex="-1">

    <div class="contenedor barra-pagina barra-pagina--ficha">
        <?php
        $miga = [['texto' => 'Venta mayorista']];
        require RASTRO_VIEWS . '/partials/breadcrumb.php';
        ?>
    </div>

    <?php /* ============================================================
             Encabezado
             ============================================================ */ ?>
    <section class="may-hero reticula" aria-labelledby="may-titulo">

        <div class="may-hero__texto">
            <p class="indice-seccion t-mono-label"><span class="indice">01</span> Venta mayorista</p>

            <h1 class="may-hero__titulo t-display-xl" id="may-titulo">Equipamos salas enteras</h1>

            <p class="may-hero__bajada t-body-lg">
                Cotizamos el proyecto completo: discos, barras, racks, mancuernas y
                accesorios, con precios que no se publican y plazo de entrega por escrito.
            </p>

            <div class="may-hero__acciones">
                <a class="boton boton--acento" href="#cotizar">
                    <span class="t-mono-label">Pedir cotización</span>
                    <span class="boton__flecha" aria-hidden="true">↓</span>
                </a>
                <?php if ($whatsapp !== null): ?>
                    <a class="boton boton--fantasma" href="<?= e($whatsapp) ?>" rel="noopener" target="_blank">
                        <span class="t-mono-label">Escribir por WhatsApp</span>
                        <span class="boton__flecha" aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="may-hero__lado">
            <figure class="may-hero__foto">
                <img src="<?= e(asset('img/ambiente/ambiente-disco-fundicion-galpon.jpg')) ?>" alt=""
                     loading="lazy" decoding="async">
            </figure>

            <dl class="may-cifras reticula reticula--superficie">
                <?php foreach ($cifras_mayorista as $cifra): ?>
                    <div class="may-cifras__fila">
                        <dt class="t-mono-label-sm"><?= e($cifra['rotulo']) ?></dt>
                        <dd class="t-mono-dato">
                            <?php if ($cifra['valor'] !== null): ?>
                                <?= e((string) $cifra['valor']) ?>
                            <?php else: ?>
                                <span class="marcador t-mono-label-sm">[ DATO ]</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <?php /* ============================================================
             A quién le vendemos
             ============================================================ */ ?>
    <section class="may-publicos" aria-labelledby="publicos-titulo">
        <div class="contenedor">
            <?php
            $seccion_indice = '02';
            $seccion_titulo = 'A quién le vendemos';
            $seccion_id     = 'publicos-titulo';
            $seccion_nota   = 'Seis tipos de cliente, un solo circuito';
            require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
            ?>
        </div>

        <ul class="may-publicos__grilla reticula">
            <?php foreach ($publicos as $i => $publico): ?>
                <li class="may-publico">
                    <p class="may-publico__indice indice t-mono-label-sm"><?= e(sprintf('%02d', $i + 1)) ?></p>
                    <h3 class="may-publico__titulo t-display-s"><?= e($publico['titulo']) ?></h3>
                    <p class="may-publico__texto t-mono-texto"><?= e($publico['texto']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php /* ============================================================
             Cómo trabajamos
             ============================================================ */ ?>
    <section class="may-pasos" aria-labelledby="pasos-titulo">
        <div class="contenedor">
            <?php
            $seccion_indice = '03';
            $seccion_titulo = 'Cómo trabajamos';
            $seccion_id     = 'pasos-titulo';
            require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
            ?>
        </div>

        <ol class="may-pasos__lista reticula">
            <?php foreach ($pasos as $i => $paso): ?>
                <li class="may-paso">
                    <p class="may-paso__numero t-display-xl" aria-hidden="true"><?= e((string) ($i + 1)) ?></p>
                    <h3 class="may-paso__titulo t-display-s">
                        <span class="visualmente-oculto">Paso <?= e((string) ($i + 1)) ?>:</span>
                        <?= e($paso['titulo']) ?>
                    </h3>
                    <p class="may-paso__texto t-mono-texto"><?= e($paso['texto']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php /* ============================================================
             Cotización
             ============================================================ */ ?>
    <section class="cotizar" id="cotizar" aria-labelledby="cotizar-titulo">
        <div class="contenedor">
            <?php
            $seccion_indice = '04';
            $seccion_titulo = 'Pedí tu cotización';
            $seccion_id     = 'cotizar-titulo';
            require RASTRO_VIEWS . '/partials/encabezado-seccion.php';
            ?>
        </div>

        <div class="cotizar__cuerpo reticula">

            <div class="cotizar__texto">
                <p class="cotizar__claim t-display-m">
                    Contanos qué espacio querés equipar y te respondemos con una
                    propuesta cerrada.
                </p>

                <ul class="cotizar__lista">
                    <li class="t-mono-texto">Listado con cantidades y precios</li>
                    <li class="t-mono-texto">Plazo de entrega por escrito</li>
                    <li class="t-mono-texto">Alternativas por rango de presupuesto</li>
                    <li class="t-mono-texto">Factura C</li>
                </ul>

                <?php if ($whatsapp !== null): ?>
                    <p class="cotizar__wsp">
                        <span class="t-mono-label-sm">¿Preferís escribir?</span>
                        <a class="cotizar__wsp-enlace t-mono-label" href="<?= e($whatsapp) ?>" rel="noopener" target="_blank">
                            WhatsApp <?= e($settings['whatsapp'] ?? '') ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <form class="formulario" method="post" action="<?= e(url('/mayoristas')) ?>#cotizar">

                <?php if ($consulta_estado === 'ok'): ?>
                    <p class="mensaje mensaje--ok t-mono-texto" role="status">
                        <span class="mensaje__marca" aria-hidden="true">✓</span>
                        Recibimos tu consulta. Te respondemos por correo o por WhatsApp.
                    </p>
                <?php elseif ($consulta_estado === 'error'): ?>
                    <p class="mensaje mensaje--error t-mono-texto" role="alert">
                        <span class="mensaje__marca" aria-hidden="true">!</span>
                        <?= e($consulta_error) ?>
                    </p>
                <?php endif; ?>

                <?php /* Trampa para robots: una persona no lo ve ni lo llena. */ ?>
                <p class="visualmente-oculto" aria-hidden="true">
                    <label for="sitio_web">Sitio web</label>
                    <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                </p>

                <?php foreach ($campos as $campo): ?>
                    <p class="formulario__campo">
                        <label class="formulario__etiqueta t-mono-label-sm" for="<?= e($campo['nombre']) ?>">
                            <?= e($campo['etiqueta']) ?>
                            <?php if (!$campo['requerido']): ?>
                                <span class="formulario__opcional">(opcional)</span>
                            <?php endif; ?>
                        </label>
                        <input class="campo t-mono-texto"
                               type="<?= e($campo['tipo']) ?>"
                               id="<?= e($campo['nombre']) ?>"
                               name="<?= e($campo['nombre']) ?>"
                               autocomplete="<?= e($campo['auto']) ?>"
                               value="<?= e($consulta[$campo['nombre']]) ?>"
                               <?= $campo['requerido'] ? 'required' : '' ?>>
                    </p>
                <?php endforeach; ?>

                <p class="formulario__campo">
                    <label class="formulario__etiqueta t-mono-label-sm" for="mensaje">Qué querés equipar</label>
                    <textarea class="campo campo--area t-mono-texto" id="mensaje" name="mensaje" rows="4"
                              placeholder="Metros cuadrados, cuánta gente entrena, si ya tenés algo…" required><?= e($consulta['mensaje']) ?></textarea>
                </p>

                <button class="boton boton--acento formulario__enviar" type="submit">
                    <span class="t-mono-label">Enviar consulta</span>
                    <span class="boton__flecha" aria-hidden="true">→</span>
                </button>

            </form>
        </div>
    </section>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
