<?php
/**
 * layout/marquee.php — la franja bordeaux de arriba de todo.
 *
 * El texto sale del panel: Banners → "Barra de texto". Cada banner activo
 * de esa posición es una frase, en el orden que diga el panel, y se repiten
 * separadas por barras. Sin ninguno activo, dice la consigna de la marca.
 *
 * Los marcadores ({descuento}, {envio_gratis}) se resuelven con
 * interpolar(), igual que en el resto del sitio: el porcentaje sale de
 * Configuración y nunca se escribe a mano.
 *
 * Va con aria-hidden porque para un lector de pantalla es la misma frase
 * doce veces seguidas. Por eso tampoco lleva enlaces: un enlace adentro de
 * algo oculto sigue recibiendo el foco del teclado y no se anuncia.
 * La animación se apaga sola con prefers-reduced-motion (ver layout.css).
 *
 * Las variables van con prefijo `layout_` porque esto se requiere dentro
 * del scope de la vista y no le puede pisar un nombre.
 */

declare(strict_types=1);

$layout_marquee_settings = repo_settings();
$layout_marquee_frases   = [];

foreach (repo_banners() as $layout_marquee_banner) {
    if (($layout_marquee_banner['posicion'] ?? '') !== 'franja') {
        continue;
    }

    $layout_marquee_frase = trim(interpolar((string) ($layout_marquee_banner['titulo'] ?? ''), $layout_marquee_settings));

    if ($layout_marquee_frase !== '') {
        $layout_marquee_frases[] = $layout_marquee_frase;
    }
}

if ($layout_marquee_frases === []) {
    $layout_marquee_frases = ['Equipamiento profesional'];
}

/**
 * La animación desplaza cada pista un 100% de su propio ancho, así que para
 * que el bucle no deje un hueco cada pista tiene que ser MÁS ANCHA QUE LA
 * VENTANA: al menos 3840 px, para cubrir un monitor 4K.
 *
 * Como ahora el texto lo escribe el cliente, las repeticiones se calculan.
 * Con la escala actual (t-display-xs, en mayúsculas) cada carácter mide unos
 * 10 px —medido: una frase de 53 caracteres más su barra ocupa 577 px—, y
 * cada barra con sus márgenes unos 40. Si se agranda la letra del marquee,
 * se re-mide y se cambia el 10. Se estima el ancho de una
 * vuelta —todas las frases una vez— y se repite hasta pasar los 3920 px.
 *
 * El ancho de la pista queda siempre cerca de esos 3920 px, así que la
 * velocidad de layout.css (74 s por pista, ~53 px/s) se mantiene sin tocar
 * la duración, que no se puede poner en línea por el CSP.
 */
$layout_marquee_vuelta = 0;

foreach ($layout_marquee_frases as $layout_marquee_frase) {
    $layout_marquee_vuelta += mb_strlen($layout_marquee_frase) * 10 + 40;
}

$layout_marquee_repeticiones = max(1, (int) ceil(3920 / max(1, $layout_marquee_vuelta)));
?>
<div class="marquee" aria-hidden="true">
    <?php for ($layout_marquee_pista = 0; $layout_marquee_pista < 2; $layout_marquee_pista++): ?>
        <div class="marquee__pista">
            <?php for ($layout_marquee_i = 0; $layout_marquee_i < $layout_marquee_repeticiones; $layout_marquee_i++): ?>
                <?php foreach ($layout_marquee_frases as $layout_marquee_frase): ?>
                    <span class="marquee__item t-display-xs"><?= e($layout_marquee_frase) ?></span>
                    <span class="marquee__barra t-mono-texto-sm">/</span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    <?php endfor; ?>
</div>
