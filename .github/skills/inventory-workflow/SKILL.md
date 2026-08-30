---
name: inventory-workflow
description: "Use when implementing or reviewing DNHS inventory stock-in, assignments, transactions, categories, reports, edit/delete flows, quantity rules, migrations, or related Pest tests."
---
# Inventory Workflow

Use this workflow for changes involving inventory records or stock movement.

## Inspect
- Read the route in `routes/web.php`.
- Trace the controller action, model relationships, migrations, and target Blade view.
- Check nearby Feature tests and role middleware.
- Confirm `Inventory` uses `item_id` as its primary key and assignments use `assigned_to_user_id` where appropriate.

## Design Checks
- Validate all request data before persistence.
- Use database transactions for multi-record stock or assignment changes.
- Prevent negative quantities and preserve transaction history.
- Keep Administrator, Property Custodian, and End User permissions separate.
- Use named routes and existing components.
- Do not expose passwords or temporary passwords.

## Implementation
1. State the controlling code path and one focused falsifiable check.
2. Make the smallest root-cause edit.
3. Add or update focused Pest coverage for authorization, validation, and regression behavior.
4. Keep business logic out of Blade.

## Validation
Run the narrowest relevant test first, then use `php artisan view:cache` for Blade changes and `php artisan test` when practical. Report changed files, commands, results, and remaining risks.
