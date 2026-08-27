# Contrato de datos — Rastro Fitness

Describe **qué devuelve cada función del repository** y qué puede dar por
sentado la vista que la llama.

> **La regla de oro del proyecto:** ninguna vista lee un JSON y ninguna vista
> arma una consulta. Toda lectura y toda escritura pasa por una función `repo_*`.

**El 27/08/2026 el repository pasó de los mocks a MySQL y ninguna de las once
vistas del sitio se tocó.** Esa era la apuesta del contrato y funcionó: cambió
el cuerpo de las funciones, no su firma ni la forma de lo que devuelven. Los
JSON de `data/` quedan como semilla histórica; los carga una sola vez
`bin/migrar.php` y después no los edita nadie.

El repository son dos archivos:

| | |
|---|---|
| `app/repository.php` | **Lectura, lo que consume el sitio público.** Lo carga `index.php` en todos los requests. |
| `app/repository-admin.php` | **Escritura y las lecturas que sólo ve el panel** —productos dados de baja, todos los pedidos—. Lo carga sólo `views/admin/_guard.php`, después de comprobar el rol. |

Si una vista necesita un dato que acá no está, se agrega una función nueva —o
un parámetro nuevo, como pasó con `repo_brands($incluir_propias)`— y se
documenta acá. Nunca un `file_get_contents` ni un `SELECT` en la vista.

---

## 1. Convenciones que valen para todo

| Tema | Regla |
|---|---|
| **Precios** | Enteros, en pesos. No hay centavos. El precio guardado es el **publicado**, que es el de Mercado Pago. |
| **Descuento** | **No se calcula en el repository.** Lo calcula `precio_con_descuento()` en `app/helpers.php` y en ningún otro lado. Ver §7. |
| **Imágenes** | Rutas relativas a `assets/`, sin barra inicial: `"img/productos/foo.jpg"`. La vista las resuelve con `asset()`. |
| **Booleanos** | `true` / `false` de verdad, no `1` / `0` ni `"si"`. |
| **Fechas** | `YYYY-MM-DD`. |
| **Bajas** | Un producto con `activo: false` **no existe** para el catálogo: no aparece en listados, ni en la ficha, ni en relacionados. Sí se resuelve por id en `repo_cart_items()` y sigue figurando en pedidos viejos. |
| **Contraseñas** | Ninguna función devuelve `password_hash`. Nunca. |
| **Copy editable** | El texto que carga el cliente **no lleva números escritos a mano**. Lleva marcadores `{descuento}`, `{envio_gratis}`, `{whatsapp}`, que la vista resuelve con `interpolar()`. Un banner que dice "15%" es una segunda fuente de verdad del dato más importante del sitio. |
| **Huecos** | Un `null` puede ser un **hueco declarado**, no un error. La vista lo dibuja como marcador visible en vez de esconder la fila. |

---

## 2. Catálogo

### `repo_products(array $filters = [], int $page = 1, int $perPage = 12): array`

Listado paginado. **La usa el catálogo, los destacados de la home y el conteo
de `/nosotros`.**

**Filtros aceptados** (todos opcionales):

| Clave | Tipo | Qué hace |
|---|---|---|
| `q` | string | Texto libre sobre nombre, SKU, descripción, categoría y marca. Sin acentos y sin distinguir mayúsculas. |
| `categoria` | string | Slug de categoría. **Uno solo.** |
| `marca` | string | Slug de marca. **Una sola.** |
| `precio_min` | int | Sobre `precio_lista`. |
| `precio_max` | int | Sobre `precio_lista`. |
| `orden` | string | `relevancia` \| `precio_asc` \| `precio_desc` \| `nombre` |
| `en_stock` | bool | Sólo lo que tiene `stock > 0`. |
| `destacado` | bool | Sólo `destacado: true`. |

`relevancia` es el orden comercial: primero lo destacado, después lo nuevo, y
dentro de cada grupo lo que no tiene stock va al final.

**Devuelve:**

```php
[
    'items'   => array,  // productos, ya pasados por _repo_producto_publico()
    'total'   => int,    // total ANTES de paginar
    'pagina'  => int,    // la página efectiva, recortada al rango válido
    'paginas' => int,    // mínimo 1, aunque no haya resultados
]
```

`pagina` se recorta: pedir la página 40 de un resultado de 3 devuelve la 3, no
una lista vacía.

