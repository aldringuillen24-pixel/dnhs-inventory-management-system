End User — Feature reference

Purpose
- Concise reference for the End User requests/assignments functionality: database shape, behavior, business logic, UI surface, and related files for future edits.

Domain model: `requests` table (previously `assignment_requests`)
- Primary fields: `id`, `item_id` (FK to `inventory.item_id`), `user_id` (requester), `target_user_id` (recipient when custodian-initiated), `transaction_id` (nullable), `quantity`, `status`, `notes`, `requested_at`, `responded_at`, `approved_by` (nullable), `approved_at` (nullable), timestamps.
- Indexes & constraints: FK constraints on `item_id`, `user_id`, `target_user_id`; indexes on `user_id`, `item_id`, `status`.
- Naming: model remains `AssignmentRequest` (avoid `Request` class conflict); table name is `requests`.

Status model (recommended)
- Unified statuses: `pending` (user-initiated awaiting review), `waiting_for_acceptance` (custodian-initiated awaiting end-user accept), `approved` (approved by custodian/admin), `denied`, `accepted` (end user accepted delivered item), `cancelled`, `returned`.

Primary flows
- End-user initiated (request to custodian/admin):
  - User submits request → status `pending` → custodian/admin reviews → `approved`/`denied` → if approved, optional delivery/assignment step and `accepted` when user confirms receipt.
- Custodian-initiated (assignment to user for acceptance):
  - Custodian creates assignment → status `waiting_for_acceptance` → user accepts (`accepted`) or declines (`denied`); admin records audit fields.

Key decisions & recommendations
- Reserve vs commit: prefer reserving stock at approval to avoid blocking inventory on every pending request. Use transactional lock when approving to avoid races.
- Serial-numbered items: if `categories.requires_serial_number`, present serial selection during approval (custodian) or at acceptance step.
- Notifications: implement in-app notifications; add email only if required.

Validation rules (server-side)
- `user_id` must be authenticated and match requester.
- `item_id` must exist; when tracked, ensure availability.
- `quantity` integer >= 1 and <= available quantity for the item (when applicable).
- `notes` optional; `reason` (or `notes`) should have a reasonable max length.

API surface and file map
- Model: `app/Models/AssignmentRequest.php` (set `protected $table = 'requests'`).
- Migrations: existing migration `database/migrations/2026_08_11_000001_create_assignment_requests_table.php` (kept for schema). New migration `database/migrations/2026_08_12_000000_rename_assignment_requests_to_requests_table.php` exists if renaming applied.
- Controllers:
  - `app/Http/Controllers/EndUser/RequestController.php` — index, create, store, show, cancel, accept (end-user), withdraw.
  - Admin controllers: `app/Http/Controllers/Admin/RequestController.php` — review, approve, deny, assign, return.
- Form Requests: `app/Http/Requests/StoreRequestRequest.php`, `UpdateRequestStatusRequest.php` for validation.
- Policies: `app/Policies/AssignmentRequestPolicy.php` — `view`, `create`, `cancel`, `accept`.
- Routes: group under `end-user` for user actions and `admin`/`property-custodian` for review actions. Use route names `endUser.requests.*` and `admin.requests.*`.
- Views:
  - `resources/views/pages/endUser/requests.blade.php` (index)
  - `resources/views/pages/endUser/requests/create.blade.php` (form/modal partial)
  - `resources/views/pages/endUser/requests/show.blade.php` (details)
  - Blade components: `resources/views/components/requests/request-row.blade.php`, `components/requests/status-badge.blade.php`, `components/requests/form-fields.blade.php`.
- Notifications: `app/Notifications/RequestCreated.php`, `RequestStatusChanged.php`.
- Tests: `tests/Feature/EndUserRequestsTest.php` (create/list/cancel/accept) and `tests/Feature/AdminRequestReviewTest.php`.

Business rules and concurrency
- Use DB transactions and `SELECT ... FOR UPDATE` when changing stock on approval.
- Implement optimistic checks on quantity at submission and hard checks at approval.

Audit and fields to set on actions
- On create: set `requested_at`, `user_id`.
- On approve/deny: set `approved_by`, `approved_at`, update `status`.
- On accept: set `responded_at`, `status = accepted`, optionally link to `transactions`.

