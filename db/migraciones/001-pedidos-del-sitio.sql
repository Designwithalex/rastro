-- ============================================================
-- 001 — Los pedidos del sitio entran a la tabla `pedidos`
--
-- db/esquema.sql es la foto de cómo tiene que quedar la base, y
-- sirve para montarla de cero. Este archivo es el otro camino:
-- cómo llevar una base QUE YA TIENE DATOS de la versión anterior
-- a esa foto, sin borrar nada.
--
-- Hace falta porque hasta ahora `pedidos` sólo guardaba los cuatro
-- pedidos de la maqueta, que venían de data/orders.json. Las compras
-- de verdad vivían aparte, en data/pedidos.json, con seis campos que
-- la tabla no tenía.
--
-- Se corre una vez:
--   php bin/aplicar-migracion.php db/migraciones/001-pedidos-del-sitio.sql
-- ============================================================

-- Qué es cada fila: una venta ('sitio') o un ejemplo ('mock'). Los
-- cuatro que ya están vinieron de la maqueta, así que se marcan antes
-- de que el default deje de aplicar.
ALTER TABLE pedidos
    ADD COLUMN origen VARCHAR(10) NOT NULL DEFAULT 'sitio' AFTER total;

UPDATE pedidos SET origen = 'mock';

-- Fecha con hora: `fecha` es sólo el día y no alcanza para ordenar
-- dos compras de la misma jornada. Los de la maqueta no tienen.
ALTER TABLE pedidos
    ADD COLUMN creado DATETIME NULL AFTER origen;

-- Lo que viaja a Mercado Pago y lo único que vuelve identificando al
-- pedido: es por donde entra el webhook, y por eso va indexado.
ALTER TABLE pedidos
    ADD COLUMN referencia VARCHAR(64) NULL AFTER creado;

-- Los tres bloques del checkout. comprador y entrega son una foto del
-- momento de la compra, con el mismo criterio que precio_unitario.
-- pago lo escribe el webhook por partes y su forma la manda el
-- proveedor, no nosotros.
ALTER TABLE pedidos
    ADD COLUMN comprador JSON NULL AFTER referencia,
    ADD COLUMN entrega   JSON NULL AFTER comprador,
    ADD COLUMN pago      JSON NULL AFTER entrega;

-- La marca que deja repo_order_update() en cada cambio de estado.
ALTER TABLE pedidos
    ADD COLUMN actualizado DATETIME NULL AFTER pago;

ALTER TABLE pedidos
    ADD KEY ix_pedidos_origen (origen, fecha),
    ADD KEY ix_pedidos_referencia (referencia);
