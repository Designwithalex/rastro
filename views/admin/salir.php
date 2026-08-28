<?php
/**
 * admin/salir.php — cierra la sesión del panel.
 *
 * Sólo por POST y con token. Un GET que cierra sesión lo dispara cualquier
 * cosa que precargue enlaces —el navegador, una extensión, un antivirus— y
 * el cliente se encuentra afuera en medio de una carga sin haber tocado
 * nada. Por eso el botón de la barra es un formulario y no un <a>.
 */

declare(strict_types=1);

if (!panel_es_post()) {
    panel_ir('/admin');
}

panel_exigir_csrf();
panel_cerrar_sesion();
panel_ir_con_aviso('/admin/ingresar', 'ok', 'Cerraste la sesión.');
