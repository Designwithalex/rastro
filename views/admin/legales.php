<?php
/**
 * admin/legales.php — Términos y condiciones, editables.
 *
 * Pedido del cliente (22/10): los términos tenían frases que no son ciertas
 * —transporte propio, envío gratis, retiro en depósito— y no había dónde
 * cambiarlas. Cada bloque es un título y un texto; una línea en blanco
 * separa párrafos. Un bloque con el título vacío se borra al guardar.
 *
 * El texto acepta los marcadores de interpolar(): {descuento} y
 * {whatsapp} se reemplazan por el valor vigente de Configuración.
 */

declare(strict_types=1);

panel_exigir_sesion();

if (panel_es_post()) {
    panel_exigir_csrf();

    $titulos = (array) ($_POST['titulo'] ?? []);
    $textos  = (array) ($_POST['texto'] ?? []);
    $bloques = [];

    foreach ($titulos as $i => $titulo) {
        $titulo = trim((string) $titulo);
        $texto  = trim(str_replace("\r\n", "\n", (string) ($textos[$i] ?? '')));

        if ($titulo === '') {
            continue;
        }

        $bloques[] = ['titulo' => mb_substr($titulo, 0, 120), 'texto' => mb_substr($texto, 0, 8000)];
    }

    $guardado = repo_save_settings([
        'terminos_bloques' => json_encode($bloques, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    panel_ir_con_aviso(
        '/admin/legales',
        $guardado !== null ? 'ok' : 'error',
        $guardado !== null ? 'Términos guardados. Ya se ven en el sitio.' : 'No se pudo guardar.'
    );
}

/* Lo que se muestra para editar es lo que se ve en el sitio, pero sin
   interpolar: si alguien escribió {descuento}, tiene que seguir viendo el
   marcador y no el número. */
$settings  = repo_settings();
$guardados = json_decode((string) ($settings['terminos_bloques'] ?? ''), true);
$bloques   = is_array($guardados) && $guardados !== []
    ? $guardados
    : array_map(static fn (array $b): array => [
        'titulo' => $b['titulo'],
        // Los textos por defecto vienen con la sangría del código fuente.
        'texto'  => preg_replace('/[ \t]*\n[ \t]*/', "\n", trim((string) ($b['texto'] ?? ''))),
    ], terminos_por_defecto($settings));

// Un bloque vacío al final, para sumar uno nuevo sin JavaScript.
$bloques[] = ['titulo' => '', 'texto' => ''];

$titulo = 'Legales';
$bajada = 'Términos y condiciones del sitio.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<form class="panel-formulario" method="post" action="<?= e(url('/admin/legales')) ?>">
    <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">

    <div class="panel-columnas">
        <div class="panel-columnas__principal">
            <?php foreach ($bloques as $i => $bloque): ?>
                <fieldset class="panel-grupo">
                    <legend class="panel-grupo__titulo">
                        <?= $bloque['titulo'] === '' ? 'Bloque nuevo' : e(sprintf('%02d', $i + 1)) ?>
                    </legend>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="titulo-<?= $i ?>">Título</label>
                        <input class="campo-panel__control" type="text" id="titulo-<?= $i ?>"
                               name="titulo[<?= $i ?>]" value="<?= e((string) $bloque['titulo']) ?>">
                    </div>

                    <div class="campo-panel">
                        <label class="campo-panel__rotulo" for="texto-<?= $i ?>">Texto</label>
                        <textarea class="campo-panel__control" id="texto-<?= $i ?>" rows="8"
                                  name="texto[<?= $i ?>]"><?= e((string) $bloque['texto']) ?></textarea>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <aside class="panel-columnas__lateral">
            <div class="panel-acciones panel-acciones--pegada">
                <button class="panel-boton panel-boton--acento panel-boton--ancho" type="submit">
                    Guardar términos
                </button>
                <p class="panel-nota">
                    Una línea en blanco separa párrafos. Para borrar un bloque, vaciá su título.
                    <code>{descuento}</code> se reemplaza por el porcentaje vigente.
                </p>
                <a class="panel-enlace" target="_blank" rel="noopener" href="<?= e(url('/terminos')) ?>">
                    Ver en el sitio
                    <span class="visualmente-oculto">(abre en una pestaña nueva)</span>
                </a>
            </div>
        </aside>
    </div>
</form>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
