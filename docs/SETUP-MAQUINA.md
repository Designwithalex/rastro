# Levantar el proyecto en otra máquina

Clonar el repo te trae el código, la documentación y los subagentes. **No te trae
credenciales, tipografías ni las sesiones de los MCP.** Esta es la lista completa.

---

## 1. Clonar y levantar

```bash
git clone https://github.com/Designwithalex/rastro.git
cd rastro
php -S localhost:8000 bin/server.php
```

Requiere **PHP 8.1 o superior**. No hay dependencias, no hay build step, no hay npm.

> Usá `bin/server.php` y **no** `php -S localhost:8000` a secas. El servidor embebido
> de PHP ignora los `.htaccess`, así que sin ese router te sirve `data/users.json`
> con los hashes y `app/config.php` con las credenciales. El router replica las reglas.

**Abrí Claude Code parado dentro de `rastro/`**, no en el home. Los cinco subagentes
del proyecto están en `.claude/agents/` y se cargan según el directorio de trabajo.

## 2. `app/config.php` — no está en el repo, y es a propósito

```bash
cp app/config.example.php app/config.php
```

Completá los valores. Cada variable dice de dónde sacarla en hPanel.
Está en `.gitignore` y excluido del deploy: vive solo en tu máquina y en el servidor.

> **Este es el momento de rotar las contraseñas.** Hoy FTP y base de datos comparten
> la misma, y pasó por un chat. Cambialas en hPanel antes de cargarlas en la máquina
> nueva y ponés solo las nuevas. El procedimiento está en `docs/DEPLOY.md`.
> Si rotás la de FTP, hay que actualizar el secret `FTP_PASSWORD` en GitHub.

## 3. Autenticaciones

| Qué | Cómo |
|---|---|
| GitHub | `gh auth login` — hace falta scope `repo` y `workflow` |
| Figma (MCP) | Se reconecta desde Claude Code. El archivo es el mismo, está en la nube |
| Notion (MCP) | Igual |

## 4. La tipografía del hero

El titular "QUE AGUANTA" usa **Urban Thunder Demo**, que está instalada localmente y
**no existe en Figma**. Si abrís el archivo sin tenerla instalada, esa línea se ve con
una fuente sustituta.

Instalala en la máquina nueva, o asumí que vas a ver un reemplazo.

> Recordá que el sufijo "Demo" casi siempre significa licencia de uso personal, sin
> derecho comercial ni de webfont. **Esto sigue bloqueando el maquetado del hero**
> hasta que se consiga la licencia completa. Ver `design/README.md`.

## 5. Assets originales del cliente — opcional

- `/Users/ale/Documents/chichalabs-clientes/rastro` (772 MB): brandbook en PDF y las
  fotos originales. Está en el Drive del cliente, linkeado en `CLAUDE.md` §8.
- `.originales-img/` dentro del repo (29 MB): copia de las fotos antes de optimizar.
  Ignorado por git.

**Ninguno de los dos hace falta para que el sitio corra**: las imágenes optimizadas
están versionadas. Los necesitás solo si querés volver a optimizar con otro criterio,
con `bin/optimizar-imagenes.sh`.

## 6. Verificar que quedó bien

```bash
php -S localhost:8000 bin/server.php &
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/          # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/nosotros  # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/no-existe # 404
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/data/users.json # 403
```

Los cuatro tienen que dar eso. El último es el que importa: si devuelve 200, estás
corriendo el servidor sin el router.

---

## Dónde está el contexto del proyecto

No hace falta releer ninguna conversación. Todo lo decidido está escrito:

| Archivo | Qué tiene |
|---|---|
| `CLAUDE.md` | Marca, arquitectura, el contrato de datos y las reglas de trabajo |
| `PENDIENTES.md` | Todo lo que espera una definición, con su decisión provisoria |
| `design/README.md` | Índice del archivo de Figma y qué falta del cliente |
| `design/direccion-ux-v3.md` | Medidas de la nav, el hero y Nosotros |
| `docs/DEPLOY.md` | Deploy, MySQL remoto y rotación de credenciales |
| `git log` | Cada decisión, con el porqué en el mensaje |

## Los subagentes

`.claude/agents/` trae los cinco del proyecto: `figma-designer`, `frontend-builder`,
`devops-deploy`, `qa-reviewer` y `notion-documenter`.

Dos cosas que conviene saber antes de usarlos:

1. **Están atados a Rastro** a propósito: rutas, paleta y la página de Notion. No
   sirven tal cual para otro cliente. Generalizarlos es una decisión pendiente.
2. **Los subagentes no heredan los servidores MCP.** `figma-designer` y
   `notion-documenter` no van a poder tocar Figma ni Notion desde un subagente: esas
   fases se corren desde la sesión principal. Está escrito en cada uno.
