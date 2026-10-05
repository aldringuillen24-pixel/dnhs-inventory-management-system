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

# Sessions and cache are stored in the database (config/session.php and
# config/cache.php default to the database driver). A missing DB_CONNECTION
# silently falls back to sqlite, which then 500s on every request because the
# .env file is never copied into the image.
if [ "${DB_CONNECTION:-sqlite}" != "pgsql" ] && [ "${DB_CONNECTION:-sqlite}" != "mysql" ]; then
    log "ERROR: DB_CONNECTION is '${DB_CONNECTION:-unset}' but must be pgsql or mysql."
    exit 1
fi

log "database: ${DB_CONNECTION} @ ${DB_HOST}:${DB_PORT:-5432}/${DB_DATABASE:-unset}"

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

# Confirm the extensions composer.json/lock need are really present. Several
# (ctype, dom, fileinfo, filter, pdo, xml, tokenizer) ship with php:8.2-apache,
# but dompdf needs ext-dom and ext-gd, so a missing one must fail loudly here
# instead of as an opaque 500 later.
for ext in bcmath curl dom fileinfo gd intl json mbstring openssl pdo pdo_pgsql tokenizer xml zip; do
    if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
        log "ERROR: required PHP extension '$ext' is not loaded."
        exit 1
    fi
done
log "PHP extension check passed"

# Initial administrator bootstrap. Render Free has no shell, so the account is
# created here on boot instead of by hand.
#
# Safety properties:
#   - Gated on ADMIN_PASSWORD, so removing that env var disables this entirely.
#   - RoleSeeder uses firstOrCreate, and AdminUserSeeder matches on `username`
#     and returns early when the user exists. Both are no-ops once the account
#     is present, so redeploys never duplicate the admin or reset its password.
#   - AdminUserSeeder alone is run, never DatabaseSeeder, so no demo inventory
#     data is written to the production database.
#   - Seeding must never block booting: a failure is logged and the app starts.
if [ -n "${ADMIN_PASSWORD:-}" ]; then
    log "admin bootstrap enabled (ADMIN_USERNAME=${ADMIN_USERNAME:-admin})"

    if php artisan db:seed --class="Database\\Seeders\\RoleSeeder" --force --no-interaction; then
        php artisan db:seed --class="Database\\Seeders\\AdminUserSeeder" --force --no-interaction \
            || log "WARNING: AdminUserSeeder did not complete, continuing"
    else
        log "WARNING: RoleSeeder failed, skipping admin creation"
    fi
else
    log "admin bootstrap disabled (ADMIN_PASSWORD not set)"
fi

# Cache warm-up must not block serving: a failure here is logged but the app
# still boots, since Laravel works fine without a cached config.
php artisan package:discover --no-interaction || log "WARNING: package:discover failed"
php artisan config:cache || log "WARNING: config:cache failed, continuing"
php artisan view:cache || log "WARNING: view:cache failed, continuing"
php artisan storage:link || log "WARNING: storage:link skipped"

chown -R www-data:www-data storage bootstrap/cache

log "starting: $*"
exec "$@"