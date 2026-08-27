# Rastro Fitness — Ecommerce

Frontend del ecommerce de Rastro Fitness, vendedores oficiales de equipamiento de
gimnasio. PHP 8 como capa de vistas, HTML/CSS/JS vanilla, datos mock en JSON.

- **Contexto completo del proyecto:** [`CLAUDE.md`](CLAUDE.md)
- **Contrato de datos (para el backend):** [`docs/DATA-CONTRACT.md`](docs/DATA-CONTRACT.md)
- **Handoff al backend dev:** [`docs/HANDOFF.md`](docs/HANDOFF.md)
- **Deploy:** [`docs/DEPLOY.md`](docs/DEPLOY.md)
- **Pendientes abiertos:** [`PENDIENTES.md`](PENDIENTES.md)

## Estado

**El sitio corre sobre MySQL y tiene panel de administración** (27/08/2026).
El repository dejó de leer los JSON sin que se tocara una sola vista: esa era
la apuesta del contrato de datos desde el primer día.

| | |
|---|---|
| Sitio público | ✅ doce rutas |
| Base de datos | ✅ MySQL, 13 tablas |
| Sesión y cuentas | ✅ login, registro, cuenta, CSRF, límite de intentos |
| Panel `/admin` | ✅ nueve secciones |
| Pagos | ❌ lo que sigue |

> **En producción todavía corre la versión anterior.** El código con base y
> panel está en `feat/panel-admin` y no se puede desplegar hasta poner MySQL en
> Hostinger: sin `app/config.php` el sitio devuelve 500. Los cuatro pasos están
> en [`docs/DEPLOY.md`](docs/DEPLOY.md).

### Rutas

| | |
|---|---|
| `/` | Home |
| `/catalogo` · `/catalogo/{categoria}` | Listado con filtros, orden y paginación |
| `/producto/{slug}` | Ficha con galería, especificaciones y relacionados |
| `/carrito` | Carrito (vive en `localStorage`) |
| `/mayoristas` | Landing del canal mayorista |
| `/nosotros` | Página de credibilidad |
| `/ingresar` · `/registro` · `/cuenta` | Cuenta |
| `/terminos` · `/arrepentimiento` | Legales |
| `/admin/*` | Panel de administración, nueve secciones |
| 404 | Página de error |

Lo que sigue es la integración de pagos. El estado completo y lo que queda está
en [`docs/HANDOFF.md`](docs/HANDOFF.md).

## Correr local

```bash
cp app/config.example.php app/config.php     # completar con la base local
mariadb -u USUARIO -p rastro < db/esquema.sql
php bin/migrar.php --recrear
php bin/crear-admin.php tu@correo.com "Nombre" "Apellido"
php -S localhost:8000 bin/server.php
```

El router es obligatorio. `php -S localhost:8000` a secas sirve el árbol de
archivos tal cual y entrega `data/users.json`, `data/settings.json` y el
código de `app/` a cualquiera que los pida: en producción eso lo tapa el
`.htaccess` de cada carpeta privada, que el servidor embebido de PHP ignora.

Requiere PHP 8.1+ con `pdo_mysql` y `mbstring`, y MySQL o MariaDB. No hay build
step, no hay dependencias, no hay npm.

## Regla de oro

Ninguna vista lee un JSON ni arma una consulta. Toda lectura pasa por
`app/repository.php` y toda escritura por `app/repository-admin.php`.
