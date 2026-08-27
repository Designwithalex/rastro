# Rastro Fitness — Ecommerce

Contexto permanente del proyecto. Leer completo antes de tocar cualquier archivo.

---

## 1. Qué es

Ecommerce de **Rastro Fitness**, vendedores oficiales de equipamiento de gimnasio.
Clientes: Juan Pedro Ramognino y Santino Pantanali. Estudio: Chichalabs.

**Dos canales de venta:**
- **Minorista** — precios públicos, carrito, checkout online.
- **Mayorista** — clubes, gimnasios, hoteles, desarrolladoras, barrios cerrados y
  empresas con gym. **No se muestran precios**: contactan por WhatsApp.

**Regla de precio (central en toda la UI):** el precio publicado es el precio
pagando con **Mercado Pago**. Junto a él se muestra siempre un **X% de descuento
por transferencia o efectivo**. Ese X es configurable: hoy en `data/settings.json`,
mañana desde el panel admin que desarrolla el otro dev.

**Prueba social obligatoria en home:** logos de "Confían en nosotros" (clientes a
los que ya les vendieron) y logos de las marcas de las que Rastro es vendedor
oficial.

---

## 2. Alcance de Chichalabs (nosotros) vs. el backend dev

| Nosotros | El otro dev |
|---|---|
| Diseño en Figma (sitio + panel admin) | Backend real, MySQL |
| Frontend completo con datos mock | Panel admin funcionando |
| `repository.php` (contrato de datos) | Reemplazar mocks por queries |
| Repo + GitHub Action de deploy FTP | Auth real (login/registro/cuenta) |
| **Checkout con Mercado Pago** (Checkout Pro) | Stock, mails y conciliación de pagos |
| `docs/HANDOFF.md` | |

El panel admin **lo diseñamos nosotros** (Figma, solo desktop) pero **lo
desarrolla él**. Por eso los frames tienen que quedar autoexplicativos:
componentes estándar y anotaciones de qué hace cada acción.

**La integración con Mercado Pago cambió de lado el 27/08/2026.** Estaba
asignada al backend dev y la hicimos nosotros: es la pieza que decide qué se
cobra, y eso depende de la regla de precio de §1, que es diseño de producto
antes que backend. Está andando de punta a punta en ambiente de prueba —
Checkout Pro, preferencia, retorno y webhook con firma validada. Lo que queda
del otro lado es lo que necesita base de datos y sesión: reservar stock,
mandar los mails y conciliar los pagos que no notifican. Todo en
`docs/MERCADOPAGO.md`.

---

## 3. Stack y restricciones

- **PHP 8+** únicamente como capa de vistas y router. Sin lógica de negocio pesada.
- **HTML + CSS + JS vanilla.** Sin frameworks, sin build step, sin npm.
- **Datos MOCK en JSON** dentro de `data/`.
- Hosting **compartido Hostinger**. Deploy por **FTP con GitHub Actions**.
- Sitio provisorio (ya provisionado, hoy con la página por defecto):
  `https://darkorange-buffalo-311255.hostingersite.com/`

---

## 4. Arquitectura

Todo el repo se despliega dentro de `public_html`. Las carpetas privadas se
protegen con su propio `.htaccess` (`Require all denied`).

```
index.php          front controller: resuelve ruta -> vista
.htaccess          rewrite de todo a index.php + headers
app/               config, router, repository, helpers,
                   mercadopago, checkout                 [privada]
views/             layout, partials y páginas            [privada]
data/              mocks JSON                            [privada]
assets/            css, js, img, fonts                   [pública]
docs/              HANDOFF, DATA-CONTRACT, DEPLOY
design/            links y exports de Figma
```

### 4.1 Regla de oro del proyecto

> **Ninguna vista lee un JSON.** Toda lectura de datos pasa por `app/repository.php`.

