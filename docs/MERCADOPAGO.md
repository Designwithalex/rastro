# Mercado Pago — investigación e integración de prueba

Estado al **27/08/2026**. Integración funcionando de punta a punta en ambiente
de prueba. Lo que falta para cobrar de verdad está en la última sección.

---

## 1. Qué se decidió y por qué

`PENDIENTES.md` #13 dejaba abierta la pregunta: **Checkout Pro (redirección) o
Bricks (embebido)**. Se implementó **Checkout Pro**. Las razones, en orden de
peso para este proyecto:

| | Checkout Pro | Bricks |
|---|---|---|
| Dónde se cargan los datos de la tarjeta | En el sitio de Mercado Pago | En el sitio de Rastro |
| Alcance de PCI | Ninguno: los datos de tarjeta nunca tocan el servidor | SAQ A-EP: hay que responder por el JS de la página de pago |
| JavaScript de terceros | **Ninguno** | El SDK de Mercado Pago |
| Cambios en el CSP del `.htaccess` | **Ninguno** | `script-src`, `connect-src` y `frame-src` |
| Medios de pago | Todos los de la cuenta, sin trabajo extra | Hay que habilitar y maquetar cada uno |
| Control del diseño | Se sale del sitio | Se queda adentro |

Las tres primeras filas son las que decidieron. El sitio tiene una
Content-Security-Policy cerrada (`default-src 'self'`, sin `'unsafe-inline'`) y
no carga un solo script de terceros; Bricks obligaba a abrirla para el único
formulario donde eso importa de verdad. Además, el equipo que sigue el proyecto
es un backend dev solo: cuanta menos superficie propia toque una tarjeta, mejor.

El costo aceptado es real y hay que decirlo: **la persona sale del sitio para
pagar**. Se pierde algo de conversión y se pierde el control visual de esa
pantalla. La contrapartida es que la marca de Mercado Pago en el momento del
pago genera confianza en el mercado argentino, que era justamente el argumento
del cliente para pedirlo.

**Si más adelante se quiere probar Bricks**, lo que hay que tocar está acotado:
`app/mercadopago.php` ya crea la preferencia (Bricks usa la misma), hay que
sumar el SDK del navegador, cambiar la vista de `/checkout` y abrir el CSP.
El resto —pedido, webhook, estados— no cambia.

### Lo que NO se hizo

- **Suscripciones / pagos recurrentes.** No hay caso de uso.
- **Marketplace / split de pagos.** Rastro cobra todo.
- **Checkout API (transparente).** Es Bricks sin el SDK visual: mismo alcance
  de PCI y más trabajo.
- **SDK oficial de PHP (`mercadopago/dx-php`).** Necesita Composer y `vendor/`.
  El proyecto no tiene build step (`CLAUDE.md` §3) y son tres endpoints. Se
  escribió un cliente de 400 líneas con cURL, con fallback a streams.

---

## 2. Cómo funciona, de punta a punta

```
  /carrito              localStorage: [{id, cantidad}]
      │
      ▼  "Finalizar compra"
  /checkout             el JS pinta el resumen y llena el campo oculto `items`
      │
      ▼  POST a sí misma
  checkout_lineas()     ← resuelve PRECIOS DE SERVIDOR contra el repository
  checkout_totales()    ← precio publicado si es MP, con descuento si es transferencia
  repo_order_create()   ← el pedido queda guardado ANTES de mandar a nadie a pagar
      │
      ├── medio = transferencia ──► /checkout/retorno?estado=transferencia
      │                             (no pasa por ninguna pasarela)
      │
      └── medio = mercado_pago
             │
             ▼  POST /checkout/preferences
          mp_crear_preferencia()  ──►  init_point
             │
             ▼  303 See Other
        ┌──────────────────────────────────┐
        │  mercadopago.com.ar (fuera)      │
        └──────────────────────────────────┘
             │                    │
             │ back_url           │ notification_url
             ▼                    ▼
     /checkout/retorno     /webhooks/mercadopago
             │                    │
             └────────┬───────────┘
                      ▼
          checkout_sincronizar_pago()
             GET /v1/payments/{id}   ← LA fuente de verdad
             repo_order_update()
```

Los dos caminos de vuelta llaman a la misma función y esa función es
idempotente. No importa cuál llegue primero, ni cuántas veces llegue cada uno.

### Los archivos

