#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash scripts/setup_xampp_https.sh [LAN_IP]"
  exit 1
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSL_DIR="${REPO_ROOT}/srms/ssl"
XAMPP_ETC="/opt/lampp/etc"
SSL_CONF="${XAMPP_ETC}/extra/httpd-ssl.conf"
XAMPP_CERT="${XAMPP_ETC}/ssl.crt/srms-local.crt"
XAMPP_KEY="${XAMPP_ETC}/ssl.key/srms-local.key"

mkdir -p "${SSL_DIR}"

detect_ip() {
  hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)' | head -n 1
}

LAN_IP="${1:-}"
if [[ -z "${LAN_IP}" ]]; then
  LAN_IP="$(detect_ip || true)"
fi

if [[ -z "${LAN_IP}" ]]; then
  read -rp "Enter the LAN IP other devices should use: " LAN_IP
fi

if [[ ! "${LAN_IP}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
  echo "Invalid IPv4 address: ${LAN_IP}"
  exit 1
fi

TMP_OPENSSL="$(mktemp)"
trap 'rm -f "${TMP_OPENSSL}"' EXIT

cat > "${TMP_OPENSSL}" <<EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
x509_extensions = v3_req

[dn]
C = KE
ST = Nairobi
L = Nairobi
O = SRMS Local
OU = XAMPP
CN = ${LAN_IP}

[v3_req]
subjectAltName = @alt_names
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth

[alt_names]
IP.1 = ${LAN_IP}
DNS.1 = localhost
DNS.2 = srms.local
EOF

openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
  -keyout "${SSL_DIR}/srms-local.key" \
  -out "${SSL_DIR}/srms-local.crt" \
  -config "${TMP_OPENSSL}" >/dev/null 2>&1

cp "${SSL_DIR}/srms-local.crt" "${XAMPP_CERT}"
cp "${SSL_DIR}/srms-local.key" "${XAMPP_KEY}"
chmod 644 "${XAMPP_CERT}"
chmod 600 "${XAMPP_KEY}"

cp "${SSL_CONF}" "${SSL_CONF}.bak.$(date +%Y%m%d%H%M%S)"

sed -i "s#^ServerName .*#ServerName ${LAN_IP}:443#" "${SSL_CONF}"
sed -i "s#^SSLCertificateFile .*#SSLCertificateFile \"${XAMPP_CERT}\"#" "${SSL_CONF}"
sed -i "s#^SSLCertificateKeyFile .*#SSLCertificateKeyFile \"${XAMPP_KEY}\"#" "${SSL_CONF}"

/opt/lampp/bin/httpd -t
/opt/lampp/lampp restartapache

cat <<EOF

HTTPS is ready for SRMS on XAMPP.

Open from this computer:
  https://localhost/srms/script/

Open from other devices:
  https://${LAN_IP}/srms/script/

If your browser warns about the local certificate, accept it once for local testing.
EOF
