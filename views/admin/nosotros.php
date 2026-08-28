<?php
/**
 * admin/nosotros.php — el contenido de la página /nosotros.
 *
 * Es la novena sección del panel, la que no estaba en los ocho frames de
 * Figma (PENDIENTES #49). Y no es la menos importante: /nosotros es la
 * página que Rastro manda por WhatsApp cuando en un club preguntan "¿quiénes
 * son estos?".
 *
 * ---------------------------------------------------------------------
 * POR QUÉ ES UNA PANTALLA CON BLOQUES Y NO NUEVE ABM
 *
 * El contenido sale de una sola función del repository, `repo_nosotros()`,
 * porque el cliente lo piensa como una sola cosa: "la página de Nosotros".
 * Fundadores, hitos y obras podrían ser tres tablas con su alta y su baja,
 * pero serían tres pantallas para editar lo que se lee de corrido en una.
 *
 * LAS LISTAS SE EDITAN COMO TEXTO. Los hitos, los pasos y las garantías son
 * listas de dos o tres campos. En vez de un widget de filas que se agregan
 * con JavaScript —y que sin JavaScript no funciona— se editan como líneas
 * con un separador. Es menos vistoso y se entiende sin explicación.
 *
 * EL AVISO DE "TEXTO PROVISORIO". `nosotros.json` trae `provisorio: true`
 * porque todo el copy lo escribió el diseño, no el cliente. La página lo
 * muestra y esta pantalla deja apagarlo: es lo último que se toca, cuando
 * el texto ya es de ellos.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

panel_exigir_sesion();

/**
 * Convierte un textarea de líneas "campo | campo | campo" en una lista de
 * registros. Se ignoran las líneas vacías y se rellena lo que falte.
 *
 * @param array<int,string> $claves nombres de las columnas, en orden
 */
$desde_lineas = static function (string $campo, array $claves): array {
    $filas = [];

    foreach (panel_lineas($campo) as $linea) {
        $partes = array_map('trim', explode('|', $linea));
        $fila   = [];

        foreach ($claves as $i => $clave) {
            $fila[$clave] = $partes[$i] ?? '';
        }

        $filas[] = $fila;
    }

    return $filas;
};

/** El camino inverso: registros a líneas, para llenar el textarea. */
$a_lineas = static function (array $filas, array $claves): string {
    $lineas = [];

    foreach ($filas as $fila) {
        if (!is_array($fila)) {
            continue;
        }

        $lineas[] = implode(' | ', array_map(
            static fn (string $clave): string => trim((string) ($fila[$clave] ?? '')),
            $claves
        ));
    }

    return implode("\n", $lineas);
};

if (panel_es_post()) {
    panel_exigir_csrf();

    /* Las cifras conservan su `clave`: es el identificador con el que la
       vista las busca. Sólo se editan el rótulo y el valor. Un valor vacío
       se guarda como null y la página lo dibuja como [ DATO ] visible, que
       es lo que hace ver en la revisión qué falta todavía. */
    $cifras = [];
    foreach (repo_nosotros()['cifras'] as $i => $cifra) {
        $valor = panel_entero('cifra_valor_' . $i);

        $cifras[] = [
            'clave'  => (string) ($cifra['clave'] ?? ''),
            'rotulo' => panel_texto('cifra_rotulo_' . $i, (string) ($cifra['rotulo'] ?? '')),
            'valor'  => $valor,
        ];
    }

    $guardado = repo_save_nosotros([
        'provisorio' => panel_booleano('provisorio'),

        'encabezado' => [
            'kicker'      => panel_texto('kicker'),
            'titulo'      => panel_texto('titulo_pagina'),
            'declaracion' => panel_texto('declaracion'),
        ],

        'cifras' => $cifras,

        'fundadores' => [
            'titulo'   => panel_texto('fundadores_titulo'),
            'texto'    => panel_texto('fundadores_texto'),
            'personas' => $desde_lineas('fundadores_personas', ['nombre', 'rol']),
        ],

        'historia' => [
            'titulo' => panel_texto('historia_titulo'),
            'texto'  => panel_texto('historia_texto'),
            'hitos'  => $desde_lineas('historia_hitos', ['anio', 'titulo', 'texto']),
        ],

        'como_trabajamos' => [
            'titulo' => panel_texto('trabajo_titulo'),
            'texto'  => panel_texto('trabajo_texto'),
            'pasos'  => $desde_lineas('trabajo_pasos', ['titulo', 'texto']),
        ],

        'obras' => [
            'titulo' => panel_texto('obras_titulo'),
            'texto'  => panel_texto('obras_texto'),
            'items'  => $desde_lineas('obras_items', ['titulo', 'texto']),
        ],

        'garantia' => [
            'titulo' => panel_texto('garantia_titulo'),
            'items'  => $desde_lineas('garantia_items', ['titulo', 'texto']),
        ],

        'donde_estamos' => [
            'titulo'    => panel_texto('donde_titulo'),
            'texto'     => panel_texto('donde_texto'),
            'direccion' => panel_texto('donde_direccion'),
        ],

        'cierre' => [
            'titulo' => panel_texto('cierre_titulo'),
            'texto'  => panel_texto('cierre_texto'),
        ],

        'franja' => [
            'titulo'       => panel_texto('franja_titulo'),
            'texto'        => panel_texto('franja_texto'),
            'enlace_texto' => panel_texto('franja_enlace'),
        ],
    ]);

    panel_ir_con_aviso(
        '/admin/nosotros',
        $guardado !== null ? 'ok' : 'error',
        $guardado !== null ? 'La página Nosotros quedó actualizada.' : 'No se pudo guardar.'
    );
}

