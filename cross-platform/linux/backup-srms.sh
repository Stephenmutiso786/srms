#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$ROOT_DIR/backups"
mkdir -p "$BACKUP_DIR"

STAMP="$(date +%Y%m%d-%H%M%S)"
tar -czf "$BACKUP_DIR/srms-backup-$STAMP.tar.gz" \
  -C "$ROOT_DIR/.." srms/script srms/database

echo "Backup created: $BACKUP_DIR/srms-backup-$STAMP.tar.gz"

