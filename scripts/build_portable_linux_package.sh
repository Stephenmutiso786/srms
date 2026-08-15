#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
STAGE_DIR="${1:-$ROOT_DIR/linux-build}"

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR/app" "$STAGE_DIR/runtime" "$STAGE_DIR/data" "$STAGE_DIR/backups"

cp -R "$ROOT_DIR/srms/script/." "$STAGE_DIR/app/"
cp -R "$ROOT_DIR/srms/database" "$STAGE_DIR/"
cp "$ROOT_DIR/cross-platform/linux/start-srms.sh" "$STAGE_DIR/start-srms.sh"
cp "$ROOT_DIR/cross-platform/linux/backup-srms.sh" "$STAGE_DIR/backup-srms.sh"
chmod +x "$STAGE_DIR/start-srms.sh" "$STAGE_DIR/backup-srms.sh"

cat <<EOF
Portable Linux package staged at: $STAGE_DIR
Next:
1. Put PHP/MySQL binaries into $STAGE_DIR/runtime if you want fully local mode
2. Run ./start-srms.sh
3. Run ./backup-srms.sh when you want an archive
EOF

