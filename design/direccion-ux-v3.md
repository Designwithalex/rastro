# Dirección UX v3 — hero, navegación y Nosotros

Agosto 2026. Base para ejecutar en Figma y después bajar al frontend.
Grilla desktop: 12 col · margen 40 · gutter 24 · contenedor 1360 (x 40 → 1400).
Grilla mobile: 4 col · margen 20 · gutter 16 · contenedor 350.

---

## 1. Hero — HECHO

Se conservó lo que trajo el cliente: foto a sangre, display sucia en la línea 2 y
el carrusel de banners a la derecha (que es el carrusel, no un error de capas).

Aplicado:
- Velo horizontal sobre la foto (0.92 → 0.22 de izquierda a derecha) para garantizar
  legibilidad del tercio izquierdo sin importar qué foto entre después.
- Ojal con el índice `[ 00 ]` del sistema, en `accent/text`, y copy sin la redundancia
  "vendedores… vendemos".
- Alineación óptica: la tinta de las dos líneas del titular arranca en x=40.
- Contraste del titular: relleno `accent/text` (5,8:1) con contorno `accent/base` de
  3 px. El `#780606` sólido daba 2:1 y no pasaba AA ni para texto grande.
- **Barra de datos a sangre** en el borde inferior, 4 celdas separadas por hairline:
  precio publicado · transferencia −15% · envío · mayoristas. Recupera la regla de
  precio, que había quedado como nota al pie centrada de 10 px, bajo el pliegue en
  un portátil de 1366×768 y ausente en mobile.

Pendiente para Ale (necesita la fuente instalada):
- Achicar la línea 2 un 12% (121,6 → ~106, interlínea 134, tracking −4) para que la
  tinta oblicua deje de pisar el carrusel, que arranca en x=787.
- Igualar altura de mayúscula, no cuerpo, y dejar 8 px entre la base de la línea 1
  y el tope de la línea 2.

---

## 2. Navegación — POR EJECUTAR

Decisión tomada: **fusionar**. El menú queda

`01 PRODUCTOS ▾ · 02 MAYORISTAS · 03 NOSOTROS · 04 CONTACTO`

"Catálogo" y "Productos" eran dos puertas al mismo lugar. `CONTACTO` ancla a la
sección `[ 09 ]` de Nosotros, no es página nueva.

### Mega-menú, escritorio

Panel a sangre 1440, borde superior e inferior de 1 px `border/subtle`, fondo
`bg/surface`, **alto fijo 504** (no cambia al pasar de categoría). Overlay negro al
60% sobre el resto de la página. Contenedor interno 1360, padding 32 arriba / 24 abajo.

| | A · Categorías | B · Productos | C · Mayoristas |
|---|---|---|---|
| Grilla | cols 1-3 | cols 4-9 | cols 10-12 |
| x | 40 → 362 | 386 → 1054 | 1078 → 1400 |

- **A**: rótulo + 6 filas de 44 separadas por hairline + "VER TODO EL CATÁLOGO →".
  Nombre a la izquierda, cantidad a la derecha en `text/secondary`.
  La primera categoría viene preseleccionada: el panel nunca abre con B vacía.
- **B**: 2 subcolumnas de 322, gutter 24, 4 filas de 64. Cada ítem:
  miniatura 48 sobre well claro + nombre `body/sm` una línea con elipsis +
  `mono/texto-sm` con `SKU · MEDIDA · ESTADO`. **Sin precio**: ocho bloques de precio
  convierten el menú en una grilla. Fila final: "VER LOS 48 DE DISCOS →" en `accent/text`.
  Los 8 productos salen del campo `destacado`. **Eso agrega una casilla al ABM del panel admin.**
- **C**: módulo fijo con borde, `[ ! ]` + MAYORISTAS + "¿Equipás una sala completa?"
  + CTA de WhatsApp a ancho completo. Es la única puerta permanente al canal sin precios.
- **Pie del panel**: hairline arriba, 48 de alto, la regla de precio a la izquierda y
  el umbral de envío a la derecha.

### Estados de los ítems

