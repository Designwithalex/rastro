<?php
/**
 * auth/salir.php — cerrar sesión.
 *
 * SOLO POST Y CON TOKEN. Un GET a /salir lo dispara cualquier imagen
 * remota apuntando a esta ruta: alcanza con que alguien pegue
 * `<img src="…/salir">` en un foro para desloguear a todo el que pase.
 * No es peligroso, pero es molesto y se evita gratis.
 *
 * Por eso no hay un enlace "Cerrar sesión" en ningún lado del sitio: hay
 * un formulario con un botón, en la cabecera y en /cuenta.
 */

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Para cerrar sesión hace falta un POST.');
}

csrf_exigir();

sesion_cerrar();

header('Location: ' . url('/'), true, 303);
exit;