Ese archivo es la **única** frontera entre presentación y datos. El día que entra
el backend dev, cambia el cuerpo de esas funciones (JSON -> MySQL) y **ni una sola
vista se toca**. Ese contrato es el corazón del handoff y está documentado en
`docs/DATA-CONTRACT.md`.

Si una vista necesita un dato que `repository.php` no expone, se agrega una función
al repository — nunca un `file_get_contents` en la vista.

### 4.2 Contrato de `repository.php`

```php
// Catálogo
repo_products(array $filters = [], int $page = 1, int $perPage = 12): array
repo_product(string $slug): ?array
repo_related_products(string $slug, int $limit = 4): array
repo_categories(): array
repo_category(string $slug): ?array

// Marca y prueba social
repo_brands(): array      // marcas de las que Rastro es vendedor oficial
repo_clients(): array     // logos "Confían en nosotros"
repo_banners(): array     // editables desde el panel

// Configuración
repo_settings(): array    // % descuento, WhatsApp, envíos, redes

// Cuenta (mock; lo conecta el backend dev)
repo_login(string $email, string $password): ?array
repo_register(array $data): array
repo_user(int $id): ?array
repo_orders(int $userId): array
repo_order(string $code): ?array

// Carrito (hoy vive en localStorage; esto resuelve los ids)
repo_cart_items(array $ids): array

// Pedidos del checkout — ampliación del 27/08/2026
repo_order_create(array $pedido): array
repo_order_update(string $codigo, array $cambios): ?array
repo_order_by_reference(string $referencia): ?array   // external_reference
repo_order_local(string $codigo): ?array
repo_order_next_code(): string
repo_log_pago(string $que, array $contexto = []): void
```

`$filters` acepta: `q`, `categoria`, `marca`, `precio_min`, `precio_max`,
`orden` (`relevancia|precio_asc|precio_desc|nombre`), `en_stock`, `destacado`.

`repo_products()` devuelve `['items'=>[], 'total'=>int, 'pagina'=>int, 'paginas'=>int]`.

### 4.3 Precio: una sola fuente de verdad

El cálculo del precio con descuento vive en **un solo helper**
(`app/helpers.php: precio_con_descuento()`), que lee el `%` de `repo_settings()`
y respeta un override por producto (`descuento_pct`). Ninguna vista hace la cuenta.

Desde que existe el checkout, esa regla tiene una segunda mitad: **cuál de los
dos precios se cobra lo decide el medio de pago**, y eso vive también en un solo
lugar, `checkout_medios()` de `app/checkout.php`.

- **Mercado Pago** → precio **publicado**. Es la definición de §1: el número
  grande del sitio es lo que se paga con Mercado Pago.
- **Transferencia o efectivo** → precio **con descuento**, y no pasa por
  ninguna pasarela: se coordina por WhatsApp.

`checkout.php` **elige** cuál de los dos números usar; no multiplica ningún
porcentaje. Si aparece un `* (100 - $pct)` fuera de `precio_con_descuento()`,
hay dos fuentes de verdad y una de las dos está mal.

### 4.4 El precio nunca viene del navegador

El carrito vive en `localStorage` y de ahí salen **`id` y `cantidad`, y nada
más**. `checkout_lineas()` vuelve a resolver los precios contra el repository
antes de cobrar. Un `precio_unitario` que llegue en el POST se ignora.

Es el agujero clásico de un checkout casero y por eso está acá arriba: sin esto,
cualquiera con la consola abierta compra una barra olímpica a un peso.

---

## 5. Marca

### 5.1 Identidad

Isotipo: una **"R" construida como flecha ascendente**, en degradé plata.
Logotipo: `RASTRO` en mayúsculas, extendido y pesado, con `FITNESS` debajo en
tracking amplio. Fondo natural de la marca: **negro**.

Archivos en `assets/img/marca/`: `rastro-completo-blanco`, `rastro-completo-negro`,
`rastro-completo-plata-sobre-negro`, `rastro-completo-plata-sobre-blanco`,
`rastro-horizontal-transparente`, `isotipo-r-{blanco,negro,plata}`, `avatar-redondo`.

