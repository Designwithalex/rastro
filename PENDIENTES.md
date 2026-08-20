# Pendientes — Rastro Fitness

Todo lo que está bloqueado esperando una definición del cliente o del backend dev.
Cada ítem tiene un **placeholder** funcionando, así el desarrollo no se frena.

---

## Para confirmar con el cliente

| # | Tema | Placeholder actual | Impacto si cambia |
|---|---|---|---|
| 1 | **% de descuento** por transferencia/efectivo | `15` en `data/settings.json` | Solo el número: la UI lo lee del helper. Bajo. |
| 2 | **Número de WhatsApp** (minorista y mayorista) | `+54 9 000 000 0000` en `settings.json` | Bajo. Se cambia en un lugar. |
| 4 | **Licencia web de Eurostile** (WOFF/WOFF2) | Michroma + Saira de Google Fonts | Medio. Si hay licencia, self-hosting y ajuste de escala tipográfica. |
| 5 | **Marcas oficiales**: listado definitivo + logos | Greencore + 5 slots dummy | Bajo. |
| 6 | **Logos "Confían en nosotros"**: quiénes y sus logos | 8 slots dummy | Bajo. |
| 7 | **Precios reales** de los productos | Precios verosímiles inventados en el mock | Bajo, es data. |
| 8 | **Costos y política de envío** (¿envío gratis desde X?) | `envio_gratis_desde: 150000` | Bajo. |
| 9 | **Dominio final** | Subdominio de Hostinger | Bajo. |
| 10 | **Datos fiscales / legales** para el footer (CUIT, razón social, defensa al consumidor, botón de arrepentimiento) | Texto dummy | Medio: el botón de arrepentimiento es obligatorio por ley en AR. |

## Definiciones de producto abiertas

| # | Tema | Decisión provisoria |
|---|---|---|
| 11 | El panel guarda **precio minorista y mayorista**, pero el mayorista contacta por WhatsApp. ¿El precio mayorista se muestra alguna vez? | Se guarda en el JSON pero **no se muestra público**. Preparado para mostrarse a usuarios con rol `mayorista` logueados. |
| 12 | **Registro de usuario**: ¿para qué sirve hoy? ¿Historial de pedidos, checkout más rápido, precios mayoristas? | Vistas maquetadas con historial de pedidos + datos. Lógica la conecta el backend dev. |
| 13 | **Checkout**: ¿Mercado Pago Checkout Pro (redirect) o Bricks (embebido)? | El carrito termina en un CTA que el backend dev cablea. Lo define él. |
| 14 | **Cotizador por proyecto con PDF por mail** (pedido "a futuro" en Notion) | Fuera de alcance de esta etapa. Anotado para fase 2 del producto. |
| 15 | **Banners editables** desde el panel | `repo_banners()` los expone; el ABM lo hace el backend dev. |

## Para el backend dev

| # | Tema |
|---|---|
| 16 | Credenciales de MySQL: se cargan **solo** en `app/config.php` del servidor y se pasan por canal seguro. Nunca al repo ni a Notion. |
| 17 | Estructura de tablas sugerida a partir de `docs/DATA-CONTRACT.md`. |
| 18 | Habilitar MySQL remoto en hPanel para desarrollo local (documentado en `docs/HANDOFF.md`). |

## Tareas técnicas nuestras

| # | Tema |
|---|---|
| 19 | Optimizar imágenes: 29 MB sin comprimir. Redimensionar + WebP antes del deploy. |
| 20 | Los PNG transparentes pesan ~1 MB c/u. Recomprimir. |

---

## Abiertos por la Fase 1 de diseño

Surgidos al revisar los assets reales y las referencias antes de maquetar la home.

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| 21 | **¿Se muestran cuotas sin interés?** Las tres referencias (fm-pesas, gfitness, bolk) muestran una tercera línea de precio: "12 cuotas sin interés de $X". `CLAUDE.md` §4.3 solo define precio Mercado Pago + descuento por transferencia. | Define si el bloque de precio tiene 2 o 3 líneas. Es el componente más repetido del sitio: cambiarlo después toca card, detalle y carrito. | Se diseña a 2 líneas (publicado + transferencia). Si el cliente confirma cuotas, se suma como tercera línea opcional del mismo componente. |
| 22 | **Fondo de las fotos de producto.** Las 68 fotos están sobre blanco o gris muy claro, y el sitio es negro. | Sin definición quedan recuadros blancos flotando sobre negro. | La card usa un *image well* claro (`#E9E9E9`) con las esquinas del contenedor: la foto entra sin recorte y el blanco pasa a ser una decisión de diseño, no un defecto. Alternativa cara: recortar fondo de las 68. |
| 23 | **Falta foto de ambiente apaisada.** Las 3 fotos de `assets/img/ambiente/` son verticales (928×1152). | Un hero full-bleed a 1440 obliga a recortarlas al 25% de su alto. | Hero partido: tipografía a la izquierda, foto vertical sangrando a la derecha. Si el cliente consigue tomas apaisadas, el hero admite las dos variantes. |
| 24 | **No hay íconos de interfaz.** Los 9 de `assets/img/iconos/` son pictogramas de categoría (kettlebell, disco, rack…), no UI. Faltan buscar, carrito, cuenta, filtro, chevron, check, alerta, cerrar. | Sin set definido cada pantalla inventa el suyo. | Set de línea 1.5 px, 24×24, estilo Lucide/Phosphor, para convivir con los pictogramas del brandbook. Confirmar licencia (ambos MIT). |
| 25 | **Formato de entrega de los logos** de "Confían en nosotros" y marcas oficiales. | Logos en JPG con fondo blanco rompen una franja monocroma sobre negro. | Pedir SVG o PNG transparente. Tratamiento: monocromo plata al 60% de opacidad, color al hover, alto normalizado. |
| 26 | **Categorías definitivas de la home.** Los nombres de archivo sugieren agarres de polea, discos, barras, mancuernas, kettlebells, bandas, yoga, boxeo, accesorios. | La grilla de categorías de la home necesita un número cerrado (6 u 8 entran prolijas). | 8 categorías provisorias tomadas de los nombres de archivo. |
| 27 | **¿Hay reseñas o rating de producto?** Las referencias muestran estrellas en la card. `repository.php` no expone ese dato. | Agrega una línea a la card y una sección al detalle. | Fuera de alcance de esta etapa. No se diseña. |
| 28 | **Copy real** del hero, del bloque mayorista y de los beneficios. | Hoy es texto puesto por el diseño. | Se escribe copy verosímil y se marca como provisorio en el frame. |
| 29 | **¿El panel admin va en claro?** El sitio es oscuro, pero un panel con tablas y formularios se lee mejor en claro. | Lo dejé resuelto en el sistema: la colección `Color` tiene modo `Sitio` (oscuro) y modo `Admin` (claro), con los mismos tokens. Confirmar con el cliente antes de diseñar el panel. |
| 30 | **Destacados en mobile a 1 columna.** A 2 columnas la card queda en 167 px y el bloque de precio no entra (necesita 236). | Se diseñó a 1 columna, card a ancho completo. Si el cliente quiere 2 columnas, hay que agregar una variante compacta del bloque de precio. |

---

## Resueltos

| Fecha | Tema | Decisión |
|---|---|---|
| 2026-08-20 | Paleta verde (el brandbook traía `#00674F` y `#2E6F40`) | **Descartado.** El cliente no quiere verde. La paleta es negro + plata/grises + bordeaux, con bordeaux como único acento. Ver `CLAUDE.md` §5.3. |
