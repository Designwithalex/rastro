# Puesta en marcha — pagos y panel de administración

Todo lo que hay que hacer **una vez**, en el servidor, para que el checkout cobre
y el panel deje entrar. Nada de esto está en el repositorio a propósito: son
credenciales y accesos.

El código ya está. Lo que falta acá son seis valores en un archivo, un webhook y
un par de permisos.

> **Orden.** El paso 1 es el único que bloquea a los demás. El 2 y el 3 son
> independientes: se pueden hacer en cualquier orden, o sólo uno de los dos si
> por ahora se quiere el panel y no los pagos.

---

## Antes de empezar

| | |
|---|---|
| **Dónde** | hPanel de Hostinger → Administrador de archivos, o por FTP |
| **Qué se toca** | Un solo archivo: `public_html/app/config.php` |
| **Cuánto lleva** | 20 minutos el panel, 40 los pagos con las cuentas ya creadas |
| **Se puede deshacer** | Sí. Todo lo de acá se revierte borrando lo que se agregó |

`app/config.php` **no está en el repositorio y no lo sube el deploy**. Vive sólo
en el servidor. Si no existe todavía, se crea copiando `app/config.example.php`,
que tiene todos los campos comentados.

> **Ojo con las comillas.** Los valores van entre **comillas simples**
> (`'valor'`). Entre comillas dobles, PHP interpreta los `$` y tanto el hash del
> panel como algunos tokens dejan de funcionar, sin dar ningún error.

---

## 1 · Que el panel deje entrar

**Sin esto el panel no deja pasar a nadie y muestra en pantalla cómo
configurarlo.** Es a propósito: un panel que escribe los datos del negocio y
arranca con una clave por defecto es un panel abierto.

### 1.1 Generar el hash de la contraseña

La contraseña no se guarda: se guarda su hash. Se genera **en el servidor**, en
la Terminal de hPanel o por SSH:

```bash
php -r 'echo password_hash("LA-CLAVE-QUE-ELIJAS", PASSWORD_DEFAULT), PHP_EOL;'
```

Sale algo así, y se copia **entero**, incluido el `$2y$12$` del principio:

```
$2y$12$Sc0K9v...........................................
```

> Si la Terminal de hPanel no está disponible, se puede generar en cualquier
> máquina con PHP: el hash no depende del servidor.

### 1.2 Cargarlo en `app/config.php`

```php
    // Panel de administración
    'panel_email'         => 'TU-MAIL@rastrofitness.com.ar',
    'panel_password_hash' => '$2y$12$Sc0K9v...',   // comillas SIMPLES
    'panel_nombre'        => 'Alex',
```

### 1.3 Probar

Entrar a `https://EL-SITIO/admin`. Tiene que pedir mail y contraseña.

| Qué ves | Qué significa |
|---|---|
| El formulario de ingreso | Bien. Entrá con lo que cargaste. |
| "El panel no está configurado en este servidor" | Falta uno de los dos valores, o el archivo no se guardó. |
| "Mail o contraseña incorrectos" con la clave correcta | El hash se copió cortado, o va entre comillas dobles. |
| Un 404 | El deploy todavía no subió las rutas del panel. Revisá que el merge esté en `main`. |

### 1.4 Permisos de escritura

El panel escribe en dos carpetas. Si no puede, al guardar dice **"no se pudo
guardar"**.

| Carpeta | Para qué |
|---|---|
| `public_html/data/` | Productos, precios, banners, configuración |
| `public_html/assets/img/subidas/` | Las imágenes que se suben desde el panel |

En hPanel → Administrador de archivos → clic derecho en la carpeta → Permisos:
**755** la carpeta y **644** los archivos de adentro. Si `assets/img/subidas/` no
existe, se crea (el deploy sube su `.htaccess`, que es lo que importa).

---

## 2 · Que el checkout cobre

Todo el detalle —incluido cómo crear la aplicación y los usuarios de prueba—
está en [`MERCADOPAGO.md`](MERCADOPAGO.md). Acá va lo mínimo.

### 2.1 Sacar las credenciales

