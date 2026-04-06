# Elimu Hub

Elimu Hub is a PHP + MySQL Student Results Management System.

## Setup

- Import the database: `srms/database/srms_makumbusho.sql`
- Configure DB: `srms/script/db/config.php`
- Web root should be: `srms/script/`

## Run (quick local)

```bash
cd srms/script
php -S localhost:8000 router.php
```

Open `http://localhost:8000`.

## Demo logins

`srms/login_credentials.txt`

## Deploy on Render (backend)

This repo includes a `Dockerfile` so Render can run the PHP app as a single web service.

1. Create a new **Render Web Service** from this repo (environment: **Docker**).
2. Create a database:
   - MySQL: use `srms/database/srms_makumbusho.sql`
   - Postgres (Neon/Supabase/etc.): use `srms/database/srms_postgres_schema.sql`
     - Optional demo seed (only if you want sample accounts/data): `srms/database/srms_postgres_seed_demo.sql`
3. In Render → Service → **Environment**, set:
   - `DB_DRIVER` (`mysql` or `pgsql`)
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
4. If your DB provider requires TLS, set `DB_SSL_MODE=REQUIRED`.

Notes:
- Uploads (student photos / logos) need persistent storage; Render’s filesystem is ephemeral unless you attach a disk or move uploads to object storage.

## Vercel (frontend)

This system’s “frontend” is PHP-rendered pages, so it must run on the same PHP server (Render/Apache) — Vercel won’t run PHP pages as a separate frontend.

If you want a true split (Vercel Next.js frontend + Render API backend), you’d need to build a new frontend that talks to an API (bigger change).
