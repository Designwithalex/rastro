# Contrato de datos — Rastro Fitness

Este documento es el acuerdo entre el frontend y el backend. Describe **qué
devuelve cada función de `app/repository.php`** y qué puede dar por sentado
la vista que la llama.

> **La regla de oro del proyecto:** ninguna vista lee un JSON y ninguna vista
> arma una consulta. Toda lectura de datos pasa por una función `repo_*`.
>
> Hoy cada función lee un mock de `data/*.json`. Cuando entre MySQL, cambia el
> **cuerpo** de estas funciones. La firma, los nombres de las claves y la forma
> del array que devuelven **no se tocan**, porque eso es lo que consumen las
> once vistas del sitio.

Si una vista necesita un dato que acá no está, se agrega una función nueva —o
un parámetro nuevo, como pasó con `repo_brands($incluir_propias)`— y se
documenta acá. Nunca un `file_get_contents` en la vista.

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
`posicion`: `hero_fondo` | `hero` | `mayorista` | `franja`.

| `posicion` | Qué es | Cuántos | Formato |
|---|---|---|---|
| `hero_fondo` | La foto detrás del hero de la home. Decorativa: va con `alt` vacío y no lleva enlace. | Uno. Si hay varios se usa el primero. | Apaisada, lado largo 1600 |
| `hero` | Las placas de promoción que rotan en el carrusel del hero. | Las que haya. Con una sola no se dibujan controles. | **4:5**, lado largo 1200 |
| `mayorista` | La foto de la franja mayorista. | Uno | Apaisada |
| `franja` | Texto de la barra superior. Sin imagen; admite `{descuento}`. | Uno | — |

> **`titulo` de un banner `hero` es su texto alternativo.** La placa es una
> pieza gráfica con el mensaje adentro de la imagen, así que el `alt` no
> describe la foto: repite lo que la placa dice. Es la única forma de que un
> lector de pantalla reciba lo mismo que se ve. Cargar una placa sin título
> deja esa promoción invisible para quien no ve la imagen.

