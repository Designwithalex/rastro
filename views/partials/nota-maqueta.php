<?php
/**
 * partials/nota-maqueta.php — la nota que acompaña a una maqueta.
 *
 * Regla 8 del lenguaje visual: no se entrega un formulario, ni una
 * pantalla que depende del backend, sin decir en pantalla qué falta
 * conectar.
 *
 * SÓLO SE VE EN DESARROLLO (`entorno => 'local'` en app/config.php).
 *
 * La nota está escrita para el equipo, no para quien compra: nombra
 * funciones del repository, archivos de data/, números de PENDIENTES y
 * componentes de Figma. Eso servía cuando el sitio era una maqueta que
 * sólo miraba el equipo. Desde que está publicado, un visitante que entra
 * a crear una cuenta lee "Maqueta. El backend conecta la sesión, el token
 * CSRF..." y lo único que entiende es que el sitio no está terminado.
 *
 * Se apagó el 11/09/2026, cuando el cliente lo vio en producción.
 *
 * OJO: apagar la nota NO arregla la pantalla que la necesitaba. Las
 * páginas que la usan siguen sin hacer lo que prometen —el registro no
 * guarda, el arrepentimiento no envía— y ahora fallan en silencio, que
 * es peor. Lo que cada una necesita está en docs/HANDOFF.md §3.
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

/* Sin config.php —o con cualquier entorno que no sea 'local'— la nota no
   se dibuja. El defecto es el silencio: si mañana alguien despliega sin
   configuración, lo que NO puede pasar es que se filtren las notas. */
if (config('entorno') !== 'local') {
    $nota_maqueta = '';
}
?>
<?php if ($nota_maqueta !== ''): ?>
    <p class="nota nota--maqueta t-mono-texto-sm" role="note">
        <span class="nota__marca" aria-hidden="true">!</span>
        <span><strong>Maqueta.</strong> <?= e($nota_maqueta) ?></span>
    </p>
<?php endif; ?>
<?php $nota_maqueta = null; ?>
