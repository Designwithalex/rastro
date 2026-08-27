# Rastro Fitness — Ecommerce

Frontend del ecommerce de Rastro Fitness, vendedores oficiales de equipamiento de
gimnasio. PHP 8 como capa de vistas, HTML/CSS/JS vanilla, datos mock en JSON.

- **Poner a andar pagos y panel en el servidor:** [`docs/PUESTA-EN-MARCHA.md`](docs/PUESTA-EN-MARCHA.md)
- **Contexto completo del proyecto:** [`CLAUDE.md`](CLAUDE.md)
- **Contrato de datos (para el backend):** [`docs/DATA-CONTRACT.md`](docs/DATA-CONTRACT.md)
- **Handoff al backend dev:** [`docs/HANDOFF.md`](docs/HANDOFF.md)
- **Mercado Pago:** [`docs/MERCADOPAGO.md`](docs/MERCADOPAGO.md)
- **Deploy:** [`docs/DEPLOY.md`](docs/DEPLOY.md)
- **Pendientes abiertos:** [`PENDIENTES.md`](PENDIENTES.md)

## Estado

**Fase 3 cerrada** (27/08/2026): checkout con Mercado Pago y panel de
administración. El sitio ya cobra en ambiente de prueba y se administra solo.

Falta cargar las credenciales en el servidor —seis valores en `app/config.php`,
un webhook y dos permisos—: [`docs/PUESTA-EN-MARCHA.md`](docs/PUESTA-EN-MARCHA.md).

**Fase 2** (26/08/2026). Las doce rutas del router tienen su vista:

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
| 404 | Página de error |

Más las del checkout y las del panel:

| | |
|---|---|
| `/checkout` · `/checkout/retorno` | Compra con Mercado Pago (Checkout Pro) |
| `/webhooks/mercadopago` | Notificaciones de pago, con firma |
| `/admin` + 8 secciones | Panel de administración |

Lo que sigue es backend: MySQL, sesión de clientes, stock y mails. Todo eso
está ordenado en [`docs/HANDOFF.md`](docs/HANDOFF.md).

## Correr local

```bash
php -S localhost:8000 bin/server.php
```

El router es obligatorio. `php -S localhost:8000` a secas sirve el árbol de
archivos tal cual y entrega `data/users.json`, `data/settings.json` y el
código de `app/` a cualquiera que los pida: en producción eso lo tapa el
`.htaccess` de cada carpeta privada, que el servidor embebido de PHP ignora.

Requiere PHP 8+. No hay build step, no hay dependencias.

## Regla de oro

Ninguna vista lee un JSON. Toda lectura de datos pasa por `app/repository.php`.
