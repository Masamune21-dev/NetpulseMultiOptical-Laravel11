#!/usr/bin/env bash
set -euo pipefail

# Run Laravel scheduler from cron.
# Example crontab (run every minute):
# * * * * * /var/www/NetpulseMultiOptical/scripts/cron/laravel_schedule_run.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/logs storage/app

LOCK_FILE="storage/app/laravel_schedule_run.lock"
LOG_FILE="storage/logs/schedule-run.log"

if command -v flock >/dev/null 2>&1; then
  flock -n "$LOCK_FILE" bash -lc "php artisan schedule:run >> \"$LOG_FILE\" 2>&1"
else
  php artisan schedule:run >> "$LOG_FILE" 2>&1
fi

