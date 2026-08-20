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
| Repo + GitHub Action de deploy FTP | Integración Mercado Pago |
| `docs/HANDOFF.md` | Auth real (login/registro/cuenta) |

El panel admin **lo diseñamos nosotros** (Figma, solo desktop) pero **lo
desarrolla él**. Por eso los frames tienen que quedar autoexplicativos:
componentes estándar y anotaciones de qué hace cada acción.

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
app/               config, router, repository, helpers   [privada]
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
```

`$filters` acepta: `q`, `categoria`, `marca`, `precio_min`, `precio_max`,
`orden` (`relevancia|precio_asc|precio_desc|nombre`), `en_stock`, `destacado`.

`repo_products()` devuelve `['items'=>[], 'total'=>int, 'pagina'=>int, 'paginas'=>int]`.

### 4.3 Precio: una sola fuente de verdad

El cálculo del precio con descuento vive en **un solo helper**
(`app/helpers.php: precio_con_descuento()`), que lee el `%` de `repo_settings()`
y respeta un override por producto (`descuento_pct`). Ninguna vista hace la cuenta.

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

Brandbook: **Eurostile** (Regular / Medium / Bold / Heavy / Black + oblicuas) y
**Eurostile Extended** (Regular / Medium / Black).
Eurostile es tipografía paga (Linotype) — ver `PENDIENTES.md`. Hasta confirmar
licencia web, fallback: **Michroma** para display (muy cercana a Eurostile Extended)
y **Saira** para UI y texto corrido. Ambas en Google Fonts.

### 5.3 Paleta

| Rol | Valor |
|---|---|
| Fondo base | negro / `#0B0B0B` |
| Grises | `#3D3D3D` · `#666666` · `#C4C4C4` · `#E0E0E0` |
| Plata | degradé (identidad del isotipo) — reservado para logo y detalles |
| **Verde** (opción A, default) | `#00674F` · escala `#0A3C30` `#3EBB9E` `#73E6CB` |
| Verde (opción B) | `#2E6F40` · escala `#253D2C` `#68BA7F` `#CFFFDC` |
| **Bordeaux** | `#780606` · escala `#DE6464` `#FFA6A6` `#FFD9D9` |

El brandbook trae **dos versiones de la paleta verde**; adoptamos la A por defecto
y queda anotado en `PENDIENTES.md` para confirmar con el cliente.

### 5.4 Semántica de color

El brandbook ya define un código de color para iconografía de Instagram, y lo
trasladamos al ecommerce:

| Brandbook | En el sitio |
|---|---|
| Plata = productos | superficies, cards, bordes |
| Verde = packs | **precio por transferencia / efectivo**, éxito, stock |
| Bordeaux = promociones | ofertas, badges de descuento, errores |
| Blanco = tips | texto principal sobre oscuro |

### 5.5 Tono visual

Industrial, oscuro, técnico. Fotografía de gimnasio real (galpón, racks, discos)
desaturada o en blanco y negro; producto recortado sobre blanco o sobre negro.
Nada "wellness", nada pastel. Referencia preferida: **https://fm-pesas.com/**.
Secundarias: tienda.gfitness.com.ar y bolkequipment.com.ar.

---

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
