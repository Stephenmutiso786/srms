# Elimu Hub

Elimu Hub is a PHP Student Results Management System (MySQL or Postgres).

## Setup

- Import the schema:
  - MySQL: `srms/database/srms_mysql_schema_clean.sql`
  - Postgres: `srms/database/srms_postgres_schema.sql`
- Configure DB: `srms/script/db/config.php`
- Web root should be: `srms/script/`

## Run (quick local)

```bash
cd srms/script
php -S localhost:8000 router.php
```

Open `http://localhost:8000`.

## Health Checks + Uptime Probes

Use these endpoints to quickly diagnose timeout issues like `ERR_TIMED_OUT`.

- Basic liveness: `/api/health`
- Deep readiness (includes DB check): `/api/health?deep=1`

Expected behavior:

- `200` when healthy
- `503` when deep check fails (for example, DB unreachable)

Quick probes:

```bash
curl -sS -i https://elimuhub.tech/api/health
curl -sS -i https://elimuhub.tech/api/health?deep=1
```

Continuous probe (every 5 seconds):

```bash
while true; do
  date '+%F %T'
  curl -sS -m 10 -o /dev/null -w 'basic=%{http_code} total=%{time_total}s\n' https://elimuhub.tech/api/health
  curl -sS -m 10 -o /dev/null -w 'deep=%{http_code} total=%{time_total}s\n' 'https://elimuhub.tech/api/health?deep=1'
  echo '---'
  sleep 5
done
```

How to interpret outages:

- Both probes timeout: network / DNS / edge path issue
- Basic `200`, deep `503`: app is up, database path is failing
- Basic `200`, deep `200`, but browser times out: likely client ISP/proxy/firewall or intermittent edge route

## Demo data (optional)

If you want sample data and logins for testing, import:

- Postgres demo seed: `srms/database/srms_postgres_seed_demo.sql`

Demo credentials (only after seeding): `srms/login_credentials.txt`

Legacy demo dumps (avoid for production):

- `srms/database/srms_makumbusho.sql`
- `srms/database/srms_postgres.sql`

## Deploy on DigitalOcean (backend)

This repo includes a `Dockerfile` so DigitalOcean App Platform can run the PHP app as a single web service.

1. Create a new **DigitalOcean App Platform** app from this repo (source type: **Dockerfile**).
2. Optional: use the provided app spec as a starting point: `.do/app.yaml`.
3. Create a database:
   - MySQL: use `srms/database/srms_mysql_schema_clean.sql`
   - Postgres (Neon/Supabase/etc.): use `srms/database/srms_postgres_schema.sql`
  - Optional demo seed (only if you want sample accounts/data): `srms/database/srms_postgres_seed_demo.sql`
  - Then run migrations (recommended):
       - `srms/database/pg_migrations/001_rbac_attendance.sql`
       - `srms/database/pg_migrations/002_parent_sessions.sql`
       - `srms/database/pg_migrations/003_fees_finance.sql`
       - `srms/database/pg_migrations/004_results_locking.sql`
       - `srms/database/pg_migrations/005_exam_timetable.sql`
       - `srms/database/pg_migrations/007_exam_engine.sql`
       - `srms/database/pg_migrations/008_notifications.sql`
       - `srms/database/pg_migrations/009_communication.sql`
       - `srms/database/pg_migrations/010_library_inventory.sql`
       - `srms/database/pg_migrations/011_transport_fleet.sql`
       - `srms/database/pg_migrations/012_rbac_enterprise.sql`
       - `srms/database/pg_migrations/013_import_export.sql`
4. In DigitalOcean App Platform → **Environment Variables**, set:
   - `DB_DRIVER` (`mysql` or `pgsql`)
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
5. If your DB provider requires TLS, set `DB_SSL_MODE=REQUIRED`.
6. Optional (report verification):
  - `APP_URL` (public base URL, e.g. `https://your-app.ondigitalocean.app`)
   - `APP_SECRET` (used to hash report cards)
   - `REPORT_PRINCIPAL_SIGN` (filename under `srms/script/images/signatures/`)
   - `REPORT_TEACHER_SIGN` (filename under `srms/script/images/signatures/`)
   - `REPORT_SCHOOL_STAMP` (filename under `srms/script/images/stamps/`)

## Initial admin setup (no demo data)

If your DB has **no staff accounts**, create the first admin via:

- Open `/setup?token=YOUR_TOKEN`
- Set `SETUP_TOKEN` in DigitalOcean App Platform Environment Variables first

## Attendance + Parent Portal

- Teachers: `Teacher → Attendance` → create a session → mark and save
- Teachers: `Teacher → Staff Attendance` → clock in/out
- Students: `Student → My Attendance`
- Parents: Admin creates parent + links students in `Admin → Parents` (requires migrations 001 + 002)
- Admin: `Admin → Staff Attendance` → mark/update daily staff attendance