| Estado | Texto | Índice | Extra |
|---|---|---|---|
| Reposo | `text/secondary` | `text/secondary` | — |
| Hover | `text/primary` | `accent/text` | 150 ms, sin subrayado |
| Foco | `text/primary` | `accent/text` | contorno 2 px blanco, canto vivo |
| Abierto | `text/primary` | `accent/text` | chevron 180° + barra 2 px `accent/base` al pie |
| Página actual | `text/primary` | `accent/text` | barra 2 px `accent/base` al pie |

### Apertura y teclado

- Hover con retardo de intención de 120 ms; cierre con 240 ms para permitir el
  movimiento diagonal hacia el panel.
- **El panel vive dentro del `<li>` de Productos**, no como hermano de la barra.
  Es el patrón de disclosure estándar: el contenido revelado sigue a su disparador
  en el orden de foco y en el árbol de accesibilidad, y es lo que permite que el
  `focusout` cierre solo sin centinelas. Consecuencia: `Tab` desde el último
  elemento del panel cae en el ítem siguiente del menú, no en el buscador. Es más
  coherente que cruzar tres ítems sin visitarlos.
- El disparador es `<button aria-expanded aria-controls>`, **no un enlace**: elimina
  el bug de "el primer toque navega en vez de abrir".
- `Enter`/`Espacio`/`↓` abre y mueve el foco a la primera categoría. `↑↓` recorre y
  cambia la columna B. `→` entra a B, `←` vuelve. `Tab` avanza linealmente y **no se
  atrapa**: un menú no es un diálogo. `Tab` desde el último elemento cierra el panel.
  `Esc` cierra y devuelve el foco al disparador, siempre.
- Anillo de foco 2 px blanco, canto vivo, en todo el panel.

### El corte de escritorio va en 1280

Medido: `.nav` con las cuatro puertas ocupa 475 px y `.cabecera__acciones` necesita 573,
así que la barra en una línea no entra hasta **1220**. Bajar el corte a 1120 deja 80 px
de tierra de nadie donde las acciones se montan sobre el último ítem del menú — y como
`.nav__enlace` está posicionado, además **captura los clics del buscador**. Si algún día
se quiere bajar, primero hay que hacer que la barra se achique de verdad.

### Mobile — cajón, no tira

La tira horizontal actual esconde el canal mayorista detrás de un gesto. Se reemplaza
por un cajón a pantalla completa que entra desde la izquierda en 200 ms.

Cabecera 56: `[hamburguesa 44][logo 110×28 centrado][buscar 44][carrito ~60]`.

La celda del carrito mide ~60 y no 44 porque el contador `[00]` es dato, no adorno:
con el contador puesto termina en x=370 sobre 390, con los mismos 20 px de margen que
el flanco izquierdo, y el logo queda centrado de verdad. El área táctil son 44 de alto,
que es lo que exige el criterio. En celular pierde el fondo bordeaux: en una barra de
cuatro celdas el botón lleno es el único elemento que grita, y el carrito vacío no es
la acción principal. El bordeaux vuelve en escritorio, donde sí es un botón.
Cuerpo: filas de 56 con hairline entre ellas · acordeón de PRODUCTOS con las 6
categorías en filas de 48 sangradas · bloque secundario (cuenta, pedidos, contacto) ·
la regla de precio en su módulo de 2 filas · CTA de WhatsApp · pie con redes.

**Dos niveles, no tres.** Tocar una categoría navega a `/catalogo/{slug}`. En celular,
"adentro de la categoría" **es** la página de la categoría, que tiene foto, filtros y
precio: le gana a una lista de texto.

El cajón **sí es un diálogo**: `role="dialog" aria-modal`, foco al cerrar, `Tab`
atrapado, `Esc` cierra, scroll del body bloqueado, targets ≥ 44×44.

---

## 3. Nosotros — POR EJECUTAR

**Para qué sirve:** un club que va a gastar $2.000.000 entra a contestar cuatro
preguntas: ¿existís?, ¿ya lo hiciste para alguien como yo?, ¿qué pasa después de que
te transfiero?, ¿a quién le escribo. Es un legajo de credibilidad, no un relato.
La foto de los fundadores es una prueba entre varias, no el eje.

**Va en los dos lados**, con roles distintos: franja en la home para el que todavía no
decidió mirar, y página `/nosotros` para el que ya está evaluando y la va a mandar por
WhatsApp cuando en el club pregunten "¿quiénes son estos?".

