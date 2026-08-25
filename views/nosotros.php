<?php
/**
 * views/nosotros.php — el legajo de credibilidad.
 *
 * PARA QUÉ SIRVE ESTA PÁGINA. Un club que va a gastar varios millones entra
 * a contestar cuatro preguntas: ¿existís?, ¿ya lo hiciste para alguien como
 * yo?, ¿qué pasa después de que te transfiero?, ¿a quién le escribo. No es
 * un relato de marca: es un legajo. La foto de los fundadores es una prueba
 * entre varias, no el eje.
 *
 * EL ORDEN DE LAS SECCIONES NO ES DECORATIVO:
 *   01-03  quién sos      · encabezado, fundadores, cómo empezó
 *   04-05  podés hacerlo  · cómo trabajamos, obras
 *   06-09  es seguro      · marcas, clientes, garantía, dónde estamos
 *   10     y ahora qué    · doble puerta, catálogo o cotización
 * Es el orden en el que se decide una compra grande.
 *
 * ESTADOS VACÍOS. Dos secciones tienen dos formas y las dos están vivas:
 *   · Sin obras cargadas, [ Obras ] NO se dibuja vacía: desaparece entera.
 *     Una sección de obras con un "próximamente" es peor que no tenerla.
 *   · Sin foto de fundadores, ese bloque colapsa a un párrafo firmado con
 *     los dos nombres. No se rellena con retratos de archivo: una página
 *     sin foto es honesta, una con dos señores comprados es un problema.
 * Hoy los dos huecos están abiertos de verdad, así que lo que se ve es la
 * variante corta. El día que lleguen la foto y las obras, entran solas.
 *
 * Los números de sección se calculan sobre lo que efectivamente se dibuja:
 * si una sección se esconde, la numeración no deja un agujero.
 */

declare(strict_types=1);

$nosotros = repo_nosotros();
$nosotros_settings = repo_settings();
$marcas   = repo_brands();
$clientes = repo_clients();
$nosotros_whatsapp = whatsapp_link($nosotros_settings, 'Hola Rastro, quiero hacerles una consulta.');

$titulo      = 'Nosotros';
$descripcion = 'Quiénes somos, cómo trabajamos y dónde estamos. Vendedores oficiales de '
             . 'equipamiento de gimnasio con depósito propio.';
$clase_body  = 'pagina-nosotros';
$estilos     = ['nosotros'];

// Numera solo lo que se dibuja.
$n = 0;
$indice = static function () use (&$n): string {
    return str_pad((string) ++$n, 2, '0', STR_PAD_LEFT);
};

$obras   = $nosotros['obras']['items'];
$foto_fundadores = $nosotros['fundadores']['foto'] ?? null;

require RASTRO_VIEWS . '/layout/head.php';
?>

