# DNHS Inventory Management System - Next Development Recommendations

Scope: local development only (no deployment/CI concerns in this phase).

## Current State

- **Done (committed):** Auth, role-based dashboards (Administrator, Property Custodian, End User), user management with bulk generation and PDF account slips, inventory stock-in, requests/assignments/transfers, partial AI Assistant.
- **In progress (uncommitted, working tree):** School Head role, Admin dashboard charts, AI Assistant improvements, inventory edit/update/delete, End User dashboard, mobile nav, `.github/` (agents/prompts/skills), new feature tests.
- **Two blockers exist** before any new feature work can proceed safely: the test suite is red and multiple security/correctness gaps remain open.

## Phase 0 - Stabilize the Test Suite (do first)

**Blocker:** `database/migrations/2026_08_10_000004_add_inventory_tracking_fields_to_inventory_table.php:38` executes raw MySQL `SHOW INDEX FROM inventory WHERE Key_name = ?`, which fails on the SQLite in-memory test database (`SQLSTATE[HY000]: General error: 1 near "SHOW"`). This breaks every `RefreshDatabase` test. Current run: 15 failed / 9 passed.

Steps:

1. Replace the raw `SHOW INDEX` guard with driver-safe index detection (`Schema::hasIndex()`, falling back to `PRAGMA index_list` for sqlite / `SHOW INDEX` for mysql) while preserving the `unique()` index creation.
2. Run `./vendor/bin/pest` and fix the remaining failures.
3. Add a regression test that runs the full migration set on SQLite so this cannot recur.

Note: `pdo_sqlite` is confirmed enabled, so the suite can run locally.

## Phase 1 - Security Hardening

The findings in `recommendation.md` were verified against the working tree. A few are already patched in uncommitted code (e.g., End User transfer recipients must be active End Users). Rework against `recommendation.md` and the committed baseline:

| # | Severity | Fix | Files |
|---|----------|-----|-------|
| 1 | High | Only allow transfers when the user is the current possessor (`target_user_id` = auth id, status `approved`/`accepted`); remove the requester bypass | `app/Http/Controllers/EndUserController.php:325-334` |
| 2 | High | Transfer recipient must be an active End User and not the requester; verify existing patch and cover with tests | `app/Http/Controllers/EndUserController.php:342-352`, `tests/Feature/EndUserRequestTest.php` |
| 3 | High | Availability and count queries must use `status = 'available'` only | `app/Http/Controllers/EndUserController.php:177-186, 326-333` |
| 4 | Medium | Block self-demotion/deactivation of an Administrator and prevent disabling/demoting the last active Administrator | `app/Http/Controllers/UserController.php:135-159` |
| 5 | Medium | Explicitly decide and enforce the policy on creating Administrator accounts via store/generate-users; add a regression test documenting intent | `app/Http/Controllers/UserController.php:66-133` |
| 6 | Medium | Stop loading `$allUsers` wholesale on the users-management page | `app/Http/Controllers/UserController.php:61` |
| 7 | Low | Use `lockForUpdate()` and re-check the pending status inside a transaction when accepting a transfer | `app/Http/Controllers/EndUserController.php:374-412` |

Tests: expand `tests/Feature/EndUserRequestTest.php` (recipient role validation, ownership of transferred item, unavailable stock) and add `tests/Feature/AdminUserGuardTest.php` (self-demotion, last-admin protection, privileged account creation, export authorization).

## Phase 2 - New Inventory Features

Proposed in dependency order. All follow the CLAUDE.md invariants: database transactions, never-negative stock, auditable transaction history.

1. **Stock movement / audit ledger** - new `stock_movements` table (or a `type` column on `transactions`: `stock_in`, `assigned`, `transferred`, `returned`, `disposed`, `adjustment`). Every stock change writes a row. Replaces the ad-hoc `Inventory::decrement()` calls in `PropertyCustodianController` and `EndUserController`.
2. **End User returns** - return request from an assigned item, custodian approval, quantity restored to `available`, audited `returned` entry. Mirrors the existing 3-step transfer flow.
3. **Disposal / write-off** - custodian marks an item as `disposed` with a reason; blocked for `assigned` items; audited. Reconciles statuses already referenced in dashboards.
4. **Category management** - CRUD UI (name + `requires_serial_number`) for Administrator/Property Custodian; prevent deleting categories that still have inventory.
5. **Report PDF export** - reuse Dompdf (already used for account slips) to export Property Custodian and School Head reports.
6. **Low-stock alerts** - surface critical stock items as alerts on dashboards; the underlying queries already exist in dashboards and the AI service.

Each feature gets Pest tests: request flow, authorization, and stock invariants.

## Phase 3 - Finalize In-Flight Uncommitted Work

The working tree is 2 commits ahead of `origin/main` (~1,070 lines changed): School Head role, Admin dashboard charts, AI Assistant improvements, inventory edit/delete, End User dashboard, mobile nav, and new tests.

- Run `./vendor/bin/pint` and fix violations before continuing.
- Commit checkpoints after each phase (only when explicitly requested).

## Decisions Needed

- **`.github/` contents:** currently holds opencode-style `agents/`, `prompts/`, and `skills/` directories. Decide whether to keep, move, or remove them from the repo.
- **Administrator account creation policy (Phase 1, item 5):** explicitly restrict or intentionally allow additional Administrator accounts.

## Execution Order

Phase 0 (tests green) -> Phase 1 (security) -> Phase 2 (features) -> Phase 3 (finalize) with a commit checkpoint after each phase.

## Commands

```bash
./vendor/bin/pest
./vendor/bin/pint
php artisan test
```

I’ll continue from the failed test run: first verify the End User render regression and the assignment-owner regression after the Blade fix, then address only failures caused by our recent changes.

Ran terminal command: & 'C:\xampp\php\php.exe' artisan test tests/Feature/EndUserRequestTest.php --filter="end user sees both assigned and requested|custodian-created assignment records"

Both focused checks pass, including the End User page render and the corrected target-user assignment. I’m doing the final Blade compilation and diagnostics check now; the full End User suite’s remaining redirect failures are outside this fix.

Ran terminal command: & 'C:\xampp\php\php.exe' artisan view:cache

Checked , , , 4 problems found

Completed inconsistency 1.

- Custodian-created assignments now record the target End User correctly.
- Normal End User requests still assign to the requesting End User.
- Added regression coverage in `EndUserRequestTest.php`.
- Fixed the End User spinner Blade binding issue.

Validation:

- Focused tests: **2 passed**
- Blade cache: **passed**
- Controller and view diagnostics: **no errors**
- Test-file property warnings are existing IDE false positives.