| Archivo | Qué hace |
|---|---|
| `app/mercadopago.php` | Habla HTTP con la API. No sabe qué es un producto. |
| `app/checkout.php` | Traduce carrito ↔ pedido ↔ preferencia. Calcula la plata. |
| `app/repository.php` | Guarda y lee pedidos (sección "Pedidos del checkout"). |
| `views/checkout/index.php` | El formulario. Recibe su propio POST. |
| `views/checkout/retorno.php` | La página de vuelta, para los cuatro finales. |
| `views/webhooks/mercadopago.php` | El endpoint de notificaciones. No es una página. |
| `assets/js/checkout.js` | Pinta el resumen y llena el campo `items`. |
| `assets/js/checkout-retorno.js` | Vacía el carrito, sólo si corresponde. |
| `bin/mp-probar.php` | Comprobación por consola. Ver §5. |

---

## 3. Las tres reglas que sostienen la integración

Están escritas en el código, en el archivo donde manda cada una. Se repiten acá
porque son las que hay que releer antes de tocar nada.

### 3.1 El precio lo pone el servidor

Del navegador salen **`id` y `cantidad`, y nada más**. Los precios se resuelven
de nuevo contra el repository en `checkout_lineas()`.

Confiar en un precio que viene del cliente es el agujero clásico de un checkout
casero: con la consola abierta, cualquiera compra una barra olímpica a un peso.
Hay una prueba que lo verifica: se manda una línea con `precio_unitario: 1` y el
pedido sale igual al precio de catálogo.

### 3.2 El precio publicado es el precio de Mercado Pago

Regla de negocio de `CLAUDE.md` §1, traducida a código en `checkout_medios()`:

- **Mercado Pago** → precio **publicado**, `descuento_aplicado_pct: 0`.
- **Transferencia** → precio **con descuento**, y no pasa por ninguna pasarela.

Cobrar el precio con descuento por Mercado Pago sería regalar el porcentaje que
justamente cubre la comisión de la pasarela.

El descuento lo sigue calculando `precio_con_descuento()` en `helpers.php` y
nadie más (`CLAUDE.md` §4.3). `checkout.php` elige cuál de los dos números usar;
no multiplica ningún porcentaje.

### 3.3 El estado del pago se pregunta, no se lee de la URL

Mercado Pago vuelve al sitio con `?status=approved&payment_id=123`. Esa URL la
escribe el navegador de quien compra: cambiar `rejected` por `approved` en la
barra de direcciones lo hace cualquiera.

Lo que se usa de la URL es **el `payment_id`**, que es un identificador. El
estado se consulta con `GET /v1/payments/{id}` usando el access token, que sólo
tiene el servidor.

Y hay un paso más: se comprueba que el `external_reference` de ese pago sea el
del pedido que se está mirando. Sin eso, pegar en la URL el id de un pago
aprobado ajeno alcanzaría para marcar el pedido propio como pagado.

---

## 4. Poner a andar el ambiente de prueba

### 4.1 Crear la aplicación

1. Entrar a <https://www.mercadopago.com.ar/developers/panel> con la cuenta de
   Mercado Pago de Rastro.
2. **Tus integraciones → Crear aplicación.**
3. Producto: **Pagos online → Checkout Pro**. Modelo: **No, uso mi propia
   plataforma** (no es una integración certificada de un e-commerce enlatado).

### 4.2 Copiar las credenciales de prueba

**Tus integraciones → (la aplicación) → Credenciales de prueba.**

Se copian dos valores: **Public Key** y **Access Token**.

> **Ojo con esto.** Desde 2025 las credenciales de prueba se generan solas al
> crear la aplicación y **empiezan con `APP_USR`, igual que las de producción**.
> Ya no hay un prefijo `TEST-` que las distinga. Lo único que las separa es la
> solapa de la que se copiaron. Por eso `app/config.php` tiene `mp_modo`: para
> dejar escrito con cuál juego se cargó, y que el sitio pueda mostrar el cartel
> de ambiente de prueba.

### 4.3 Escribir `app/config.php`

```bash
cp app/config.example.php app/config.php
```

Y completar:

```php
'base_url'            => 'https://rastrofitness.com',
'mp_modo'             => 'test',
'mp_access_token'     => 'APP_USR-...',   // de la solapa de PRUEBA
'mp_public_key'       => 'APP_USR-...',   // de la solapa de PRUEBA
'mp_webhook_secret'   => '',              // se completa en 4.5
'mp_notification_url' => '',              // vacío salvo que uses un túnel
```

