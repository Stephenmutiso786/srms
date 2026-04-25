# SRMS Deployment on Railway (Free Tier)

This guide deploys the current PHP monolith using the existing Dockerfile.

## 1) Prerequisites

- GitHub repo is up to date.
- Railway account created.
- Decide your database engine:
  - Recommended: PostgreSQL
  - Alternative: MySQL

## 2) Create Railway Project

1. Open Railway dashboard.
2. Create a new project.
3. Add service from GitHub repo.
4. Railway will detect the Dockerfile and build the app container.

## 3) Add Database Service

1. Inside the same Railway project, add a PostgreSQL service (recommended).
2. Copy connection values from the DB service variables.

## 4) Set Web Service Variables

Set these variables on the web service:

- `DB_DRIVER=pgsql`
- `DB_HOST=<railway-postgres-host>`
- `DB_PORT=<railway-postgres-port>`
- `DB_NAME=<railway-postgres-db-name>`
- `DB_USER=<railway-postgres-user>`
- `DB_PASS=<railway-postgres-password>`
- `DB_SSL_MODE=REQUIRED`
- `APP_URL=https://<your-railway-domain>`
- `APP_SECRET=<long-random-secret>`

Optional but recommended:

- `SETUP_TOKEN=<long-random-token>`
- `REPORT_PRINCIPAL_SIGN=<url-or-path>`
- `REPORT_TEACHER_SIGN=<url-or-path>`
- `REPORT_SCHOOL_STAMP=<url-or-path>`

## 5) Import Database Schema

Use the PostgreSQL schema first:

- `srms/database/srms_postgres_schema.sql`

Then apply migrations in order from:

- `srms/database/pg_migrations/`

If you want demo data, import:

- `srms/database/srms_postgres_seed_demo.sql`

## 6) First Deploy Checks

After deploy succeeds, test:

- `/api/health`
- `/api/health?deep=1`
- Login page
- Report card generation and PDF output

## 7) Free Tier Expectations

- Service may sleep after inactivity (cold start delay is normal).
- Keep one web service + one DB service only.
- Avoid heavy background jobs on free tier.
- Large uploads should move to object storage later.

## 8) Performance Tips Before Upgrading

- Keep app and DB in the same Railway region.
- Minimize migration/demo imports in production.
- Use only required environment variables.
- Avoid repeatedly regenerating heavy PDFs during testing.

## 9) When You Upgrade Later

- Move to paid plan for always-on runtime.
- Add object storage for persistent uploads.
- Add separate worker service for long tasks.

## 10) Rollback Plan

- Keep Render deployment active during Railway validation.
- Test Railway with your custom domain disabled first.
- Switch DNS only after end-to-end checks pass.
- If issues appear, point DNS back to Render immediately.
