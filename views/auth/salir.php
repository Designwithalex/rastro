<?php
/**
 * auth/salir.php — cierra la sesión del cliente.
 *
 * Sólo por POST y con token, igual que el panel. Un GET que cierra sesión
 * lo dispara cualquier cosa que precargue enlaces —el navegador, una
 * extensión, un antivirus— y la persona se encuentra afuera sin haber
 * tocado nada. Por eso el "Salir" de la cabecera es un formulario.
 */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !sesion_csrf_valido()) {
    header('Location: ' . url('/'), true, 303);

    exit;
}

sesion_salir();

header('Location: ' . url('/'), true, 303);

exit;
