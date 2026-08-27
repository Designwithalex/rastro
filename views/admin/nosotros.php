<?php
/**
 * admin/nosotros.php — la novena sección del panel.
 *
 * ESTA PANTALLA NO ESTÁ DISEÑADA EN FIGMA. Era PENDIENTES #49: el
 * contrato se amplió con `repo_nosotros()` y las ocho pantallas dibujadas
 * no la cubrían. Se resuelve con el mismo lenguaje que las otras ocho
 * —bloques de campos sobre superficie blanca, la misma tipografía, los
 * mismos avisos— así que cuando se dibuje el frame no debería haber
 * sorpresas. Queda anotado que el diseño va después del código, al revés
 * que en todo el resto del proyecto.
 *
 * ES UNA SOLA PANTALLA Y NO TRES. El cliente piensa "la página de
 * Nosotros" como una sola cosa: fundadores, hitos, obras y cifras son
 * bloques de eso, no tres secciones distintas. Por eso el contrato tiene
 * una función y no tres, y por eso acá hay un formulario y no tres ABM.
 *
 * LOS HUECOS SE VEN. Un valor vacío se dibuja en el sitio como
 * `[ DATO ]`, `[ AÑO ]` o `[ ROL ]`, en bordeaux. Esta pantalla lo dice
 * al lado de cada campo: quien carga tiene que saber que dejarlo vacío no
 * esconde la fila, la marca.
 *
 * LA CIFRA DEL CATÁLOGO NO SE CARGA. Sale de contar los productos
 * activos. Se muestra deshabilitada para que nadie la busque.
 */

declare(strict_types=1);

$admin_titulo  = 'Nosotros';
$admin_seccion = 'nosotros';

require RASTRO_VIEWS . '/admin/_guard.php';

$contenido = repo_nosotros();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_exigir();

    /* Se parte del contenido actual y se pisan sólo los campos que esta
       pantalla edita. Así, si el contrato crece con un bloque nuevo que
       todavía no tiene formulario, guardar desde acá no lo borra. */
    $nuevo = $contenido;

    $nuevo['provisorio'] = !empty($_POST['provisorio']);

    $nuevo['encabezado']['kicker']      = trim((string) ($_POST['kicker'] ?? ''));
    $nuevo['encabezado']['titulo']      = trim((string) ($_POST['titulo_pagina'] ?? ''));
    $nuevo['encabezado']['declaracion'] = trim((string) ($_POST['declaracion'] ?? ''));

    $nuevo['franja']['titulo']       = trim((string) ($_POST['franja_titulo'] ?? ''));
    $nuevo['franja']['texto']        = trim((string) ($_POST['franja_texto'] ?? ''));
    $nuevo['franja']['enlace_texto'] = trim((string) ($_POST['franja_enlace'] ?? ''));

    // Cifras: sólo el valor. El rótulo y la clave son del contrato.
    foreach ($nuevo['cifras'] as $i => $cifra) {
        if (($cifra['clave'] ?? '') === 'productos_en_catalogo') {
            continue;
        }

        $valor = trim((string) (($_POST['cifra'] ?? [])[$cifra['clave'] ?? ''] ?? ''));
        $nuevo['cifras'][$i]['valor'] = $valor === '' ? null : (int) $valor;
    }

    // Fundadores.
    $nuevo['fundadores']['titulo']   = trim((string) ($_POST['fundadores_titulo'] ?? ''));
    $nuevo['fundadores']['texto']    = trim((string) ($_POST['fundadores_texto'] ?? ''));
    $nuevo['fundadores']['foto']     = trim((string) ($_POST['fundadores_foto'] ?? '')) ?: null;
    $nuevo['fundadores']['foto_alt'] = trim((string) ($_POST['fundadores_foto_alt'] ?? '')) ?: null;

    $personas = [];
    foreach ((array) ($_POST['persona_nombre'] ?? []) as $i => $nombre) {
        $nombre = trim((string) $nombre);
        $rol    = trim((string) (($_POST['persona_rol'] ?? [])[$i] ?? ''));

        if ($nombre !== '') {
            $personas[] = ['nombre' => $nombre, 'rol' => $rol ?: null];
        }
    }
    $nuevo['fundadores']['personas'] = $personas;

    // Obras. Una obra sin nombre no es una obra: se descarta la fila.
    $obras = [];
    foreach ((array) ($_POST['obra_nombre'] ?? []) as $i => $nombre) {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            continue;
        }

        $obras[] = [
            'nombre' => $nombre,
            'ciudad' => trim((string) (($_POST['obra_ciudad'] ?? [])[$i] ?? '')) ?: null,
            'texto'  => trim((string) (($_POST['obra_texto'] ?? [])[$i] ?? '')) ?: null,
            'foto'   => trim((string) (($_POST['obra_foto'] ?? [])[$i] ?? '')) ?: null,
        ];
    }
    $nuevo['obras']['items'] = $obras;

    $nuevo['donde_estamos']['direccion'] = trim((string) ($_POST['direccion'] ?? '')) ?: null;
    $nuevo['donde_estamos']['texto']     = trim((string) ($_POST['donde_texto'] ?? ''));

    repo_nosotros_guardar($nuevo);
    admin_avisar('Contenido guardado.');

    header('Location: ' . url('/admin/nosotros'), true, 303);
    exit;
}

