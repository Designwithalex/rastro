-- ============================================================
-- 003 — Datos de envío y variantes por producto
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
--   php bin/aplicar-migracion.php db/migraciones/003-envio-y-variantes.sql
-- ============================================================

ALTER TABLE productos
    ADD COLUMN envio JSON NULL AFTER peso_kg;

-- Variantes (mismo pedido, punto 18): una mancuerna hexagonal de 2,5 a
-- 40 kg sigue siendo un producto por peso —cada uno con su SKU, su precio
-- y su stock, que es lo que pasa en el depósito— pero los que comparten
-- grupo se muestran como uno solo, con selector.
ALTER TABLE productos
    ADD COLUMN variante_grupo  VARCHAR(80) NULL AFTER envio,
    ADD COLUMN variante_nombre VARCHAR(40) NULL AFTER variante_grupo,
    ADD KEY ix_productos_variante (variante_grupo);
