#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/storage/app/backups}"
SKIP_BACKUP="${SKIP_BACKUP:-0}"

cd "$APP_DIR"

env_value() {
  # Read a value from .env without sourcing it (values may contain shell characters).
  grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

echo "[1/9] Checking production environment"
if [[ ! -f .env ]]; then
  echo "ERROR: .env is missing. Copy .env.production.example to .env and configure it first." >&2
  exit 1
fi

if grep -Eq '^APP_DEBUG=(true|1)$' .env; then
  echo "ERROR: APP_DEBUG must be false in production." >&2
  exit 1
fi

if grep -Eq '^APP_ENV=(local|testing)$' .env; then
  echo "ERROR: APP_ENV must be production." >&2
  exit 1
fi

echo "[2/9] Entering maintenance mode"
$PHP_BIN artisan down --retry=60 || true

deployed=0
on_exit() {
  if [[ "$deployed" == "1" ]]; then
    $PHP_BIN artisan up || true
  else
    # Leave the site down so nobody uses a half-deployed release.
    echo "DEPLOYMENT FAILED: the site is still in maintenance mode." >&2
    echo "Fix the problem and re-run this script, or restore the backup in $BACKUP_DIR and run 'php artisan up'." >&2
  fi
}
trap on_exit EXIT

echo "[3/9] Backing up the database"
if [[ "$SKIP_BACKUP" == "1" ]]; then
  echo "Skipped (SKIP_BACKUP=1)."
elif [[ "$(env_value DB_CONNECTION)" == "mysql" ]] && command -v mysqldump >/dev/null 2>&1; then
  mkdir -p "$BACKUP_DIR"
  backup_file="$BACKUP_DIR/db-$(date +%Y%m%d-%H%M%S).sql.gz"
  MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump \
    --single-transaction --quick --routines \
    -h "$(env_value DB_HOST)" -P "$(env_value DB_PORT)" -u "$(env_value DB_USERNAME)" \
    "$(env_value DB_DATABASE)" | gzip > "$backup_file"
  echo "Saved $backup_file"
  # Keep the 10 most recent backups.
  ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
else
  echo "ERROR: cannot back up automatically (needs MySQL and mysqldump)." >&2
  echo "Back up the database yourself, then re-run with SKIP_BACKUP=1." >&2
  exit 1
fi

echo "[4/9] Installing PHP dependencies"
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "[5/9] Building frontend assets"
if command -v "$NPM_BIN" >/dev/null 2>&1; then
  $NPM_BIN ci
  $NPM_BIN run build
else
  echo "npm is unavailable; using the committed public/build assets."
fi

echo "[6/9] Running database migrations"
$PHP_BIN artisan migrate --force

echo "[7/9] Refreshing Laravel caches"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "[8/9] Ensuring storage link and restarting queue workers"
$PHP_BIN artisan storage:link || true
$PHP_BIN artisan queue:restart

echo "[9/9] Deployment checks"
$PHP_BIN artisan route:list --path=api/v1 >/dev/null
$PHP_BIN artisan subscriptions:expire || true
$PHP_BIN artisan payments:reconcile --hours=48 || true

deployed=1
echo "Deployment completed successfully. Check $(env_value APP_URL)/health"
