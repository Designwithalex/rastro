<?php
/**
 * layout/cajon.php — el menú de celular.
 *
 * Reemplaza a la tira horizontal que se desplazaba: ahí el canal mayorista
 * —que es medio negocio— quedaba escondido detrás de un gesto que nadie
 * hace. Acá las cuatro puertas se ven de una.
 *
 * ESTO SÍ ES UN DIÁLOGO, y el mega-menú de escritorio no. La diferencia no
 * es un capricho: el cajón tapa la página entera, así que mientras está
 * abierto nada de atrás tiene que ser alcanzable. Por eso role="dialog",
 * aria-modal, foco atrapado, Esc que cierra y devuelve el foco a la
 * hamburguesa, y el scroll del cuerpo bloqueado. Un panel de escritorio que
 * deja ver la página no cumple ninguna de esas condiciones y atrapar el Tab
 * ahí sería un error.
 *
 * DOS NIVELES, NO TRES. Tocar una categoría navega a /catalogo/{slug}. En un
 * celular, "adentro de la categoría" ES la página de la categoría: tiene
 * foto, filtros y precio. Una tercera pantalla con una lista de texto pierde
 * contra eso.
 *
 * Variables que trae de header.php:
 *   $layout_categorias  array  repo_categories()
 *   $layout_nav         array  las cuatro puertas del menú
 */

declare(strict_types=1);

$cajon_settings = repo_settings();
$cajon_whatsapp = whatsapp_link($cajon_settings);

// Si ya estás mirando el catálogo, el acordeón abre desplegado: el menú no
// te hace buscar de nuevo dónde estabas parado.
$cajon_productos_abierto = es_ruta_activa('/catalogo') || es_ruta_activa('/producto');

/* El bloque secundario depende de si hay sesión, y antes no: era una lista
   fija que ofrecía "Ingresar" y "Crear cuenta" a alguien que ya estaba
   adentro, y no tenía "Salir" en ningún lado.

   Eso dejaba sin salida a quien entra desde el celular. El "Salir" de la
   cabecera vive en `.cabecera__acciones`, que está en `display: none` hasta
   los 1280px —"Cuenta vive en el cajón cuando no hay lugar en la barra",
   dice layout.css—, sólo que el cajón nunca lo había recibido. */
$cajon_usuario = sesion_usuario();

$cajon_secundario = $cajon_usuario !== null
    ? [['texto' => 'Mis pedidos', 'ruta' => '/cuenta']]
    : [
        ['texto' => 'Ingresar',     'ruta' => '/ingresar'],
        ['texto' => 'Crear cuenta', 'ruta' => '/registro'],
    ];
