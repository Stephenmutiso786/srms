#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: bash set-server-url.sh http://LAN-IP/srms/script/"
  exit 1
fi

URL="$1"
CONFIG_FILE="capacitor.config.json"

if [[ ! -f "${CONFIG_FILE}" ]]; then
  echo "Could not find ${CONFIG_FILE}."
  exit 1
fi

node -e "
const fs = require('fs');
const file = '${CONFIG_FILE}';
const nextUrl = process.argv[1];
const data = JSON.parse(fs.readFileSync(file, 'utf8'));
data.server = data.server || {};
data.server.url = nextUrl;
fs.writeFileSync(file, JSON.stringify(data, null, 2) + '\n');
" "${URL}"

echo "Updated server.url to ${URL}"

if [[ -d android ]]; then
  npx cap sync android
fi