Developer guidance (for Copilot or humans)
- Keep the model named `AssignmentRequest` and `protected $table = 'requests'` to avoid class name conflicts.
- Use Form Requests for validation; centralize business logic into a service (`app/Services/RequestService.php`) to keep controllers thin.
- Keep UI components small and testable; use AJAX for details if needed.
- Add feature tests covering both initiator flows (custodian-initiated accept vs user-initiated review/approve).

Migration / rename checklist
- Backup DB before running rename migration.
- Apply `php artisan migrate` to run the rename migration file.
- Search the codebase for `assignment_requests` literal queries and update to `requests`.
- Clear caches: `php artisan config:clear`, `route:clear`, `view:clear`.

Short Copilot prompts (token-efficient)
- "EndUser requests: show me files to add — model, controller, routes, form requests, policy, views, notifications, tests." 
- "Create `StoreRequestRequest` validation rules: item_id exists, quantity numeric >=1 <= available." 

Use this document as the single reference for end-user request behavior and file locations.

# Copilot instructions — End User requests module

Purpose
- Short reference for the End User `assignment_requests` feature: DB shape, expected user flow, and recommended module structure.

AssignmentRequests table (what it stores)
- `id` (PK)
- `user_id` → references `users.id` (request owner)
- `inventory_id` or `item_id` → references inventory table
- `quantity` (int)
- `reason` (text)
- `status` (string/enum): `pending`, `approved`, `denied`, `cancelled`, `returned`
- `requested_at` / `created_at`, `updated_at`
- `approved_by` (nullable), `approved_at` (nullable)
- `notes` / `admin_comment` (nullable)

Indexes & constraints
- FK constraints on `user_id` and `inventory_id`
- Indexes on `user_id`, `inventory_id`, and `status`
- Prevent negative `quantity` via validation and DB unsigned int

End-user request item (what the user sees/does)
- Create request: choose item, enter quantity and reason, submit.
- My requests list: shows recent requests with status badges and timestamps.
- Request details: view item, reason, history, and any admin comments.
- Cancel request: allowed only when `status == pending`.
- Notifications: user receives update when status changes.

Validation (server-side)
- `user_id` must be authenticated user
- `inventory_id` exists and is available (if tracked)
- `quantity` numeric, min 1, and <= available stock when applicable
- `reason` required, max length (e.g., 1000 chars)

Suggested module structure (files & responsibilities)
- Model: `app/Models/AssignmentRequest.php` (Eloquent, casts, fillable, relations)
- Controller: `app/Http/Controllers/EndUser/RequestController.php` or reuse `EndUserController` methods (index, create, store, show, cancel)
- Views: `resources/views/pages/endUser/requests.blade.php` (index)
  - `create.blade.php` (form)
  - `show.blade.php` (details)
  - small partials/components: `resources/views/components/requests/*` for list item, form fields, status badge
- Routes: add under `end-user` group in `routes/web.php` with names like `endUser.requests`, `endUser.requests.store`, `endUser.requests.show`
- Policy: `app/Policies/AssignmentRequestPolicy.php` to guard view/store/cancel actions
- Service (optional): `app/Services/RequestService.php` for business logic (reserve stock, notifications)
- Notifications: `app/Notifications/RequestCreated.php`, `RequestStatusChanged.php`
- Tests: `tests/Feature/EndUserRequestsTest.php` (create, list, cancel, permissions)

Integration points
- Inventory: check stock and requires_serial_number flag on `categories` if applicable
- User: tie `user_id` to `users` table; show requester name in admin views
- Admin panel: separate approval/deny flows with audit fields filled (`approved_by`, `approved_at`)

Developer notes / Copilot guidance
- Prefer reusing `AssignmentRequest` model if it exists—add relations and scopes (e.g., `scopeForUser($q,$id)`).
- Keep views small: render request rows with a Blade component and load details via AJAX if needed.
- Keep validation in a Form Request class for clarity (e.g., `StoreAssignmentRequest`).
- Add DB index migrations instead of editing old migrations.

That's it — use this file as a short reference for generating code, tests, and UI for end-user requests.