> **Hasta el hero v3 no existía `hero_fondo`:** la vista tomaba el primer
> banner de `hero` como fondo y del segundo en adelante armaba el collage.
> Era una regla implícita que el panel no tenía cómo explicarle al cliente,
> y que obligaba a cargar la foto de fondo y las placas en la misma bolsa.
> Ahora son dos posiciones distintas porque son dos cosas distintas (#61).

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

### `repo_order(string $code): ?array`

Un pedido por su código (`RF-2026-0418`).

> ⚠️ **Esta función no valida quién pide el pedido, y el código es adivinable**
> (`RF-año-ddmm`). Devuelve nombre, dirección y total de una compra. **Por eso
> la pantalla de detalle de pedido no está construida.** Antes de exponerla
> hay que exigir sesión y comparar `usuario_id` contra el usuario logueado, o
> recibir el id del dueño como segundo argumento y filtrar acá.

### Estados de pedido

Los del mock son `en_camino` | `entregado` | `cancelado`. El checkout sumó los
que necesita un pago para existir:

| Estado | Cuándo |
|---|---|
| `pendiente_pago` | Pedido creado, esperando que Mercado Pago confirme. |
| `pendiente_transferencia` | Se eligió transferencia; se coordina por WhatsApp. |
| `pagado` | El pago está `approved` o `authorized`. |
| `cancelado` | El pago se rechazó o se anuló. |
| `en_disputa` | El comprador abrió un reclamo (`in_mediation`). |
| `devuelto` | Devolución o contracargo. |
| `en_camino`, `entregado` | Los pone Rastro a mano, después de cobrar. |

La traducción de estados de Mercado Pago a estos vive en **un solo lugar**,
`mp_estado_pedido()` de `app/mercadopago.php`. **Confirmar cuáles maneja el
negocio de verdad (#36):** si aparecen más, se suma una variante del chip en
`cuenta.css` y la tabla no se toca.

---

## 6 bis. Pedidos del checkout

Ampliación del contrato del **27/08/2026**, con la integración de Mercado Pago.
El contrato original tenía pedidos de sólo lectura; para cobrar hace falta
crearlos y actualizarlos. El detalle de la integración está en
`docs/MERCADOPAGO.md`.

**Por qué existen estas funciones:** la preferencia de pago necesita un
`external_reference`, y la notificación que vuelve trae ese dato y el id del
pago, nada más. Sin un pedido guardado **antes** de mandar a nadie a pagar,
cuando Mercado Pago avisa "el pago 123 se aprobó" no hay contra qué cruzarlo.

Hoy escriben `data/pedidos.json`, que está en `.gitignore` porque tiene datos de
compradores y lo escribe el sitio, no una persona. `data/orders.json` sigue
siendo el mock versionado.

### `repo_order_create(array $pedido): array`

Guarda un pedido nuevo. Devuelve `['ok'=>bool, 'pedido'=>?array, 'error'=>?string]`.

Le pone `codigo`, `fecha`, `creado` y `referencia` si no vienen. **No valida
precios ni stock**: eso ya lo hizo `app/checkout.php`. Esta función persiste.

Si devuelve `ok: false`, la vista **no manda a nadie a pagar**: un pago que
vuelve con una referencia inexistente no se puede reconciliar con nada.

### `repo_order_update(string $codigo, array $cambios): ?array`

Pisa las claves que le pasen y devuelve el pedido actualizado, o `null` si no
existe o no se pudo guardar.

Los arrays se mezclan **a un nivel**: pasar `pago` reemplaza sólo las claves que
vengan. Eso permite que el webhook escriba `pago.estado` sin borrar
`pago.preferencia`, que lo escribió el checkout minutos antes.

### `repo_order_by_reference(string $referencia): ?array`

Por el `external_reference` que se le mandó a Mercado Pago. **Es la función del
webhook**: la notificación no trae el código de Rastro, trae el id del pago, y
del pago se lee la referencia.

### `repo_order_local(string $codigo): ?array`

Un pedido creado por el sitio, por su código. Complementa a `repo_order()`, que
sólo mira el mock versionado.

> Cuando el panel de administración muestre pedidos, tiene que leer **las dos**
> fuentes o va a listar tres compras de ejemplo y ninguna real
> (`PENDIENTES.md` #73).

### `repo_order_next_code(): string`

`RF-2026-4F7A`. Cuatro caracteres al azar de un alfabeto sin `0/O` ni `1/I`,
porque estos códigos se dictan por teléfono.

Los del mock eran `RF-año-ddmm`: se repiten si dos personas compran el mismo día
y se enumeran con sólo saber la fecha. Con MySQL el que manda es el id
autoincremental, pero **el código visible conviene que siga siendo aleatorio**:
es el que se filtra por mail y por WhatsApp.

### `repo_log_pago(string $que, array $contexto = []): void`

Una línea JSON por evento en `data/mp-eventos.log`. Es la única forma de
reconstruir qué pasó con un pago cuando alguien reclama.

**Nunca se loguea el access token ni el cuerpo completo de un pago**: ahí viajan
los últimos cuatro dígitos de la tarjeta y el mail del comprador.

### Lo que agrega un pedido del checkout

Sobre la forma de `data/orders.json`, tres bloques nuevos. Son bloques aparte a
propósito: un pedido viejo sin ellos se sigue leyendo igual.

```php
'referencia' => 'RF-2026-4F7A',   // external_reference; por defecto, el código
'comprador'  => ['nombre', 'apellido', 'email', 'telefono', 'documento'],
'entrega'    => ['calle', 'localidad', 'provincia', 'codigo_postal', 'notas'],
'pago'       => [
    'proveedor'   => 'mercado_pago',  // null si es transferencia
    'entorno'     => 'test',          // o 'produccion'
    'preferencia' => '17849...-abc',  // id de la preferencia
    'payment_id'  => '1234567890',    // id del pago
    'estado'      => 'approved',      // status crudo de Mercado Pago
    'detalle'     => 'accredited',    // status_detail
    'metodo'      => 'visa',
    'tipo'        => 'credit_card',
    'cuotas'      => 3,
    'monto'       => 372980.0,
    'actualizado' => '2026-08-27T18:27:21-03:00',
],
```

Se guarda el `estado` crudo además del estado traducido del pedido porque son
dos vocabularios distintos y el de Mercado Pago es el que hay que citar cuando
se abre un reclamo.

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

## 9. Escritura — `app/repository-escritura.php`

Hasta agosto de 2026 el contrato era de sólo lectura: el sitio mostraba mocks y el
panel lo desarrollaba el backend dev. **Con el cambio de alcance del 27/08/2026 el
panel lo desarrollamos nosotros**, así que el contrato tiene ahora una segunda mitad.

Vive en un archivo aparte porque el sitio público no escribe nada: `index.php` sólo
carga `repository-escritura.php` cuando la ruta empieza con `/admin`.

### Las funciones

```php
// Catálogo
repo_save_product(array $datos): ?array      // alta o edición; devuelve lo guardado
repo_delete_product(int $id): bool
repo_recount_categories(): bool              // recalcula productos_count

// Colecciones cortas
repo_save_category(array $datos): ?array
repo_delete_category(int $id): array         // ['ok'=>bool, 'motivo'=>string]
repo_save_brand(array $datos): ?array
repo_delete_brand(int $id): array            // ['ok'=>bool, 'motivo'=>string]
repo_save_client(array $datos): ?array
repo_delete_client(int $id): bool
repo_save_banner(array $datos): ?array
repo_delete_banner(int $id): bool

// Pedidos — sólo el estado
repo_save_order_status(string $codigo, string $estado): bool

// Configuración y contenido
repo_save_settings(array $datos): ?array     // merge, no reemplazo
repo_save_nosotros(array $datos): ?array     // merge recursivo
```

### Lecturas que sólo usa el panel

`repo_banners()`, `repo_products()` y `repo_orders()` están armadas para el sitio
público: filtran lo inactivo, paginan y acotan por usuario. El panel necesita
exactamente lo contrario, y por eso tiene sus propias funciones en vez de un
parámetro que haya que acordarse de pasar:

```php
repo_all_products(): array   // sin paginar, ordenado por nombre
repo_all_banners(): array    // incluye los apagados
repo_all_orders(): array     // de todos los usuarios
```

Un banner apagado que el panel no ve es un banner que no se puede volver a prender.

### Cinco reglas que el backend tiene que sostener

1. **Devuelven lo guardado, no un booleano.** La vista necesita el id recién asignado
   para redirigir, y el registro completo para mostrar lo que quedó y no lo que se
   mandó. `null` significa "no se pudo escribir", y la pantalla lo dice: un cartel de
   éxito sobre un archivo que no cambió es peor que un error.

2. **Los borrados con dependencias devuelven el motivo.** Borrar una categoría que
   tiene productos los dejaría fuera del catálogo y de la búsqueda sin que nada lo
   avise. `repo_delete_category()` y `repo_delete_brand()` devuelven
   `['ok' => false, 'motivo' => 'No se puede borrar: 6 productos…']` para que la
   pantalla pueda explicarlo. **TODO(backend):** con MySQL esto es una FK con
   `ON DELETE RESTRICT`, pero el mensaje lo sigue armando esta función.

3. **`productos_count` es derivado.** Lo recalcula `repo_recount_categories()` en cada
   alta y cada baja. No se escribe desde un formulario. **TODO(backend):** pasa a ser
   un `COUNT(*)` con `GROUP BY`, y esta función desaparece.

4. **Settings y Nosotros se guardan por merge.** El formulario muestra diez campos y
   el archivo puede tener más —los que agregue Mercado Pago, por ejemplo—. Un
   reemplazo completo los borraría en silencio la primera vez que alguien toque
   "Guardar" en una pantalla que no los conoce.

5. **De un pedido sólo cambia el estado.** Los importes son los del día de la compra
   y no se recalculan nunca contra el catálogo actual (regla 1.4). Un pedido editable
   después de cobrado no sirve como comprobante.

### `orden` es por grupo, no por tabla

En `banners`, `orden` sólo tiene sentido dentro de su `posicion`: la segunda placa
del carrusel es la segunda del carrusel, no la segunda de la tabla. `repo_save_banner()`
renumera por posición. En categorías, marcas y clientes —que son un solo grupo— la
renumeración es sobre la colección entera.

Después de cada guardado los `orden` quedan **renumerados de 1 en adelante**. Si el
cliente pone tres elementos en la posición 2, el orden queda indefinido y depende de
cómo el motor resuelva el empate; después de esto siempre hay un primero y un segundo.

### Escritura atómica

`_repo_escribir_json()` escribe en un temporal de la **misma carpeta** y renombra.
Un `rename()` dentro del mismo sistema de archivos es atómico: o está el archivo
viejo entero o el nuevo entero, nunca medio JSON. Sin eso, un timeout de PHP a mitad
de un `fwrite` deja el catálogo roto y sin recuperación, porque no hay base de datos
atrás. **TODO(backend):** con MySQL esto es una transacción y la función desaparece.

### Validar no es tarea del repository

Estas funciones asumen datos ya limpios. Quién valida es la vista del panel, que es
la que sabe qué formulario los mandó y puede devolver el error al lado del campo.
El repository normaliza la **forma** (tipos, claves, orden), no el **contenido**.

---

## 10. Estructura de tablas sugerida

No es obligatoria: es la traducción directa de los mocks.

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

pedidos        id, codigo UNIQUE, referencia UNIQUE, usuario_id NULL, fecha,
               creado, actualizado, estado, medio_pago,
               subtotal INT, envio INT, descuento_aplicado_pct DECIMAL(5,2),
               total INT,
               comprador_nombre, comprador_apellido, comprador_email,
               comprador_telefono, comprador_documento,
               entrega_calle, entrega_localidad, entrega_provincia,
               entrega_codigo_postal, entrega_notas TEXT NULL
pedido_items   id, pedido_id, producto_id, nombre, sku, cantidad,
               precio_unitario INT

-- Un pedido puede acumular varios intentos de pago: alguien que reintenta
-- después de un rechazo deja dos. Por eso es una tabla y no columnas de
-- `pedidos`. El estado del pedido lo decide el pago aprobado, si hay uno.
pedido_pagos   id, pedido_id, proveedor, entorno, preferencia_id,
               payment_id UNIQUE NULL, estado, detalle, metodo, tipo,
               cuotas INT, monto DECIMAL(12,2), actualizado

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

## 11. Lo que el contrato todavía no resuelve

| # | Tema | Dónde está anotado |
|---|---|---|
| 11 | ¿El precio mayorista se muestra alguna vez? | `PENDIENTES.md` |
| ~~13~~ | ~~Checkout Pro o Bricks~~ → **Checkout Pro**, integrado el 27/08/2026. Ver `docs/MERCADOPAGO.md`. |
| 21 | Cuotas sin interés: tercera línea del bloque de precio | `bloque-precio.php` |
| 68 | Si se anuncian cuotas sin interés, hay que configurarlas en la cuenta | `docs/MERCADOPAGO.md` |
| 69 | La página de retorno se puede abrir con un código ajeno (falta sesión) | `checkout/retorno.php` |
| 70 | El stock no se reserva al crear el pedido | `app/checkout.php` |
| 73 | El panel todavía no lee los pedidos del checkout | `PENDIENTES.md` |
| 36 | Qué estados de pedido maneja el negocio | `cuenta/index.php` |
| 38 | ¿Direcciones múltiples? | `cuenta/index.php` |
| 39 | Qué pasa con una cuenta marcada como mayorista | `auth/registro.php` |
| 48 | Contra qué subtotal se mide el envío gratis | `carrito.js` |
| 63 | Filtros de selección múltiple | `catalogo.php` |
| 65 | "A pedido" como estado distinto de "sin stock" | `catalogo.php` |
