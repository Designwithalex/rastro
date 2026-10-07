# Handoff — Rastro Fitness

Para el desarrollador que toma el backend.

**Lo que recibís:** el sitio completo maquetado y funcionando con datos mock,
un contrato de datos cerrado —lectura y escritura— y **el panel de
administración andando**, con sus nueve secciones.

**Lo que tenés que hacer:** reemplazar los mocks por MySQL sin tocar ni una
vista, integrar Mercado Pago y conectar la autenticación de los clientes.

> **CAMBIO DE ALCANCE — 27/08/2026.** El panel de administración salió de esta
> lista: lo hicimos nosotros. Ya no hay que programarlo; hay que cambiarle la
> fuente de datos, igual que al resto del sitio. Lo que sigue explicado abajo
> es qué toca y qué no.

---

## 1. Levantarlo en cinco minutos

```bash
git clone https://github.com/Designwithalex/rastro.git
cd rastro
cp app/config.example.php app/config.php     # completá los valores
php -S localhost:8000 bin/server.php
```

PHP 8.1 o superior. **No hay dependencias, no hay build step, no hay npm.**

> Usá `bin/server.php` y **no** `php -S localhost:8000` a secas. El servidor
> embebido ignora los `.htaccess`, así que sin ese router te sirve
> `data/users.json` con los hashes y `app/config.php` con las credenciales.

Comprobá que quedó bien:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/                 # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/no-existe        # 404
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/data/users.json  # 403
```

El último es el que importa: si devuelve 200, estás sirviendo el árbol de
archivos y no el front controller.

El detalle completo (autenticaciones, tipografías, assets originales) está en
`docs/SETUP-MAQUINA.md`. El despliegue, en `docs/DEPLOY.md`.

---

## 2. Cómo está armado

```
index.php          front controller: resuelve la ruta y cede a una vista
.htaccess          rewrite de todo a index.php + cabeceras + CSP
app/               config, router, repository, helpers   [privada]
  repository.php             lectura  — todo el sitio
  repository-escritura.php   escritura — sólo el panel
  panel.php                  sesión, CSRF, subidas — sólo /admin
views/             layout, partials y las 12 páginas     [privada]
  admin/                     las pantallas del panel (+ Administradores, 07/10/2026)
data/              datos JSON — los escribe el panel     [privada]
assets/            css, js, img, fonts                   [pública]
  img/subidas/               lo que sube el cliente, + .htaccess propio
