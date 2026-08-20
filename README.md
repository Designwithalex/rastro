# Rastro Fitness — Ecommerce

Frontend del ecommerce de Rastro Fitness, vendedores oficiales de equipamiento de
gimnasio. PHP 8 como capa de vistas, HTML/CSS/JS vanilla, datos mock en JSON.

- **Contexto completo del proyecto:** [`CLAUDE.md`](CLAUDE.md)
- **Contrato de datos (para el backend):** [`docs/DATA-CONTRACT.md`](docs/DATA-CONTRACT.md)
- **Handoff al backend dev:** [`docs/HANDOFF.md`](docs/HANDOFF.md)
- **Deploy:** [`docs/DEPLOY.md`](docs/DEPLOY.md)
- **Pendientes abiertos:** [`PENDIENTES.md`](PENDIENTES.md)

## Correr local

```bash
php -S localhost:8000
```

Requiere PHP 8+. No hay build step, no hay dependencias.

## Regla de oro

Ninguna vista lee un JSON. Toda lectura de datos pasa por `app/repository.php`.
