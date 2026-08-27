<?php
/**
 * partials/nota-maqueta.php — la nota que acompaña a una maqueta.
 *
 * Regla 8 del lenguaje visual: no se entrega un formulario, ni una
 * pantalla que depende del backend, sin decir en pantalla qué falta
 * conectar. La nota se ve en el sitio a propósito; se borra cuando la
 * pieza deja de ser maqueta.
 *
 * CÓMO SE USA
 *
 *   $nota_maqueta = 'El backend conecta el POST, la validación y la sesión.';
 *   require RASTRO_VIEWS . '/partials/nota-maqueta.php';
 *
 * Va con role="note" y no con role="alert": un alert interrumpe al lector
 * de pantalla, y esto es contexto permanente de la página, no algo que
 * acaba de pasar.
 */

declare(strict_types=1);

$nota_maqueta = trim((string) ($nota_maqueta ?? ''));
?>
<?php if ($nota_maqueta !== ''): ?>
    <p class="nota nota--maqueta t-mono-texto-sm" role="note">
        <span class="nota__marca" aria-hidden="true">!</span>
        <span><strong>Maqueta.</strong> <?= e($nota_maqueta) ?></span>
    </p>
<?php endif; ?>
<?php $nota_maqueta = null; ?>
