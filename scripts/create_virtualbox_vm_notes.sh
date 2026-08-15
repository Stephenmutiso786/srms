#!/usr/bin/env bash
set -euo pipefail

cat <<'EOF'
VirtualBox VM workflow for SRMS:
1. Create the VM in VirtualBox.
2. Install Windows or Ubuntu.
3. Copy the SRMS repo into the VM.
4. Install PHP and MySQL inside the guest.
5. Open the app locally and verify login.
6. Take a snapshot named "SRMS Ready".
7. Export the VM or clone it to other machines.
EOF

