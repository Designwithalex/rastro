# Pendientes — Rastro Fitness

Todo lo que está bloqueado esperando una definición del cliente o del backend dev.
Cada ítem tiene un **placeholder** funcionando, así el desarrollo no se frena.

---

## Para confirmar con el cliente

| # | Tema | Placeholder actual | Impacto si cambia |
|---|---|---|---|
| 1 | **% de descuento** por transferencia/efectivo | `15` en `data/settings.json` | Solo el número: la UI lo lee del helper. Bajo. |
| 2 | **Número de WhatsApp** (minorista y mayorista) | `+54 9 000 000 0000` en `settings.json` | Bajo. Se cambia en un lugar. |
| 4 | **Licencia web de Eurostile** (WOFF/WOFF2) | Michroma + Saira + JetBrains Mono de Google Fonts | Medio. Si hay licencia, self-hosting y ajuste de escala. JetBrains Mono se queda igual: cubre un rol que Eurostile no tiene. |
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
| ~~16~~ | Credenciales de MySQL: se cargan **solo** en `app/config.php` del servidor y se pasan por canal seguro. Nunca al repo ni a Notion. |
| ~~17~~ | Estructura de tablas sugerida a partir de `docs/DATA-CONTRACT.md`. |
| ~~18~~ | Habilitar MySQL remoto en hPanel para desarrollo local (documentado en `docs/HANDOFF.md`). |

## Tareas técnicas nuestras

| # | Tema |
|---|---|
| ~~19~~ | ~~Optimizar imágenes~~ → resuelto el 2026-08-25, ver abajo. |
| ~~20~~ | ~~Recomprimir los PNG transparentes~~ → resuelto el 2026-08-25, ver abajo. |
| ~~46~~ | ~~`docs/DATA-CONTRACT.md` y `docs/HANDOFF.md`~~ → escritos el 2026-08-26, ver abajo. |
| ~~47~~ | ~~No hay `views/errors/404.php`~~ → entró el 2026-08-26 con el bloque de páginas, ver abajo. |

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

---

Los nueve que siguen salieron de la misma revisión, pero son datos que tiene
que cargar el cliente y no decisiones de diseño: por eso van sin la columna de
"por qué importa". Estaban quedando debajo de "Resueltos" sin encabezado, lo
que los hacía parecer cerrados. **Están los nueve abiertos.**

| # | Tema | Decisión provisoria |
|---|---|---|
| 31 | **Código, stock y medida por producto.** La v2 los muestra en cada card. | Hoy son datos verosímiles inventados. `repository.php` ya los expone (`sku`, `stock`), así que no hay cambio de contrato: falta que el cliente cargue los reales. |
| 32 | **Dónde se retira.** La ficha dice "En depósito, sin cargo. Córdoba capital". | Inventado por el diseño. Falta la dirección real y si hay retiro en más de un punto. |
| 33 | **Política de garantía.** La ficha dice 12 meses por defecto de fabricación. | Inventado. Confirmar plazo real y qué cubre. |
| 34 | **Especificaciones técnicas por producto** (material, diámetro, peso, buje, uso). | La ficha las muestra en tabla y `repository.php` ya las expone como `especificaciones`. Falta que el cliente las cargue: sin esto la ficha queda a medias. |
| 35 | **Umbral de envío gratis.** Hoy $150.000 en `settings.json`. | Aparece en la ficha y en el carrito. Un solo valor, se cambia en un lugar. |
| 36 | **Estados de pedido.** La cuenta muestra En camino, Entregado y Cancelado. | Inventados. Confirmar qué estados maneja el negocio de verdad: si hay más, se agrega una variante del chip y la tabla no se toca. |
| 37 | **A qué correo llega el formulario mayorista** y qué campos son obligatorios. | Maqueta con cinco campos. El backend conecta el envío; a futuro alimenta el cotizador con PDF. |
| 38 | **¿Direcciones múltiples en Mi cuenta?** La navegación tiene la sección pero no está diseñada. | Depende de si un cliente puede tener más de un lugar de entrega. Si es una sola, se saca del menú. |
| 39 | **Registro: "Para un gimnasio o empresa".** El selector está diseñado. | Falta definir qué pasa después: ¿el usuario queda marcado como mayorista y ve precios distintos, o solo dispara un aviso al equipo? |

