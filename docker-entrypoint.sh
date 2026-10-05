#!/bin/sh
# Render boot script for the Laravel Docker image.
# - Rebinds Apache from port 80 to Render's $PORT.
# - Waits for the database, runs migrations (covers the database-backed
#   session/cache/queue tables), refreshes Laravel's caches.
# - Hands off to apache2-foreground.
#
# Every step is echoed to stdout so a boot failure is visible in the Render
# log even when APP_DEBUG=false hides the message from the browser.
#
# NOTE: `php artisan route:cache` is intentionally NOT run here because
# routes/web.php uses closure routes, which cannot be cached.
set -e

PORT="${PORT:-80}"

log() {
    echo "[entrypoint] $*"
}

log "booting on port ${PORT}"

# Apache must listen on Render's assigned port, not 80.
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# Surface the most common misconfiguration immediately instead of letting
# every request fail later with a generic 500.
if [ -z "${APP_KEY:-}" ]; then
    log "ERROR: APP_KEY is not set. Add it in the Render dashboard"
    log "       (generate one locally with: php artisan key:generate --show)"
    exit 1
fi

if [ -z "${DB_HOST:-}" ]; then
    log "ERROR: DB_HOST is not set. Add the Postgres variables in the Render dashboard."
    exit 1
fi

log "database: ${DB_CONNECTION:-unset} @ ${DB_HOST}:${DB_PORT:-5432}/${DB_DATABASE:-unset}"

# The managed database may not accept connections on the very first boot.
attempt=0
until php artisan migrate --force --no-interaction; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 5 ]; then
        log "ERROR: migrations failed after 5 attempts."
        log "Last 40 log lines:"
        tail -n 40 storage/logs/laravel.log 2>/dev/null || echo "(no laravel.log yet)"
        exit 1
    fi
    log "database not ready, retrying migrations in 5s (attempt ${attempt}/5)..."
    sleep 5
done
log "migrations complete"

# Cache warm-up must not block serving: a failure here is logged but the app
# still boots, since Laravel works fine without a cached config.
php artisan package:discover --no-interaction || log "WARNING: package:discover failed"
php artisan config:cache || log "WARNING: config:cache failed, continuing"
php artisan view:cache || log "WARNING: view:cache failed, continuing"
php artisan storage:link || log "WARNING: storage:link skipped"

chown -R www-data:www-data storage bootstrap/cache

log "starting: $*"
exec "$@"