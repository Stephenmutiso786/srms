#!/bin/bash

# School Records Management System - Database Migration Runner
# This script executes all pending PostgreSQL/MySQL migrations

DB_HOST="${DB_HOST:=127.0.0.1}"
DB_PORT="${DB_PORT:=3306}"
DB_USER="${DB_USER:=root}"
DB_PASS="${DB_PASS:=}"
DB_NAME="${DB_NAME:=srms}"
DB_DRIVER="${DB_DRIVER:=mysql}"

MIGRATIONS_DIR="./srms/database/pg_migrations"

if [ ! -d "$MIGRATIONS_DIR" ]; then
    echo "Error: Migrations directory not found at $MIGRATIONS_DIR"
    exit 1
fi

echo "========================================="
echo "SRMS Database Migration Runner"
echo "========================================="
echo "Database: $DB_NAME"
echo "Host: $DB_HOST"
echo "Driver: $DB_DRIVER"
echo ""

# Count total migrations
TOTAL_MIGRATIONS=$(ls "$MIGRATIONS_DIR"/*.sql 2>/dev/null | wc -l)
echo "Found $TOTAL_MIGRATIONS migration files"
echo ""

# Run migrations
if [ "$DB_DRIVER" = "mysql" ]; then
    for migration_file in $(ls "$MIGRATIONS_DIR"/*.sql | sort); do
        migration_name=$(basename "$migration_file")
        echo "⏳ Applying: $migration_name..."
        
        if [ -z "$DB_PASS" ]; then
            mysql -u "$DB_USER" -h "$DB_HOST" -P "$DB_PORT" "$DB_NAME" < "$migration_file" 2>&1
        else
            mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" "$DB_NAME" < "$migration_file" 2>&1
        fi
        
        if [ $? -eq 0 ]; then
            echo "✓ Completed: $migration_name"
        else
            echo "⚠ Warning/Info: $migration_name (some constraints/tables may already exist)"
        fi
        echo ""
    done
elif [ "$DB_DRIVER" = "pgsql" ]; then
    for migration_file in $(ls "$MIGRATIONS_DIR"/*.sql | sort); do
        migration_name=$(basename "$migration_file")
        echo "⏳ Applying: $migration_name..."
        
        PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -f "$migration_file" 2>&1
        
        if [ $? -eq 0 ]; then
            echo "✓ Completed: $migration_name"
        else
            echo "⚠ Warning/Info: $migration_name (some constraints/tables may already exist)"
        fi
        echo ""
    done
else
    echo "Error: Unknown database driver: $DB_DRIVER"
    exit 1
fi

echo "========================================="
echo "Migration process complete!"
echo "========================================="