<main class="nosotros" id="contenido">

    <?php if ($nosotros['provisorio']): ?>
        <?php /* Se apaga poniendo "provisorio": false en data/nosotros.json.
                 Mientras esté prendido, cualquiera que abra la página sabe
                 que el texto es de relleno y no una promesa del negocio. */ ?>
        <p class="nosotros__aviso t-mono-label">
            <span class="indice">!</span>
            Copy provisorio · pendiente de contenido del cliente
        </p>
    <?php endif; ?>

    <?php /* ===== Encabezado + declaración + barra de datos ============ */ ?>
    <header class="nosotros__encabezado contenedor">
        <p class="indice-seccion t-mono-label">
            <span class="indice"><?= e($indice()) ?></span>
            <?= e($nosotros['encabezado']['kicker'] ?? '') ?>
        </p>

        <h1 class="nosotros__titulo t-display-xl">
            <?= e($nosotros['encabezado']['titulo'] ?? '') ?>
        </h1>

        <p class="nosotros__declaracion t-body-lg">
            <?= e($nosotros['encabezado']['declaracion'] ?? '') ?>
        </p>

        <?php
        $cifras = $nosotros['cifras'];
        $cifras_clase = 'cifras--barra';
        require RASTRO_VIEWS . '/partials/cifras.php';
        ?>
    </header>

    <?php /* ===== Los fundadores ======================================= */ ?>
    <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-fundadores">
        <p class="indice-seccion t-mono-label">
            <span class="indice"><?= e($indice()) ?></span>
            <?= e($nosotros['fundadores']['titulo'] ?? '') ?>
        </p>

        <h2 class="visualmente-oculto" id="nosotros-fundadores">
            <?= e($nosotros['fundadores']['titulo'] ?? '') ?>
        </h2>

        <div class="<?= $foto_fundadores ? 'fundadores fundadores--con-foto' : 'fundadores' ?>">
            <?php if ($foto_fundadores): ?>
                <?php
                $fundadores_webp = imagen_webp($foto_fundadores);
                // Las medidas se leen del archivo: la foto de los fundadores la
                // sube el cliente y no tiene por qué medir lo que mide hoy.
                $fundadores_medidas = imagen_medidas($foto_fundadores);
                ?>
                <picture class="fundadores__foto">
                    <?php if ($fundadores_webp !== null): ?>
                        <source srcset="<?= e($fundadores_webp) ?>" type="image/webp">
                    <?php endif; ?>
                    <img src="<?= e(asset($foto_fundadores)) ?>"
                         alt="<?= e($nosotros['fundadores']['foto_alt'] ?? '') ?>"
                         <?php if ($fundadores_medidas !== null): ?>width="<?= e((string) $fundadores_medidas['ancho']) ?>" height="<?= e((string) $fundadores_medidas['alto']) ?>"<?php endif; ?>
                         loading="lazy" decoding="async">
                </picture>
            <?php endif; ?>

            <div class="fundadores__cuerpo">
                <p class="fundadores__texto t-body-lg">
                    <?= e($nosotros['fundadores']['texto'] ?? '') ?>
                </p>

                <?php /* La firma. Sin foto, esto ES el bloque: dos nombres
                         propios pesan más que cualquier adjetivo. */ ?>
                <ul class="fundadores__personas">
                    <?php foreach ($nosotros['fundadores']['personas'] as $persona): ?>
                        <li class="fundadores__persona">
                            <p class="fundadores__nombre t-display-s"><?= e($persona['nombre'] ?? '') ?></p>
                            <?php if (($persona['rol'] ?? null) !== null && $persona['rol'] !== ''): ?>
                                <p class="fundadores__rol t-mono-texto-sm"><?= e($persona['rol']) ?></p>
                            <?php else: ?>
                                <p class="fundadores__rol fundadores__rol--pendiente t-mono-texto-sm indice">Rol</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>

    <?php /* ===== Cómo empezó · línea de tiempo ======================== */ ?>
    <?php if ($nosotros['historia']['hitos'] !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-historia">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                <?= e($nosotros['historia']['titulo'] ?? '') ?>
            </p>

            <h2 class="nosotros__subtitulo t-display-l" id="nosotros-historia">
                <?= e($nosotros['historia']['texto'] ?? '') ?>
            </h2>

            <ol class="linea-tiempo reticula reticula--celdas">
                <?php foreach ($nosotros['historia']['hitos'] as $hito): ?>
                    <li class="linea-tiempo__hito">
                        <?php if (($hito['anio'] ?? null) !== null && $hito['anio'] !== ''): ?>
                            <p class="linea-tiempo__anio t-mono-dato"><?= e($hito['anio']) ?></p>
                        <?php else: ?>
                            <p class="linea-tiempo__anio linea-tiempo__anio--pendiente t-mono-label indice">Año</p>
                        <?php endif; ?>
                        <p class="linea-tiempo__titulo t-display-s"><?= e($hito['titulo'] ?? '') ?></p>
                        <p class="linea-tiempo__texto t-body-sm"><?= e($hito['texto'] ?? '') ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php /* ===== Cómo trabajamos ====================================== */ ?>
    <?php if ($nosotros['como_trabajamos']['pasos'] !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-trabajo">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                <?= e($nosotros['como_trabajamos']['titulo'] ?? '') ?>
            </p>

            <h2 class="nosotros__subtitulo t-display-l" id="nosotros-trabajo">
                <?= e($nosotros['como_trabajamos']['texto'] ?? '') ?>
            </h2>

            <ol class="pasos reticula reticula--celdas">
                <?php foreach ($nosotros['como_trabajamos']['pasos'] as $i => $paso): ?>
                    <li class="pasos__paso">
                        <p class="pasos__letra t-mono-label"><?= e(chr(65 + $i)) ?></p>
                        <p class="pasos__titulo t-display-s"><?= e($paso['titulo'] ?? '') ?></p>
                        <p class="pasos__texto t-body-sm"><?= e($paso['texto'] ?? '') ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php /* ===== Obras ================================================
             La prueba más fuerte de la página y hoy la que no existe.
             Sin obras cargadas la sección no se dibuja: no hay versión
             vacía, ni "próximamente", ni tres recuadros grises. */ ?>
    <?php if ($obras !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-obras">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                <?= e($nosotros['obras']['titulo'] ?? '') ?>
            </p>

            <h2 class="nosotros__subtitulo t-display-l" id="nosotros-obras">
                <?= e($nosotros['obras']['texto'] ?? '') ?>
            </h2>

            <ul class="obras reticula reticula--celdas">
                <?php foreach ($obras as $obra): ?>
                    <li class="obras__obra">
                        <?php if (($obra['foto'] ?? null) !== null && $obra['foto'] !== ''): ?>
                            <?php
                            $obra_webp = imagen_webp((string) $obra['foto']);
                            $obra_medidas = imagen_medidas((string) $obra['foto']);
                            ?>
                            <picture class="obras__foto">
                                <?php if ($obra_webp !== null): ?>
                                    <source srcset="<?= e($obra_webp) ?>" type="image/webp">
                                <?php endif; ?>
                                <img src="<?= e(asset((string) $obra['foto'])) ?>"
                                     alt="<?= e($obra['foto_alt'] ?? '') ?>"
                                     <?php if ($obra_medidas !== null): ?>width="<?= e((string) $obra_medidas['ancho']) ?>" height="<?= e((string) $obra_medidas['alto']) ?>"<?php endif; ?>
                                     loading="lazy" decoding="async">
                            </picture>
                        <?php endif; ?>

                        <div class="obras__cuerpo">
                            <p class="obras__nombre t-display-s"><?= e($obra['nombre'] ?? '') ?></p>
                            <p class="obras__lugar t-mono-texto-sm"><?= e($obra['lugar'] ?? '') ?></p>
                            <p class="obras__texto t-body-sm"><?= e($obra['texto'] ?? '') ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php /* ===== Marcas oficiales =====================================
             En la home son prueba social; acá son credenciales. Los logos
             todavía no llegaron (PENDIENTES #5): se listan los nombres,
             que es lo que hay. */ ?>
    <?php if ($marcas !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-marcas">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                Marcas oficiales
            </p>

            <h2 class="nosotros__subtitulo t-display-l" id="nosotros-marcas">
                Somos vendedores oficiales
            </h2>

            <ul class="credenciales reticula reticula--celdas">
                <?php foreach ($marcas as $marca): ?>
                    <li class="credenciales__item">
                        <?php if (($marca['logo'] ?? '') !== ''): ?>
                            <img class="credenciales__logo" src="<?= e(asset($marca['logo'])) ?>"
                                 alt="<?= e($marca['nombre'] ?? '') ?>"
                                 width="200" height="80" loading="lazy" decoding="async">
                        <?php else: ?>
                            <p class="credenciales__nombre t-mono-label"><?= e($marca['nombre'] ?? '') ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php /* ===== Clientes ============================================= */ ?>
    <?php if ($clientes !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-clientes">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                Confían en nosotros
            </p>

            <h2 class="nosotros__subtitulo t-display-l" id="nosotros-clientes">
                Ya les vendimos
            </h2>

            <ul class="credenciales reticula reticula--celdas">
                <?php foreach ($clientes as $cliente): ?>
                    <li class="credenciales__item">
                        <?php if (($cliente['logo'] ?? '') !== ''): ?>
                            <img class="credenciales__logo" src="<?= e(asset($cliente['logo'])) ?>"
                                 alt="<?= e($cliente['nombre'] ?? '') ?>"
                                 width="200" height="80" loading="lazy" decoding="async">
                        <?php else: ?>
                            <p class="credenciales__nombre t-mono-label"><?= e($cliente['nombre'] ?? '') ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php /* ===== Garantía, envíos y posventa ========================== */ ?>
    <?php if ($nosotros['garantia']['items'] !== []): ?>
        <section class="nosotros__seccion contenedor" aria-labelledby="nosotros-garantia">
            <p class="indice-seccion t-mono-label">
                <span class="indice"><?= e($indice()) ?></span>
                <?= e($nosotros['garantia']['titulo'] ?? '') ?>
            </p>

            <h2 class="visualmente-oculto" id="nosotros-garantia">
                <?= e($nosotros['garantia']['titulo'] ?? '') ?>
            </h2>

            <dl class="ficha-legal reticula reticula--celdas">
                <?php foreach ($nosotros['garantia']['items'] as $item): ?>
                    <div class="ficha-legal__fila">
                        <dt class="ficha-legal__titulo t-mono-label"><?= e($item['titulo'] ?? '') ?></dt>
                        <dd class="ficha-legal__texto t-body-sm"><?= e($item['texto'] ?? '') ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php require RASTRO_VIEWS . '/partials/regla-precio.php'; ?>
        </section>
    <?php endif; ?>

    <?php /* ===== Dónde estamos + contacto =============================
             Ancla del ítem 04 CONTACTO del menú: no hay página de contacto
             aparte. Y va una foto del depósito, no un mapa: un mapa dice
             "hay un pin", una foto del galpón dice "hay stock". */ ?>
    <section class="nosotros__seccion contenedor" id="contacto" aria-labelledby="nosotros-contacto">
        <p class="indice-seccion t-mono-label">
            <span class="indice"><?= e($indice()) ?></span>
            <?= e($nosotros['donde_estamos']['titulo'] ?? '') ?>
        </p>

        <h2 class="nosotros__subtitulo t-display-l" id="nosotros-contacto">
            <?= e($nosotros['donde_estamos']['texto'] ?? '') ?>
        </h2>

        <div class="deposito">
            <?php if (($nosotros['donde_estamos']['foto'] ?? null) !== null): ?>
                <?php
                $deposito_foto    = (string) $nosotros['donde_estamos']['foto'];
                $deposito_webp    = imagen_webp($deposito_foto);
                $deposito_medidas = imagen_medidas($deposito_foto);
                ?>
                <picture class="deposito__foto">
                    <?php if ($deposito_webp !== null): ?>
                        <source srcset="<?= e($deposito_webp) ?>" type="image/webp">
                    <?php endif; ?>
                    <img src="<?= e(asset($deposito_foto)) ?>"
                         alt="<?= e($nosotros['donde_estamos']['foto_alt'] ?? '') ?>"
                         <?php if ($deposito_medidas !== null): ?>width="<?= e((string) $deposito_medidas['ancho']) ?>" height="<?= e((string) $deposito_medidas['alto']) ?>"<?php endif; ?>
                         loading="lazy" decoding="async">
                </picture>
            <?php endif; ?>

            <dl class="deposito__datos reticula reticula--celdas">
                <div class="deposito__fila">
                    <dt class="t-mono-label">Depósito</dt>
                    <dd class="t-body-sm">
                        <?php if (($nosotros['donde_estamos']['direccion'] ?? null) !== null): ?>
                            <?= e($nosotros['donde_estamos']['direccion']) ?>
                        <?php else: ?>
                            <span class="dato-pendiente t-mono-label indice">Dirección</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="deposito__fila">
                    <dt class="t-mono-label">Horario</dt>
                    <dd class="t-body-sm"><?= e($nosotros_settings['horario'] ?? '') ?></dd>
                </div>
                <?php if ($nosotros_whatsapp !== null): ?>
                    <div class="deposito__fila">
                        <dt class="t-mono-label">WhatsApp</dt>
                        <dd class="t-body-sm">
                            <a class="deposito__enlace" href="<?= e($nosotros_whatsapp) ?>" rel="noopener" target="_blank">
                                <?= e($nosotros_settings['whatsapp'] ?? '') ?>
                                <span class="visualmente-oculto">(abre WhatsApp en una pestaña nueva)</span>
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>
                <div class="deposito__fila">
                    <dt class="t-mono-label">Correo</dt>
                    <dd class="t-body-sm">
                        <a class="deposito__enlace" href="mailto:<?= e($nosotros_settings['email'] ?? '') ?>">
                            <?= e($nosotros_settings['email'] ?? '') ?>
                        </a>
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <?php /* ===== Cierre de doble puerta ===============================
             A esta altura el visitante ya sabe qué es: minorista que quiere
             precio, o alguien que equipa un espacio y necesita cotización. */ ?>
    <section class="nosotros__seccion nosotros__cierre contenedor" aria-labelledby="nosotros-cierre">
        <p class="indice-seccion t-mono-label">
            <span class="indice"><?= e($indice()) ?></span>
            Y ahora
        </p>

        <h2 class="nosotros__subtitulo t-display-l" id="nosotros-cierre">
            <?= e($nosotros['cierre']['titulo'] ?? '') ?>
        </h2>

        <p class="nosotros__declaracion t-body-md">
            <?= e($nosotros['cierre']['texto'] ?? '') ?>
        </p>

        <div class="nosotros__puertas">
            <?php foreach ($nosotros['cierre']['acciones'] as $accion): ?>
                <a class="<?= e('puerta puerta--' . ($accion['tipo'] ?? 'secundario') . ' t-mono-label') ?>"
                   href="<?= e(url($accion['ruta'] ?? '/')) ?>">
                    <?= e($accion['texto'] ?? '') ?>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<?php require RASTRO_VIEWS . '/layout/footer.php'; ?>
