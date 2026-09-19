<?php
/**
 * admin/arrepentimientos.php — los pedidos de arrepentimiento.
 *
 * La décima sección del panel, y existe por una razón concreta: la
 * Resolución 424/2020 da **10 días corridos** para resolver cada pedido.
 * Hasta acá la única forma de enterarse era el mail que dispara el
 * formulario, y un mail puede caer en spam, puede borrarse, o puede llegar
 * a una casilla que nadie mira los fines de semana. Algo que vence en diez
 * días no puede depender de eso.
 *
 * NO SE BORRAN Y NO SE EDITAN. Son la constancia de que alguien ejerció un
 * derecho: lo único que se toca es el estado. Un registro que se puede
 * borrar no sirve para demostrar nada, que es justamente lo que la
 * resolución pide poder hacer.
 *
 * El contador de días desde que entró está arriba de todo a propósito. El
 * plazo es lo que importa acá, y un listado que no lo muestra obliga a
 * hacer la cuenta a mano en la cabeza.
 */

declare(strict_types=1);

panel_exigir_sesion();

$estados = panel_estados_arrepentimiento();

if (panel_es_post()) {
    panel_exigir_csrf();

    $codigo = panel_texto('codigo');
    $nuevo  = panel_texto('estado');

    if (!isset($estados[$nuevo])) {
        panel_ir_con_aviso('/admin/arrepentimientos', 'error', 'Ese estado no existe.');
    }

    /* El resultado en una variable: llamarla dos veces en el ternario la
       ejecutaría dos veces, y esto escribe un archivo. */
    $guardado = repo_save_arrepentimiento_estado($codigo, $nuevo);

    panel_ir_con_aviso(
        '/admin/arrepentimientos',
        $guardado ? 'ok' : 'error',
        $guardado ? 'Estado actualizado.' : 'No se pudo actualizar.'
    );
}

$pedidos = repo_arrepentimientos();

/* Los que todavía esperan. Es el número que importa: los resueltos son
   historia y los rechazados también. */
$abiertos = array_values(array_filter(
    $pedidos,
    static fn (array $p): bool => in_array($p['estado'] ?? '', ['recibido', 'en_curso'], true)
));

/** Días corridos desde que entró el pedido. */
$dias = static function (string $creado): int {
    $t = strtotime($creado);

    return $t === false ? 0 : (int) floor((time() - $t) / 86400);
};

$titulo = 'Arrepentimientos';
$bajada = 'La ley da 10 días corridos para resolver cada uno.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<?php if ($abiertos !== []): ?>
    <p class="panel-nota panel-nota--alerta">
        <strong><?= e((string) count($abiertos)) ?>
        sin resolver.</strong>
        Cada uno vence a los 10 días corridos de recibido.
    </p>
<?php endif; ?>

<?php if ($pedidos === []): ?>

    <p class="panel-vacio">
        Todavía no entró ningún pedido de arrepentimiento.
        Van a aparecer acá apenas alguien complete el formulario de
        <code>/arrepentimiento</code>.
    </p>

<?php else: ?>

    <div class="panel-tabla-marco">
        <table class="panel-tabla">
            <thead>
                <tr>
                    <th scope="col">Trámite</th>
                    <th scope="col">Quién</th>
                    <th scope="col">Pedido</th>
                    <th scope="col" class="panel-tabla__num">Días</th>
                    <th scope="col">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <?php
                    $codigo   = (string) ($p['codigo'] ?? '');
                    $abierto  = in_array($p['estado'] ?? '', ['recibido', 'en_curso'], true);
                    $edad     = $dias((string) ($p['creado'] ?? ''));
                    $vencido  = $abierto && $edad > 10;
                    ?>
                    <tr>
                        <th scope="row">
                            <code><?= e($codigo) ?></code>
                            <span class="panel-tabla__sku"><?= e(substr((string) ($p['creado'] ?? ''), 0, 10)) ?></span>
                        </th>

                        <td class="panel-tabla__principal">
                            <?= e((string) ($p['nombre'] ?? '')) ?>
                            <span class="panel-tabla__sku">
                                <a class="panel-enlace" href="mailto:<?= e((string) ($p['email'] ?? '')) ?>">
                                    <?= e((string) ($p['email'] ?? '')) ?>
                                </a>
                            </span>
                        </td>

                        <td>
                            <?php if (($p['pedido'] ?? '') !== ''): ?>
                                <code><?= e((string) $p['pedido']) ?></code>
                            <?php else: ?>
                                <span class="panel-tabla__sin-foto" aria-hidden="true">—</span>
                                <span class="visualmente-oculto">sin número de pedido</span>
                            <?php endif; ?>
                        </td>

                        <?php /* Los días van con la palabra al lado cuando vencieron:
                                 un número en rojo y nada más no le dice nada a quien
                                 no distingue ese rojo (CLAUDE.md §5.4). */ ?>
                        <td class="panel-tabla__num<?= $vencido ? ' panel-tabla__num--alerta' : '' ?>">
                            <?= e((string) $edad) ?>
                            <?php if ($vencido): ?>
                                <span class="panel-pastilla panel-pastilla--alerta">Vencido</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form class="panel-linea" method="post"
                                  action="<?= e(url('/admin/arrepentimientos')) ?>">
                                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                                <input type="hidden" name="codigo" value="<?= e($codigo) ?>">

                                <div class="campo-panel">
                                    <label class="campo-panel__rotulo visualmente-oculto"
                                           for="e-<?= e($codigo) ?>">Estado</label>
                                    <select class="campo-panel__control" id="e-<?= e($codigo) ?>" name="estado">
                                        <?php foreach ($estados as $clave => $rotulo): ?>
                                            <option value="<?= e($clave) ?>"
                                                <?= ($p['estado'] ?? '') === $clave ? 'selected' : '' ?>>
                                                <?= e($rotulo) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button class="panel-boton panel-boton--chico" type="submit">Guardar</button>
                            </form>
                        </td>
                    </tr>

                    <?php if (($p['detalle'] ?? '') !== ''): ?>
                        <tr>
                            <td colspan="5" class="panel-tabla__detalle">
                                <span class="panel-tabla__sku">Comentario</span>
                                <?= e((string) $p['detalle']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="panel-nota">
        No se borran ni se editan: son la constancia de que alguien ejerció un
        derecho. Lo único editable es el estado.
    </p>

<?php endif; ?>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
