#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/../srms/script"
PHP_BIN="${SRMS_PHP_BIN:-php}"
PORT="${SRMS_PORT:-8000}"

cd "$APP_DIR"
exec "$PHP_BIN" -S "127.0.0.1:$PORT" router.php

