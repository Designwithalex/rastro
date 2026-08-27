-- ============================================================
-- esquema.sql — Rastro Fitness
--
-- Es la traducción del contrato de datos a tablas. Cada decisión
-- de acá tiene su explicación en docs/DATA-CONTRACT.md; los
-- comentarios de este archivo son los que hacen falta para
-- entender el SQL, no para repetir el contrato.
--
-- Se corre una vez:
--   mariadb -u rastro -p rastro < db/esquema.sql
--
-- Y después se cargan los datos:
--   php bin/migrar.php
--
-- utf8mb4 en todo: los nombres de producto llevan tildes y el copy
-- del cliente puede traer emoji. utf8 a secas en MySQL son 3 bytes
-- y no alcanza.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Catálogo
-- ============================================================

DROP TABLE IF EXISTS categorias;
CREATE TABLE categorias (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(80)  NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    descripcion     TEXT             NULL,
    pictograma      VARCHAR(255)     NULL,
    orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_slug (slug),
    KEY ix_categorias_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS marcas;
CREATE TABLE marcas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(80)  NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    logo            VARCHAR(255)     NULL,
    orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- La línea propia existe en la tabla porque un producto tiene que
    -- poder resolver el nombre de su marca, pero queda afuera de la
    -- franja "vendedores oficiales": uno no es vendedor oficial de sí
    -- mismo. Ver repo_brands($incluir_propias).
    es_propia       TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marcas_slug (slug),
    KEY ix_marcas_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS productos;
CREATE TABLE productos (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug                VARCHAR(160) NOT NULL,
    sku                 VARCHAR(40)  NOT NULL,
    nombre              VARCHAR(200) NOT NULL,
    categoria_id        INT UNSIGNED     NULL,
    marca_id            INT UNSIGNED     NULL,
    descripcion_corta   VARCHAR(400)     NULL,
    descripcion         TEXT             NULL,

    -- Enteros en pesos. No hay centavos en este catálogo y un DECIMAL
    -- invitaría a que aparezcan.
    precio_lista        INT UNSIGNED NOT NULL DEFAULT 0,
    precio_mayorista    INT UNSIGNED     NULL,

    -- Override por producto. NULL = usar el global de settings.
    -- No se pone 0 por defecto: 0 es "sin descuento" y NULL es "usá el
    -- global", y son dos cosas distintas.
    descuento_pct       DECIMAL(5,2)     NULL,

    stock               INT NOT NULL DEFAULT 0,
    destacado           TINYINT(1) NOT NULL DEFAULT 0,
    nuevo               TINYINT(1) NOT NULL DEFAULT 0,
    imagen              VARCHAR(255)     NULL,
    peso_kg             DECIMAL(7,2)     NULL,
    activo              TINYINT(1) NOT NULL DEFAULT 1,

    -- Medidas de la foto principal. Existen para poder escribir width y
    -- height en el <img> sin abrir el archivo en cada request, que es lo
    -- que hace hoy imagen_medidas(). Las llena el alta del panel.
    ancho_px            INT UNSIGNED     NULL,
    alto_px             INT UNSIGNED     NULL,

    creado              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_slug (slug),
    UNIQUE KEY uq_productos_sku (sku),

    -- El catálogo filtra por categoría, por marca y por activo, y ordena
    -- por destacado y por stock. Estos son los índices que usa de verdad.
    KEY ix_productos_categoria (categoria_id, activo),
    KEY ix_productos_marca (marca_id, activo),
    KEY ix_productos_activo (activo, destacado, stock),


    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE SET NULL,
    CONSTRAINT fk_productos_marca FOREIGN KEY (marca_id)
        REFERENCES marcas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS producto_imagenes;
CREATE TABLE producto_imagenes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id  INT UNSIGNED NOT NULL,
    ruta         VARCHAR(255) NOT NULL,
    -- El orden es información: la galería de la ficha usa la primera
    -- como toma principal.
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_producto_imagenes (producto_id, orden),
    CONSTRAINT fk_imagenes_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS producto_especificaciones;
CREATE TABLE producto_especificaciones (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id  INT UNSIGNED NOT NULL,
    etiqueta     VARCHAR(80)  NOT NULL,
    valor        VARCHAR(255) NOT NULL,
    orden        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_producto_especs (producto_id, orden),
    CONSTRAINT fk_especs_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Prueba social y contenido editable
-- ============================================================

DROP TABLE IF EXISTS clientes;
CREATE TABLE clientes (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(160) NOT NULL,
    logo    VARCHAR(255)     NULL,
    orden   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_clientes_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS banners;
CREATE TABLE banners (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo    VARCHAR(255)     NULL,
    imagen    VARCHAR(255)     NULL,
    enlace    VARCHAR(255)     NULL,
    posicion  VARCHAR(40)  NOT NULL DEFAULT 'hero',
    activo    TINYINT(1)   NOT NULL DEFAULT 1,
    orden     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_banners_posicion (posicion, activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración del sitio: una fila por clave.
-- Clave-valor y no una tabla de una sola fila con veinte columnas
-- porque el panel agrega ajustes con el tiempo, y sumar un ajuste no
-- puede ser un ALTER TABLE.
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    clave  VARCHAR(80)  NOT NULL,
    valor  TEXT             NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El contenido de /nosotros lo edita UNA sola pantalla del panel y el
-- cliente lo piensa como una sola cosa, así que se guarda como un
-- documento y no repartido en seis tablas. Ver repo_nosotros().
DROP TABLE IF EXISTS nosotros;
CREATE TABLE nosotros (
    id           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    contenido    LONGTEXT NOT NULL,
    actualizado  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Una sola fila, garantizado por la base y no por convención.
    CONSTRAINT ck_nosotros_fila_unica CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Cuenta
-- ============================================================

DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(120) NOT NULL,
    apellido       VARCHAR(120) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    telefono       VARCHAR(60)      NULL,

    -- cliente | mayorista | admin. El guard de /admin exige 'admin'.
    rol            VARCHAR(20)  NOT NULL DEFAULT 'cliente',

    empresa        VARCHAR(190)     NULL,
    cuit           VARCHAR(20)      NULL,
    creado         DATE         NOT NULL,
    activo         TINYINT(1)   NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    -- 190 y no 255 en el índice: con utf8mb4 son 4 bytes por carácter y
    -- 255 se pasa del límite de 767 bytes de los índices viejos de
    -- InnoDB. Con 190 anda en cualquier versión.
    UNIQUE KEY uq_usuarios_email (email),
    KEY ix_usuarios_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS direcciones;
CREATE TABLE direcciones (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED NOT NULL,
    calle         VARCHAR(255) NOT NULL,
    ciudad        VARCHAR(120)     NULL,
    provincia     VARCHAR(120)     NULL,
    codigo_postal VARCHAR(20)      NULL,
    principal     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY ix_direcciones_usuario (usuario_id),
    CONSTRAINT fk_direcciones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pedidos;
CREATE TABLE pedidos (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo                 VARCHAR(40)  NOT NULL,
    usuario_id             INT UNSIGNED     NULL,
    fecha                  DATE         NOT NULL,
    estado                 VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    medio_pago             VARCHAR(30)      NULL,
    subtotal               INT NOT NULL DEFAULT 0,
    envio                  INT NOT NULL DEFAULT 0,

    -- El pedido guarda el porcentaje con el que se vendió. No se
    -- recalcula nunca contra settings: el descuento de hoy no es el
    -- de abril.
    descuento_aplicado_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    total                  INT NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_codigo (codigo),
    KEY ix_pedidos_usuario (usuario_id, fecha),
    KEY ix_pedidos_estado (estado, fecha),
    -- ON DELETE SET NULL y no CASCADE: borrar un usuario no puede
    -- borrar la facturación.
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS pedido_items;
CREATE TABLE pedido_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id        INT UNSIGNED NOT NULL,

    -- Referencia floja al producto: si mañana se borra del catálogo, la
    -- línea del pedido sobrevive con el nombre y el precio con los que
    -- se vendió. Por eso nombre, sku y precio_unitario están acá y no
    -- se leen de productos.
    producto_id      INT UNSIGNED     NULL,
    nombre           VARCHAR(200) NOT NULL,
    sku              VARCHAR(40)      NULL,
    cantidad         INT UNSIGNED NOT NULL DEFAULT 1,
    precio_unitario  INT NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    KEY ix_pedido_items (pedido_id),
    CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Panel: intentos de login
--
-- El límite de intentos necesita memoria entre requests y la sesión no
-- sirve, porque quien prueba contraseñas no manda la cookie. Se guarda
-- por correo y por IP.
-- ============================================================

DROP TABLE IF EXISTS intentos_login;
CREATE TABLE intentos_login (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email   VARCHAR(190) NOT NULL,
    ip      VARBINARY(16) NOT NULL,
    momento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_intentos_email (email, momento),
    KEY ix_intentos_ip (ip, momento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
