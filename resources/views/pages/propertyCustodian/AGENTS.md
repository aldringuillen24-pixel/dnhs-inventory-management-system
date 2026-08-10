# Property Custodian Views

## Scope

These rules apply only to `resources/views/pages/propertyCustodian` and its subdirectories.

## Views

- `dashboard.blade.php`: Property Custodian overview.
- `inventory.blade.php`: Inventory list and item-status interface.
- `transactions.blade.php`: Inventory transaction history interface.
- `onboarding.blade.php`: Required first-login account setup.

## Conventions

- Extend and use the existing Blade layouts and components; do not duplicate shared navigation or page shells.
- Reusable Blade components are located in `resources/views/components`; check this directory before creating new view markup.
- Shared application layouts are located in `resources/views/layouts`.
- Use existing Tailwind CSS and Alpine.js patterns before introducing new styling or JavaScript.
- Keep views presentational: validation, authorization, queries, and inventory updates belong in routes, controllers, Form Requests, models, or services.
- Use named routes with the `propertyCustodian.` prefix.
- Display session success/error messages and validation errors consistently with adjacent views.
- Keep all labels, empty states, and status indicators clear for school property custodians.

## Before Editing

1. Review the target view, its layout/components, the related route, and `PropertyCustodianController`.
2. Identify the required data and ensure it is passed by the controller.
3. Preserve the Property Custodian role boundary and onboarding flow.
4. Add or update a focused feature test when a UI change affects behavior, routes, validation, or authorization.

## Future Inventory Database Work

- Create new item and inventory-transaction migrations in `database/migrations` when persistence is introduced.
- Add Property Custodian inventory routes in `routes/web.php` within the existing `propertyCustodian.` route group.
- Add controller actions to `app/Http/Controllers/PropertyCustodianController.php` or a dedicated inventory controller when the feature grows.
- Add Eloquent models and relationships in `app/Models` when items, assignments, or stock movements require persistence.
- Connect modal forms only after the corresponding route, validation, authorization, controller action, and database schema exist.