---

## Abiertos por la Fase 2 de frontend

Surgidos al armar la capa de datos y el layout compartido.

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| 40 | **Faltan fotos de producto en dos categorías enteras.** De las 68 fotos que mandó el cliente, ninguna es una kettlebell, un half rack ni una jaula de potencia.<br><br>**Categorías afectadas:** `kettlebells` (2 de 3 productos sin foto) y `racks-y-jaulas` (2 de 4).<br><br>**Los 4 productos:** `RS-KB-012` Kettlebell de fundición 12 kg · `RS-KB-016` Kettlebell de fundición 16 kg · `RS-RK-HALF` Half rack de sentadillas · `RS-RK-CAGE` Jaula de potencia.<br><br>**Qué se necesita:** una toma frontal sobre fondo claro por producto, mínimo 1000 px de lado largo. Para el rack y la jaula, además una toma de ambiente: son las dos piezas de mayor ticket del catálogo y se venden mostrando la escala. | Son dos de las seis categorías de la home, y `racks-y-jaulas` concentra los dos productos más caros ($1.290.000 y $1.890.000). Un rack sin foto no se vende: el comprador de un club necesita ver la estructura antes de escribir por WhatsApp. | Las seis categorías quedan como están, no se achica ninguna. Los 4 productos apuntan a `assets/img/productos/sin-foto.svg`, un marcador que usa el mismo *image well* claro (`#E9E9E9`) que la card, así el hueco se lee como una decisión y no como una imagen rota. Se reemplaza archivo por archivo a medida que lleguen las fotos, sin tocar código. |
| 43 | **Rutas legales agregadas al router**: `/terminos` y `/arrepentimiento`. | El botón de arrepentimiento es obligatorio en todas las páginas (Res. 424/2020) y el pie tiene que enlazar a algún lado. | Las rutas existen y hoy muestran el andamio. Las páginas se maquetan cuando el cliente pase el texto legal (#10). |
| 44 | **Los recortes con transparencia quedaron solo en WebP** (`assets/img/productos/png/*.webp`). | Los PNG originales pesaban hasta 1,5 MB cada uno. | Cada recorte tiene su gemelo `.jpg` sobre blanco en la carpeta de arriba, que sirve de respaldo. Hoy los mocks usan el `.jpg`; los recortes quedan disponibles para el hero y el bento. |
| 48 | **¿Contra qué subtotal se mide el envío gratis?** Hoy el umbral de $150.000 se compara contra el subtotal **publicado** (precio Mercado Pago), no contra el de transferencia. Un carrito de **$170.000 publicado / $144.500 por transferencia** hoy tiene envío gratis: superó el umbral con un precio que el cliente no llega a pagar. Ni #8 ni #35 lo definen. | Toca la plata de verdad y aparece en la ficha, en el carrito y en el checkout. Es la clase de detalle que nadie discute hasta que un pedido sale con el envío mal cobrado. | **Se mantiene el comportamiento actual** (se mide contra el publicado) y queda marcado con `TODO(backend)` en `assets/js/carrito.js`. Las dos opciones:<br><br>**A · Contra el publicado (lo que hay hoy).** Más carritos califican, el envío gratis se siente más accesible y empuja el ticket. Costo: Rastro regala envíos en compras que facturan por debajo del umbral.<br>**B · Contra lo que efectivamente paga.** El umbral se cumple con plata real. Costo: pagar por transferencia —que es lo que a Rastro le conviene— te puede sacar el envío gratis, y eso es un mensaje contradictorio en el carrito.<br><br>Si sale B, el cambio es una línea en `calcularTotales()` y su equivalente en PHP cuando exista el checkout. |
| ~~45~~ | **Usuarios de prueba con contraseña conocida** en `data/users.json`: `demo@rastrofitness.com.ar` y `mayorista@rastrofitness.com.ar`, los dos con `rastro2026`. | Es un mock, pero viaja en el repo y se despliega. | Se guardan como hash bcrypt, no en texto plano. El backend dev tiene que borrar el archivo al conectar la base real. |

---

## Abiertos por la Fase 2 · navegación y Nosotros

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| ~~49~~ | **El panel admin necesita una novena sección: "Nosotros".** El contrato de datos se amplió con `repo_nosotros()`, que devuelve el contenido de esa página: encabezado, cuatro cifras, fundadores, hitos, obras, cómo trabajamos, garantía, dónde estamos y cierre. Las ocho pantallas diseñadas —Dashboard, Productos, Pedidos, Marcas, Clientes, Banners, Categorías y Configuración— no lo cubren. | Sin pantalla, el cliente no puede tocar la página que más va a querer editar: es la que manda por WhatsApp cuando en un club preguntan "¿quiénes son estos?". | **Una sola función, no tres.** `repo_fundadores()`, `repo_hitos()` y `repo_obras()` serían tres consultas y tres ABM para lo que el cliente piensa como una sola cosa. Del lado del panel es una sección con varios bloques de campos. Falta dibujar la pantalla: entra en la próxima tanda de diseño. |
| 50 | **El contenido de /nosotros lo tiene que cargar el cliente.** Falta: los años en el rubro, los gimnasios equipados y las provincias con envío (la cuarta cifra sale sola del catálogo); los años de los cuatro hitos; los roles de Santino Pantanali y Juan Pedro Ramognino; la foto de los dos; las obras; y la dirección del depósito. | Es una página de credibilidad: los huecos se notan más acá que en cualquier otro lado. | Cada hueco se dibuja **visible** con `[ DATO ]`, `[ AÑO ]`, `[ ROL ]` o `[ DIRECCIÓN ]` en bordeaux, para que en la revisión alguien pregunte el número. Una celda escondida no la reclama nadie. La página además muestra arriba un aviso de **copy provisorio** que se apaga poniendo `"provisorio": false` en `data/nosotros.json`. |
| 51 | **No hay obras cargadas** y esa es la prueba más fuerte de la página: tres gimnasios equipados, con nombre, ciudad, una línea de qué se hizo y una foto. Si el cliente solo puede dar una cosa, que sea esta. | Un club decide mirando lo que ya hiciste para otro club. | La sección `[ 05 ]` **no se dibuja vacía: se esconde entera**, y la numeración de secciones se recalcula sola sobre lo que se muestra. La variante con obras está escrita y probada: se activa cargando `obras.items` en `data/nosotros.json`, sin tocar código. Lo mismo con la foto de los fundadores: sin foto, el bloque colapsa a un párrafo firmado con los dos nombres. **No se ponen retratos de archivo.** |
| 52 | **Las miniaturas de producto las genera un script, no el panel.** `bin/optimizar-imagenes.sh` deja un WebP de 96 px en `assets/img/productos/miniaturas/` para el mega-menú, que dibuja hasta ocho fotos por categoría a 48 px de lado. | Sin miniatura, abrir el menú baja los archivos de 1000 px: cientos de KB para pintar estampillas. | `imagen_miniatura()` cae al original si el archivo no existe, así que nada se rompe. **TODO(backend):** cuando el panel permita subir fotos, el alta tiene que generar la miniatura además del WebP grande. |
| 53 | **El mega-menú resuelve seis consultas por página** —los ocho productos de cada una de las seis categorías— y se dibuja en todas las páginas del sitio. | Con los mocks no se nota; con MySQL y tráfico real, sí. | Queda anotado con `TODO(backend)` en `views/layout/header.php`. Se resuelve con una sola consulta que traiga los N primeros de cada categoría, o cacheando el bloque. No cambia el marcado. |
| 54 | **Sin JavaScript no abre el mega-menú de escritorio con teclado ni con clic.** El disparador es un `<button>` por decisión de diseño (elimina el bug del primer toque en táctil) y un botón sin JS no hace nada. | Es un caso de borde: el carrito entero vive en `localStorage`, así que sin JS tampoco se puede comprar. | `assets/css/sin-js.css`, cargado desde `<noscript>`, deja el cajón de celular estático —si no, un celular sin JS se queda sin ningún menú— y en escritorio abre el panel con el mouse y con el foco. En ese modo el `aria-expanded` queda en `false` y es una imprecisión conocida: no hay quién lo actualice. Lo que **sí** se corrigió es el `role="dialog" aria-modal`: no viene del marcado, lo pone `nav.js` al arrancar. Un `aria-expanded` desactualizado molesta sobre un botón; un `aria-modal` sobre un bloque siempre visible le esconde la página entera a un lector de pantalla. Todos los enlaces del menú están en el marcado siempre, así que un rastreador los ve igual. |

---

## Abiertos por el panel y la base

El 27/08/2026 cambió el alcance: el panel de administración y los pagos pasaron
a ser nuestros. El catálogo se mudó a MySQL y el panel quedó entero.

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| 66 | **En Hostinger todavía no hay base de datos.** El código nuevo no arranca sin `app/config.php` con credenciales de MySQL. | Es lo único que separa el panel de estar en producción. Sin esto, desplegar tira el sitio: 500 en todas las páginas. | El trabajo vive en la rama `feat/panel-admin` y `main` sigue igual que producción, así que un push accidental no rompe nada. Los cuatro pasos —crear base, subir config, correr el esquema, migrar y crear el admin— están en `docs/DEPLOY.md`. |
| 67 | **Falta el webhook de Mercado Pago**, y con él la decisión Checkout Pro o Bricks (#13). | Es lo único grande que queda del proyecto. | El botón del carrito está puesto y lo dice en pantalla. Cuando se integre, hay que sumar los orígenes de MP a la CSP del `.htaccess`: si no, Bricks no carga y **no hay ningún error visible**, el navegador lo bloquea en silencio. |
| 68 | **El alta de producto no genera la miniatura de 96 px** del mega-menú. Hoy la hace `bin/optimizar-imagenes.sh`, que se corre a mano. | Sin miniatura, abrir el mega-menú baja los archivos de 1000 px: cientos de KB para dibujar estampillas de 48. | `imagen_miniatura()` cae al original, así que no se rompe nada: pesa más. Se resuelve generando el WebP de 96 en `subir_imagen()`. |
| 69 | **No hay envío de correo.** Sin eso no se puede recuperar una contraseña, ni avisar de un pedido nuevo, ni mandar el formulario mayorista ni la constancia del botón de arrepentimiento. | Son cuatro pendientes distintos que en realidad son uno solo. | Hostinger da SMTP con el hosting. Falta elegir si se usa `mail()` o SMTP autenticado, y crear la casilla. |
| 70 | **El detalle de pedido de `/cuenta` no está construido**, aunque ya se puede. | Era el pendiente de seguridad más viejo del proyecto. | `repo_order()` ahora acepta el id del dueño como segundo argumento y la cuenta lo pasa siempre, así que la pantalla ya se puede hacer sin exponer los pedidos de otro. Falta sólo dibujarla. |
| 71 | **La pantalla de Nosotros del panel se programó sin frame.** Era el #49. | Es la única de las nueve que no tiene diseño previo. | Se resolvió con el mismo lenguaje que las otras ocho —bloques de campos, misma tipografía, mismos avisos—. Falta dibujar el frame en Figma para que el archivo quede completo, pero el código no debería cambiar. |

---

## Abiertos por el cierre de la Fase 2

Salieron de maquetar las once páginas contra los frames. Ninguno frena nada:
el sitio funciona con la decisión provisoria que está en la última columna.

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| 59 | **La numeración de secciones de la home no cierra en Figma.** La franja de Nosotros dice `[ 07 ]`, número que ya usa Medios de pago, y Mayoristas dice `[ 10 ]`. | Los índices son una de las ocho reglas del lenguaje visual. Repetido o salteado, el recurso deja de leerse como sistema y pasa a leerse como error. | **El front va correlativo**: 05 Categorías, 06 Destacados, 07 Medios de pago, 08 Confían, 09 Vendedores, 10 Quiénes somos, 11 Mayoristas. Falta pasarle la corrección a los dos frames de Figma. |
| 60 | **La sexta categoría no entra en el bento de la home.** El frame tiene seis celdas: cinco categorías más "Ver todo". Hoy hay seis categorías cargadas. | Accesorios es la categoría con más productos del catálogo y en la home no aparece. Se llega igual por el mega-menú y por el catálogo, pero no desde el bloque que existe para eso. | Se dibujan las cinco primeras, como el frame. Las dos salidas son: sumar una fila al bento (rompe la asimetría, que es lo que le da carácter) o aceptar que el bento es una selección y no un índice. **Lo decide el cliente**, es su catálogo. |
| 61 | **Las tres placas de promoción del hero no existen en el repo.** El frame las muestra superpuestas a la derecha del titular: son piezas de diseño del cliente, del estilo de las que publica en Instagram. | Es media pantalla del hero. | La vista dibuja el collage **a partir del segundo banner de posición `hero`**. Con uno solo no lo dibuja, porque ese banner ya es la foto de fondo y repetirlo pone la misma imagen dos veces en la misma pantalla. Cuando el cliente cargue las placas desde el panel, aparecen solas. Hay que pedírselas en 422 × 531 y 303 × 381. |
| 62 | **El gris del titular del hero (`#d9d9d9`) no es un token.** Es una capa de color suelta de Figma, sin variable asociada. | Es el color del elemento más grande de la home y hoy vive como valor crudo en `home.css`. Si mañana se ajusta, se ajusta en dos lados. | Declarado una sola vez, como `--hero-titular` en `.pagina-home`. Cae entre `plata/1` (`#f2f2f2`) y `plata/2` (`#c4c4c4`): o se lo suma a la colección como `plata/0`, o se usa uno de los dos que ya existen. |
| 63 | **Los filtros del catálogo son de selección única, y el frame dibuja casillas.** Una casilla promete poder marcar tres categorías a la vez; `repo_products()` acepta una. | Es la diferencia entre un control que hace lo que dice y uno que no. | Van como **enlaces** que arman la URL con el filtro puesto o sacado: se ven igual que el frame, andan sin JavaScript y la URL queda compartible. Pasar a múltiple es un cambio de contrato —`categoria` y `marca` aceptando arrays— y hay que hacerlo antes de que el catálogo crezca. |
| 64 | **En Figma quedó `display/hero` duplicado** y los nodos del titular están desvinculados de sus estilos. | El archivo se vende como "31 estilos de texto y Dev Mode devuelve el nombre real de la variable". Un estilo repetido y seis nodos sueltos rompen justamente eso. | El CSS ya está escrito con los valores que están **en el canvas**, que es lo que se ve. Falta un pase de limpieza en Figma: borrar el duplicado y volver a aplicar los estilos a los seis nodos del titular. |
| 65 | **"A pedido" no es lo mismo que "sin stock"**, y el catálogo sólo sabe lo segundo. El frame de filtros ofrece las dos opciones. | Un producto que no está en depósito pero se consigue en dos semanas es vendible; uno discontinuado, no. Hoy los dos se ven igual. | El filtro ofrece nada más que "En stock". Sumar "A pedido" es un campo nuevo en el producto, no un filtro nuevo: hay que definir primero si el negocio lo distingue. |

---

## Abiertos por el hero v3

El cliente mandó una referencia el 26/08/2026 y pidió cambiar sólo el bloque de texto
del hero. Se hizo en Figma, escritorio y celular. Lo que quedó colgando:

| # | Tema | Por qué importa | Decisión provisoria |
|---|---|---|---|
| ~~55~~ | ~~Saira Condensed Black no está self-hosteada~~ → resuelto el 2026-08-26, ver abajo. |
| 56 | **`MARCA EL CAMINO.` va en bordeaux `#780606` sobre negro: contraste ≈ 2:1.** | Es el claim de marca del hero. La referencia del cliente lo tiene así, y el titular anterior (`QUE AGUANTA`) también, pero ahí lo salvaba un contorno blanco que ahora no está. | **Se mantiene el rojo de la referencia.** Es texto decorativo y el mensaje no depende de él. Si el cliente lo quiere más legible, la salida es `bordeaux/700` (`#8f0808`) o sumarle el contorno. Confirmar viéndolo en pantalla, no en captura. |
| 57 | **Se perdió el ojal "Vendedores oficiales · Argentina".** La referencia pone `EQUIPAMIENTO` en ese lugar. | Era una línea de credibilidad arriba de todo. | El dato sigue en el marquee de la barra superior y en la sección `[ 09 ] Vendedores oficiales`. Si el cliente lo reclama en el hero, entra como tercera línea de la barra de datos, no arriba del titular. |
| 58 | **El botón secundario del hero quedó con el relleno del sistema** (blanco 10%), no con el contorno de 1 px de la referencia. | Cambiarlo acá desincroniza el hero del componente `Botón`, que usa el mismo estilo en todo el sitio. | Se deja el del sistema. Si se quiere el contorno, se cambia en el componente y baja a las nueve pantallas de una. |

---

## Resueltos

| Fecha | Tema | Decisión |
|---|---|---|
| 2026-08-27 | **El catálogo pasó a MySQL** | **Resuelto sin tocar una sola vista.** Era la apuesta del contrato desde el día uno: cambió el cuerpo de las 17 funciones `repo_*`, no su firma ni la forma de lo que devuelven. Verificado contra la base: los filtros dan 6 discos, 18 de línea propia y 11 Greencore —que suman 29—, y la ficha trae sus 7 especificaciones. De paso mejoraron dos cosas: las imágenes y especificaciones de un listado se traen en 2 consultas y no en 2 por producto, y `productos_count` sale de un `LEFT JOIN` agrupado. |
| 2026-08-27 | #16, #17, #18 Credenciales, tablas y MySQL remoto | **Ya no aplican como estaban escritos**: eran instrucciones para un backend dev externo que ya no existe. `db/esquema.sql` tiene las 13 tablas y `docs/DEPLOY.md` el procedimiento de credenciales y de acceso remoto. |
| 2026-08-27 | **Sesión, login y roles** | **Resuelto.** `session_start()` endurecido, regeneración de id, CSRF en todos los POST, límite de intentos por correo y por IP, y rol `admin` en la misma tabla de usuarios. Cerrar sesión es POST y devuelve 405 a un GET. En la sesión se guarda sólo el id: el rol se relee de la base en cada request, para que bajarle los permisos a alguien tenga efecto sin esperar a que cierre sesión. |
| 2026-08-27 | **El límite de intentos no bloqueaba nunca** | **Bug encontrado al probarlo.** `momento` lo escribía MySQL con su reloj y la ventana la calculaba PHP con el suyo: en la máquina de desarrollo PHP corre en UTC y MySQL en hora local, tres horas de diferencia, y la condición no se cumplía jamás. El contador devolvía siempre 0, en silencio. Ahora la ventana la calcula MySQL con `NOW()`. Verificado: ocho intentos pasan, el noveno bloquea y sigue bloqueado aunque después venga la contraseña correcta. |
| 2026-08-27 | **El panel de administración** | **Las nueve secciones, funcionando.** Dashboard, Productos, Pedidos, Categorías, Marcas, Clientes, Banners, Nosotros y Configuración. Usa los mismos 24 tokens del sitio en su variante Admin: no hay un segundo sistema de diseño, hay dos modos del mismo. El guard vive en el layout, así que ninguna pantalla puede olvidarse de proteger: se protege por existir. |
| 2026-08-27 | Cinco pantallas del panel no podían redirigir después de un POST | **Bug encontrado al probarlo.** Imprimían la cabecera antes de manejar el POST, así que el `header('Location')` del final no salía: los datos se guardaban pero la persona se quedaba en la misma página y recargar reenviaba el formulario. El guard se separó del layout en `_guard.php` y ahora las cinco corren el guard, manejan el POST y recién después imprimen. Es el mismo error que hacía que `/admin/productos/9999` devolviera 200 con dos páginas pegadas. |
| 2026-08-27 | #49 La novena sección del panel, "Nosotros" | **Programada.** Se hizo sin frame, con el mismo lenguaje que las otras ocho. Queda abierto dibujar el frame (#71), pero el código no debería cambiar. |
| 2026-08-27 | #45 `data/users.json` con hashes viajaba al servidor | **Resuelto.** Desde que el catálogo vive en la base, `data/` y `db/` quedaron excluidos del deploy: son semilla histórica y no tienen por qué estar en `public_html`. |
| 2026-08-26 | #55 Saira Condensed Black self-hosteada | **Resuelto, y sin depender del cliente.** WOFF2 subconjunto latin de Fontsource, 18 KB, OFL. Desbloquea el maquetado del hero. Se precarga **sólo en la home**, con `$precargar_titular` en `layout/head.php`: es la única página con titular, y bajarla en el catálogo sería pagar por una fuente que esa página no dibuja. `.t-display-hero` estaba declarada en `tokens.css` y sin usar, así que se reutilizó en vez de inventar un nombre nuevo. |
| 2026-08-26 | **Fase 2 cerrada.** Las doce rutas del router tienen su vista | **Resuelto.** Home, catálogo, ficha, carrito, mayoristas, nosotros, ingresar, registro, mi cuenta, términos, arrepentimiento y 404. Todo el contenido sale de `repository.php`. Se borró el andamio `views/partials/en-construccion.php` y su bloque en `layout.css`: ya no hay ruta que caiga ahí. Cuando falta un archivo de vista, `index.php` devuelve 500 con log —es un despliegue a medias, no una página que no existe— en vez de un 404 bonito que esconde el problema. |
| 2026-08-26 | #46 `docs/DATA-CONTRACT.md` y `docs/HANDOFF.md` | **Escritos.** El contrato documenta las 17 funciones del repository, la forma de cada respuesta, las convenciones que el backend tiene que sostener y una estructura de tablas sugerida. El handoff ordena lo que falta por lo que desbloquea a lo demás, y arranca por la sesión, que es lo que traba login, cuenta y checkout. |
| 2026-08-26 | #47 `views/errors/404.php` | **Entró.** `router_404()` ya no necesita el andamio como respaldo. La página muestra además el código y la ruta, que es lo primero que mira alguien que llegó desde un enlace roto y quiere avisar cuál era. |
| 2026-08-26 | Selección múltiple en los filtros del catálogo | **Se difiere y se documenta (#63).** El frame dibuja casillas; `repo_products()` acepta una categoría y una marca. Se implementaron como enlaces en vez de poner una casilla que promete lo que no puede cumplir. |
| 2026-08-26 | El filtro de marca escondía dos tercios del catálogo | **Resuelto con un parámetro, no con una función nueva.** `repo_brands()` filtraba la línea propia porque alimenta la franja "vendedores oficiales" de la home, donde Rastro no va. Pero 19 de los 30 productos son de línea propia, así que el filtro del catálogo no los encontraba. Ahora acepta `$incluir_propias`, `false` por defecto: ninguna llamada existente cambia de resultado. |
| 2026-08-26 | Dónde vivían las clases de formulario | **Resuelto.** `.formulario*` estaba en `mayoristas.css` y `.campo` en `catalogo.css`; las páginas de cuenta y de legales no cargan esas hojas y salían con la etiqueta y el campo en la misma línea. Pasaron a `componentes.css`, junto con `.nota` y `.marcador`. Las usan cinco páginas. |
| 2026-08-26 | Licencia de `Urban Thunder Demo`, que bloqueaba el maquetado del hero | **Resuelto por el camino largo: la fuente ya no se usa.** El hero v3 que pidió el cliente cambia el titular por Saira Condensed Black. Se verificaron las 17 páginas del archivo de Figma y no queda un solo nodo de texto con esa familia. La anotación vieja del titular quedó reescrita con las decisiones nuevas. Sigue abierta la licencia de Eurostile (#4), que es otra cosa. |
| 2026-08-26 | Cómo se resuelve la textura de metal del titular | **Relleno de imagen, no degradé.** El cliente mandó una referencia con chapa fotográfica. Se generó `assets/img/texturas/acero-cepillado.jpg` (1200 × 147, 30 KB) y se aplica como relleno `IMAGE` en modo Recortar con matriz identidad, así el archivo se estira al alto de cada línea. El degradé plata sigue reservado a precios y cifras (`CLAUDE.md` §5.6). |
| 2026-08-25 | Observación 3 del QA: el orden de foco no coincidía con el orden visual de la cabecera abajo de 1280 | **Resuelto con la cabecera nueva.** Quedó abierta a propósito porque la cabecera se rehacía. Ahora los elementos están en el marcado en el mismo orden en el que se ven y lo que no corresponde a un ancho se apaga con `display:none`, que además lo saca del árbol de accesibilidad. Ninguna regla de CSS reordena la barra. El buscador de celular tampoco empuja una segunda fila: se despliega y se queda con la barra entera. |
| 2026-08-20 | Paleta verde (el brandbook traía `#00674F` y `#2E6F40`) | **Descartado.** El cliente no quiere verde. La paleta es negro + plata/grises + bordeaux, con bordeaux como único acento. Ver `CLAUDE.md` §5.3. |
| 2026-08-23 | Dirección de diseño: clásica (v1) o ficha técnica (v2) | **v2.** Canto vivo, retícula de hairlines, titular a 80 px, datos técnicos por producto y JetBrains Mono para los números. Foundations y los 5 componentes ya están migrados. Ver `CLAUDE.md` §5.6. |
| 2026-08-23 | #30 Destacados en mobile a 1 columna | Confirmado en la v2: una columna a sangre, cards separadas por hairline. Con el bloque de precio nuevo entra cómodo. |
| 2026-08-25 | #19 y #20 Peso de las imágenes | **Resuelto.** `bin/optimizar-imagenes.sh` deja las fotos de producto en 1000 px de lado largo, las de ambiente en 1600 y la marca en 600, y genera un `.webp` al lado de cada archivo. Los PNG con transparencia quedan solo en WebP. De 29 MB a 9,4 MB, con el archivo más pesado en 298 KB. Los originales se guardan en `.originales-img/`, fuera del repo. |
| 2026-08-25 | #41 ¿Rastro va en la franja de "vendedores oficiales"? | **No.** Esa franja dice "somos vendedores oficiales de estas marcas": son marcas de terceros, y la línea propia ahí le saca el sentido a la frase. La marca queda en `brands.json` con `es_propia: true` para que los 18 productos de línea propia resuelvan su nombre, pero `repo_brands()` la filtra y no la devuelve. El nombre de la marca de un producto viene en el propio producto, en `marca_nombre`, que el repository resuelve contra la tabla completa. Sin funciones nuevas en el contrato. |
| 2026-08-25 | #42 Falta un Michroma chico en la escala | **Resuelto.** Se creó `display/xs` en Figma (Michroma 10/14, tracking 1.2, mayúsculas) y `.t-display-xs` en `tokens.css`. El marquee usa la clase y ya no hay ningún override de familia en CSS. |
| 2026-08-25 | #4 Tipografías self-hosted | **Parcial.** Michroma, Saira y JetBrains Mono se sirven desde `assets/fonts/` en WOFF2, subconjunto latin, 88 KB en total. Sigue abierta la licencia de Eurostile. Ver `assets/fonts/LICENCIAS.txt`. |
