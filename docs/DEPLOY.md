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

## `app/config.php` en el servidor

No lo crea el deploy: hay que subirlo **una sola vez** por FTP o por el Administrador
de archivos de hPanel, copiando `app/config.example.php` y completando los valores.
Como está excluido de la sincronización, los deploys posteriores no lo pisan.

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
