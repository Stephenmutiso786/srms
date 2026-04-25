# SRMS Deployment on Railway (Standard Hosting)

This guide deploys the current PHP monolith using the existing Dockerfile on a normal Railway setup, with a clean path to scale later.

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

## 7) Baseline Hosting Expectations

- Use one web service + one DB service to start.
- Keep app and DB in the same Railway region.
- Keep uploads small at first; plan object storage as traffic grows.
- Run report generation tests before switching DNS.

## 8) Performance Tips (Current Plan)

- Keep app and DB in the same Railway region.
- Minimize migration/demo imports in production.
- Use only required environment variables.
- Avoid repeatedly regenerating heavy PDFs during testing.

## 9) Upgrade Path Later

- Increase web service resources when traffic rises.
- Add object storage for persistent uploads.
- Add a separate worker service for long-running tasks.
- Add monitoring/alerts and periodic backups.

## 10) Rollback Plan

- Keep Render deployment active during Railway validation.
- Test Railway with your custom domain disabled first.
- Switch DNS only after end-to-end checks pass.
- If issues appear, point DNS back to Render immediately.
