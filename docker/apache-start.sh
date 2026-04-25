#!/usr/bin/env bash
set -e

# Railway/runtime safety: ensure exactly one Apache MPM is active.
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

exec apache2-foreground
