# AGENTS.md

This repository is a Laravel 12 inventory management system for a school / departmental environment with role-based access and a dedicated AI assistant.

## Project overview

- Framework: Laravel 12
- PHP: 8.2+
- Frontend: Blade + Tailwind CSS + Alpine.js + Vite
- Test framework: Pest
- Database: MySQL/PostgreSQL/SQLite, with tests running on SQLite in memory

This project is not a generic starter template. The important domain behavior is the inventory lifecycle and role-specific access.

## Core business domain

The app manages:

- inventory stock-in and stock-out
- item assignment to end users
- transfers between users
- return requests and return approvals
- role-based dashboards and approvals
- AI-assisted inventory questions for role-scoped responses

## Key files to read first

When making code changes, start with these files based on the task:

- routes/web.php — role routes and protected access map
- app/Http/Controllers/PropertyCustodianController.php — core inventory workflow logic
- app/Http/Controllers/EndUserController.php — end-user request and assignment flows
- app/Http/Controllers/UserController.php — admin/user management
- app/Models/Inventory.php — primary inventory entity
- app/Models/User.php — user and role relationship model
- app/Models/AssignmentRequest.php — assignment workflow model
- app/Models/StockMovement.php — movement audit trail
- app/Models/Category.php — category and serial-number rules
- app/Services/AiInventoryService.php — AI assistant behavior
- database/migrations/ — schema and rollout rules
- docs/ — project-specific documentation for setup, roles, deployment, workflow, and testing

## Role model

The app uses role-based access via middleware and route groups.

Key roles:

- Administrator
- Property Custodian
- School Head
- End User

Route protection in routes/web.php is the source of truth for who can access which pages and actions.

Important rule:

- Any change to routes, permissions, or access checks should preserve role-specific workflow intent.
- Do not bypass middleware or add broad access without matching business rules.

## Inventory workflow

The main operational flow is:

1. Property custodian stock-in inventory items
2. Items are tracked by category, quantity, and serial-number requirements when configured
3. End users request or receive assigned items
4. Custodian approves assignment or transfer actions
5. End users may request returns
6. Custodian approves returns and updates inventory state
7. Movements and transactions are recorded for auditability

When changing the inventory logic, verify the business flow across:

- inventory creation and editing
- assignment approvals
- return processing
- transaction history
- stock movement records

## AI assistant rules

The AI assistant is implemented in app/Services/AiInventoryService.php and exposed through the authenticated endpoint in routes/web.php.

Important constraints:

- it should be role-scoped, not generic
- it may use OpenRouter with fallback behavior
- the system should degrade gracefully when the API key is missing or the provider fails
- responses should remain grounded in the app’s business data and permissions

If AI behavior is changed, validate the actual tests in tests/Feature/AiAssistantTest.php and keep responses safe and role-aware.

## Testing expectations

This project uses Laravel Pest and SQLite in memory for tests.

Common commands:

```bash
php artisan test
php artisan test tests/Feature/InventoryStockInTest.php
php artisan test tests/Feature/AiAssistantTest.php
php artisan test tests/Feature/AuthRoleSetupTest.php
php artisan test tests/Feature/EndUserRequestTest.php
```

Preferred testing style:

- use real database state assertions
- prefer feature tests that exercise route + DB behavior
- validate role restrictions and inventory workflows end-to-end
- avoid mock-only tests for core business behavior

## Project conventions

- Keep changes aligned with the inventory domain and role-based access model.
- Prefer small, focused edits over broad refactors.
- When changing database structure, consider whether the migration and affected logic are consistent.
- Do not assume the app is a generic Laravel admin template; it has domain-specific inventory rules.
- Use existing docs in the docs/ folder as the project’s source of truth for architecture and workflow details.

## Useful project docs

- docs/architecture.md
- docs/setup.md
- docs/roles-and-access.md
- docs/ai-assistant.md
- docs/database.md
- docs/deployment.md
- docs/inventory-workflows.md
- docs/testing.md

## Recommended agent workflow

Before implementing a task:

1. Identify the relevant route and controller
2. Read the business model and migration involved
3. Check the relevant tests for the workflow
4. Make the minimal change that matches the domain rules
5. Run the focused Pest tests for the affected behavior

## Final guidance

Treat the app as a role-based inventory system with an AI layer, not as a blank Laravel starter. Successful changes should preserve:

- security boundaries by role
- approval and audit flows
- inventory correctness
- testability through real behavior validation
