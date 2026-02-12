# Tributary

Action item lifecycle engine scaffolded with Laravel 12 + PostgreSQL + Inertia React.

## Stack

- Laravel 12 (latest)
- PHP 8.2+
- PostgreSQL (Railway-compatible via `DATABASE_URL`)
- Inertia.js + React + TypeScript + Vite SSR (Breeze scaffolding)

## Local Setup

```bash
cd ~/clawd/tributary
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

## Environment Variables

Database is configured for Railway-style URLs:

```env
DB_CONNECTION=pgsql
DATABASE_URL=
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tributary
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

API token middleware uses:

```env
TRIBUTARY_API_TOKEN=your-secret-token
```

## API

### Health endpoint (public)

- `GET /api/health`

Returns:

```json
{
  "status": "ok",
  "app": "Tributary",
  "timestamp": "2026-02-12T..."
}
```

### Token-protected endpoint (example)

- `GET /api/user`
- Header required: `X-Api-Token: <TRIBUTARY_API_TOKEN>`

Middleware: `App\Http\Middleware\RequireApiToken` (alias: `api.token`).

## CORS

CORS is enabled for web/API access in `config/cors.php` with:

- `paths`: `api/*`, `sanctum/csrf-cookie`
- `allowed_origins`: `*`
- `allowed_methods`: `*`
- `allowed_headers`: `*`

## Notes

- This is scaffold-only for local development.
- Railway deployment is intentionally not included yet.
