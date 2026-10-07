<?php
/**
 * admin/administradores.php — quién puede entrar al panel.
 *
 * Un administrador es una cuenta normal del sitio con `rol: "admin"`. Esta
 * pantalla NO crea cuentas ni maneja contraseñas: la persona se registra
 * en /registro con su correo y su clave, y acá se le da o se le quita el
 * acceso. Así ninguna contraseña pasa por las manos de quien da el acceso,
 * y si alguien la olvida, la recupera solo desde "Olvidé mi contraseña".
 *
 * Además de estos, existe el administrador de arranque de app/config.php.
 * Se muestra pero no se toca desde acá: es la llave de emergencia, la que
 * sigue abriendo aunque alguien se equivoque en esta pantalla.
 *
 * Dos frenos:
 *   · Nadie se quita el acceso a sí mismo. Sería la forma más rápida de
 *     quedarse afuera sin querer.
 *   · Al quitar el acceso, la cuenta vuelve a `mayorista` si tiene empresa
 *     cargada y a `cliente` si no. Es lo que habría elegido el registro.
 *
 * Quitar el acceso corta también la sesión que esa persona tenga abierta:
 * panel_usuario() revalida el rol en cada pedido.
 */

declare(strict_types=1);

panel_exigir_sesion();

$yo = panel_usuario() ?? [];

