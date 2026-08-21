#!/usr/bin/env bash

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# FrankenPHP's php-cli SAPI currently exposes an empty PHP_BINARY. Laravel's
# `artisan test` command uses that constant to start PHPUnit and consequently
# calls proc_open() with an empty program name. Run PHPUnit through the same
# portable runtime directly so the documented test command works reliably.
if [ "${1:-}" = "artisan" ] && [ "${2:-}" = "test" ]; then
  shift 2
  exec "$PROJECT_DIR/.runtime/bin/frankenphp" php-cli \
    "$PROJECT_DIR/vendor/bin/phpunit" "$@"
fi

exec "$PROJECT_DIR/.runtime/bin/frankenphp" php-cli "$@"
