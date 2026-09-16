#!/usr/bin/env bash
set -euo pipefail
PHP_BIN="${PHP_BIN:-php}"

$PHP_BIN artisan about
$PHP_BIN artisan migrate:status
$PHP_BIN artisan route:list --path=api/v1
$PHP_BIN artisan schedule:list || true
$PHP_BIN artisan queue:failed || true

echo
printf '%s\n' "Required cron:" "* * * * * cd $(pwd) && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
printf '%s\n' "Recommended persistent worker:" "$PHP_BIN artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600"