if (panel_es_post()) {
    panel_exigir_csrf();

    $accion = panel_texto('accion');

    if ($accion === 'dar') {
        $email = mb_strtolower(panel_texto('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            panel_ir_con_aviso('/admin/administradores', 'error', 'Revisá el correo: no parece una dirección válida.');
        }

        $cuenta = null;

        foreach (repo_all_users() as $u) {
            if (mb_strtolower(trim((string) ($u['email'] ?? ''))) === $email) {
                $cuenta = $u;
                break;
            }
        }

        if ($cuenta === null) {
            panel_ir_con_aviso(
                '/admin/administradores',
                'error',
                'No hay ninguna cuenta con ese correo. Primero tiene que registrarse en el sitio, en /registro.'
            );
        }

        if (($cuenta['activo'] ?? true) !== true) {
            panel_ir_con_aviso('/admin/administradores', 'error', 'Esa cuenta está dada de baja.');
        }

        if (($cuenta['rol'] ?? '') === 'admin') {
            panel_ir_con_aviso('/admin/administradores', 'ok', 'Esa cuenta ya era administradora.');
        }

        $guardado = repo_save_user(['id' => (int) $cuenta['id'], 'rol' => 'admin']);

        panel_ir_con_aviso(
            '/admin/administradores',
            $guardado !== null ? 'ok' : 'error',
            $guardado !== null
                ? 'Listo: ' . $email . ' ya puede entrar al panel con su contraseña.'
                : 'No se pudo guardar.'
        );
    }

    if ($accion === 'quitar') {
        $id = panel_entero('id', 0) ?? 0;

        if (($yo['origen'] ?? '') === 'users' && (int) ($yo['id'] ?? 0) === $id) {
            panel_ir_con_aviso(
                '/admin/administradores',
                'error',
                'No podés quitarte el acceso a vos mismo. Pedíselo a otro administrador.'
            );
        }

        $cuenta = $id > 0 ? repo_user($id) : null;

        if ($cuenta === null || ($cuenta['rol'] ?? '') !== 'admin') {
            panel_ir_con_aviso('/admin/administradores', 'error', 'Esa cuenta no es administradora.');
        }

        $rol = trim((string) ($cuenta['empresa'] ?? '')) !== '' ? 'mayorista' : 'cliente';

        $guardado = repo_save_user(['id' => $id, 'rol' => $rol]);

        panel_ir_con_aviso(
            '/admin/administradores',
            $guardado !== null ? 'ok' : 'error',
            $guardado !== null
                ? 'Se le quitó el acceso a ' . (string) ($cuenta['email'] ?? '') . '.'
                : 'No se pudo guardar.'
        );
    }

    panel_ir('/admin/administradores');
}

$administradores = array_values(array_filter(
    repo_all_users(),
    static fn (array $u): bool => ($u['rol'] ?? '') === 'admin'
));

$admin_config = mb_strtolower(trim((string) config('panel_email', '')));

$titulo = 'Administradores';
$bajada = 'Quién puede entrar a este panel.';

require RASTRO_VIEWS . '/admin/layout/cabeza.php';
?>

<p class="panel-nota">
    Para sumar a alguien: primero se registra en el sitio, en
    <code>/registro</code>, con su correo y su contraseña. Después escribís su
    correo acá abajo. Entra al panel con esa misma contraseña, y si la olvida
    la recupera desde "Olvidé mi contraseña". Todos los administradores pueden
    editar todo.
</p>

<div class="panel-tabla-marco">
    <table class="panel-tabla">
        <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Correo</th>
                <th scope="col">Cuenta</th>
                <th scope="col"><span class="visualmente-oculto">Acciones</span></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($admin_config !== ''): ?>
                <tr>
                    <td><?= e((string) config('panel_nombre', 'Administración')) ?></td>
                    <td><code><?= e($admin_config) ?></code></td>
                    <td>
                        <span class="panel-pastilla">Configuración del servidor</span>
                    </td>
                    <td>
                        <?php /* Es la llave de emergencia: no se quita desde el panel. */ ?>
                        Fijo
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($administradores as $admin): ?>
                <?php
                $adm_id   = (int) ($admin['id'] ?? 0);
                $soy_yo   = ($yo['origen'] ?? '') === 'users' && (int) ($yo['id'] ?? 0) === $adm_id;
                ?>
                <tr>
                    <td>
                        <?= e(trim((string) ($admin['nombre'] ?? '') . ' ' . (string) ($admin['apellido'] ?? ''))) ?>
                        <?php if ($soy_yo): ?>
                            <span class="panel-pastilla panel-pastilla--ok">Vos</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e((string) ($admin['email'] ?? '')) ?></code></td>
                    <td>
                        <?php $adm_alta = strtotime((string) ($admin['creado'] ?? '')); ?>
                        Registrada<?= $adm_alta ? ' el ' . e(date('d/m/Y', $adm_alta)) : '' ?>
                    </td>
                    <td>
                        <?php if (!$soy_yo): ?>
                            <form method="post" action="<?= e(url('/admin/administradores')) ?>"
                                  data-confirmar="¿Quitarle el acceso al panel a <?= e((string) ($admin['email'] ?? '')) ?>?">
                                <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
                                <input type="hidden" name="accion" value="quitar">
                                <input type="hidden" name="id" value="<?= e((string) $adm_id) ?>">
                                <button class="panel-enlace panel-enlace--peligro" type="submit">Quitar acceso</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($administradores === []): ?>
    <p class="panel-vacio panel-vacio--chico">
        Todavía no hay cuentas del sitio con acceso al panel.
    </p>
<?php endif; ?>

<section class="panel-alta" aria-labelledby="alta-admin">
    <h2 class="panel-seccion__titulo" id="alta-admin">Dar acceso</h2>

    <form class="panel-alta__formulario panel-linea" method="post"
          action="<?= e(url('/admin/administradores')) ?>">
        <input type="hidden" name="csrf" value="<?= e(panel_csrf()) ?>">
        <input type="hidden" name="accion" value="dar">

        <div class="campo-panel campo-panel--crece">
            <label class="campo-panel__rotulo" for="nuevo-admin">Correo de la cuenta registrada</label>
            <input class="campo-panel__control" type="email" id="nuevo-admin" name="email"
                   autocomplete="off" required>
        </div>

        <button class="panel-boton panel-boton--acento" type="submit">Dar acceso</button>
    </form>
</section>

<?php require RASTRO_VIEWS . '/admin/layout/pie.php'; ?>