`app/config.php` está en `.gitignore` y **no se sincroniza por FTP**: vive sólo
en el servidor y en la máquina de cada dev.

> **`base_url` no puede ser `localhost` ni una IP.** Mercado Pago rechaza la
> preferencia: las `back_urls` necesitan un dominio con DNS. Es la causa número
> uno del error `invalid back_urls`. Ver §7.

### 4.4 Crear una cuenta compradora de prueba

**Tus integraciones → (la aplicación) → Cuentas de prueba → Crear cuenta**, tipo
**comprador**, país Argentina, con un saldo cualquiera.

Da un usuario y una contraseña. Sirven para iniciar sesión dentro del checkout y
probar el pago con dinero en cuenta. Para pagar con tarjeta de prueba **no hace
falta**: se puede pagar como invitado.

Se pueden tener hasta 10 y caducan a los 60 días sin uso.

### 4.5 Dar de alta el webhook

**Tus integraciones → (la aplicación) → Webhooks → Configurar notificaciones.**

- **Modo:** Prueba (hay una solapa por modo; la clave secreta es distinta en
  cada una).
- **URL:** `https://rastrofitness.com/webhooks/mercadopago`
- **Eventos:** marcar **Pagos** (`payment`). Nada más, por ahora.
- Guardar y apretar **"Revelar clave secreta"**.

Esa clave va en `mp_webhook_secret`. **Sin ella el endpoint rechaza todas las
notificaciones con un 401**, que es el comportamiento correcto: sin firma no hay
forma de saber si el aviso lo mandó Mercado Pago o cualquier otro.

---

## 5. Probar

### 5.1 Por consola, antes que nada

```bash
php bin/mp-probar.php
```

Contesta las cuatro preguntas que uno se hace cuando algo no anda:

1. ¿Este PHP puede hablar con la API? (cURL o streams, TLS, salida a red)
2. ¿El access token sirve y de qué cuenta es?
3. ¿Se puede crear una preferencia con los datos reales del catálogo?
4. ¿La validación de la firma del webhook está bien implementada?

Imprime un `init_point` para abrir en el navegador. **No cobra nada**: una
preferencia es una intención de cobro, no un pago. Aun así se planta si detecta
`mp_modo => 'produccion'`, porque una preferencia real ensucia los reportes de
la cuenta del cliente.

Conviene correrlo **también por SSH en Hostinger**: ahí aparecen los problemas
que en local no se ven (un PHP sin cURL, un firewall de salida, un certificado
viejo).

### 5.2 Las tarjetas de prueba

Argentina, todas con vencimiento **11/30**:

| Tipo | Marca | Número | CVV |
|---|---|---|---|
| Crédito | Mastercard | `5031 7557 3453 0604` | 123 |
| Crédito | Visa | `4509 9535 6623 3704` | 123 |
| Crédito | Amex | `3711 803032 57522` | 1234 |
| Débito | Mastercard | `5287 3383 1025 3304` | 123 |
| Débito | Visa | `4002 7686 9439 5619` | 123 |

**El resultado lo decide el NOMBRE DEL TITULAR**, no el número de tarjeta. Se
escribe el código en el campo del nombre, con DNI `12345678`:

| Titular | Qué pasa |
|---|---|
| `APRO` | Aprobado |
| `CONT` | Queda pendiente |
| `OTHE` | Rechazado por error general |
| `CALL` | Rechazado: hay que autorizar con el banco |
| `FUND` | Rechazado por fondos insuficientes |
| `SECU` | Rechazado por código de seguridad inválido |
| `EXPI` | Rechazado por fecha de vencimiento |
| `FORM` | Rechazado por error de formulario |

Los cuatro primeros son los que hay que probar sí o sí: son las cuatro pantallas
distintas que puede ver un comprador.

### 5.3 Recorrido completo

1. Agregar dos o tres productos al carrito.
2. `/carrito` → **Finalizar compra**.
3. Completar los datos, elegir **Mercado Pago**, **Ir a pagar**.
4. En Mercado Pago: pagar con tarjeta, titular `APRO`, DNI `12345678`.
5. Vuelve solo a `/checkout/retorno` (por `auto_return`, hasta 40 segundos).
6. Comprobar:
   - la página dice **"Listo, recibimos tu pago"**;
   - el carrito quedó vacío;
   - en `data/pedidos.json` el pedido está en `estado: "pagado"` con su
     `pago.payment_id`;
   - en `data/mp-eventos.log` están `preferencia.creada`, `webhook.procesado` y
     `pago.sincronizado`.
