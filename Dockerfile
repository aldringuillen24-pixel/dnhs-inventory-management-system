# Production image for the DNHS Inventory Management System (Laravel 12, PHP 8.2).
#
# Multi-stage build:
#   1. frontend — Node 20 compiles the Vite/Vue/Tailwind assets (public/build).
#   2. vendor    — Composer installs PHP production dependencies only.
#   3. runtime   — php:8.2-apache serves Laravel from /public, with Python 3
#                  available for the ML demand-forecast trainer (ml/*.py).
#
# No secrets are baked in. All configuration comes from environment variables
# supplied by Render (see render.yaml). The .env file is never copied.

# ---------------------------------------------------------------- frontend ---
FROM node:20-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ------------------------------------------------------------------ vendor ---
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi

# ------------------------------------------------------------------ runtime ---
FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    FORECAST_PYTHON_BINARY=python3

# System libraries, PHP extensions required by composer.json/lock
# (dompdf: gd/zip/mbstring/dom — queue worker: pcntl — i18n: intl),
# plus Python 3 for ml/forecast_demand.py (scikit-learn, joblib).
# NOTE: tokenizer is compiled first and alone. Building it in parallel (-j)
# together with the other Zend-based extensions races on zend_language_parser
# and fails the whole build ("No rule to make target ... zend_language_parser.y").
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libxml2-dev libzip-dev libonig-dev libcurl4-openssl-dev \
        libicu-dev libpq-dev \
        python3 python3-pip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install tokenizer \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath ctype curl dom fileinfo filter gd intl mbstring \
        opcache pcntl pdo pdo_mysql pdo_pgsql xml zip \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production OPcache defaults.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html

# Application source (vendor/, node_modules/ and .env are excluded
# via .dockerignore), then the built artifacts from the earlier stages.
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Python ML dependencies for the demand-forecast trainer.
# (--break-system-packages is required on Debian bookworm images.)
RUN pip3 install --no-cache-dir --break-system-packages -r ml/requirements.txt

# Writable directories for the web server user.
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
        storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
