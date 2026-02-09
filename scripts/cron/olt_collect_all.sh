#!/usr/bin/env bash
set -euo pipefail

# Run OLT collector safely from cron.
# Example crontab (run every 10 minutes):
# */10 * * * * /var/www/NetpulseMultiOptical/scripts/cron/olt_collect_all.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/logs storage/app

LOCK_FILE="storage/app/olt_collect_all.lock"
LOG_FILE="storage/logs/olt-collect-all.log"

if command -v flock >/dev/null 2>&1; then
  flock -n "$LOCK_FILE" bash -lc "php artisan olt:collect-all >> \"$LOG_FILE\" 2>&1"
else
  # Fallback: no locking if flock isn't available.
  php artisan olt:collect-all >> "$LOG_FILE" 2>&1
fi
