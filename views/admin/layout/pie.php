<?php
/**
 * admin/layout/pie.php — cierre de cualquier pantalla del panel.
 * El par de cabeza.php: cierra lo que ese archivo abrió.
 */

declare(strict_types=1);
?>
<?php if (empty($panel_pelado)): ?>

        </main>
    </div>

    <footer class="panel__pie">
        <p>
            Datos sobre archivos JSON en <code>data/</code>.
            Cuando entre la base de datos, estas pantallas no cambian.
        </p>
    </footer>

<?php endif; ?>

<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