7. Repetir con titular `OTHE`. Ahí el carrito **no** se vacía —a propósito, para
   poder reintentar— y la página explica el motivo concreto del rechazo.
8. Repetir eligiendo **Transferencia**: no sale del sitio, el pedido queda en
   `pendiente_transferencia` y el total lleva el descuento aplicado.

### 5.4 Probar el webhook

**Con el simulador de Mercado Pago** (lo más parecido a la realidad):
Tus integraciones → Webhooks → **Simular**. Se elige el evento `payment`, se
pega un id de pago de prueba y se dispara. Manda una notificación **firmada de
verdad**, así que ejercita la validación completa.

**Contra una máquina de escritorio**, con un túnel:

```bash
cloudflared tunnel --url http://localhost:8000
# o: ngrok http 8000
```

Se copia la URL pública a `mp_notification_url` (`…/webhooks/mercadopago`) y
también a `base_url`, porque las `back_urls` tienen el mismo problema con
`localhost`.

**Qué contesta el endpoint y por qué:**

| Situación | Código | Por qué |
|---|---|---|
| Todo bien | `200` | Procesado. |
| Firma inválida, vieja o ausente | `401` | Queda registrado de los dos lados. |
| Tipo que no atendemos | `200` | No queremos que reintente ocho veces al pedo. |
| Pago inexistente (404 de la API) | `200` | No va a existir en seis horas. |
| No se pudo consultar o guardar | `500` | El reintento de Mercado Pago es lo que queremos. |
| `GET` desde un navegador | `405` | No es una página. |

Mercado Pago espera 22 segundos y reintenta enseguida, a los 15 y 30 minutos, y
a las 6, 48 y 96 horas.

---

## 6. Seguridad — qué se hizo

| | |
|---|---|
| El access token no sale del servidor | Vive en `app/config.php`, que está en `.gitignore` y no se sincroniza por FTP. No se imprime, no se loguea, no viaja al navegador. |
| Los precios los pone el servidor | §3.1. |
| El estado del pago se consulta por API | §3.3. |
| El pago se cruza contra el pedido | Se compara `external_reference`; si no coincide, se descarta. |
| Las notificaciones se validan con HMAC-SHA256 | `mp_firma_valida()`. Comparación en tiempo constante con `hash_equals()`. |
| Los replay se rechazan | Se exige que el `ts` de la firma esté dentro de 5 minutos. La documentación no lo pide; sin eso, una firma capturada vale para siempre. |
| Doble clic / doble POST | `X-Idempotency-Key` atada al código del pedido, más el bloqueo del botón en el navegador. |
| Los códigos de pedido no son adivinables | `RF-2026-4F7A`: cuatro caracteres al azar de un alfabeto sin `0/O` ni `1/I`. Los del mock eran `RF-año-ddmm`, enumerables con la fecha. |
| La página de retorno no filtra datos personales | Muestra código, líneas, total y estado. **No** muestra dirección, teléfono ni apellido, porque el código viaja en la URL y todavía no hay sesión que lo ate a nadie. |
| Los datos de compradores no van al repo | `data/pedidos.json` y `data/mp-eventos.log` están en `.gitignore`, en una carpeta con `Require all denied`, y el `.htaccess` de la raíz niega `.json` y `.log` en cualquier ubicación. |
| El CSP no se tocó | Checkout Pro no necesita ninguna excepción: es una redirección del servidor, no un script de terceros. |

### Lo que sigue abierto

- **No hay token CSRF.** Hace falta sesión y el proyecto todavía no la tiene
  (`docs/HANDOFF.md` la pone primera en la lista). Mientras tanto,
  `checkout_mismo_origen()` compara `Origin`/`Referer`, que frena el caso simple.
  Lo que sostiene el monto es la validación de precios del servidor, no esto.
- **El pedido no se ata a un usuario.** `usuario_id` queda en `0`.
- **El stock no se reserva.** Se valida al armar el pedido y no se toca. Dos
  personas pueden comprar la última unidad con segundos de diferencia.

---

## 7. Cuando algo falla

