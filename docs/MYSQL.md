# La base de datos

Rastro puede leer sus datos de dos lugares: de **MySQL** o de los archivos
`data/*.json`. Lo decide una sola cosa, y es si `app/config.php` tiene
credenciales cargadas.

```php
// app/config.php  — no está en el repo y no se sincroniza por FTP
'db_host' => 'srvNNNN.hstgr.io',   // con esto puesto: MySQL
'db_host' => '',                   // vacío: los JSON, como siempre
```

No hay un flag aparte ni una constante que haya que acordarse de cambiar.
`db_activa()` (en `app/db.php`) mira esas credenciales y el resto del
código se acomoda solo.

**Volver atrás es vaciar `db_host`.** No hay que redesplegar, ni revertir un
commit, ni restaurar nada: el request siguiente ya lee los archivos.

---

## Por qué existe esto

El motivo no es la velocidad. Con 30 productos, leer un JSON está bien.

El motivo es que **el ciclo viejo perdía compras**. Guardar un pedido era
leer la lista entera, agregarle uno y reescribirla. Dos personas comprando
en el mismo segundo leen la MISMA lista, cada una le suma el suyo, y la que
escribe segunda pisa a la primera: un pedido cobrado que no existe en ningún
lado, sin un solo error en el log.

El `LOCK_EX` que tenía `_repo_pedidos_guardar()` no alcanzaba, porque
serializa las escrituras y no el ciclo leer-modificar-escribir.

Con base, dar de alta un pedido es un `INSERT` de una fila. Dos compras
simultáneas son dos `INSERT` y entran las dos.

---

## Cómo está armado

Todo el acceso a datos del sitio pasa por dos funciones: `_repo_json()` para
leer y `_repo_escribir_json()` para escribir. Las 62 funciones `repo_*` de
`repository.php` y `repository-escritura.php` no saben de dónde salen los
datos: reciben arrays y devuelven arrays.

Así que la migración **no reescribió esas 62 funciones**. Reimplementó esas
dos, y `app/repository-mysql.php` traduce entre las tablas y la forma exacta
que tenían los JSON.

Esa traducción no es trivial y ahí está casi todo el riesgo del archivo:

- MySQL no tiene booleanos y devuelve `1` donde el JSON tenía `true`.
- PDO entrega los `DECIMAL` como la cadena `"10.00"` donde había el entero `10`.
- Una columna `VARCHAR NOT NULL` guarda `''` donde el JSON tenía `null`.

Cuatro almacenes no pasan por `_repo_json()` y tienen su propio camino, porque
los escribe el sitio en runtime: los pedidos del checkout, los
arrepentimientos, los enlaces de recuperación de contraseña y los intentos de
login.

---

## Antes de tocar producción

```bash
php bin/verificar.php
```

| suite | qué comprueba |
|---|---|
| `escritura` | guardar y volver a leer no deforma nada, y el panel da de alta, edita y borra un producto sin perder nada |
| `pedidos` | el alta no pierde compras simultáneas; el webhook actualiza sin romper |
| `formularios` | arrepentimientos, recuperación de clave, intentos de login |
| `correo` | el cliente SMTP, contra un servidor de mentira que habla TLS de verdad |

Las tres primeras escriben en la base y borran lo suyo al terminar. `correo`
no toca la base ni manda nada afuera.

### La suite que no está en esa lista

`php bin/verificar-paridad.php` se corre **a mano y sólo al migrar**. Compara
la base contra los `data/*.json`, que son la semilla: la foto del día que se
migró. Ya cumplió su función.

No se puede dejar como control permanente porque en cuanto alguien carga un
producto o se registra un cliente, la base y los archivos dejan de coincidir
—que es justamente lo que tiene que pasar— y marcaría como falla un dato
nuevo que está perfectamente bien. Un control que se pone en rojo por
funcionar es un control que la gente aprende a ignorar.

Lleva además una lista de **diferencias toleradas**: cuatro casos donde MySQL
y el JSON no coinciden y se comprobó, leyendo todos los consumidores del
dato, que ningún código puede notarlo. Cada una dice por qué y cita el
archivo y la línea.

---

## Montar la base

**De cero**, en una base vacía:

```bash
mariadb -u usuario -p base < db/esquema.sql
php bin/migrar.php
```

`bin/migrar.php` levanta los `data/*.json` y los carga. Se corre **una vez**,
cuando se conecta la base. Después de eso los datos viven en MySQL y los JSON
quedan como semilla histórica: volver a correrlo sobre una base con contenido
cargado por el cliente le pisa el trabajo, y por eso pide `--forzar`.

**Sobre una base que ya tiene datos**, nunca se recrea. Se aplica la migración
que corresponda:

```bash
php bin/aplicar-migracion.php db/migraciones/001-pedidos-del-sitio.sql
```

`db/esquema.sql` es la foto de cómo tiene que quedar la base y sirve para
montarla de cero. `db/migraciones/` es el otro camino: cómo llevar una base
con datos de una versión del esquema a la siguiente, sin borrar nada.

---

## Las credenciales

Salen de hPanel de Hostinger, en *Bases de datos → Administración de bases de
datos MySQL*. Van a `app/config.php`, que **no está en el repo** (`.gitignore`)
y **no se sincroniza por FTP** (está excluido en el workflow de deploy): vive
sólo en el servidor y en la máquina de cada dev.

Hostinger acepta conexión remota a MySQL, así que se puede trabajar contra la
base real desde la máquina local sin SSH.

Ojo con esto: `bin/` también está excluido del deploy, así que **los scripts de
migración y verificación no llegan al servidor por FTP**. Se corren desde la
máquina local apuntando a la base de producción, que es como se hizo la
migración inicial.
