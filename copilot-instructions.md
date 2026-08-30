# Copilot Instructions

This is a Laravel 12 inventory management system for schools/departments. Copilot should follow these guidelines when suggesting code or explaining the project.

## Project Context

- **Framework:** Laravel 12 with PHP 8.2+
- **Frontend:** Blade + Tailwind CSS + Alpine.js + Vite
- **Testing:** Pest (SQLite in-memory for tests)
- **Domain:** Role-based inventory management with AI assistant

This is **not** a generic starter template—it has specific business rules around inventory workflows, role-based access, and auditability.

## Core Behavior

The app manages:
- Stock-in and stock-out of inventory
- User assignments and transfers
- Return request/approval workflows
- Role-scoped dashboards
- AI-assisted inventory Q&A

## When Suggesting Code

1. **Always check roles first.** Look at `routes/web.php` and the role middleware before suggesting routes or controllers.
2. **Preserve the workflow.** Any inventory change should maintain stock tracking, approval flows, and audit trails.
3. **Validate against tests.** If changing core behavior, check relevant Pest tests in `tests/Feature/`.
4. **Keep it role-aware.** The AI assistant and dashboards are role-scoped; suggestions should not bypass role boundaries.

## Key Files

- `routes/web.php` — role-based routing (source of truth for access)
- `app/Http/Controllers/PropertyCustodianController.php` — inventory operations
- `app/Http/Controllers/EndUserController.php` — user requests and transfers
- `app/Models/Inventory.php` — main inventory entity
- `app/Models/User.php` — user and role model
- `app/Services/AiInventoryService.php` — AI assistant with role-scoped responses
- `database/migrations/` — schema and business rules
- `docs/` — detailed architecture and workflow docs
- `AGENTS.md` — agent guidance for continuation

## Testing Conventions

- Run `php artisan test` for the full suite
- Use `RefreshDatabase` to reset state per test
- Write feature tests that exercise real routes + database behavior
- Avoid mock-only tests for core inventory logic
- Validate role restrictions end-to-end

## Common Patterns

### Adding a route
1. Add it to the role's route group in `routes/web.php`
2. Create or update the controller method
3. Add a Pest feature test to validate role access and business logic

### Changing inventory logic
1. Update the migration if schema changes
2. Update the controller method
3. Add a test for the new behavior
4. Check that approvals and audit trails still work

### Adding AI assistant functionality
1. Update `AiInventoryService.php`
2. Ensure responses are role-scoped
3. Add tests in `tests/Feature/AiAssistantTest.php`
4. Verify fallback behavior when API is unavailable

## Do Not

- Bypass role middleware or access checks
- Assume the app is a generic admin dashboard
- Add broad routes without role validation
- Skip audit/stock movement tracking for inventory changes
- Assume all users should see all data (always filter by role)

## Documentation

Start with these docs for deeper context:
- `docs/architecture.md` — system design
- `docs/roles-and-access.md` — permission model
- `docs/inventory-workflows.md` — full lifecycle
- `docs/ai-assistant.md` — AI behavior and fallback
- `docs/database.md` — schema overview
- `docs/testing.md` — test setup and patterns

For agent-level guidance, see `AGENTS.md`.
