# Diseño — Rastro Fitness

Archivo de Figma del proyecto. Team **Projects v2**.

**https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/Rastro-Fitness---Sistema-de-dise%C3%B1o-y-sitio**

---

## Páginas

| Página | Qué hay | Link directo |
|---|---|---|
| **Portada** | Alcance, índice y decisiones tomadas | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=25-2) |
| **Foundations** | Paleta, tipografía, espaciado, radios | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=5-2) |
| **Componentes** | Los 5 componentes base con sus estados | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=4-4) |
| **Sitio · Home** | Home desktop y mobile | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=4-6) |
| **Admin** | Vacía. Se diseña en el bloque siguiente | — |

## Pantallas

| Pantalla | Medida | Link |
|---|---|---|
| Home desktop | 1440 × 4076 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=15-2) |
| Home mobile | 390 × 5250 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=21-84) |

## Componentes

| Componente | Variantes | Link |
|---|---|---|
| Badge | 4 tipos: descuento, nuevo, agotado, oficial | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=7-11) |
| Botón | 3 estilos × 4 estados = 12 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=8-51) |
| Bloque de precio | 3 tipos × 3 tamaños = 9 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=9-60) |
| Input | 4 estados: default, foco, error, deshabilitado | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=10-49) |
| Card de producto | 2 estados: default, hover | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=13-44) |

---

## Cómo está armado el sistema

**4 colecciones de variables**, todas con scopes y code syntax cargados, así que
Dev Mode devuelve el nombre real de la variable CSS:

- `Primitivos` — 21 colores crudos. Ocultos: no se usan directo.
- `Color` — 24 tokens semánticos aliaseados a primitivos. **Dos modos: `Sitio` (oscuro) y `Admin` (claro).**
- `Espaciado` — escala de 4, de 4 a 120.
- `Radio` — de 0 a full. Chicos a propósito: la marca es angular.

**19 estilos de texto.** Michroma para display y precios, Saira para UI y texto.

**El code syntax de cada variable ya apunta al nombre CSS final** (`var(--color-accent-base)`),
así que `assets/css/tokens.css` se escribe copiando de Figma, sin traducir a mano.

## Decisiones que conviene no reabrir

1. **Sin verde.** Bordeaux `#780606` es el único acento.
2. **El bloque de precio es un solo componente** con 9 variantes. Card, detalle y
   carrito usan el mismo. Si cambia el diseño del precio, cambia en un lugar.
3. **La foto de producto va sobre un well claro** (`#E9E9E9`). Las 68 fotos del
   cliente vienen sobre blanco y el sitio es negro: en vez de pelear con eso, el
   fondo claro pasa a ser parte del diseño de la card.
4. **El hero es partido** (texto izquierda, foto derecha) porque las 3 fotos de
   ambiente son verticales. En mobile la foto pasa a fondo con overlay, que es
   donde ese encuadre rinde mejor.
5. **El error siempre lleva ícono**, nunca solo color: el bordeaux también
   significa promoción en este sistema.
6. **El panel admin usa el modo claro** del mismo set de tokens. Falta confirmarlo
   con el cliente (`PENDIENTES.md` #29).

## Qué falta de esta fase

- Catálogo, detalle, carrito, login, registro, mi cuenta y mayoristas.
- Panel admin completo (solo desktop), con frames anotados para el backend dev.
- Íconos de interfaz: hoy son placeholders geométricos (`PENDIENTES.md` #24).
- Logos de clientes y de marcas oficiales: hoy son slots (`PENDIENTES.md` #25).
