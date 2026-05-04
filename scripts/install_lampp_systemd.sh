#!/usr/bin/env bash
# Install and enable lampp.service for XAMPP autostart on boot (systemd)
# Usage: sudo bash scripts/install_lampp_systemd.sh
set -euo pipefail

SERVICE_FILE="/etc/systemd/system/lampp.service"

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root: sudo bash scripts/install_lampp_systemd.sh"
  exit 1
fi

cat > "$SERVICE_FILE" <<'EOF'
[Unit]
Description=XAMPP (LAMP) stack
After=network.target

[Service]
Type=forking
ExecStart=/opt/lampp/lampp start
ExecStop=/opt/lampp/lampp stop
ExecReload=/opt/lampp/lampp restart
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable lampp.service
systemctl start lampp.service
systemctl status -l lampp.service

echo "lampp.service installed and started. XAMPP will now autostart on boot."