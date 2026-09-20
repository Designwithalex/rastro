# El correo del sitio

Rastro manda tres mails, y los tres importan:

| cuándo | a quién |
|---|---|
| alguien pide cambiar su contraseña | el enlace, al cliente |
| alguien se arrepiente de una compra | la constancia, al cliente |
| lo mismo | el aviso, a Rastro |

---

## Por qué no llegaban

El sitio usaba `mail()` de PHP. Eso **entrega** el mensaje al servidor del
hosting, que lo manda desde `no-reply@rastrofitness.com`.

El problema es que ese servidor no figura en el registro SPF del dominio. Para
Gmail, entonces, es alguien diciendo ser Rastro sin poder probarlo: lo manda a
spam o lo rechaza sin avisar. Y como `mail()` devuelve `true` apenas el
servidor acepta el mensaje, del lado del sitio parecía que todo había salido
bien.

Por eso el enlace de recuperación de contraseña no llegaba nunca.

---

## La solución: SMTP

Con SMTP el mail sale de una casilla real, con su usuario y su clave. El
proveedor que la emite ya está autorizado a mandar por el dominio, así que el
mensaje pasa las verificaciones.

Se configura en `app/config.php`, que vive sólo en el servidor:

```php
'smtp_host' => 'smtp.hostinger.com',
'smtp_port' => 587,
'smtp_user' => 'ventas@rastrofitness.com',
'smtp_pass' => '••••••••',
```

Los datos salen de hPanel, en **Correos → Cuentas de correo**. Si no hay
ninguna casilla creada, hay que crearla primero: es la casilla la que le da
permiso al sitio para mandar.

**Vacío `smtp_host`, el sitio vuelve a `mail()`.** Es el mismo interruptor que
la base de datos: una credencial presente o ausente, nada más que acordarse de
cambiar.

### El remitente

Con SMTP, el mail sale **desde la casilla con la que nos autenticamos**, no
desde un `no-reply@` inventado. La mayoría de los proveedores rechazan un
remitente que no coincida con el usuario que inició sesión, y los que lo
aceptan suelen marcarlo como sospechoso.

### Los dos puertos

- **587** arranca en claro y sube a TLS con `STARTTLS`. Es lo normal.
- **465** es TLS desde el primer byte.

Se deduce del puerto. `smtp_seguridad => 'tls'` sólo hace falta para forzar TLS
directo en un puerto raro.

El certificado del servidor **se verifica siempre**. `smtp_ca` existe para un
servidor de correo interno con una autoridad propia, y suma esa autoridad a las
de confianza: no desactiva la verificación.

---

## Probarlo

Desde el panel: **/admin/configuracion → Probar el envío de correo**.

Dice por dónde salió (SMTP o `mail()`) y, si falla, **el motivo exacto**: no es
lo mismo una clave equivocada que un puerto cerrado. Antes decía sólo "el
servidor rechazó el envío", que no alcanza para arreglar nada.

Que el servidor lo acepte no garantiza que llegue: hay que mirar también la
carpeta de spam.

---

## Qué hay probado, y qué no

```bash
php bin/verificar-correo.php
```

Levanta un servidor SMTP de mentira —`bin/smtp-falso.php`— que habla TLS de
verdad con un certificado generado al momento, y comprueba la conversación
comando por comando:

- los comandos salen en orden: `EHLO`, `STARTTLS`, `AUTH`, `MAIL FROM`,
  `RCPT TO`, `DATA`, `QUIT`;
- entiende respuestas de varias líneas (`250-STARTTLS` / `250 HELP`), que es
  donde se rompe un cliente que lee una sola;
- escapa el punto al principio de una línea, que si no corta el mensaje ahí;
- el remitente es la casilla autenticada;
- **con clave configurada y sin STARTTLS, no manda la clave**: se planta
  antes, porque `AUTH` la manda en base64 y eso no es cifrado;
- una clave rechazada devuelve `false` con el motivo, no un `true` mudo.

**Lo que no prueba** es que el proveedor real acepte el mensaje. Eso sólo lo
dice el botón del panel, contra el servidor de verdad.

---

## Si aun con SMTP llega a spam

Falta autenticar el dominio. En hPanel, en la zona DNS:

- **SPF**: autoriza al servidor de correo a mandar por el dominio.
- **DKIM**: le pone una firma a cada mensaje.

Los dos los genera Hostinger desde el panel de correo. Sin ellos el mail sale,
pero llega marcado.