| Síntoma | Causa casi segura | Qué hacer |
|---|---|---|
| `invalid back_urls` al crear la preferencia | `base_url` es `localhost`, una IP, o está vacía | Poner un dominio con DNS. Para probar en local, un túnel (§5.4). |
| `auto_return invalid` | `back_urls.success` vacía | Sale de `base_url`: revisar que esté cargada. |
| `401 Must provide your access_token` | Token vacío, mal copiado o con espacios | Copiarlo entero, sin saltos de línea. |
| `403 At least one policy returned UNAUTHORIZED` | Token mal formado o de otra aplicación | Volver a copiarlo de la solapa correcta. |
| El webhook siempre contesta `401` | Falta `mp_webhook_secret`, o es el de la otra solapa (prueba vs. producción) | La clave secreta es distinta en cada modo. |
| El webhook contesta `401` sólo en producción | Se configuró el webhook en un modo y se prueba en el otro | Revisar la solapa. |
| Llega la notificación pero dice `sin pedido` | El `external_reference` no está en `data/pedidos.json` | Suele ser un pago de otro servidor o de otra corrida de pruebas. |
| El pedido no cambia de estado | `data/pedidos.json` no es escribible | `chmod` de la carpeta `data/` en el servidor. En el log aparece `no es escribible`. |
| `Este PHP no tiene cURL ni allow_url_fopen` | El PHP del hosting está pelado | Cambiar la versión de PHP en hPanel. |
| Vuelve a `/checkout/retorno` y dice "no tenemos el pago" | No llegó `payment_id` ni por URL ni por webhook | Si el webhook no está configurado, este es el síntoma. |

El log de `data/mp-eventos.log` tiene una línea por evento con `cuando`, `que`,
el pedido y el id del pago. Es lo primero que hay que mirar. No guarda el token
ni los datos de la tarjeta.

---

## 8. Lo que falta para cobrar de verdad

En orden de lo que desbloquea a lo demás. **Todo esto es del backend dev.**

1. **Sesión.** Sin ella no hay token CSRF, el pedido no se ata a un usuario y
   `/checkout/retorno` no puede validar quién lo mira. Es la misma sesión que
   traba login y cuenta (`docs/HANDOFF.md`).

2. **Stock.** Reservarlo al crear el pedido y descontarlo cuando el pago se
   aprueba, dentro de una transacción. Hoy se valida y no se toca.

3. **Los pedidos en MySQL.** `data/pedidos.json` pasa a ser una tabla `pedidos`
   con su `pedido_items`. Las cinco funciones del repository son dos `SELECT`,
   un `INSERT` y un `UPDATE`. Estructura sugerida en `docs/DATA-CONTRACT.md`.

4. **Mails.** Confirmación al comprador y aviso a Rastro cuando el pago se
   aprueba. Va en el webhook, pero **fuera** del request: encolado, porque
   Mercado Pago corta a los 22 segundos.

5. **Costo de envío.** Hoy es `0` y la UI dice "a coordinar" (`PENDIENTES` #8).
   Cuando exista la tabla, entra en `shipments.cost` de la preferencia.

6. **Credenciales de producción.** Cambiar los tres valores `mp_*` por los de la
   solapa de producción, poner `mp_modo => 'produccion'` y **dar de alta el
   webhook otra vez en el modo producción**: la URL es la misma, la clave
   secreta no.

7. **Conciliación.** Un pago que quede `in_process` y nunca dispare un webhook
   existe. Una tarea diaria que busque los pedidos `pendiente_pago` de más de
   una hora y los consulte por API. `mp_obtener_pago()` ya está.

8. **Los pedidos en el panel.** El panel de administración tiene
   `/admin/pedidos` y `/admin/pedidos/{codigo}`. Cuando esta rama se integre,
   esas pantallas tienen que leer también los pedidos que crea el checkout, no
   sólo el mock de `data/orders.json`. `repo_order_local()` y
   `repo_order_by_reference()` están para eso.

---

## 9. Referencias

- Panel de desarrolladores — <https://www.mercadopago.com.ar/developers/panel>
- Checkout Pro, integración — <https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/landing>
- Crear preferencia (API) — <https://www.mercadopago.com.ar/developers/es/reference/preferences/_checkout_preferences/post>
- URLs de retorno — <https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/configure-back-urls>
- Webhooks y firma secreta — <https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/payment-notifications>
- Tarjetas de prueba — <https://www.mercadopago.com.ar/developers/es/docs/your-integrations/test/cards>
- Cuentas de prueba — <https://www.mercadopago.com.ar/developers/es/docs/your-integrations/test/accounts>

> La documentación de Mercado Pago se puede leer en Markdown agregándole `.md` a
> cualquier URL de `/developers/`. Es la forma cómoda de consultarla desde la
> terminal o de pasársela a un asistente.