### 5.2 Tipografía

Tres familias, con roles que no se pisan:

| Familia | Rol | Estilos |
|---|---|---|
| **Michroma** | Display y precios. Reemplaza a Eurostile Extended. | `display/xl` `display/l` `display/m` `display/s` · `precio/lg` `precio/md` `precio/sm` |
| **JetBrains Mono** | Todo lo que es dato: rótulos, códigos, especificaciones, precio por transferencia, índices de sección. | `mono/label` `mono/label-sm` `mono/dato` `mono/texto` `mono/texto-sm` |
| **Saira** | Texto corrido y párrafos largos. | `body/lg` `body/md` `body/sm` `body/xs` |
| **Saira Condensed** | Sólo el titular del hero, en Black. Entró el 26/08/2026 con el hero v3 y reemplazó a `Urban Thunder Demo`. | `display/hero` `display/hero-sub` `display/hero-m` `display/hero-sub-m` |

El brandbook pide **Eurostile** y **Eurostile Extended**, que son pagas (Linotype).
Michroma y Saira son el reemplazo hasta confirmar si el cliente tiene licencia web
(ver `PENDIENTES.md` #4). La escala está armada para que cambiar la familia no mueva
el layout.

JetBrains Mono no está en el brandbook: la sumamos en la v2 porque el negocio es
numérico —10 kg, 2,20 m, código de producto, stock— y la tipografía monoespaciada
es la que hace legible esa información.

### 5.3 Paleta

**Decisión del cliente: sin verde.** El brandbook traía dos variantes de verde
(`#00674F` y `#2E6F40`); las dos quedan descartadas. La paleta del sitio es
**negro + plata/grises + bordeaux**.

| Rol | Valor |
|---|---|
| Fondo base | negro `#000000` · superficie `#0B0B0B` |
| Superficies elevadas | `#151515` · `#1F1F1F` |
| Grises | `#3D3D3D` · `#666666` · `#C4C4C4` · `#E0E0E0` |
| Plata | degradé del isotipo — logo, bordes de acento, detalles |
| **Bordeaux (único acento)** | `#780606` · escala `#DE6464` `#FFA6A6` `#FFD9D9` |
| Texto | blanco sobre negro; `#C4C4C4` para secundario |

Con un solo color de acento, el peso visual lo cargan el **contraste**, la
**tipografía** y el **espacio** — no el color. Eso empuja la estética hacia el
lado industrial que pide la marca, y coincide con la referencia fm-pesas.com.

### 5.4 Semántica de color

| Uso | Color |
|---|---|
| Producto, superficies, bordes | plata / grises |
| **Precio por transferencia + badge de %** | **bordeaux** — es el acento de ahorro |
| Promos, ofertas, destacados | bordeaux |
| Éxito (agregado al carrito, guardado) | blanco/plata + ícono de check, sin color |
| Error y validación | `#DE6464` + ícono + texto. **Nunca solo color** |
| Texto principal | blanco |

Ojo: promoción y error comparten familia cromática. Se diferencian por **ícono,
ubicación y forma**, no por color. Es requisito de accesibilidad, no un detalle.

El brandbook define además un código de color para íconos de Instagram que sí incluye
verde (packs). Eso queda **solo para RRSS**; el sitio no lo usa.

### 5.5 Tono visual

Industrial, oscuro, técnico. Fotografía de gimnasio real (galpón, racks, discos)
desaturada o en blanco y negro; producto recortado sobre blanco o sobre negro.
Nada "wellness", nada pastel. Referencia preferida: **https://fm-pesas.com/**.
Secundarias: tienda.gfitness.com.ar y bolkequipment.com.ar.

---

### 5.6 Lenguaje visual (v2)

En agosto de 2026 el cliente eligió la dirección "ficha técnica" sobre la primera
propuesta. Las reglas que la definen:

- **Canto vivo.** Todos los radios valen 0. El único que sobrevive es `radio/full`,
  para pastillas y avatares.
- **Retícula de hairlines.** Las piezas no se separan con recuadros propios: el
  contenedor se pinta de `border/subtle` y los hijos van con 1 px de gap. Esa línea
  continua es lo que le da unidad al sitio.
- **Tipografía sobredimensionada.** El titular del hero es 148 px en escritorio y
  70 px en celular, en mayúsculas, en Saira Condensed Black. Los titulares de
  sección siguen en Michroma a 48 px.
- **Composición asimétrica.** Bento en hero y categorías, no grillas parejas.
- **Números en degradé plata.** El degradé del isotipo sale del logo y pasa a los
  precios y las cifras grandes. Es el único lugar donde se usa.
- **Chapa sólo en el titular del hero.** `PROFESIONAL` y `RASTRO.` van con relleno
  de textura (`assets/img/texturas/acero-cepillado.jpg`), no con el degradé. Es el
  único punto del sitio con textura fotográfica adentro del texto.
- **Fotos de ambiente en duotono bordeaux**; fotos de producto sobre **blanco pleno**.
- **Secciones numeradas** con el índice `[ 01 ]` en tipografía técnica.
- **Datos visibles**: cada producto muestra código, stock y medida, no solo nombre
  y precio. Eso es lo que un club o un gimnasio necesita para decidir una compra.

La versión anterior queda en la página `Archivo · Home v1` de Figma, y congelada
como imagen en la página de comparativa.

## 6. Assets disponibles

Origen: `/Users/ale/Documents/chichalabs-clientes/rastro` (ya copiados al repo).

- `assets/img/marca/` — 9 variantes de logo
- `assets/img/iconos/` — 9 íconos de línea en blanco y negro (kettlebell, mancuerna,
  barra, disco, rack, envío, documento, pregunta, contenedor)
- `assets/img/favicon/` — 8 tamaños
- `assets/img/productos/` — 68 fotos (45 genéricos + 23 sobre blanco)
- `assets/img/productos/png/` — 23 PNG con fondo transparente (línea Greencore)
- `assets/img/ambiente/` — 3 fotos lifestyle de gimnasio

**Greencore** aparece como línea/marca en las fotos: es candidata a "marca de la que
somos vendedores oficiales". Confirmar el listado definitivo (`PENDIENTES.md`).

Las imágenes están sin optimizar (29 MB). Antes del deploy: redimensionar y generar
WebP. No versionar originales pesados sin optimizar.

---

## 7. Reglas de trabajo

1. **Credenciales nunca en el repo ni en Notion.** `app/config.php` está en
   `.gitignore`; solo se versiona `app/config.example.php`. Ante la duda, frenar y
   preguntar.
2. **Toda lectura de datos pasa por `repository.php`.** Sin excepciones.
3. **Cada fase cierra con:** resumen, archivos tocados, pendientes, y actualización
   de la página de Notion del proyecto.
4. **Ambigüedades** (% de descuento real, WhatsApp, marcas definitivas) -> placeholder
   en `data/settings.json` + anotar en `PENDIENTES.md`. No inventar valores finales.
5. **Commits atómicos, conventional commits** (`feat:`, `fix:`, `docs:`, `chore:`).
6. Escapar **siempre** la salida en vistas (`e()` en `helpers.php`). Nada de eco crudo.

---

## 8. Enlaces

- Notion del proyecto: https://app.notion.com/p/3c18437e26f380ec9a3bc3202527f36a
- Drive del cliente: https://drive.google.com/drive/folders/17A56drHb-n59DK6x9uPZNrufPmcwhxpX
- Sitio provisorio Hostinger: https://darkorange-buffalo-311255.hostingersite.com/
- Assets originales: `/Users/ale/Documents/chichalabs-clientes/rastro`
