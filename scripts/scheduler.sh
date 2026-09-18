#!/usr/bin/env bash
set -euo pipefail
SPOT_PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SPOT_PROJECT_DIR"
exec 9>storage/framework/spot-scheduler.lock
flock -n 9 || exit 0
trap 'exit 0' TERM INT
while true; do
  "$SPOT_PROJECT_DIR/php" artisan schedule:run || true
  sleep 60 &
  wait $! || true
done
