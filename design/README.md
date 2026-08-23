# Diseño — Rastro Fitness

Archivo de Figma del proyecto. Team **Projects v2**.

**https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs**

El sistema está en **v2 — "ficha técnica"**, elegida por el cliente el 23/08/2026
sobre la primera dirección. Ver `CLAUDE.md` §5.6.

---

## Páginas

| Página | Qué hay | Link |
|---|---|---|
| **Portada** | Alcance, índice y decisiones | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=25-2) |
| **★ Comparativa v1 · v2** | Tablero para presentarle al cliente | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=44-3) |
| **Foundations** | Paleta, tipografía, espaciado, radios | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=5-2) |
| **Componentes** | Los 5 componentes con sus estados | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=4-4) |
| **Sitio · Home** | Home desktop y mobile | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=28-3) |
| **Archivo · Home v1** | Primera dirección, como referencia | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=15-2) |
| **Admin** | Vacía. Se diseña más adelante | — |

> La página de comparativa usa **capturas congeladas**, no los frames vivos. Eso es a
> propósito: la v1 quedó registrada tal como se presentó y no se altera aunque el
> sistema siga cambiando.

## Pantallas

| Pantalla | Medida | Link |
|---|---|---|
| Home desktop | 1440 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=28-3) |
| Home mobile | 390 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=37-2) |

## Componentes

| Componente | Variantes | Link |
|---|---|---|
| Badge | 4 tipos: descuento, nuevo, agotado, oficial | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=7-11) |
| Botón | 3 estilos × 4 estados = 12 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=8-51) |
| Bloque de precio | 3 tipos × 3 tamaños = 9 | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=9-60) |
| Input | 4 estados | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=10-49) |
| Card de producto | 2 estados: default, hover | [abrir](https://www.figma.com/design/32nxqpSmVmX4nvo0zyCRSs/?node-id=13-44) |

La home usa **instancias** de estos componentes. Cambiar el Bloque de precio o el
Botón actualiza el sitio entero.

---

## Cómo está armado el sistema

**4 colecciones de variables**, con scopes y code syntax cargados: Dev Mode devuelve
el nombre real de la variable CSS, así que `assets/css/tokens.css` se escribe copiando
de Figma sin traducir a mano.

- `Primitivos` — 21 colores crudos, ocultos.
- `Color` — 24 tokens semánticos aliaseados. **Modos `Sitio` (oscuro) y `Admin` (claro).**
- `Espaciado` — escala de 4, de 4 a 120.
- `Radio` — todo en 0. Sólo sobrevive `radio/full` para pastillas y avatares.

**26 estilos de texto** en tres familias: Michroma (display y precios),
JetBrains Mono (datos) y Saira (texto corrido).

## Reglas del lenguaje

1. **Canto vivo.** Radio 0 en todo.
2. **La separación es un hairline de 1 px**, no un recuadro por pieza: el contenedor
   se pinta de `border/subtle` y los hijos van con gap 1.
3. **El bloque de precio es un solo componente** con 9 variantes. Card, ficha y
   carrito usan el mismo.
4. **La foto de producto va sobre blanco pleno**, con marcas de encuadre y el código
   abajo a la izquierda.
5. **El degradé plata es sólo para números y logo.** Nunca para texto corrido.
6. **El error siempre lleva ícono**, nunca solo color: el bordeaux también es promoción.
7. **Las secciones van numeradas** con el índice `[ 01 ]`.

## Qué falta

- Catálogo, ficha de producto, carrito, login, registro, mi cuenta y mayoristas.
- Panel admin completo (solo desktop), con frames anotados para el backend dev.
- Íconos de interfaz: hoy son placeholders geométricos (`PENDIENTES.md` #24).
- Logos de clientes y de marcas oficiales (`PENDIENTES.md` #25).
