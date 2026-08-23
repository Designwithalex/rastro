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
| **Sitio · Home BOLD** | Propuesta alternativa: misma paleta, lenguaje "ficha técnica". Desktop y mobile | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=28-3) |
| **Admin** | Vacía. Se diseña en el bloque siguiente | — |

## Pantallas

| Pantalla | Medida | Link |
|---|---|---|
| Home desktop | 1440 × 4076 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=15-2) |
| Home mobile | 390 × 5250 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=21-84) |
| Home BOLD desktop | 1440 × 4585 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=28-3) |
| Home BOLD mobile | 390 × 6120 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=37-2) |

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


---

## Propuesta alternativa — Home BOLD

Página **Sitio · Home BOLD**. Misma paleta exacta (negro, plata/grises, bordeaux),
otro lenguaje. Solo desktop, como demo.

**Concepto: ficha técnica.** El gimnasio ya es numérico —10 KG, 2,20 M, 20 kg— y la
UI técnica también. Ese es el puente real entre "tech" y "gimnasio": no es
decoración, es que los dos mundos hablan en números y especificaciones.

**Qué cambia respecto de la versión aprobada**

| | Versión 1 | BOLD |
|---|---|---|
| Tipografías | Michroma + Saira | Michroma + **JetBrains Mono** + Saira |
| Titular | 48 px | **80 px**, a sangre |
| Radios | 2 a 8 px | **0. Todo canto vivo** |
| Layout | grillas parejas | **bento asimétrico** |
| Separación | bordes por card | **hairlines de 1 px que arman una retícula continua** |
| Fotos | sobre well gris | **duotono bordeaux** en ambiente, **blanco puro** en producto |
| Secciones | título | **índice `[ 01 ]` en cada una** |
| Números | tipografía plana | **degradé plata del isotipo** |
| Card | contorno + botón lleno | **ficha con SKU, marcas de encuadre y precio dominante** |

**Lo que se mantiene igual**
- Los 24 tokens de color. No se tocó ni un valor.
- El precio doble: publicado (Mercado Pago) + transferencia en bordeaux con el %.
- Las dos franjas de logos, el bloque mayorista y el botón de arrepentimiento.
- Los pictogramas del brandbook, ahora como marcadores de categoría.

**Cómo se resuelve en mobile**
- El hero pasa a foto a sangre con duotono y overlay al 66%, titular a 30 px.
- Los tres números de la ficha van en una tira de tres celdas separadas por hairlines.
- Categorías: una celda grande con foto arriba, 2×2 abajo y "Ver todo" a ancho completo.
- Destacados: una columna a sangre, cards separadas por hairline en vez de contorno.
- La retícula de 1 px se mantiene en todo: es lo que le da unidad al sistema.

**Si se adopta, hay que rehacer**
1. Foundations: sumar JetBrains Mono, pasar los radios a 0, agregar los estilos mono.
2. Los 5 componentes: cambian caja, estados y densidad.
3. Nada más: la home ya está en desktop y mobile.
