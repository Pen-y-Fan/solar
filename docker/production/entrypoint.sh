#!/bin/sh
set -e

cd /var/www/html

if [ ! -f storage/oauth-private.key ] && [ -z "${APP_KEY:-}" ]; then
    echo "WARNING: APP_KEY is not set. Set it in .env (php artisan key:generate --show) before going live." >&2
fi

# storage/ and bootstrap/cache/ must be writable by the app user even when
# the whole tree arrives read-only from a bind mount or fresh volume.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
[ -L public/storage ] || php artisan storage:link --force || true

# WAL mode makes SQLite far more resilient to a hard power loss (writes are
# appended to a separate log and checkpointed later, instead of rewriting the
# main file in place). Idempotent — safe to run on every boot.
DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -f "$DB_FILE" ]; then
    php -r '$pdo = new PDO("sqlite:" . $argv[1]); $pdo->exec("PRAGMA journal_mode=WAL;");' "$DB_FILE" \
        || echo "WARNING: failed to enable SQLite WAL mode on $DB_FILE" >&2
fi

# Cache config/routes/views for production performance. These are safe to
# regenerate on every boot and must NOT be committed to the image, since
# they bake in the runtime environment.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Solar entrypoint ready. Run 'docker compose exec app php artisan migrate --force' manually the first time (and after the SQL dump is loaded) — migrations are intentionally NOT run automatically so a pre-loaded database is never touched without confirmation."

exec "$@"