### Franja en la home
Después de destacados, **antes** del bloque mayorista: la prueba de confianza precede
al pedido de contacto. 1440 × 320, hairlines arriba y abajo.
Izquierda cols 1-5: `[ 0X ] QUIÉNES SOMOS` + titular `display/l` + 3 líneas + enlace.
Derecha cols 7-12: 4 celdas de 334 × 140 en 2×2, contenedor pintado con gap de 1 px.
Cifra en `precio/lg` con la clase `.plata`, rótulo en `mono/label-sm`.

Celdas: AÑOS EN EL RUBRO · GIMNASIOS EQUIPADOS · PRODUCTOS EN CATÁLOGO · PROVINCIAS.
**Tres de los cuatro números no existen.** Placeholder `[ DATO ]` visible para que en
la revisión con el cliente se vea el hueco. Si no llegan, se reemplazan por cuatro
hechos verdaderos (los dos fundadores, la ciudad, "vendedores oficiales").

### Página /nosotros — secciones en orden

| | Sección | Por qué ahí |
|---|---|---|
| 01 | Encabezado + declaración + barra de 4 datos | Primero hay que contestar "quién es esto" y que se vea real |
| 02 | Los fundadores · Santino Pantanali y Juan Pedro Ramognino | Dos caras identificables le ganan a cualquier adjetivo en B2B |
| 03 | Cómo empezó · línea de tiempo | La historia interesa recién cuando ya decidiste que son gente real |
| 04 | Cómo trabajamos · 4 celdas | Contesta "qué pasa después de que pago", el bloqueo real de una compra grande |
| 05 | Obras · 3 gimnasios equipados | La prueba más fuerte. **Si el cliente solo puede dar una cosa, que sea esta** |
| 06+07 | Marcas oficiales y clientes | En la home son prueba social; acá son credenciales |
| 08 | Garantía, envíos y posventa | Texto real, no marketing |
| 09 | Dónde estamos + **foto del galpón, no un mapa** | Un mapa dice "hay un pin"; una foto del depósito dice "hay stock" |
| 10 | Cierre de doble puerta: catálogo / cotizar | A esta altura ya sabés qué visitante sos |

Lectura del orden: 01-03 *quién sos* · 04-05 *podés hacerlo* · 06-09 *es seguro* ·
10 *y ahora qué*. Es el orden en que se decide una compra grande.

**Todo el copy es relleno** y va rotulado en Figma con `COPY PROVISORIO · PENDIENTE
CLIENTE` en `accent/text`.

**Estados que hay que dibujar igual:** sin obras cargadas, `[ 05 ]` no se renderiza
vacía, se oculta la sección entera. Sin foto de fundadores, `[ 02 ]` colapsa a un
párrafo firmado. **No se reemplaza con retratos de stock**: una página sin foto es
honesta, una con dos señores comprados es un problema.

---

## 4. `text/disabled` no se usa. En ningún lado.

El token existe y sigue definido, pero **ninguna hoja del sitio lo usa** y eso es una
invariante, no una casualidad. Sobre los fondos reales del sistema da entre 3,18:1 y
3,66:1, o sea que no llega al 4,5:1 de AA para texto normal en ninguno.

`CLAUDE.md` §5.3 ya lo reserva para lo inhabilitado. La trampa es que "dato apagado"
y "dato secundario" se parecen: la cantidad de productos de una categoría, el rótulo
de una fila, el horario de atención y el CUIT **son dato legible**, y van en
`text/secondary` (#C4C4C4), que da entre 10,5:1 y 12:1.

Cuando algo parezca merecer un gris más apagado, la respuesta es bajar el tamaño o el
peso, no el contraste.

## 5. Decisión de sistema: dos componentes de precio, no uno

- **`regla-precio`** — la política, sin monto. "Publicado = Mercado Pago · −15% por
  transferencia". Vive en hero, pie del mega-menú, cajón mobile y pie del sitio.
  Lee `descuento_transferencia_pct` de `repo_settings()`.
- **`bloque-precio`** — el precio de un producto, con montos y badge. Vive en card,
  ficha y carrito.

Si quedan como un solo componente, alguien va a terminar poniendo un monto en el hero.
