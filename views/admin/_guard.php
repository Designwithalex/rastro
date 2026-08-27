<?php
/**
 * admin/_guard.php — la puerta del panel.
 *
 * Exige sesión con rol admin y carga el lado de escritura del contrato.
 * No imprime nada.
 *
 * ESTÁ SEPARADO DE _cabecera.php a propósito. Una pantalla que tiene que
 * decidir si existe lo que le pidieron —un producto por id, un pedido por
 * código— necesita poder devolver un 404 ANTES de imprimir el primer byte.
 * Si el guard viviera adentro de la cabecera, para cuando la vista se da
 * cuenta de que el producto no existe ya salió el `<!doctype>` y la
 * cabecera del panel, y `router_404()` termina dibujando dos páginas
 * pegadas con un 200 ya enviado. Pasó exactamente eso.
 *
 * Entonces:
 *
 *   require RASTRO_VIEWS . '/admin/_guard.php';   // guard, sin salida
 *   $producto = repo_admin_product($id);
 *   if ($producto === null) { router_404(); }     // todavía se puede
 *   …
 *   require RASTRO_VIEWS . '/admin/_cabecera.php';  // recién ahora imprime
 *
 * `_cabecera.php` lo incluye igual, con require_once, así que una pantalla
 * que no necesita 404 puede seguir incluyendo sólo la cabecera.
 */

declare(strict_types=1);

$admin_usuario = sesion_exigir_admin();

require_once RASTRO_RAIZ . '/app/repository-admin.php';