En el [panel de desarrolladores de Mercado Pago](https://www.mercadopago.com.ar/developers/panel):
**Tus integraciones → tu aplicación → Credenciales**.

Hay **dos juegos**: de prueba y de producción. **Los dos empiezan con `APP_USR`
y no se distinguen mirándolos**, sólo por la solapa de la que se copiaron. Es
fácil mezclarlas y cobrar de verdad creyendo que se está probando.

**Arrancá con las de prueba.**

### 2.2 Cargarlas

```php
    // Mercado Pago
    'mp_modo'             => 'test',          // 'test' o 'produccion'
    'mp_access_token'     => 'APP_USR-...',   // privada: nunca sale del servidor
    'mp_public_key'       => 'APP_USR-...',
    'mp_webhook_secret'   => '',              // se completa en el paso 2.3
    'mp_notification_url' => '',              // vacío: se arma solo con base_url
```

`mp_modo` no cambia el comportamiento por sí solo: es para dejar escrito con cuál
de los dos juegos se cargó el archivo, y el sitio lo usa para mostrar el cartel de
ambiente de prueba. **Tiene que coincidir con las credenciales de arriba.**

Verificá también que `base_url` sea el dominio real, sin barra final:

```php
    'base_url' => 'https://darkorange-buffalo-311255.hostingersite.com',
```

De ahí salen las URLs de vuelta de Mercado Pago. Con `localhost` o vacío, la
creación de la preferencia falla con `invalid back_urls`.

### 2.3 Dar de alta el webhook

**Este paso es el que más se saltea, y sin él el pago se cobra pero el pedido
nunca cambia de estado.** El síntoma es que la persona vuelve al sitio y lee "no
tenemos el pago todavía".

En el panel de desarrolladores: **Tus integraciones → tu aplicación → Webhooks →
Configurar notificaciones**.

| Campo | Valor |
|---|---|
| URL | `https://EL-SITIO/webhooks/mercadopago` |
| Evento | **Pagos** (`payment`) |

Al guardar aparece **"Revelar clave secreta"**. Esa clave va en
`mp_webhook_secret`. Sin ella el endpoint rechaza **todas** las notificaciones
con un 401 — que es lo correcto: sin firma no hay forma de saber si el aviso lo
mandó Mercado Pago o cualquiera.

> La clave secreta es **distinta en prueba y en producción**. Si se cambia de
> modo, hay que volver a este paso.

### 2.4 Probar

```bash
php bin/mp-probar.php
```

Ese script comprueba las credenciales y crea una preferencia de prueba sin cobrar
nada. Después, una compra completa con una [tarjeta de prueba](MERCADOPAGO.md):
el pedido tiene que aparecer en `/admin/pedidos` marcado como **Venta** y pasar a
**Pagado** cuando llega la notificación.

---

## 3 · Pasar a cobrar de verdad

Cuando la prueba salga bien:

1. Cambiar `mp_access_token` y `mp_public_key` por los de **producción**.
2. Poner `'mp_modo' => 'produccion'`.
3. **Volver a dar de alta el webhook** en la solapa de producción y cargar la
   clave secreta nueva en `mp_webhook_secret`.
4. Hacer una compra real de monto chico y devolverla.

---

## Lo que hay que saber para no romper nada

### Los datos viven en el servidor

Desde que existe el panel, `data/` está **excluido del deploy**. Los JSON del
repositorio son la semilla; la verdad está en el servidor. Si se volvieran a
sincronizar, un push borraría los productos, los precios, los banners y los
pedidos de un saque, sin aviso.

**Consecuencia:** un `data/*.json` nuevo no llega solo, hay que subirlo a mano.
Está explicado en [`DEPLOY.md`](DEPLOY.md).

### No hay copias de respaldo automáticas

Antes de un cambio grande, bajar `data/` por FTP y guardarlo con la fecha. Son
60 KB. Es todo el catálogo y todos los pedidos.

### Qué mirar cuando algo falla

| Dónde | Qué tiene |
|---|---|
| `data/mp-eventos.log` | Una línea por cada cosa que dijo Mercado Pago. Es lo primero que hay que abrir. |
| Registro de errores de hPanel | Los errores de PHP |
| `/admin/pedidos` | Si el pedido está pero no cambia de estado, el problema es el webhook |

La tabla completa de síntomas y causas está en
[`MERCADOPAGO.md`](MERCADOPAGO.md) §7.

---

## Lo que todavía no hace el sitio

Ninguna de estas cosas frena el cobro, pero conviene saberlas antes de anunciar
la tienda. El detalle está en `MERCADOPAGO.md` §8 y en `PENDIENTES.md`.

| | Qué pasa hoy |
|---|---|
| **El stock no se reserva** | Se valida al armar el pedido y no se descuenta. Dos personas pueden comprar la última unidad con segundos de diferencia. |
| **No se mandan mails** | Ni al comprador ni a Rastro. El aviso de que entró una venta hay que ir a buscarlo al panel. |
| **El envío es "a coordinar"** | Va en `0` y se arregla por WhatsApp. |
| **Se compra sin cuenta** | El pedido no queda atado a un usuario, así que no aparece en "Mi cuenta". Los datos del comprador quedan guardados en el pedido. |
| **Las imágenes no se optimizan al subirlas** | Una foto de celular de 4 MB entra tal cual. Conviene achicarlas antes de subirlas. |

---

## Resumen: la lista de una sola pantalla

```
[ ] 1. Generar el hash            php -r 'echo password_hash("...", PASSWORD_DEFAULT);'
[ ] 2. app/config.php             panel_email + panel_password_hash
[ ] 3. Permisos 755/644           data/  y  assets/img/subidas/
[ ] 4. Probar                     https://EL-SITIO/admin

[ ] 5. Credenciales de PRUEBA     mp_access_token + mp_public_key + mp_modo='test'
[ ] 6. Verificar base_url         el dominio real, sin barra final
[ ] 7. Alta del webhook           https://EL-SITIO/webhooks/mercadopago  (evento: Pagos)
[ ] 8. Clave secreta              mp_webhook_secret
[ ] 9. Probar                     php bin/mp-probar.php  +  una compra de prueba

[ ] 10. Producción                credenciales nuevas + mp_modo='produccion'
[ ] 11. Webhook de producción     alta nueva + clave secreta nueva
[ ] 12. Una compra real chica     y devolverla
```