$n = repo_nosotros();

$titulo = 'Nosotros';
$bajada = 'El contenido de la página que se manda cuando preguntan quiénes son.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if (($n['provisorio'] ?? false) === true): ?>
    <p class="panel-nota panel-nota--alerta">
        <strong>El texto está marcado como provisorio.</strong>
        Lo escribió el diseño para que la página tenga forma. La página lo avisa
        en pantalla hasta que se destilde la casilla del final.
    </p>
<?php endif; ?>

<form class="panel-formulario" method="post" action="<?= e(url('/admin/nosotros')) ?>">
    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Encabezado</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="kicker">Volanta</label>
            <input class="campo-panel__control" type="text" id="kicker" name="kicker"
                   value="<?= e((string) ($n['encabezado']['kicker'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="titulo_pagina">Titular</label>
            <input class="campo-panel__control" type="text" id="titulo_pagina" name="titulo_pagina"
                   value="<?= e((string) ($n['encabezado']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="declaracion">Declaración</label>
            <textarea class="campo-panel__control" id="declaracion" name="declaracion"
                      rows="4"><?= e((string) ($n['encabezado']['declaracion'] ?? '')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Las cuatro cifras</legend>

        <p class="campo-panel__ayuda">
            Un valor vacío se dibuja como <code>[ DATO ]</code> en la página, a propósito:
            así se ve qué falta en vez de leerse como si estuviera resuelto.
        </p>

        <?php foreach ((array) ($n['cifras'] ?? []) as $i => $cifra): ?>
            <div class="panel-fila">
                <div class="campo-panel campo-panel--crece">
                    <label class="campo-panel__rotulo" for="cifra-rotulo-<?= e((string) $i) ?>">Rótulo</label>
                    <input class="campo-panel__control" type="text"
                           id="cifra-rotulo-<?= e((string) $i) ?>" name="cifra_rotulo_<?= e((string) $i) ?>"
                           value="<?= e((string) ($cifra['rotulo'] ?? '')) ?>">
                </div>

                <div class="campo-panel campo-panel--angosto">
                    <label class="campo-panel__rotulo" for="cifra-valor-<?= e((string) $i) ?>">Valor</label>
                    <input class="campo-panel__control" type="text" inputmode="numeric"
                           id="cifra-valor-<?= e((string) $i) ?>" name="cifra_valor_<?= e((string) $i) ?>"
                           value="<?= ($cifra['valor'] ?? null) === null ? '' : e((string) (int) $cifra['valor']) ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Quién te atiende</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="fundadores_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="fundadores_titulo" name="fundadores_titulo"
                   value="<?= e((string) ($n['fundadores']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="fundadores_texto">Texto</label>
            <textarea class="campo-panel__control" id="fundadores_texto" name="fundadores_texto"
                      rows="3"><?= e((string) ($n['fundadores']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="fundadores_personas">Personas</label>
            <textarea class="campo-panel__control campo-panel__control--mono" id="fundadores_personas"
                      name="fundadores_personas" rows="3"><?= e($a_lineas((array) ($n['fundadores']['personas'] ?? []), ['nombre', 'rol'])) ?></textarea>
            <p class="campo-panel__ayuda">
                Una por línea: <code>Nombre | Rol</code>. El rol puede quedar vacío.
            </p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Cómo empezó</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="historia_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="historia_titulo" name="historia_titulo"
                   value="<?= e((string) ($n['historia']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="historia_texto">Texto</label>
            <textarea class="campo-panel__control" id="historia_texto" name="historia_texto"
                      rows="2"><?= e((string) ($n['historia']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="historia_hitos">Hitos</label>
            <textarea class="campo-panel__control campo-panel__control--mono" id="historia_hitos"
                      name="historia_hitos" rows="6"><?= e($a_lineas((array) ($n['historia']['hitos'] ?? []), ['anio', 'titulo', 'texto'])) ?></textarea>
            <p class="campo-panel__ayuda">
                Uno por línea: <code>Año | Título | Texto</code>. El año puede quedar vacío.
            </p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Cómo trabajamos</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="trabajo_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="trabajo_titulo" name="trabajo_titulo"
                   value="<?= e((string) ($n['como_trabajamos']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="trabajo_texto">Texto</label>
            <textarea class="campo-panel__control" id="trabajo_texto" name="trabajo_texto"
                      rows="2"><?= e((string) ($n['como_trabajamos']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="trabajo_pasos">Pasos</label>
            <textarea class="campo-panel__control campo-panel__control--mono" id="trabajo_pasos"
                      name="trabajo_pasos" rows="6"><?= e($a_lineas((array) ($n['como_trabajamos']['pasos'] ?? []), ['titulo', 'texto'])) ?></textarea>
            <p class="campo-panel__ayuda">Uno por línea: <code>Título | Texto</code>.</p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Obras</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="obras_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="obras_titulo" name="obras_titulo"
                   value="<?= e((string) ($n['obras']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="obras_texto">Texto</label>
            <textarea class="campo-panel__control" id="obras_texto" name="obras_texto"
                      rows="2"><?= e((string) ($n['obras']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="obras_items">Salas equipadas</label>
            <textarea class="campo-panel__control campo-panel__control--mono" id="obras_items"
                      name="obras_items" rows="5"><?= e($a_lineas((array) ($n['obras']['items'] ?? []), ['titulo', 'texto'])) ?></textarea>
            <p class="campo-panel__ayuda">
                Una por línea: <code>Título | Texto</code>. Vacío, la sección no se dibuja.
            </p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Garantía, envíos y posventa</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="garantia_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="garantia_titulo" name="garantia_titulo"
                   value="<?= e((string) ($n['garantia']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="garantia_items">Puntos</label>
            <textarea class="campo-panel__control campo-panel__control--mono" id="garantia_items"
                      name="garantia_items" rows="5"><?= e($a_lineas((array) ($n['garantia']['items'] ?? []), ['titulo', 'texto'])) ?></textarea>
            <p class="campo-panel__ayuda">Uno por línea: <code>Título | Texto</code>.</p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Dónde estamos</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="donde_titulo">Título</label>
            <input class="campo-panel__control" type="text" id="donde_titulo" name="donde_titulo"
                   value="<?= e((string) ($n['donde_estamos']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="donde_texto">Texto</label>
            <textarea class="campo-panel__control" id="donde_texto" name="donde_texto"
                      rows="3"><?= e((string) ($n['donde_estamos']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="donde_direccion">Dirección</label>
            <input class="campo-panel__control" type="text" id="donde_direccion" name="donde_direccion"
                   value="<?= e((string) ($n['donde_estamos']['direccion'] ?? '')) ?>">
            <p class="campo-panel__ayuda">Vacía, la página no promete una dirección que no existe.</p>
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Cierre y franja de la portada</legend>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="cierre_titulo">Título del cierre</label>
            <input class="campo-panel__control" type="text" id="cierre_titulo" name="cierre_titulo"
                   value="<?= e((string) ($n['cierre']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="cierre_texto">Texto del cierre</label>
            <textarea class="campo-panel__control" id="cierre_texto" name="cierre_texto"
                      rows="3"><?= e((string) ($n['cierre']['texto'] ?? '')) ?></textarea>
        </div>

        <?php /* Esta franja no está en /nosotros: es el bloque de la PORTADA
                 que resume quiénes son. Se edita acá porque su contenido sale
                 del mismo archivo, y conviene decirlo para que nadie lo
                 busque en la sección de banners. */ ?>
        <p class="campo-panel__ayuda">
            Lo que sigue es la franja de <strong>la portada</strong>, no de esta página.
        </p>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="franja_titulo">Título de la franja</label>
            <input class="campo-panel__control" type="text" id="franja_titulo" name="franja_titulo"
                   value="<?= e((string) ($n['franja']['titulo'] ?? '')) ?>">
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="franja_texto">Texto de la franja</label>
            <textarea class="campo-panel__control" id="franja_texto" name="franja_texto"
                      rows="3"><?= e((string) ($n['franja']['texto'] ?? '')) ?></textarea>
        </div>

        <div class="campo-panel">
            <label class="campo-panel__rotulo" for="franja_enlace">Texto del enlace</label>
            <input class="campo-panel__control" type="text" id="franja_enlace" name="franja_enlace"
                   value="<?= e((string) ($n['franja']['enlace_texto'] ?? '')) ?>">
        </div>
    </fieldset>

    <fieldset class="panel-grupo">
        <legend class="panel-grupo__titulo">Estado del texto</legend>

        <label class="campo-panel__casilla">
            <input type="checkbox" name="provisorio" value="1"
                   <?= ($n['provisorio'] ?? false) === true ? 'checked' : '' ?>>
            El texto todavía es provisorio
        </label>
        <p class="campo-panel__ayuda">
            Mientras esté tildado, la página avisa en pantalla que el contenido es de muestra.
            Destildalo cuando el texto ya sea el de ustedes.
        </p>
    </fieldset>

    <div class="panel-acciones">
        <button class="panel-boton panel-boton--acento" type="submit">Guardar la página</button>
        <a class="panel-enlace" href="<?= e(url('/nosotros')) ?>" target="_blank" rel="noopener">
            Ver la página
            <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
        </a>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
