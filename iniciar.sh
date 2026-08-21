#!/usr/bin/env bash

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PATH="$PROJECT_DIR/.runtime/bin:$PATH"

cd "$PROJECT_DIR"

if [ ! -f .env ]; then
  cp .env.example .env
  "$PROJECT_DIR/.runtime/bin/frankenphp" php-cli artisan key:generate
fi

"$PROJECT_DIR/.runtime/bin/frankenphp" php-server \
  --root "$PROJECT_DIR/public" \
  --listen 0.0.0.0:8000 &
BACKEND_PID=$!

cleanup() {
  kill "$BACKEND_PID" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

echo "Spot disponível em http://localhost:8000"
npm run dev -- --host 0.0.0.0