/* Filas vacías de arranque para que se pueda cargar sin JavaScript. */
$personas = $contenido['fundadores']['personas'];
while (count($personas) < 2) {
    $personas[] = ['nombre' => '', 'rol' => ''];
}

$obras = $contenido['obras']['items'];
while (count($obras) < 3) {
    $obras[] = ['nombre' => '', 'ciudad' => '', 'texto' => '', 'foto' => ''];
}

/* La cabecera va recién acá, no arriba: el bloque de POST necesita poder
   redirigir, y un header() después del primer byte de HTML no sale. */
require RASTRO_VIEWS . '/admin/_cabecera.php';
?>

<p class="admin__conteo t-mono-texto">
    Esto es lo que se ve en <a href="<?= e(url('/nosotros')) ?>" target="_blank" rel="noopener">/nosotros ↗</a>
    y en la franja de la home. Lo que quede vacío se dibuja como hueco visible en bordeaux,
    no se esconde: es a propósito, para que en una revisión alguien pregunte el dato.
</p>

<form class="form-admin" method="post" action="<?= e(url('/admin/nosotros')) ?>" data-avisar-cambios>
    <?= csrf_campo() ?>

    <?php /* ============================================================
             Aviso de copy provisorio
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Estado de la página</legend>

        <label class="opcion-admin">
            <input type="checkbox" name="provisorio" value="1" <?= $contenido['provisorio'] ? 'checked' : '' ?>>
            <span class="t-mono-texto">El texto todavía es provisorio</span>
        </label>
        <span class="form-admin__ayuda t-mono-texto-sm">
            Marcada, la página muestra arriba un aviso de que el contenido no es definitivo.
            Se destilda cuando el texto esté cerrado.
        </span>
    </fieldset>

    <?php /* ============================================================
             Encabezado
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Encabezado</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="kicker">Ojal</label>
                <input class="campo t-mono-texto" type="text" id="kicker" name="kicker"
                       value="<?= e((string) ($contenido['encabezado']['kicker'] ?? '')) ?>">
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="titulo_pagina">Título</label>
                <input class="campo t-mono-texto" type="text" id="titulo_pagina" name="titulo_pagina"
                       value="<?= e((string) ($contenido['encabezado']['titulo'] ?? '')) ?>">
            </p>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="declaracion">Declaración</label>
                <textarea class="campo campo--area t-mono-texto" id="declaracion" name="declaracion"
                          rows="3"><?= e((string) ($contenido['encabezado']['declaracion'] ?? '')) ?></textarea>
            </p>
        </div>
    </fieldset>

    <?php /* ============================================================
             Cifras
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Las cuatro cifras</legend>

        <div class="form-admin__grilla">
            <?php foreach ($contenido['cifras'] as $cifra): ?>
                <?php $es_catalogo = ($cifra['clave'] ?? '') === 'productos_en_catalogo'; ?>
                <p class="form-admin__campo">
                    <label class="form-admin__etiqueta t-mono-label-sm" for="cifra-<?= e((string) $cifra['clave']) ?>">
                        <?= e((string) ($cifra['rotulo'] ?? $cifra['clave'])) ?>
                    </label>
                    <input class="campo t-mono-texto" type="number"
                           id="cifra-<?= e((string) $cifra['clave']) ?>"
                           name="cifra[<?= e((string) $cifra['clave']) ?>]"
                           value="<?= e((string) ($cifra['valor'] ?? '')) ?>"
                           <?= $es_catalogo ? 'disabled' : '' ?>>
                    <span class="form-admin__ayuda t-mono-texto-sm">
                        <?= $es_catalogo
                            ? 'Se cuenta sola sobre los productos activos. No se carga a mano.'
                            : 'Vacío se dibuja como [ DATO ] en bordeaux.' ?>
                    </span>
                </p>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <?php /* ============================================================
             Fundadores
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Fundadores</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="fundadores_titulo">Título</label>
                <input class="campo t-mono-texto" type="text" id="fundadores_titulo" name="fundadores_titulo"
                       value="<?= e((string) ($contenido['fundadores']['titulo'] ?? '')) ?>">
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="fundadores_foto">Foto de los dos</label>
                <input class="campo t-mono-texto" type="text" id="fundadores_foto" name="fundadores_foto"
                       value="<?= e((string) ($contenido['fundadores']['foto'] ?? '')) ?>"
                       placeholder="img/nosotros/…">
                <span class="form-admin__ayuda t-mono-texto-sm">
                    Sin foto, el bloque se reduce a un párrafo firmado con los dos nombres.
                    No se pone un retrato de archivo.
                </span>
            </p>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="fundadores_texto">Texto</label>
                <textarea class="campo campo--area t-mono-texto" id="fundadores_texto" name="fundadores_texto"
                          rows="4"><?= e((string) ($contenido['fundadores']['texto'] ?? '')) ?></textarea>
            </p>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="fundadores_foto_alt">
                    Descripción de la foto
                </label>
                <input class="campo t-mono-texto" type="text" id="fundadores_foto_alt" name="fundadores_foto_alt"
                       value="<?= e((string) ($contenido['fundadores']['foto_alt'] ?? '')) ?>">
                <span class="form-admin__ayuda t-mono-texto-sm">
                    Es lo que lee alguien que no puede ver la foto. Describí qué se ve, no repitas el título.
                </span>
            </p>
        </div>

        <div class="form-admin__campo">
            <span class="form-admin__etiqueta t-mono-label-sm">Quiénes son</span>
            <div class="repetible" id="lista-personas">
                <?php foreach ($personas as $i => $persona): ?>
                    <div class="repetible__fila">
                        <input class="campo t-mono-texto" type="text" name="persona_nombre[<?= e((string) $i) ?>]"
                               value="<?= e((string) ($persona['nombre'] ?? '')) ?>" placeholder="Nombre y apellido">
                        <input class="campo t-mono-texto" type="text" name="persona_rol[<?= e((string) $i) ?>]"
                               value="<?= e((string) ($persona['rol'] ?? '')) ?>" placeholder="Rol">
                        <button class="repetible__quitar" type="button" data-repetible-quitar
                                aria-label="Quitar persona">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="boton boton--fantasma repetible__sumar" type="button" data-repetible-sumar="lista-personas">
                <span class="t-mono-label">Sumar persona</span>
            </button>
        </div>
    </fieldset>

    <?php /* ============================================================
             Obras
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Obras</legend>

        <p class="form-admin__ayuda t-mono-texto-sm">
            Es la prueba más fuerte de la página: un club decide mirando lo que ya hiciste
            para otro club. <strong>Si la lista queda vacía, la sección no se dibuja en el
            sitio</strong> —una sección de obras vacía es peor que no tenerla— y la
            numeración de las demás se recalcula sola.
        </p>

        <div class="repetible repetible--obras" id="lista-obras">
            <?php foreach ($obras as $i => $obra): ?>
                <div class="repetible__fila repetible__fila--obra">
                    <input class="campo t-mono-texto" type="text" name="obra_nombre[<?= e((string) $i) ?>]"
                           value="<?= e((string) ($obra['nombre'] ?? '')) ?>" placeholder="Club o gimnasio">
                    <input class="campo t-mono-texto" type="text" name="obra_ciudad[<?= e((string) $i) ?>]"
                           value="<?= e((string) ($obra['ciudad'] ?? '')) ?>" placeholder="Ciudad">
                    <input class="campo t-mono-texto" type="text" name="obra_texto[<?= e((string) $i) ?>]"
                           value="<?= e((string) ($obra['texto'] ?? '')) ?>" placeholder="Qué se entregó">
                    <input class="campo t-mono-texto" type="text" name="obra_foto[<?= e((string) $i) ?>]"
                           value="<?= e((string) ($obra['foto'] ?? '')) ?>" placeholder="img/obras/…">
                    <button class="repetible__quitar" type="button" data-repetible-quitar
                            aria-label="Quitar obra">×</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="boton boton--fantasma repetible__sumar" type="button" data-repetible-sumar="lista-obras">
            <span class="t-mono-label">Sumar obra</span>
        </button>
    </fieldset>

    <?php /* ============================================================
             Dónde estamos y franja
             ============================================================ */ ?>
    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Dónde estamos</legend>

        <div class="form-admin__grilla">
            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="direccion">Dirección del depósito</label>
                <input class="campo t-mono-texto" type="text" id="direccion" name="direccion"
                       value="<?= e((string) ($contenido['donde_estamos']['direccion'] ?? '')) ?>">
                <span class="form-admin__ayuda t-mono-texto-sm">
                    Vacío se dibuja como [ DIRECCIÓN ]. La ficha de producto también dice
                    "retiro en depósito", así que este dato falta en dos lados.
                </span>
            </p>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="donde_texto">Texto</label>
                <textarea class="campo campo--area t-mono-texto" id="donde_texto" name="donde_texto"
                          rows="3"><?= e((string) ($contenido['donde_estamos']['texto'] ?? '')) ?></textarea>
            </p>
        </div>
    </fieldset>

    <fieldset class="form-admin__bloque">
        <legend class="form-admin__leyenda t-display-s">Franja de la home</legend>

        <p class="form-admin__ayuda t-mono-texto-sm">
            Es el bloque de Nosotros que aparece en la portada, antes del bloque mayorista.
        </p>

        <div class="form-admin__grilla">
            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="franja_titulo">Título</label>
                <input class="campo t-mono-texto" type="text" id="franja_titulo" name="franja_titulo"
                       value="<?= e((string) ($contenido['franja']['titulo'] ?? '')) ?>">
            </p>

            <p class="form-admin__campo form-admin__campo--ancho">
                <label class="form-admin__etiqueta t-mono-label-sm" for="franja_texto">Texto</label>
                <textarea class="campo campo--area t-mono-texto" id="franja_texto" name="franja_texto"
                          rows="3"><?= e((string) ($contenido['franja']['texto'] ?? '')) ?></textarea>
            </p>

            <p class="form-admin__campo">
                <label class="form-admin__etiqueta t-mono-label-sm" for="franja_enlace">Texto del enlace</label>
                <input class="campo t-mono-texto" type="text" id="franja_enlace" name="franja_enlace"
                       value="<?= e((string) ($contenido['franja']['enlace_texto'] ?? '')) ?>">
            </p>
        </div>
    </fieldset>

    <div class="form-admin__acciones">
        <button class="boton boton--acento" type="submit">
            <span class="t-mono-label">Guardar contenido</span>
            <span class="boton__flecha" aria-hidden="true">→</span>
        </button>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/_pie.php'; ?>