```

`panel.php` y `repository-escritura.php` se cargan **sólo** cuando la ruta
empieza con `/admin`: el sitio público no escribe nada y no abre sesión.

Todo se despliega dentro de `public_html`. `app/`, `views/` y `data/` tienen su
propio `.htaccess` con `Require all denied`, y además el `.htaccess` de la raíz
niega cualquier `.json` y cualquier `.md` pase lo que pase con el rewrite.

**Los puntos donde vas a trabajar son dos: `app/repository.php` y
`app/repository-escritura.php`.**

Todas las vistas leen datos por el primero y el panel escribe por el segundo.
Cambiás el cuerpo de esas funciones —donde dice `_repo_json('products')` va a
decir `SELECT`, y donde dice `_repo_escribir_json()` va a decir `INSERT`— y ni
una vista ni una pantalla del panel se entera. **El contrato completo está en [`DATA-CONTRACT.md`](DATA-CONTRACT.md)
y es lo primero que conviene leer.**

`app/helpers.php` es presentación: escape, moneda, rutas y el cálculo del
precio con descuento. No lo toques para conectar la base.

---

## 3. Las diez cosas que faltan, en orden

(Eran once. La #3.10, el panel, salió de la lista: ya está hecho.)

Están ordenadas por lo que desbloquea a lo demás. Cada una tiene un
`TODO(backend)` en el código, en el lugar exacto.

> **Actualización del 27/08/2026 — el checkout ya no es tuyo.**
> El pago con Mercado Pago está integrado y andando en ambiente de prueba:
> Checkout Pro, creación de la preferencia, página de retorno y webhook con
> validación de firma. La tabla de alcance de `CLAUDE.md` §2 lo daba de tu
> lado y ya no hace falta.
>
> Lo que sí sigue siendo tuyo, y está detallado en
> [`docs/MERCADOPAGO.md`](MERCADOPAGO.md) §8: **sesión** (que es 3.2 y traba
> el token CSRF del checkout), **reserva y descuento de stock**, **pasar los
> pedidos a MySQL**, **los mails de confirmación**, **el costo de envío** y
> **una tarea de conciliación** para los pagos cuyo webhook nunca llegue.
>
> Antes de tocar nada del checkout, leé las tres reglas de la §3 de ese
> documento. Las dos primeras —el precio lo pone el servidor, y el estado del
> pago se pregunta por API en vez de leerse de la URL— son las que sostienen
> que esto no se pueda romper desde la consola del navegador.

### 3.1 Conexión PDO — `app/repository.php:_repo_json()`

Es el andamio del mock: lee un JSON y lo cachea en memoria por request.
Reemplazalo por la conexión que sale de `app/config.php`.

### 3.2 Sesión

**No existe `session_start()` en el sitio público.** Es lo que bloquea login,
registro, Mi cuenta y el checkout.

> El panel **sí** tiene sesión, en `app/panel.php`, y es un buen molde para
> esta: cookie `HttpOnly` + `SameSite=Strict` + `Secure` según el esquema,
> `session_regenerate_id(true)` al identificarse, caducidad por inactividad y
> un token CSRF por sesión. Lo que no comparte es de dónde salen los usuarios,
> y por eso son dos puertas distintas.

- `views/auth/ingresar.php` — el POST ya llega a `repo_login()` y verifica el
  hash. Falta abrir la sesión, regenerar el id, sumar token CSRF y límite de
  intentos.
- `views/auth/registro.php` — `repo_register()` ya valida. Falta persistir con
  `password_hash()` y mandar el mail de bienvenida.
- `views/cuenta/index.php` — hoy muestra **siempre** el usuario id 1 y lo dice
  en pantalla. Con sesión, la ruta redirige a `/ingresar` si no hay nadie.
- El enlace "Cuenta" del header (`views/layout/header.php:164`) va a
  `/ingresar` y tiene que ir a `/cuenta` cuando haya sesión.
- "Cerrar sesión" está como **botón deshabilitado y no como enlace**, a
  propósito: un GET lo dispara cualquier imagen remota. Cuando exista, es un
  POST con CSRF.

### 3.3 ⚠️ `repo_order()` — antes de exponer el detalle de pedido

Esta función **no valida quién pide el pedido** y el código es adivinable
(`RF-año-ddmm`). Devuelve nombre, dirección y total de una compra.

**Por eso la pantalla de detalle de pedido no está construida.** No la
construyas hasta exigir sesión y comparar `usuario_id` contra el usuario
logueado, o recibir el id del dueño como segundo argumento y filtrar adentro
de la función.

### 3.4 Mercado Pago

- `views/carrito.php` — el botón "Pagar con Mercado Pago" no dispara nada y lo
  dice en pantalla. Falta decidir **Checkout Pro (redirect) o Bricks
  (embebido)** — es tu decisión, está anotada como `PENDIENTES.md` #13 — y
  crear la preferencia con las líneas del carrito.
- El `.htaccess` de la raíz tiene una **Content-Security-Policy estricta**:
  `default-src 'self'`, sin scripts ni estilos en línea. Cuando integres MP hay
  que sumar sus orígenes ahí. Está marcado con un `TODO(backend)` en el
  archivo.

### 3.5 El endpoint del carrito

`views/carrito.php` imprime hoy un índice con **los 30 productos activos**
(unos 6 KB) en un atributo `data-`, para que el navegador pueda dibujar las
filas. Con un catálogo real no escala.

La solución ya está en el contrato: un endpoint que reciba los ids del carrito
y llame a **`repo_cart_items()`**, que existe justamente para eso.

> **Cuando lo hagas, no muevas el cálculo del descuento al JavaScript.** El
> endpoint tiene que devolver los dos precios ya resueltos por
> `precio_con_descuento()`. Hay una sola fuente de verdad y está en PHP.

### 3.6 El formulario mayorista

`views/mayoristas.php` — no envía. Falta definir a qué correo llega y qué
campos son obligatorios de verdad (#37), validación del lado del servidor y
protección contra envíos automáticos.

### 3.7 El botón de arrepentimiento

`views/legales/arrepentimiento.php` — **obligatorio por la Resolución 424/2020**
y por eso la página existe desde el primer día. Cuando conectes el envío, tiene
que **dejar constancia**: número de trámite y copia por mail a quien lo pide.
Eso es lo que la resolución exige poder demostrar.

### 3.8 Consultas del mega-menú

`views/layout/header.php:51` — el mega-menú resuelve **seis consultas por
página** (los ocho productos de cada una de las seis categorías) y se dibuja en
todas las páginas del sitio. Con los mocks no se nota; con MySQL y tráfico
real, sí. Se resuelve con una sola consulta que traiga los N primeros de cada
categoría, o cacheando el bloque. **No cambia el marcado.**

### 3.9 Conteos y medidas

- `repo_categories()` recalcula `productos_count` con un bucle por categoría.
  Con MySQL es un `COUNT` agrupado.
- `imagen_medidas()` abre el archivo en cada request para escribir `width` y
  `height` en el `<img>` y que la página no salte al cargar. Con la base
  conviene guardar `ancho_px` y `alto_px` al subir la foto.
- El alta de una foto tiene que generar **también la miniatura de 96 px** en
  `assets/img/productos/miniaturas/`, además del WebP grande. Hoy la genera
  `bin/optimizar-imagenes.sh`. Si falta, el sitio no se rompe: cae al original.

### 3.10 El panel de administración — **ya está hecho**

Nueve secciones, sólo escritorio, en `/admin`: Tablero · Productos · Pedidos ·
Categorías · Marcas · Clientes · Banners · Nosotros · Configuración.

**Lo que tenés que hacer acá es cambiarle la fuente de datos, no programarlo.**
Toda la escritura pasa por `app/repository-escritura.php`, que es la misma
frontera que `repository.php` pero para el otro sentido. Cambiás el cuerpo de
esas catorce funciones —JSON por `INSERT`/`UPDATE`/`DELETE`— y ninguna de las
pantallas se toca. El contrato completo, con lo que devuelve cada una y las
cinco reglas que hay que sostener, está en `docs/DATA-CONTRACT.md` §9.

Tres cosas que conviene saber antes de tocarlo:

- **`app/panel.php` es la puerta**: sesión, CSRF, subida de imágenes y lectura
  de formularios. Tiene su propio login, aparte del de clientes (§3.2), porque
  protege otra cosa: el panel escribe archivos en el servidor y no podía
  esperar a que la auth de clientes existiera. El administrador sale de
  `app/config.php`; sin eso configurado el panel no deja entrar a nadie.
- **`data/` está excluido del deploy.** Desde que el panel escribe, los JSON
  del repo son la semilla y la verdad vive en el servidor. Un deploy que los
  sincronizara borraría la carga del cliente (`docs/DEPLOY.md`). Cuando entre
  MySQL esto deja de aplicar y se puede volver a incluir `data/`.
- **`assets/img/subidas/`** es la única carpeta donde escribe el servidor.
  Tiene un `.htaccess` propio que apaga la ejecución de PHP ahí adentro, y
  `panel_subir_imagen()` sólo guarda lo que pasa `getimagesize()`, con la
  extensión que él mismo le pone. No aflojes ninguna de las dos.

**Lo que el panel todavía no hace y te toca:** optimizar las imágenes al
subirlas (`PENDIENTES.md` #69) — hoy entran tal cual, hasta 6 MB, y no se
genera el WebP ni la miniatura de 96 px de §3.9.

**La referencia del panel es el panel andando, no el archivo de Figma.** Los
ocho frames viejos describen un panel que no se construyó así y no se van a
actualizar: es una decisión tomada, no un pendiente (`PENDIENTES.md` #70).
Quedan en Figma como archivo histórico.

### 3.11 Borrar `data/users.json`

Tiene dos usuarios de prueba con contraseña conocida (`rastro2026`), guardados
como hash bcrypt. **Borralo el día que conectes la base real.**

---

## 4. Lo que NO tenés que tocar

| | Por qué |
|---|---|
| **Las vistas de `views/`** | Están escritas contra el contrato. Si necesitás cambiar una, avisá: probablemente falte una función en el repository. |
| **`assets/css/tokens.css`** | Se genera desde las variables de Figma. Si cambia un token, cambia allá y se copia acá. |
| **El cálculo del descuento** | Vive en `precio_con_descuento()` y en ningún otro lado. |
| **`assets/js/carrito.js`** | Guarda id y cantidad, nada más. No agregues cálculos de precio ahí. |
| **Las pantallas de `views/admin/`** | Están escritas contra `repository-escritura.php`. Si necesitás cambiar una, probablemente falte una función en el repository. |
| **`estados_pedido()` en `helpers.php`** | Es la lista de estados que comparten `/cuenta` y el panel. Duplicarla hace que el panel ofrezca estados que la página del cliente no sabe dibujar. |
| **El `.htaccess` de `assets/img/subidas/`** | Apaga la ejecución de PHP en la única carpeta donde escribe el servidor. |

---

## 5. Reglas del proyecto que conviene respetar

1. **Credenciales nunca en el repo ni en Notion.** `app/config.php` está en
   `.gitignore` y excluido del deploy. Se pasan por canal seguro.
2. **Toda lectura de datos pasa por `repository.php`.** Sin excepciones.
3. **Escapar siempre la salida** en las vistas, con `e()`. Nada de eco crudo.
4. **Commits atómicos, conventional commits** (`feat:`, `fix:`, `docs:`).
5. **Ambigüedades** → placeholder en `data/settings.json` + anotar en
   `PENDIENTES.md`. No inventar valores finales.
6. **Un dato que falta se ve.** El sitio dibuja `[ DATO ]`, `[ confirmar ]` y
   notas de maqueta a propósito: un hueco escondido no lo reclama nadie. Se
   borran cuando el dato llega, no antes.

---

## 6. Datos que faltan del cliente

No los podés resolver vos, pero conviene que sepas que están abiertos porque
aparecen como marcadores visibles en el sitio. La lista completa y actualizada
está en [`PENDIENTES.md`](../PENDIENTES.md).

Los que más pesan:

- **Texto legal** de términos, cambios, garantía y privacidad (#10).
- **Fotos** de cuatro productos: dos kettlebells, un half rack y una jaula de
  potencia — los dos de mayor ticket del catálogo (#40).
- **Logos** en SVG de clientes y marcas oficiales (#25, #6).
- **Costos y política de envío** por zona (#8).
- **Dirección del depósito** y política de garantía real (#32, #33).
- **Contenido de `/nosotros`**: obras, foto de los fundadores y las tres
  cifras (#50, #51).

---

## 7. Dónde está todo

| | |
|---|---|
| Contrato de datos | [`docs/DATA-CONTRACT.md`](DATA-CONTRACT.md) |
| **Mercado Pago y checkout** | [`docs/MERCADOPAGO.md`](MERCADOPAGO.md) |
| **Poner a andar pagos y panel en el servidor** | [`docs/PUESTA-EN-MARCHA.md`](PUESTA-EN-MARCHA.md) |
| Despliegue y credenciales | [`docs/DEPLOY.md`](DEPLOY.md) |
| Levantar el proyecto | [`docs/SETUP-MAQUINA.md`](SETUP-MAQUINA.md) |
| Contexto del proyecto y marca | [`CLAUDE.md`](../CLAUDE.md) |
| Todo lo que está abierto | [`PENDIENTES.md`](../PENDIENTES.md) |
| Diseño | [`design/README.md`](../design/README.md) |
| Figma | https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs |
| Sitio provisorio | https://rastrofitness.com/ |

Cualquier duda de contrato, escribinos antes de cambiar una vista. Ese archivo
es el corazón del handoff y es lo que permite que las dos partes avancen sin
pisarse.
