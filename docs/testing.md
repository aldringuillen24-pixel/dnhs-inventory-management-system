# Testing Guide

This project uses Laravel Pest for automated testing. The test setup is configured in the project root and uses an in-memory SQLite database for fast, isolated test runs.

## 1. Test framework

- Framework: Laravel + Pest
- Test runner: `php artisan test` or `composer run test`
- PHPUnit config: [phpunit.xml](../phpunit.xml)
- Test directory: [tests](../tests)

The PHPUnit config sets:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_STORE=array`
- `SESSION_DRIVER=array`
- `QUEUE_CONNECTION=sync`

This keeps tests fast and independent from the local development database.

---

## 2. Test structure

The project currently organizes tests into:

- [tests/Feature](../tests/Feature) — route, workflow, permission, and behavior tests
- [tests/Unit](../tests/Unit) — smaller isolated logic tests
- [tests/Pest.php](../tests/Pest.php) — Pest bootstrap/configuration

Examples of real feature coverage in this repo include:

- inventory stock-in behavior
- AI assistant role-scoped response behavior
- admin guard rules
- auth and role setup
- end-user request and transfer flows

---

## 3. How to run tests

### Run the full suite

```bash
composer run test
```

Or directly:

```bash
php artisan test
```

### Run a specific file

```bash
php artisan test tests/Feature/InventoryStockInTest.php
```

### Run a specific test by filter

```bash
php artisan test --filter="stock-in"
```

### Run with coverage

```bash
php artisan test --coverage
```

---

## 4. Current high-value test areas

### Inventory stock-in tests

The most important inventory tests validate:

- stock-in creates audit ledger entries
- serial-number stock-in creates one row per serial number
- regular stock-in creates one inventory record with the entered quantity
- inventory listing groups matching item names correctly
- disposed items are excluded from the inventory list

Relevant file:

- [tests/Feature/InventoryStockInTest.php](../tests/Feature/InventoryStockInTest.php)

### AI assistant tests

The AI assistant tests validate:

- deduplication of repeated current questions in request payloads
- successful OpenRouter responses
- local fallback when no API key is configured
- retry behavior for provider failures
- fallback on malformed provider payloads

Relevant file:

- [tests/Feature/AiAssistantTest.php](../tests/Feature/AiAssistantTest.php)

### Auth and role tests

These validate:

- seeded admin login flow
- role-based redirects
- admin guard restrictions

Relevant files:

- [tests/Feature/AuthRoleSetupTest.php](../tests/Feature/AuthRoleSetupTest.php)
- [tests/Feature/AdminUserGuardTest.php](../tests/Feature/AdminUserGuardTest.php)

### End-user workflow tests

These check:

- request creation
- available-stock validation
- assigned item visibility
- transfer restrictions
- race-condition safety

Relevant file:

- [tests/Feature/EndUserRequestTest.php](../tests/Feature/EndUserRequestTest.php)

---

## 5. Test patterns used in this repo

### Refresh database per test

The project uses `RefreshDatabase` in many feature tests, which ensures each test starts from a clean schema and data state.

Example:

```php
uses(RefreshDatabase::class);
```

### Role setup in test bootstrap

Several tests create their own roles and users directly in the test lifecycle before exercising the API or route behavior.

### Assertions against real database state

The tests validate database records and route responses instead of only checking mock objects. This is consistent with the project’s domain-driven workflows.

---

## 6. Best practices for adding new tests

When adding tests for this project, prefer:

- real database assertions via `assertDatabaseHas` / `assertDatabaseMissing`
- role-aware request tests using `actingAs()`
- behavior-based feature tests over mock-only tests
- targeted validation for inventory rules, request approvals, and role restrictions

Good examples to follow:

- [tests/Feature/InventoryStockInTest.php](../tests/Feature/InventoryStockInTest.php)
- [tests/Feature/EndUserRequestTest.php](../tests/Feature/EndUserRequestTest.php)
- [tests/Feature/AiAssistantTest.php](../tests/Feature/AiAssistantTest.php)

---

## 7. Quick commands for common work

```bash
# Full suite
php artisan test

# Inventory flow only
php artisan test tests/Feature/InventoryStockInTest.php

# AI assistant only
php artisan test tests/Feature/AiAssistantTest.php

# Auth and routing
php artisan test tests/Feature/AuthRoleSetupTest.php

# End-user request workflow
php artisan test tests/Feature/EndUserRequestTest.php
```

---

## 8. Summary

The project’s test suite is designed to protect the inventory lifecycle, user role rules, and AI assistant resilience. The best tests in this repo are feature tests that validate real behavior through routes, database state, and role-based access rules.

If a new feature is added, the preferred pattern is to create a focused Pest feature test for the real workflow and validate it end-to-end with the database setup the project already uses.
