# Property Custodian — Views & Backend

## Scope

Applies to `resources/views/pages/propertyCustodian` and related backend (controllers, routes, models).

## Compact Structure

- Views: `dashboard.blade.php`, `inventory.blade.php`, `transactions.blade.php`, `onboarding.blade.php`.
- Controllers: `PropertyCustodianController` (or dedicated controllers under `app/Http/Controllers`).
- Models: `Inventory`, `Category`, `Transaction`, `User`, `Role` (in `app/Models`).
- Components & layouts: reuse `resources/views/components` and `resources/views/layouts`.
- Routes: HTTP URL prefix `property-custodian` and named-route prefix `propertyCustodian.` (protect with `auth` + `role:Property Custodian`).

## Backend Login & Onboarding (summary)

- Authentication: uses standard Laravel auth (`/login`) with role-based gating via `role:Property Custodian` middleware.
- Onboarding: accounts seeded with `temporary_password` must complete `onboarding.blade.php`; controllers should detect `temporary_password` and redirect to onboarding until cleared.
- Authorization: protect all property-custodian routes with `auth` and the role middleware; use Form Requests or controller validation for modal forms.

## Editing Guidelines (short)

- Keep views presentational; place validation, queries, and persistence in controllers, Form Requests, or models.
- Reuse existing Blade components and Tailwind/Alpine patterns; prefer named routes over hard-coded URLs.
- Add focused Pest feature tests for any route/validation/authorization changes.

## Notes

- Only wire modal forms to routes/controllers after corresponding backend actions and migrations exist.
- Use database migrations for schema changes and transactions for multi-record inventory updates.
