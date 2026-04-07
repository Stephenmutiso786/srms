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
    - Then run migrations (recommended):
       - `srms/database/pg_migrations/001_rbac_attendance.sql`
       - `srms/database/pg_migrations/002_parent_sessions.sql`
       - `srms/database/pg_migrations/003_fees_finance.sql`
       - `srms/database/pg_migrations/005_exam_timetable.sql`
3. In Render → Service → **Environment**, set:
   - `DB_DRIVER` (`mysql` or `pgsql`)
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
4. If your DB provider requires TLS, set `DB_SSL_MODE=REQUIRED`.

## Initial admin setup (no demo data)

If your DB has **no staff accounts**, create the first admin via:

- Open `/setup?token=YOUR_TOKEN`
- Set `SETUP_TOKEN` in Render Environment first

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

Notes:
- Uploads (student photos / logos) need persistent storage; Render’s filesystem is ephemeral unless you attach a disk or move uploads to object storage.

## Vercel (frontend)

This system’s “frontend” is PHP-rendered pages, so it must run on the same PHP server (Render/Apache) — Vercel won’t run PHP pages as a separate frontend.

If you want a true split (Vercel Next.js frontend + Render API backend), you’d need to build a new frontend that talks to an API (bigger change).
