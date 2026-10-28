-- ============================================================
-- 003 — Datos de envío por producto
--
-- Pedido del cliente (22/10/2026): medidas de la caja, cantidad de
-- bultos y una marca de "el envío se cotiza aparte" para racks,
-- jaulas y bancos. Los usa app/envio.php y el agregador de envíos
-- cuando entre.
--
-- Es JSON y no cinco columnas porque se lee y se escribe siempre
-- entero, y porque el agregador va a pedir más datos (valor
-- declarado, apilable) que no tiene sentido sumar de a un ALTER.
--
-- HAY QUE CORRERLA ANTES DE PUBLICAR el código que la usa: sin la
-- columna, el catálogo no carga.
--   php bin/aplicar-migracion.php db/migraciones/003-envio-por-producto.sql
-- ============================================================

ALTER TABLE productos
    ADD COLUMN envio JSON NULL AFTER peso_kg;
