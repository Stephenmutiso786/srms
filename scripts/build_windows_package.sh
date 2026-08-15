#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
STAGE_DIR="${1:-$ROOT_DIR/windows-build}"

mkdir -p "$STAGE_DIR/app" "$STAGE_DIR/installer" "$STAGE_DIR/runtime/php"
cp -R "$ROOT_DIR/srms/script/." "$STAGE_DIR/app/"
cp "$ROOT_DIR/srms/database/srms_mysql_schema_clean.sql" "$STAGE_DIR/installer/"

cat <<EOF
Windows staging is ready at: $STAGE_DIR
Next:
1. Copy a portable PHP runtime into $STAGE_DIR/runtime/php
2. Open windows/SRMS.iss in Inno Setup on Windows
3. Compile SRMS-Setup.exe
EOF

