# DNHS Inventory Management System

## Project Overview

This is a Laravel 12 inventory management system for DNHS. It currently provides role-based access for Administrators and Property Custodians, account onboarding, user management, and PDF account-slip exports.

## Technology Stack

- PHP 8.2 and Laravel 12
- Blade templates in `resources/views`
- Tailwind CSS v4, Alpine.js, and Vite
- Eloquent ORM and database migrations
- Pest PHP for tests
- Dompdf for PDF generation

## Application Structure

- Routes: `routes/web.php`
- Controllers: `app/Http/Controllers`
- Models: `app/Models`
- Migrations: `database/migrations`
- Views: `resources/views`
- Tests: `tests/Feature` and `tests/Unit`

## Roles and Access

- `Administrator` routes use the `admin` prefix and `role:Administrator` middleware.
- `Property Custodian` routes use the `property-custodian` prefix and `role:Property Custodian` middleware.
- Keep new protected routes inside the appropriate authentication and role middleware group.
- Property Custodians with a `temporary_password` must complete onboarding before using their dashboard.

## Data Conventions

- The `roles` table primary key is `role_id`; use it in foreign keys and Eloquent relationships.
- The `users` table uses separate `first_name` and `last_name` fields, plus `username`, `status`, and `temporary_password`.
- Add schema changes through new migrations; do not edit migrations that may already have run.
- Validate all request data in controllers or Form Requests before persisting it.
- Use database transactions for multi-record inventory updates or stock movements.
- Prevent stock quantities from becoming negative and retain an auditable transaction history when inventory features are added.

## Implementation Guidelines

- Follow Laravel conventions and existing formatting.
- Use Eloquent relationships and eager-load relations used by views.
- Use named routes and redirect helpers rather than hard-coded URLs.
- Keep business logic out of Blade views.
- Use Blade components and Tailwind utilities consistent with the existing TailAdmin layout.
- Apply authorization middleware or policies to every role-sensitive action.
- Never expose passwords or `temporary_password` values in logs, views, or API responses.

## Required Change Workflow

Before implementing any change:

1. Review the relevant routes, controllers, models, migrations, views, and existing tests.
2. Identify the current behavior, affected user roles, validation rules, and database impact.
3. Explain the proposed approach and affected files before editing.
4. Run the most relevant existing tests first when tests are available.

When implementing a change:

1. Keep the change focused and preserve existing behavior unless the requested change requires otherwise.
2. Add or update tests for new behavior, validation, authorization, and regressions.
3. Run focused tests after implementation, then run the broader test suite when practical.
4. Report changed files, test results, and any unresolved risks.

## Commands

```bash
composer run dev
php artisan test
./vendor/bin/pest
./vendor/bin/pint
npm run dev
npm run build
```

## Testing Expectations

- Add or update Pest feature tests for changed routes, validation, authorization, and inventory behavior.
- Use the in-memory SQLite test database configured in `phpunit.xml`.
- Run the most relevant test file first, then run the broader suite when practical.
