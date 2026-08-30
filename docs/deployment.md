# Deployment Guide

This project is a Laravel application intended to run on a conventional PHP hosting environment or a managed server with Node.js support. It is structured to be deployed with Laravel’s standard production workflow, plus the frontend Vite build step.

## 1. Deployment assumptions

The app expects:

- PHP 8.2+
- Composer
- Node.js 18+
- a supported database such as MySQL, PostgreSQL, or SQLite
- a web server such as Apache or Nginx
- writable permissions for `storage/` and `bootstrap/cache/`

The project includes Laravel 12, Tailwind CSS, Vite, and a queue configuration using the database driver.

## 2. Required production environment variables

Create a production `.env` file with at least:

```env
APP_NAME="DNHS Inventory Management System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_generated_key_here
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

OPENROUTER_API_KEY=your_api_key_here
OPENROUTER_MODEL=meta-llama/llama-3.3-70b-instruct:free
OPENROUTER_MAX_ATTEMPTS=2
```

## 3. Build the frontend assets

Before production deployment, build the front-end bundle:

```bash
npm install
npm run build
```

This generates the production asset output used by Vite and Laravel.

## 4. Install PHP dependencies

```bash
composer install --optimize-autoloader --no-dev
```

## 5. Generate application key

If the key is not already present:

```bash
php artisan key:generate
```

## 6. Run Laravel migrations

```bash
php artisan migrate --force
```

If the schema has not been created yet, this should be run after the database is reachable.

## 7. Cache Laravel configuration

Run the production cache commands:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 8. Storage permissions

Ensure the web server can write to:

```bash
storage/
bootstrap/cache/
```

Typical Linux permissions:

```bash
chmod -R 775 storage bootstrap/cache
```

## 9. Queue worker

Because the app uses a database queue configuration, the worker should run in production:

```bash
php artisan queue:work --tries=3
```

This is important if background jobs or queued actions are used by the system.

## 10. Optional storage link

If uploads or generated files depend on a public storage path:

```bash
php artisan storage:link
```

## 11. Recommended hosting pattern

For a typical deployment, the application is served by PHP-FPM behind Nginx or Apache, with:

- public web root pointing to `public/`
- app root pointing to the Laravel project directory
- database connection configured in `.env`
- Node build completed before deployment

## 12. Production health checks

After deployment, verify the following:

```bash
php artisan --version
php artisan route:list
php artisan config:show app.env
php artisan test
```

Also confirm:

- homepage loads without errors
- authentication works
- role routes redirect correctly
- inventory pages render
- AI assistant works with the configured API key or fallback mode

## 13. Common production issues

### 1. 500 errors after deployment

Check:

```bash
php artisan optimize:clear
php artisan config:cache
```

### 2. Missing APP_KEY

Generate a key:

```bash
php artisan key:generate
```

### 3. Database connection issues

Verify:

```env
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

### 4. Frontend assets not loading

Run:

```bash
npm run build
```

Then confirm the `public/build` artifacts exist.

## 14. Rollout recommendation

For production releases, use this sequence:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## 15. Summary

This app is a standard Laravel production deployment with one extra frontend build step. The most important production concerns are:

- the `.env` production configuration
- database access and migration execution
- asset compilation with Vite
- queue workers
- proper storage and permission settings
- AI key configuration when the assistant is enabled

This project is straightforward to deploy as a normal Laravel application, as long as the environment is prepared correctly and the production key/cache steps are followed.