> **Pendiente de contrato (#63).** El frame del catálogo dibuja casillas de
> verificación, que prometen selección múltiple. Hoy `categoria` y `marca` son
> uno solo, así que el frontend los implementó como enlaces. Pasar a múltiple
> significa aceptar arrays en esas dos claves.

### `repo_product(string $slug): ?array`

Un producto por su slug. `null` si no existe **o si está dado de baja**.

### `repo_related_products(string $slug, int $limit = 4): array`

Productos de la misma categoría, sin repetir el que se está viendo. Ordena
primero por misma marca, después por stock y después por destacado.

### `repo_categories(): array`

Categorías ordenadas por `orden`, cada una con `productos_count`.

> `productos_count` **se recalcula** sobre los productos activos, no se lee del
> mock: un número que miente en la grilla de la home es peor que no tenerlo.
> **TODO(backend):** resolverlo con un `COUNT` agrupado, no con un bucle.

### `repo_category(string $slug): ?array`

Una categoría por su slug. `null` si no existe. La vista del catálogo la usa
para decidir si tira 404: `/catalogo/mancuernitas` **es un 404**, no un
listado vacío.

### Forma de un producto

```json
{
  "id": 1,
  "slug": "disco-bumper-greencore-10-kg",
  "sku": "RS-DB-010",
  "nombre": "Disco bumper Greencore 10 kg",
  "categoria": "discos",
  "marca": "greencore",
  "marca_nombre": "Greencore",
  "descripcion_corta": "…",
  "descripcion": "…",
  "precio_lista": 74900,
  "precio_mayorista": 58400,
  "descuento_pct": null,
  "stock": 24,
  "destacado": true,
  "nuevo": false,
  "imagen": "img/productos/disco-bumper-greencore-10kg.jpg",
  "imagenes": ["img/productos/…", "…"],
  "especificaciones": [{ "label": "Material", "valor": "Caucho virgen" }],
  "peso_kg": 10,
  "activo": true
}
```

- **`marca_nombre` lo pone el repository**, no está en la tabla de productos.
  Sale de resolver `marca` contra la tabla completa de marcas, **la línea
  propia incluida**. Con MySQL es un `LEFT JOIN marcas ON marcas.slug =
  productos.marca`, **sin** el `WHERE` que filtra las propias.
- **`descuento_pct` es un override por producto.** `null` significa "usá el
  global de settings". Ver §7.
- **`precio_mayorista` nunca sale a pantalla.** Está para el día que un usuario
  con rol `mayorista` logueado vea precios distintos (#11).
- `especificaciones` es una lista ordenada de pares. El orden importa: es el
  orden en el que se dibuja la tabla de la ficha.

---

## 3. Marca y prueba social

### `repo_brands(bool $incluir_propias = false): array`

Marcas ordenadas por `orden`.

Por defecto **deja afuera la línea propia** (`es_propia: true`). Esa es la
función que alimenta la franja "Vendedores oficiales" de la home, y ahí Rastro
no va: uno no es vendedor oficial de sí mismo.

Con `$incluir_propias = true` devuelve todas. La usa el **filtro de marca del
catálogo**, que hace la pregunta contraria: "¿de qué marca es este producto?".
Sin la propia, ese filtro esconde 19 de los 30 productos del catálogo.

Con MySQL es el mismo `SELECT` con o sin `WHERE es_propia = 0`.

### `repo_clients(): array`

Logos de "Confían en nosotros": clientes a los que ya se les vendió. Ordenados
por `orden`. `logo` puede venir vacío; la vista escribe el nombre en su lugar.

### `repo_banners(): array`

Banners activos, ordenados por `posicion` y `orden`. La vista elige por
`posicion`: `hero` | `mayorista` | `franja`.

> La home usa el **primer** banner de `hero` como foto de fondo. A partir del
> **segundo** dibuja el collage de placas de la derecha (#61).

---

## 4. Contenido de página

### `repo_nosotros(): array`

Todo el contenido de la sección "Nosotros" de una sola vez. Alimenta dos
piezas: la página `/nosotros` completa y la franja de la home.

**Una función y no tres a propósito:** `repo_fundadores()`, `repo_hitos()` y
`repo_obras()` serían tres consultas y tres ABM para lo que el cliente piensa
como una sola cosa.

Devuelve las claves `provisorio`, `encabezado`, `cifras`, `fundadores`,
`historia`, `como_trabajamos`, `obras`, `garantia`, `donde_estamos`, `cierre`
y `franja`. Todas existen siempre, aunque estén vacías: la vista pregunta si
están vacías, no si existen.

**Reglas que la vista da por sentadas:**

- Un valor en `null` es un **hueco declarado** y se dibuja como marcador
  visible (`[ DATO ]`, `[ AÑO ]`, `[ ROL ]`).
- `obras.items` vacío **no es un error**: la página esconde la sección entera.
  Una sección de obras vacía es peor que no tenerla.
- `fundadores.foto` en `null` colapsa el bloque a un párrafo firmado. **No se
  reemplaza por un retrato de archivo.**
- La cifra `productos_en_catalogo` **no se carga a mano**: la resuelve el
  repository contra el catálogo.

> **TODO(backend):** es la **novena** sección del panel de administración. Las
> ocho diseñadas son Dashboard, Productos, Pedidos, Marcas, Clientes, Banners,
> Categorías y Configuración; falta dibujar "Nosotros" (#49).

---

## 5. Configuración

### `repo_settings(): array`

```json
{
  "descuento_transferencia_pct": 15,
  "whatsapp": "+54 9 000 000 0000",
  "whatsapp_mensaje": "…",
  "email": "ventas@rastrofitness.com.ar",
  "horario": "…",
  "envio_gratis_desde": 150000,
  "moneda": "ARS",
  "razon_social": "Rastro Fitness S.R.L.",
  "cuit": "30-00000000-0",
  "instagram": "https://…"
}
```

Es la tabla que edita el panel. **`descuento_transferencia_pct` es el dato más
importante del sitio**: aparece en cada card, en cada ficha, en el carrito, en
la banda de la home y en los términos. Nunca se escribe a mano en ningún lado.

`whatsapp` vacío es un estado válido: `whatsapp_link()` devuelve `null` y la
vista **esconde el botón**. Un enlace a `wa.me/` sin destinatario lleva a la
home de WhatsApp, que es peor que no ofrecerlo.

---

## 6. Cuenta

> **Todo este bloque es mock y lo conecta el backend.** Ninguna de estas
> funciones abre sesión: `session_start()` no existe todavía en el proyecto.

### `repo_login(string $email, string $password): ?array`

Verifica contra `password_verify()` y devuelve el usuario **sin su hash**, o
`null`. Ya gasta el mismo tiempo cuando el correo no existe, para no filtrar
por diferencia de tiempos qué direcciones están registradas.

**TODO(backend):** abrir la sesión, regenerar el id de sesión, límite de
intentos por IP y por correo, y token CSRF en el formulario.

### `repo_register(array $data): array`

```php
['ok' => bool, 'errores' => array<string,string>, 'usuario' => ?array]
```

Valida y **no persiste**. `errores` está indexado por nombre de campo, que es
lo que la vista usa para marcar el input y escribir el mensaje debajo.

Valida: nombre y apellido no vacíos, correo con forma de correo, correo no
tomado, contraseña de 8 o más.

**TODO(backend):** guardar con `password_hash()`, mandar el mail de bienvenida
y abrir la sesión.

### `repo_user(int $id): ?array`

Un usuario por id, sin hash.

### `repo_orders(int $userId): array`

Pedidos de un usuario, del más nuevo al más viejo.

Los items vienen **tal como se guardaron**: nombre, SKU y `precio_unitario`
del día de la compra. **No se enriquecen contra el catálogo actual y no se
recalculan.** El precio de hoy no es el precio al que se vendió en abril. Cada
pedido guarda además su propio `descuento_aplicado_pct`.

### `repo_order(string $code, ?int $usuarioId = null): ?array`

Un pedido por su código (`RF-2026-0418`).

El segundo argumento acota el pedido a su dueño, y la cuenta del cliente lo
pasa siempre. Sin él, el código es adivinable —`RF-año-ddmm`— y la función
devuelve nombre, dirección y total de una compra: cualquiera leería los pedidos
de cualquiera probando códigos. Era la razón por la que la ficha de pedido del
cliente no se había construido.

El panel usa `repo_admin_pedido()`, que no filtra por dueño: ahí el dueño es
Rastro.

### Estados de pedido

`en_camino` | `entregado` | `cancelado`. Son los del mock. **Confirmar cuáles
maneja el negocio de verdad (#36):** si aparecen más, se suma una variante del
chip en `cuenta.css` y la tabla no se toca.

---

## 7. Precio: una sola fuente de verdad

**El repository no calcula descuentos.** Lo hace `precio_con_descuento()` en
`app/helpers.php`:

```php
precio_con_descuento(array $producto, array $settings): array
// ['publicado' => int, 'con_descuento' => int, 'porcentaje' => float,
//  'ahorro' => int, 'tiene_descuento' => bool]
```

1. El precio publicado es `precio_lista`, y es el precio **pagando con Mercado
   Pago**.
2. El porcentaje sale de `settings.descuento_transferencia_pct`, **salvo** que
   el producto traiga su propio `descuento_pct`.
3. Un porcentaje fuera de rango (`<= 0` o `>= 100`) **se ignora**, no se
   recorta al extremo. Recortar un 150 mal tipeado a 100 es regalar el
   producto, que es el error caro; con 0 el precio queda alto y alguien se da
   cuenta.
4. `"12,5"` escrito con coma vale 12,5 y no 12: lo resuelve `numero_decimal()`.
   El campo canónico en la base es un número con punto decimal.

**Del lado del navegador tampoco se calcula.** La página del carrito recibe
los dos precios de cada producto ya resueltos por PHP, en un atributo `data-`,
y `carrito.js` sólo multiplica por la cantidad y suma. Si aparece un
porcentaje en un archivo `.js`, hay dos fuentes de verdad y una está mal.

> **Abierto (#21).** El bloque de precio está diseñado a dos líneas
> (publicado + transferencia). Si el cliente confirma cuotas sin interés, se
> agrega una tercera línea en `views/partials/bloque-precio.php` y baja sola a
> la card, la ficha y el carrito.

---

## 8. Carrito

### `repo_cart_items(array $ids): array`

Resuelve los ids que el carrito guarda en `localStorage`, **en el mismo orden
en el que llegaron**, con su `activo` y su `stock` puestos. Las cantidades no
viven acá: las pone el JavaScript.

Devuelve el producto **aunque esté dado de baja**, justamente para que el
carrito pueda avisar que algo se dio de baja o se quedó sin stock en vez de
hacerlo desaparecer sin explicación.

> **TODO(backend) — el más urgente de esta sección.** Hoy la página del
> carrito imprime un índice con **los 30 productos activos** (unos 6 KB) para
> que el navegador pueda dibujar las filas. Con un catálogo real eso no
> escala. La solución ya está en el contrato: un endpoint que reciba los ids
> del carrito y llame a esta función.

---

## 8 bis. El lado de escritura — `app/repository-admin.php`

Lo carga sólo el panel. Tres reglas que valen para todas estas funciones:

1. **Toda operación que toque más de una tabla va en una transacción.** Guardar
   un producto son tres escrituras —el producto, sus imágenes y sus
   especificaciones— y a medias no sirve.
2. **Validan y devuelven los errores por campo.** La vista dibuja; no decide
   qué es válido. La forma es siempre
   `['ok' => bool, 'errores' => array<string,string>, 'id' => ?int]`.
3. **Nada se borra si tiene historia.** Un producto se da de baja (`activo = 0`),
   no se borra: borrarlo deja los pedidos viejos sin referencia y el carrito de
   quien lo tenía cargado sin explicación.

| Función | Qué hace |
|---|---|
| `repo_admin_metricas()` | Las cuatro cifras del dashboard. "Ventas del mes" no cuenta cancelados. |
| `repo_admin_products($filtros, $page, $perPage)` | Como `repo_products()` pero **ve también lo dado de baja**. |
| `repo_admin_product($id)` | Un producto por id, activo o no, con imágenes y especificaciones. |
| `repo_producto_guardar($datos, ?$id)` | Alta y edición. El slug **no se recalcula** al editar: cambiarlo rompe los enlaces ya compartidos. |
| `repo_producto_activo($id, $activo)` | Baja y alta lógica. |
| `repo_estados_pedido()` | Los cuatro estados. Sumar uno es tocar sólo esta función (#36). |
| `repo_admin_pedidos($filtros, $page, $perPage)` | Todos los pedidos, con el nombre de quien compró. |
| `repo_admin_pedido($codigo)` | Un pedido con items y cliente. **No filtra por dueño**: acá el dueño es Rastro. |
| `repo_pedido_estado($codigo, $estado)` | Cambia el estado. Rechaza estados que no existen. |
| `repo_categoria_guardar` · `repo_categoria_borrar` | ABM de categorías. El borrado avisa cuántos productos quedan sueltos. |
| `repo_marca_guardar` · `repo_marca_borrar` | Ídem marcas. |
| `repo_cliente_guardar` · `repo_cliente_borrar` | Ídem logos de clientes. |
| `repo_banner_guardar` · `repo_banner_borrar` | Ídem banners. |
| `repo_admin_marcas()` | `repo_brands(true)`: todas, la línea propia incluida. |
| `repo_nosotros_guardar($contenido)` | Guarda el documento entero. La cifra del catálogo se descarta: se calcula sola. |
| `repo_settings_editables()` | **Qué ajustes deja editar el panel**, con su tipo y su explicación. Agregar un ajuste es agregarlo acá. |
| `repo_settings_guardar($valores)` | Sólo escribe claves de esa lista: un POST con una clave inventada no crea un ajuste. |
| `admin_avisar($texto, $tipo)` | Deja un mensaje para la pantalla siguiente, después del redirect. |

### Subidas de imagen — `app/subidas.php`

`subir_imagen($archivo, $carpeta, $base)` devuelve
`['ok' => bool, 'ruta' => ?string, 'error' => ?string, 'ancho' => ?int, 'alto' => ?int]`.

El tipo se decide con `getimagesize()`, que lee los bytes: el nombre del archivo
y `$_FILES['type']` los manda quien sube. El nombre final lo inventa el servidor
a partir del SKU. Y la defensa que de verdad importa: la función escribe un
`.htaccess` en la carpeta de imágenes que apaga la ejecución de PHP y CGI, así
que aunque alguna vez se cuele un archivo con código adentro, ahí no corre.

## 9. Estructura de tablas

Es lo que crea `db/esquema.sql`. Cada decisión que no se lee sola está comentada
en ese archivo.

```sql
productos      id, slug UNIQUE, sku, nombre, categoria_id, marca_id,
               descripcion_corta, descripcion, precio_lista INT,
               precio_mayorista INT, descuento_pct DECIMAL(5,2) NULL,
               stock INT, destacado BOOL, nuevo BOOL, imagen,
               peso_kg DECIMAL(6,2) NULL, activo BOOL,
               ancho_px INT NULL, alto_px INT NULL

producto_imagenes   id, producto_id, ruta, orden
producto_especs     id, producto_id, label, valor, orden

categorias     id, slug UNIQUE, nombre, descripcion, pictograma, orden
marcas         id, slug UNIQUE, nombre, logo, orden, es_propia BOOL
clientes       id, nombre, logo, orden
banners        id, titulo, imagen, enlace, posicion, activo, orden

usuarios       id, nombre, apellido, email UNIQUE, password_hash,
               telefono, rol, empresa NULL, cuit NULL, creado, activo
direcciones    id, usuario_id, calle, ciudad, provincia, codigo_postal

pedidos        id, codigo UNIQUE, usuario_id, fecha, estado, medio_pago,
               subtotal INT, envio INT, descuento_aplicado_pct DECIMAL(5,2),
               total INT
pedido_items   id, pedido_id, producto_id, nombre, sku, cantidad,
               precio_unitario INT

settings       clave PK, valor
nosotros       (una fila por bloque, o JSON: lo edita una sola pantalla)
```

Dos columnas que no están en los mocks y conviene sumar:

- **`productos.ancho_px` y `alto_px`.** Hoy `imagen_medidas()` abre el archivo
  en cada request para poder escribir `width` y `height` en el `<img>` y que
  la página no salte al cargar. Con la base conviene guardarlos al subir la
  foto.
- **`producto_imagenes.orden`.** La galería de la ficha usa la primera imagen
  como principal, así que el orden es información.

---

## 10. Lo que el contrato todavía no resuelve

| # | Tema | Dónde está anotado |
|---|---|---|
| 11 | ¿El precio mayorista se muestra alguna vez? | `PENDIENTES.md` |
| 13 | Checkout Pro o Bricks | `views/carrito.php` |
| 21 | Cuotas sin interés: tercera línea del bloque de precio | `bloque-precio.php` |
| 36 | Qué estados de pedido maneja el negocio | `cuenta/index.php` |
| 38 | ¿Direcciones múltiples? | `cuenta/index.php` |
| 39 | Qué pasa con una cuenta marcada como mayorista | `auth/registro.php` |
| 48 | Contra qué subtotal se mide el envío gratis | `carrito.js` |
| 63 | Filtros de selección múltiple | `catalogo.php` |
| 65 | "A pedido" como estado distinto de "sin stock" | `catalogo.php` |
