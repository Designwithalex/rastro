-- ============================================================
-- 002 — Cuándo se movió un arrepentimiento
--
-- repo_save_arrepentimiento_estado() deja una marca de tiempo cada
-- vez que cambia el estado, y la tabla no tenía dónde ponerla.
--
-- No es un detalle administrativo: la Resolución 424/2020 da 10 días
-- corridos para resolver un arrepentimiento, y esta columna es la
-- única constancia de cuándo se atendió.
--
-- Se corre una vez:
--   php bin/aplicar-migracion.php db/migraciones/002-arrepentimientos-actualizado.sql
-- ============================================================

ALTER TABLE arrepentimientos
    ADD COLUMN actualizado DATETIME NULL AFTER creado;
