# Estado del proyecto — Rastro Fitness

Este documento era el handoff a un backend dev externo. **El 27/08/2026 cambió
el alcance: el panel de administración y la integración de pagos los hacemos
nosotros.** Ya no hay dos partes, así que esto pasó a ser lo que queda por
hacer y en qué orden.

---

## 1. Dónde está el proyecto

| | |
|---|---|
| **Sitio público** | ✅ Completo. Las doce rutas del router tienen su vista. |
| **Base de datos** | ✅ MySQL. El repository dejó de leer JSON el 27/08/2026 sin tocar una sola vista. |
| **Sesión y cuentas** | ✅ Login, registro, cuenta, cierre de sesión, CSRF y límite de intentos. |
| **Panel de administración** | ✅ Las nueve secciones, funcionando contra la base. |
| **Pagos** | ❌ Lo que sigue. |

**En producción todavía corre la versión anterior**, la que leía los JSON. El
código con base y panel está en la rama `feat/panel-admin` y **no se puede
desplegar hasta poner MySQL en Hostinger**: sin `app/config.php` con
credenciales, el sitio devuelve 500 en todas las páginas. Los cuatro pasos están
en [`DEPLOY.md`](DEPLOY.md).

---

## 2. Levantarlo en una máquina

```bash
git clone https://github.com/Designwithalex/rastro.git
cd rastro
cp app/config.example.php app/config.php     # completar con la base local
mariadb -u root -e "CREATE DATABASE rastro CHARACTER SET utf8mb4"
mariadb -u USUARIO -p rastro < db/esquema.sql
php bin/migrar.php --recrear
php bin/crear-admin.php tu@correo.com "Nombre" "Apellido"
php -S localhost:8000 bin/server.php
```

PHP 8.1 o superior con `pdo_mysql` y `mbstring`, y MySQL o MariaDB. No hay
dependencias, no hay build step, no hay npm.

> Usá `bin/server.php` y **no** `php -S localhost:8000` a secas: el servidor
> embebido ignora los `.htaccess` y sirve `app/config.php` a cualquiera.

El detalle completo está en [`SETUP-MAQUINA.md`](SETUP-MAQUINA.md).

---

## 3. Cómo está armado

```
index.php               front controller: ruta -> vista
.htaccess               rewrite + cabeceras + CSP
app/
  db.php                conexión PDO
  repository.php        LECTURA. Lo que consume el sitio público
  repository-admin.php  ESCRITURA. Lo carga sólo el panel
  sesion.php            sesión, CSRF, límite de intentos, guards
  subidas.php           subida de imágenes
  helpers.php           presentación: escape, moneda, rutas, precio
  router.php            tabla de rutas
views/                  layout, partials, páginas y panel      [privada]
db/esquema.sql          las 13 tablas
data/                   semilla histórica. Ya no la lee nadie   [privada]
assets/                 css, js, img, fonts                     [pública]
bin/                    scripts de línea de comandos. No se despliega
```

**Todo el acceso a datos pasa por los dos `repository*.php`.** Ninguna vista
arma una consulta. El contrato completo está en
[`DATA-CONTRACT.md`](DATA-CONTRACT.md) y es lo primero que conviene leer.

### Tres reglas que sostienen el resto

1. **El descuento se calcula en un solo lugar**, `precio_con_descuento()` en
   `helpers.php`. Ni el repository ni el JavaScript lo tocan. La página del
   carrito recibe los dos precios ya resueltos desde PHP.
2. **Nada se borra si tiene historia.** Un producto se da de baja. Un pedido
   guarda su propio precio unitario y su propio porcentaje de descuento, y no
   se recalculan nunca: el precio de hoy no es el precio al que se vendió.
3. **Un dato que falta se ve.** El sitio dibuja `[ DATO ]`, `[ confirmar ]` y
   notas de maqueta a propósito. Se borran cuando el dato llega, no antes.

---

## 4. Lo que sigue: pagos

Es lo único grande que queda.

**4.1 · Decidir Checkout Pro o Bricks** (`PENDIENTES.md` #13). Pro es un
redirect a Mercado Pago y se resuelve en una tarde; Bricks es embebido, queda
mejor y es más trabajo.

**4.2 · Crear la preferencia** con las líneas del carrito.
`views/carrito.php` tiene el botón puesto y sin cablear, y lo dice en pantalla.

**4.3 · Sumar los orígenes de Mercado Pago a la CSP.** El `.htaccess` de la raíz
tiene `default-src 'self'` y no permite scripts ni estilos en línea. Está
marcado con un `TODO(backend)` en el archivo. **Sin esto, Bricks no carga y no
hay ningún error visible**: el navegador bloquea el script en silencio.

**4.4 · El webhook de confirmación** tiene que crear el pedido con
`descuento_aplicado_pct` y `precio_unitario` congelados, no leerlos del catálogo
en ese momento.

**4.5 · El endpoint del carrito.** Hoy `views/carrito.php` imprime un índice con
los 30 productos activos —unos 6 KB— para que el navegador dibuje las filas. Con
un catálogo real no escala. La solución ya está en el contrato:
`repo_cart_items()` existe justamente para eso.

---

## 5. Lo que queda después de pagos

| | |
|---|---|
| **Detalle de pedido en /cuenta** | Ya se puede: `repo_order()` acepta el id del dueño. Falta la pantalla. |
| **Recuperar contraseña** | Necesita envío de correo, que el proyecto todavía no tiene. |
| **Formulario mayorista** | No envía. Falta a qué correo llega (#37). |
| **Botón de arrepentimiento** | No envía. Cuando se conecte tiene que dejar constancia: número de trámite y copia por mail. Lo exige la Resolución 424/2020. |
| **Miniaturas al subir** | El alta guarda la foto pero no genera la miniatura de 96 px del mega-menú. Hoy la hace `bin/optimizar-imagenes.sh`. Si falta, el sitio cae al original: pesa más, no se rompe. |
| **Mega-menú** | Seis consultas por página, en todas las páginas. Con tráfico real conviene una sola consulta o cachear el bloque. |

---

## 6. Datos que faltan del cliente

Aparecen como marcadores visibles en el sitio. La lista completa está en
[`PENDIENTES.md`](../PENDIENTES.md); los que más pesan:

- **Texto legal** de términos, cambios, garantía y privacidad (#10).
- **Fotos** de cuatro productos, incluidos los dos de mayor ticket (#40).
- **Logos** en SVG de clientes y marcas oficiales (#25, #6).
- **Costos y política de envío** por zona (#8).
- **Número de WhatsApp real**: hoy es un placeholder y todos los botones del
  sitio abren un chat con un número que no existe.
- **Contenido de `/nosotros`**: obras, foto de los fundadores y tres cifras
  (#50, #51). Ya se carga desde el panel.

---

## 7. Dónde está todo

| | |
|---|---|
| Contrato de datos | [`docs/DATA-CONTRACT.md`](DATA-CONTRACT.md) |
| Despliegue y base en Hostinger | [`docs/DEPLOY.md`](DEPLOY.md) |
| Levantar el proyecto | [`docs/SETUP-MAQUINA.md`](SETUP-MAQUINA.md) |
| Contexto del proyecto y marca | [`CLAUDE.md`](../CLAUDE.md) |
| Todo lo que está abierto | [`PENDIENTES.md`](../PENDIENTES.md) |
| Diseño | [`design/README.md`](../design/README.md) |
| Figma | https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs |
| Sitio provisorio | https://darkorange-buffalo-311255.hostingersite.com/ |
