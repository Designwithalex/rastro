<?php
/**
 * admin/_pie.php — cierre de cualquier pantalla del panel.
 *
 * El panel no carga carrito.js ni nav.js: no tiene carrito ni mega-menú.
 * Lo único que baja es admin.js, y sólo para las tres cosas que no se
 * pueden resolver sin JavaScript: confirmar un borrado, agregar filas a
 * una lista de campos y previsualizar una foto antes de subirla.
 *
 * Todo lo demás del panel funciona sin JavaScript, a propósito: es la
 * herramienta de trabajo del cliente y no puede depender de que un script
 * cargue bien.
 */

declare(strict_types=1);
?>
        </main>
    </div>
</div>

<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
