<?php
/**
 * partials/iconos.php — los íconos de interfaz, en SVG en línea.
 *
 * PENDIENTES #24: el brandbook trae pictogramas de categoría (kettlebell,
 * disco, rack), no íconos de interfaz. Este es el set provisorio que se
 * decidió ahí: línea de 1,5 px sobre una caja de 24, sin relleno, heredando
 * el color del texto con currentColor.
 *
 * Van en línea y no como archivo por dos motivos:
 *
 *   · El Content-Security-Policy de la raíz no permite <style> ni <script>
 *     embebidos, pero sí SVG en el marcado. Un sprite externo obligaría a
 *     una petición más para cinco figuras de 200 bytes.
 *   · Un ícono que hereda currentColor cambia de color con el estado del
 *     botón —hover, foco, activo— sin una sola regla extra de CSS.
 *
 * icono() NO interpola datos: devuelve una cadena fija de una lista cerrada.
 * Por eso su salida se imprime SIN e() —es marcado propio del sitio, no un
 * dato— y por eso un nombre desconocido devuelve cadena vacía en vez de
 * intentar dibujar algo. Si alguna vez necesita recibir un valor de afuera,
 * deja de valer esa excepción y hay que escaparlo.
 *
 * Todos salen con aria-hidden: el nombre accesible lo pone el botón que los
 * contiene, con un <span class="visualmente-oculto">.
 */

declare(strict_types=1);

if (!function_exists('icono')) {
    /**
     * Devuelve el SVG de un ícono del set de interfaz.
     *
     * @param string $nombre menu | cerrar | buscar | carrito | chevron
     */
    function icono(string $nombre): string
    {
        $trazo = '<svg class="icono" viewBox="0 0 24 24" width="24" height="24" fill="none"'
               . ' stroke="currentColor" stroke-width="1.5" stroke-linecap="square"'
               . ' stroke-linejoin="miter" aria-hidden="true" focusable="false">';

        $figura = match ($nombre) {
            // Tres líneas parejas. Canto vivo: las puntas van rectas.
            'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'cerrar'  => '<path d="M5 5l14 14M19 5L5 19"/>',
            'buscar'  => '<circle cx="11" cy="11" r="6.25"/><path d="M15.5 15.5L21 21"/>',
            // Bolsa, no changuito: el changuito es de supermercado.
            'carrito' => '<path d="M4 7h16l-1.2 13H5.2L4 7z"/><path d="M8.5 9.5V6a3.5 3.5 0 0 1 7 0v3.5"/>',
            'chevron' => '<path d="M5 9l7 7 7-7"/>',
            default   => '',
        };

        return $figura === '' ? '' : $trazo . $figura . '</svg>';
    }
}
