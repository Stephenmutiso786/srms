#!/usr/bin/env bash
# Apply migration 038: allow NULL scores and add has_score
# Usage: run from project root. Review the SQL before running.
set -euo pipefail
MIGRATION_PATH="srms/database/pg_migrations/038_allow_null_scores_and_add_has_score.sql"
if [ ! -f "$MIGRATION_PATH" ]; then
  echo "Migration file not found: $MIGRATION_PATH"
  exit 1
fi

echo "BACKUP your database before proceeding. Examples:"
echo "MySQL: mysqldump -u root -p srms_db > srms_db.backup.sql"
echo "Postgres: pg_dump -U postgres -d srms_db -f srms_db.backup.sql"

echo
read -p "Proceed to apply migration to PostgreSQL? (y/N) " yn
if [[ "$yn" =~ ^[Yy]$ ]]; then
  read -p "Postgres connection string (e.g. postgresql://user:pass@host:port/dbname): " conn
  psql "$conn" -f "$MIGRATION_PATH"
  echo "Migration applied to PostgreSQL."
fi

read -p "Proceed to apply migration to MySQL/MariaDB? (y/N) " yn2
if [[ "$yn2" =~ ^[Yy]$ ]]; then
  read -p "MySQL host:port (default localhost:3306): " mysql_host
  mysql_host=${mysql_host:-localhost:3306}
  read -p "MySQL user: " mysql_user
  read -s -p "MySQL password: " mysql_pass
  echo
  read -p "MySQL database name: " mysql_db
  mysql -h "${mysql_host%%:*}" -P "${mysql_host##*:}" -u "$mysql_user" -p"$mysql_pass" "$mysql_db" < "$MIGRATION_PATH"
  echo "Migration applied to MySQL/MariaDB."
fi

echo "Done. Verify your data and webapp."