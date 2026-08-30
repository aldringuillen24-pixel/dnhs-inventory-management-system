# Setup Guide

This project is a Laravel 12 inventory management system for DNHS. It uses a role-based web app, Blade views, Tailwind CSS, and a role-aware AI assistant connected to OpenRouter.

## Requirements

Before setup, make sure these are installed:

- PHP 8.2 or newer
- Composer
- Node.js 18+ and npm
- A local database server such as MySQL, PostgreSQL, or SQLite
- Git

## 1. Clone the repository

```bash
git clone <repo-url>
cd dnhs-inventory-management-system
```

## 2. Install PHP dependencies

```bash
composer install
```

## 3. Install frontend dependencies

```bash
npm install
```

## 4. Create the environment file

Copy the example environment file:

```bash
copy .env.example .env
```

On Linux/macOS:

```bash
cp .env.example .env
```

## 5. Generate the application key

```bash
php artisan key:generate
```

## 6. Configure the database

Update `.env` with your local database values. The default example is MySQL-based:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tailadmin_laravel
DB_USERNAME=root
DB_PASSWORD=
```

If you are using SQLite locally, you can configure:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/your/project/database/database.sqlite
```

Then create the database file if needed:

```bash
touch database/database.sqlite
```

## 7. Run migrations

```bash
php artisan migrate
```

If you want to start fresh with seeded demo data:

```bash
php artisan migrate:fresh --seed
```

## 8. Start the app

### Recommended development mode

```bash
composer run dev
```

This runs the Laravel server, queue worker, log monitoring, and Vite dev server together.

### Manual mode

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Then open:

```text
http://localhost:8000
```

## 9. Optional: create storage symlink

```bash
php artisan storage:link
```

## 10. AI assistant setup

The app supports a role-aware AI assistant through OpenRouter.

Add this to `.env`:

```env
OPENROUTER_API_KEY=your_api_key_here
OPENROUTER_MODEL=meta-llama/llama-3.3-70b-instruct:free
OPENROUTER_MAX_ATTEMPTS=2
```

If the key is not set, the AI assistant falls back to a grounded local response using app data instead of failing completely.

## 11. Useful development commands

```bash
php artisan route:list
php artisan test
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
```

## 12. Production build

```bash
npm run build
```

Optional Laravel optimization:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 13. Common troubleshooting

### Composer autoload issues

```bash
composer dump-autoload
```

### Database connection errors

- confirm `.env` values are correct
- ensure the database server is running
- confirm the database exists

### Frontend build errors

```bash
rm -rf node_modules package-lock.json
npm install
```

### Cache problems

```bash
php artisan optimize:clear
```

## 14. Important project files

These are the main starting points for local development:

- [routes/web.php](../routes/web.php)
- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)
- [app/Models/Inventory.php](../app/Models/Inventory.php)
- [app/Models/User.php](../app/Models/User.php)
- [app/Services/AiInventoryService.php](../app/Services/AiInventoryService.php)
- [config/services.php](../config/services.php)

This setup guide is meant to help a new developer get the app running quickly and understand the required local configuration for the inventory system.
