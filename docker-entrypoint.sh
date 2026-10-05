#!/bin/sh
# Render boot script for the Laravel Docker image.
# - Rebinds Apache from port 80 to Render's $PORT.
# - Waits for the database, runs migrations (covers the database-backed
#   session/cache/queue tables), refreshes Laravel's caches.
# - Hands off to apache2-foreground.
#
# NOTE: `php artisan route:cache` is intentionally NOT run here because
# routes/web.php uses closure routes, which cannot be cached.
set -e

PORT="${PORT:-80}"

# Apache must listen on Render's assigned port, not 80.
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# The managed database may not accept connections on the very first boot.
attempt=0
until php artisan migrate --force; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 5 ]; then
        echo "Migrations failed after 5 attempts, giving up."
        exit 1
    fi
    echo "Database not ready, retrying migrations in 5s (attempt ${attempt}/5)..."
    sleep 5
done

php artisan package:discover --ansi || true
php artisan config:cache
php artisan view:cache
php artisan storage:link || true

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