## Fees & Finance (Phase 3)

- Admin: `Admin → Fees & Finance`
  - Set fee items/amounts in `Admin → Fee Structure`
  - Generate invoices + record payments in `Admin → Invoices`
- Student: `Student → Fees`
- Parent: `Parent → Fees`

## Accountant role (Phase 4)

- Create an accountant from `Admin → Teachers` (now Staff) and choose Role = Accountant.
- Accountant login redirects to `/accountant`.
- Accountant can manage fee structure, invoices, and payments.

## Results analytics + ranking (Phase 6)

- Admin: `Admin → Results Analytics` (pick class + term to see ranking + charts)
- Student: `Student → My Ranking`
- Approvals: lock/unlock via `Admin → Results Locks` (requires `004_results_locking.sql`)

## Exam timetable (Phase 7)

- Run DB migration: `srms/database/pg_migrations/005_exam_timetable.sql`
- Admin: `Admin → Exam Timetable` (create entries per class + term)
- Teacher: `Teacher → Exam Timetable` (shows schedule for their subjects)
- Student: `Student → Exam Timetable` (shows schedule for their class)

## Audit logs (Phase 8)

- Requires DB migration `srms/database/pg_migrations/001_rbac_attendance.sql` (creates `tbl_audit_logs`)
- Admin: `Admin → Audit Logs`
- Auto logged events: login/logout, attendance, finance, timetable, results locks

## M-Pesa STK Push (Phase 9)

- Run DB migration: `srms/database/pg_migrations/006_mpesa_stk.sql`
- Admin: `Admin → M-Pesa` (configure)
- Callback URL: `https://YOUR-DOMAIN/api/mpesa_callback`
  - Optional security: set `MPESA_CALLBACK_TOKEN` env var and it will be required by the callback endpoint
- Invoices: `Admin/Accountant → Invoices` → **STK Push**
- Environment variables (recommended on DigitalOcean):
  - `MPESA_ENABLED=1`
  - `MPESA_ENV=sandbox` (or `live`)
  - `MPESA_SHORTCODE=...`
  - `MPESA_PASSKEY=...`
  - `MPESA_CONSUMER_KEY=...`
  - `MPESA_CONSUMER_SECRET=...`
  - `MPESA_CALLBACK_URL=https://YOUR-DOMAIN/api/mpesa_callback`

Notes:

- Uploads (student photos / logos) should use persistent storage (DigitalOcean Volumes or Spaces/object storage).

## Report cards (Exam engine)

1. Run migration: `srms/database/pg_migrations/007_exam_engine.sql`
2. Lock results: `Admin → Results Locks`
3. Generate report cards: `Admin → Report Tool → Generate Report Cards`
4. Student/Parent: `Report Card` menu
5. Verify by code: `/verify_report?code=YOUR_CODE`

## Exam settings + notifications

- Report settings: `Admin → Report Settings` (best-of, weights, fees lock)
- Exam management: `Admin → Exams` (types + create exams + status)
- Notifications: `Admin → Notifications`
- Auto alerts: report card generation triggers class notifications for students + parents

## Communication + Library + Inventory + Transport

- Communication: `Admin → Communication` (announcements, internal messages, SMS/email hooks)
- Library: `Admin → Library` (books + loans)
- Inventory: `Admin → Inventory` (assets + stock adjustments)
- Transport: `Admin → Transport` (vehicles, routes, stops, assignments)

## RBAC + Module Locks

- Run migration: `srms/database/pg_migrations/012_rbac_enterprise.sql`
- Super Admin: set `tbl_staff.level = 9` for the system owner (full access)
- Roles & permissions: use `tbl_roles`, `tbl_permissions`, `tbl_user_roles`
- Roles UI: `Admin → Roles & Permissions` (assign roles to staff)
- Module locks: `Admin → Module Locks` (requires `system.manage` permission)
- Migrations: `Admin → Migrations` (applies all Postgres migrations)

## Import / Export + PDF

- Run migration: `srms/database/pg_migrations/013_import_export.sql`
- Import/Export: `Admin → Import / Export`
- Imports supported: Students, Teachers, Marks, CBC assessments (CSV)
- Exports supported: Students (CSV/PDF), Results (CSV), CBC (CSV)

## Fix “module not installed” errors

- Open `Admin → Migrations`
- Click **Apply All Migrations**
- Reload the module page

## Vercel (frontend)

This system’s “frontend” is PHP-rendered pages, so it must run on the same PHP server (DigitalOcean/Apache) — Vercel won’t run PHP pages as a separate frontend.

If you want a true split (Vercel Next.js frontend + DigitalOcean API backend), you’d need to build a new frontend that talks to an API (bigger change).