?>
<div class="cajon" id="cajon-nav" data-cajon hidden>

    <?php /* El velo cierra al tocarlo. No es un botón porque para el teclado
             ya están Esc y el botón de cerrar: sería una parada de más. */ ?>
    <div class="cajon__velo" data-cajon-cerrar></div>

    <?php /* role="dialog" y aria-modal NO están en el marcado: los pone
             assets/js/nav.js al arrancar. Sin JavaScript este bloque queda
             a la vista y estático (ver assets/css/sin-js.css), y un
             aria-modal sobre algo siempre visible le esconde la página
             entera a un lector de pantalla: todo lo que está afuera del
             diálogo deja de existir. Un atributo de estado desactualizado
             es una molestia; este sería una página en blanco. */ ?>
    <div class="cajon__panel" aria-labelledby="cajon-titulo" tabindex="-1">

        <div class="cajon__barra">
            <p class="cajon__titulo t-mono-label" id="cajon-titulo">Menú</p>
            <button class="cajon__cerrar" type="button" data-cajon-cerrar>
                <?= icono('cerrar') ?>
                <span class="visualmente-oculto">Cerrar el menú</span>
            </button>
        </div>

        <div class="cajon__cuerpo">

            <nav class="cajon__nav" aria-label="Menú principal">
                <ul class="cajon__lista">

                    <?php foreach ($layout_nav as $cajon_i => $cajon_item): ?>
                        <?php
                        $cajon_indice = str_pad((string) ($cajon_i + 1), 2, '0', STR_PAD_LEFT);
                        $cajon_actual = $cajon_item['tipo'] === 'enlace' && es_ruta_exacta($cajon_item['ruta']);
                        ?>

                        <?php if ($cajon_item['tipo'] === 'mega'): ?>
                            <li class="cajon__fila">
                                <?php /* aria-current="true" y no "page": el disparador no
                                         es un enlace y no lleva a ninguna página; lo que dice
                                         es que la sección en la que estás parado es esta. */ ?>
                                <button class="<?= e(trim('cajon__enlace t-mono-label ' . ($cajon_productos_abierto ? 'es-actual' : ''))) ?>"
                                        type="button"
                                        aria-expanded="<?= $cajon_productos_abierto ? 'true' : 'false' ?>"
                                        <?= $cajon_productos_abierto ? 'aria-current="true"' : '' ?>
                                        aria-controls="cajon-categorias" data-cajon-acordeon>
                                    <span class="cajon__indice"><?= e($cajon_indice) ?></span>
                                    <span class="cajon__texto"><?= e($cajon_item['texto']) ?></span>
                                    <span class="cajon__chevron"><?= icono('chevron') ?></span>
                                </button>

                                <ul class="cajon__sublista" id="cajon-categorias"
                                    <?= $cajon_productos_abierto ? '' : 'hidden' ?>>
                                    <?php foreach ($layout_categorias as $cajon_categoria): ?>
                                        <li>
                                            <a class="cajon__subenlace"
                                               href="<?= e(url('/catalogo/' . $cajon_categoria['slug'])) ?>"
                                               <?= es_ruta_exacta('/catalogo/' . $cajon_categoria['slug']) ? 'aria-current="page"' : '' ?>>
                                                <span class="t-body-sm"><?= e($cajon_categoria['nombre']) ?></span>
                                                <span class="cajon__cantidad t-mono-texto-sm">
                                                    <?= e(str_pad((string) ($cajon_categoria['productos_count'] ?? 0), 2, '0', STR_PAD_LEFT)) ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <a class="cajon__subenlace cajon__subenlace--todo t-mono-label"
                                           href="<?= e(url('/catalogo')) ?>"
                                           <?= es_ruta_exacta('/catalogo') ? 'aria-current="page"' : '' ?>>
                                            Ver todo el catálogo
                                            <span class="cajon__flecha" aria-hidden="true">→</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="cajon__fila">
                                <a class="<?= e(trim('cajon__enlace t-mono-label ' . ($cajon_actual ? 'es-actual' : ''))) ?>"
                                   href="<?= e(url($cajon_item['ruta'])) ?>"
                                   <?= $cajon_actual ? 'aria-current="page"' : '' ?>>
                                    <span class="cajon__indice"><?= e($cajon_indice) ?></span>
                                    <span class="cajon__texto"><?= e($cajon_item['texto']) ?></span>
                                </a>
                            </li>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </ul>
            </nav>

            <?php /* Bloque secundario: la cuenta no compite con el catálogo. */ ?>
            <ul class="cajon__secundario">
                <?php foreach ($cajon_secundario as $cajon_enlace): ?>
                    <li>
                        <a class="cajon__enlace-secundario t-body-sm"
                           href="<?= e(url($cajon_enlace['ruta'])) ?>"
                           <?= es_ruta_exacta($cajon_enlace['ruta']) ? 'aria-current="page"' : '' ?>>
                            <?= e($cajon_enlace['texto']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php /* Acá no va un "Cerrar sesión": sería un tercer lugar para
                         lo mismo. Desde el celular se llega por "Mis pedidos",
                         que lleva a /cuenta, y ahí está el botón de verdad. */ ?>
            </ul>

            <div class="cajon__regla">
                <?php require RASTRO_VIEWS . '/partials/regla-precio.php'; ?>
            </div>

            <?php if ($cajon_whatsapp !== null): ?>
                <a class="cajon__cta t-mono-label" href="<?= e($cajon_whatsapp) ?>"
                   rel="noopener" target="_blank">
                    Escribinos por WhatsApp
                    <span class="visualmente-oculto">(abre WhatsApp en una pestaña nueva)</span>
                </a>
            <?php endif; ?>

            <div class="cajon__pie">
                <a class="cajon__pie-enlace t-mono-texto-sm" href="<?= e($cajon_settings['instagram'] ?? '') ?>"
                   rel="noopener" target="_blank">
                    Instagram
                    <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
                </a>
                <a class="cajon__pie-enlace t-mono-texto-sm" href="mailto:<?= e($cajon_settings['email'] ?? '') ?>">
                    <?= e($cajon_settings['email'] ?? '') ?>
                </a>
                <p class="cajon__horario t-mono-texto-sm"><?= e($cajon_settings['horario'] ?? '') ?></p>
            </div>

        </div>
    </div>
</div>
