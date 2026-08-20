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

## Resueltos

| Fecha | Tema | Decisión |
|---|---|---|
| 2026-08-20 | Paleta verde (el brandbook traía `#00674F` y `#2E6F40`) | **Descartado.** El cliente no quiere verde. La paleta es negro + plata/grises + bordeaux, con bordeaux como único acento. Ver `CLAUDE.md` §5.3. |
