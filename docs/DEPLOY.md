# Deploy — Rastro Fitness

Hosting compartido de **Hostinger**. El deploy es por **FTP con GitHub Actions**:
cada push a `main` sincroniza el repositorio dentro de `public_html`.

Repositorio: `https://github.com/Designwithalex/rastro` (privado).

---

## Cómo funciona

`.github/workflows/deploy.yml` corre en cada push a `main` y en disparo manual
(pestaña **Actions** -> *Deploy a Hostinger* -> *Run workflow*).

Sube todo el repositorio a `/public_html/`, **menos**:

- `.git`, `.github`, `node_modules`, `.DS_Store`
- `docs/` y `design/` — documentación interna, no hace falta en el servidor
- todos los `*.md`
- **`app/config.php`** — las credenciales viven solo en el servidor

## Secrets

Ya están cargados en el repositorio (Settings -> Secrets and variables -> Actions).
Son cuatro y el workflow los busca con estos nombres exactos:

| Secret | Qué es |
|---|---|
| `FTP_SERVER` | IP o host del servidor FTP |
| `FTP_USERNAME` | Usuario FTP de la cuenta |
| `FTP_PASSWORD` | Contraseña de ese usuario |
| `FTP_PORT` | `21` |

Salen de hPanel -> **Archivos** -> **Cuentas FTP**. Si hay que rotarlos, se cambian
ahí y se vuelven a cargar en GitHub: el workflow no se toca.

## Estructura en el servidor

Todo el repositorio se despliega dentro de `public_html`. Las carpetas privadas se
blindan con su propio `.htaccess`:

```
public_html/
├── index.php        front controller
├── .htaccess        rewrite + headers
├── app/             + .htaccess  Require all denied
├── views/           + .htaccess  Require all denied
├── data/            + .htaccess  Require all denied
├── assets/          público
└── app/config.php   NO versionado, solo acá
```

Verificar después del primer deploy que `…/data/products.json` y `…/app/config.php`
devuelvan **403**, no el contenido.

## ⚠️ Antes del primer deploy con base: poner MySQL en Hostinger

Desde el 27/08/2026 el sitio **no arranca sin base de datos**. El catálogo, los
pedidos y la configuración viven en MySQL, no en `data/*.json`. Si se despliega el
código nuevo sin haber hecho estos cuatro pasos, el sitio devuelve 500 en todas las
páginas.

**1 · Crear la base en hPanel**

`hPanel → Bases de datos → Administración de bases de datos MySQL`

Crear base y usuario. Hostinger le antepone el id de la cuenta a los dos:
quedan como `uNNNNNNNNN_rastro`. Anotar host, nombre, usuario y contraseña.

> La contraseña de la base **no** puede ser la misma que la de FTP. Son dos
> superficies de ataque distintas y una filtración no debería abrir las dos.

**2 · Subir `app/config.php`**

No lo crea el deploy y no se sincroniza: hay que subirlo **una sola vez** por FTP o
por el Administrador de archivos de hPanel, copiando `app/config.example.php` y
completando los valores del paso 1. Con `'entorno' => 'produccion'`.

**3 · Crear las tablas**

Por consola, si la cuenta tiene acceso SSH:

```bash
mariadb -h HOST -u USUARIO -p BASE < db/esquema.sql
```

Si no, `hPanel → Bases de datos → phpMyAdmin → Importar` y subir `db/esquema.sql`.

**4 · Cargar los datos iniciales y crear el primer admin**

`bin/` no se despliega, así que estos dos scripts se corren desde una máquina con
acceso remoto a la base (ver "Desarrollo local", más abajo, para habilitarlo):

```bash
php bin/migrar.php --recrear    # carga el catálogo desde data/*.json
php bin/crear-admin.php tu@correo.com "Nombre" "Apellido"
```

`crear-admin.php` pide la contraseña por teclado y no la toma como argumento: un
argumento queda en el historial del shell y en la lista de procesos.

> **Es la única forma de crear un admin.** No hay alta de admin desde el panel ni
> desde el registro público: el rol lo decide el servidor.

Recién con estos cuatro pasos hechos se puede pushear a `main`.

## Verificar después del deploy

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://EL-SITIO/           # 200
curl -s -o /dev/null -w "%{http_code}\n" https://EL-SITIO/admin      # 302 a /ingresar
curl -s -o /dev/null -w "%{http_code}\n" https://EL-SITIO/app/config.php   # 403
```

El último es el que importa: si `app/config.php` devuelve 200, las credenciales de
la base están publicadas.

## Desarrollo local

```bash
php -S localhost:8000 bin/server.php
```

El router es obligatorio: `php -S localhost:8000` a secas ignora los
`.htaccess` de `app/`, `views/` y `data/`, y sirve los mocks —incluidos los
hashes de `users.json`— a cualquiera que los pida. `bin/server.php` replica
en local el bloqueo que en Hostinger hace Apache.

Mientras los datos sean mock no hace falta base de datos. Cuando el backend dev
conecte MySQL y quiera trabajar contra la base remota, hay que habilitar el acceso:

**hPanel -> Bases de datos -> MySQL remoto**

1. Elegir la base.
2. Agregar la IP pública desde donde se conecta (se ve en `curl ifconfig.me`).
3. Guardar. Tarda unos minutos en propagar.

Existe la opción de habilitar `%` (cualquier IP). **No conviene**: deja la base
expuesta a internet con solo usuario y contraseña. Si se usa para una prueba, hay
que quitarla apenas termina.

## Rotar credenciales

- **FTP:** hPanel -> Archivos -> Cuentas FTP -> cambiar contraseña. Después
  actualizar el secret `FTP_PASSWORD` en GitHub.
- **Base de datos:** hPanel -> Bases de datos -> Administración de bases de datos
  MySQL -> el usuario -> cambiar contraseña. Después actualizar `app/config.php`
  en el servidor y avisarle al backend dev por canal seguro.

Conviene que FTP y base de datos **no compartan contraseña**: son dos superficies
de ataque distintas y una filtración no debería abrir las dos.

## Por qué el deploy va por FTPS y no por FTP

`.github/workflows/deploy.yml` usa `protocol: ftps`. Con `ftp` a secas el
usuario y la contraseña de Hostinger viajan en texto plano por la red del
runner de GitHub, en cada push a `main`. La action lo soporta y Hostinger
también, así que no hay razón para no cifrarlo.

FTPS acá es **FTP explícito sobre TLS**: mismo puerto 21, misma cuenta, mismo
`server-dir`. No es SFTP y no requiere cambiar ningún secret.

**Si un deploy falla con un error de handshake TLS o de certificado:** volver
a `protocol: ftp` destraba la subida, pero deja las credenciales expuestas.
No es un arreglo, es un parche. Antes de eso conviene probar en este orden:

1. Confirmar en hPanel que la cuenta FTP tiene TLS habilitado.
2. Probar a mano: `curl --ssl-reqd -u USUARIO ftp://SERVIDOR/` .
3. Si el certificado de Hostinger es autofirmado, la action acepta
   `security: loose`, que cifra igual pero no valida el certificado. Es
   bastante mejor que texto plano.

Si aun así hay que revertir a `ftp`, dejar anotado en `PENDIENTES.md` por qué,
para que no quede como una decisión deliberada.
