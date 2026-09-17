# Database Design

## 1. Purpose and scope

This document describes the current relational database design of the DNHS Inventory Management System. It is based on the committed Laravel migrations, Eloquent models, controller workflows, and feature tests.

The database supports:

- role-based authentication and authorization
- inventory stock-in and item classification
- assignment, transfer, and return workflows
- stock movement auditing
- maintenance and disposal status tracking
- password-reset OTP verification
- Laravel cache and queue infrastructure

The database is implemented through Laravel migrations and is intended to work with MySQL, PostgreSQL, and SQLite for testing.

## 2. Design principles

The current design follows these principles:

1. Inventory state is stored on the `inventory` record.
2. Requests represent pending or approved workflow actions.
3. Transactions represent formal assignments and transfers.
4. Stock movements provide an append-only operational audit trail.
5. Foreign keys protect relationships and define deletion behavior.
6. Role records control access at the application layer.
7. Password reset OTP values are stored as hashes, not plaintext codes.

## 3. Main domain tables

### 3.1 `users`

The Laravel users table is extended for the school inventory domain.

Important columns:

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `role_id` | Nullable foreign key to `roles.role_id`; set to null when the role is deleted. |
| `first_name` | User first name. |
| `last_name` | User last name. |
| `username` | Unique login identifier. |
| `email` | Email address used for password reset and notifications. |
| `password` | Hashed login password. |
| `temporary_password` | Optional onboarding flag/value used by role dashboards. |
| `status` | Account state, currently `active` or `inactive`. |
| `remember_token` | Laravel persistent-login token. |
| `created_at`, `updated_at` | Laravel timestamps. |

Relationships:

- A user belongs to one role.
- A user can create or own inventory records through `inventory.user_id`.
- A user can be assigned inventory through `inventory.assigned_to_user_id`.
- A user can create, request, approve, or receive workflow records depending on the operation.

### 3.2 `roles`

Stores the application roles used by the role middleware.

| Column | Purpose |
| --- | --- |
| `role_id` | Primary key. |
| `role_name` | Unique role name, such as Administrator, Property Custodian, School Head, or End User. |
| `description` | Optional role description. |
| `created_at`, `updated_at` | Laravel timestamps. |

The role relationship is implemented by `User::role()` and `Role::users()`.

### 3.3 `categories`

Classifies inventory and determines whether serial numbers are required.

| Column | Purpose |
| --- | --- |
| `category_id` | Primary key. |
| `category_name` | Unique category name. |
| `requires_serial_number` | Boolean flag controlling serialized stock-in validation. |
| `created_at`, `updated_at` | Laravel timestamps. |

A category has many inventory records.

### 3.4 `inventory`

This is the central domain table. Each row represents an inventory item or a quantity of a non-serialized item.

| Column | Purpose |
| --- | --- |
| `item_id` | Primary key. |
| `category_id` | Required foreign key to `categories.category_id`; category deletion is restricted. |
| `unit` | Unit of measure, such as piece or copy. |
| `user_id` | Required foreign key to the user who recorded or owns the inventory entry. |
| `assigned_to_user_id` | Nullable foreign key to `users.id`; set to null when the assigned user is deleted. |
| `item_name` | Inventory item name. |
| `description` | Optional item description. |
| `quantity` | Unsigned quantity currently represented by the row. |
| `unit_cost` | Decimal unit cost. |
| `ics_no` | Optional government or institutional tracking number. |
| `serial_number` | Optional unique serial number. |
| `inventory_item_no` | Unique generated inventory identifier, such as `INV-000001`. |
| `status` | Current lifecycle status. Defaults to `available`. |
| `qr_code` | Unique generated QR tracking value. |
| `date_acquired` | Acquisition date. |
| `created_at`, `updated_at` | Laravel timestamps. |

Current statuses observed in the application:

- `available`
- `assigned`
- `under_inspection`
- `under_maintenance`
- `disposed`

Important behavior:

- Serialized stock-in creates one inventory row per serial number.
- Non-serialized stock-in can create one row with a quantity greater than one.
- Assignment and return workflows update inventory status and quantity.
- Maintenance changes an available item to `under_maintenance`.
- Disposal changes an available item to `disposed`.

## 4. Workflow tables

### 4.1 `requests`

The original migration creates `assignment_requests`, but a later migration renames the table to `requests`. The `AssignmentRequest` model explicitly uses the `requests` table.

This is a polymorphic workflow table at the business level, although it is a normal relational table at the database level. It supports assignment requests, transfer requests, and return requests.

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `item_id` | Foreign key to `inventory.item_id`; deletion is restricted. |
| `user_id` | User who initiated the request. |
| `target_user_id` | User who is the target or recipient of the request. |
| `transaction_id` | Optional foreign key to `transactions.id`; set to null if the transaction is deleted. |
| `quantity` | Quantity requested or transferred. |
| `status` | Workflow state stored as a string. |
| `notes` | Optional request or response notes. |
| `requested_at` | Request creation time. |
| `responded_at` | Approval, decline, or processing time. |
| `created_at`, `updated_at` | Laravel timestamps. |

Statuses currently used by the application include:

- `waiting for approval`
- `waiting for custodian approval`
- `approved`
- `declined`
- `transferred`
- `returned`
- `cancelled`
- `return_pending`

The table is intentionally reused for several workflows, but this also means the meaning of `user_id`, `target_user_id`, and `status` depends on the request type.

### 4.2 `transactions`

Stores formal assignment, return, and transfer records.

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `user_id` | Current recipient or assigned user. |
| `from_user_id` | Nullable source user for transfers; set to null if the source user is deleted. |
| `item_id` | Foreign key to `inventory.item_id`; deletion is restricted. |
| `quantity` | Quantity involved in the transaction. |
| `transaction_date` | Date the transaction occurred. |
| `status` | Transaction state, commonly `assigned`, `returned`, or transfer-related values. |
| `return_date` | Optional return date. |
| `created_at`, `updated_at` | Laravel timestamps. |

Relationships:

- A transaction belongs to an inventory item.
- A transaction belongs to the recipient through `user_id`.
- A transaction may belong to a source user through `from_user_id`.
- A request may reference a transaction through `requests.transaction_id`.

### 4.3 `stock_movements`

Provides the audit ledger for inventory changes and operational events.

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `inventory_id` | Foreign key to `inventory.item_id`; cascades when the inventory row is deleted. |
| `user_id` | Nullable user who performed the movement; set to null if deleted. |
| `movement_type` | Event type, such as `stock_in`, `returned`, `maintenance`, or `disposed`. |
| `quantity` | Quantity associated with the event. |
| `quantity_before` | Quantity snapshot before the event. |
| `quantity_after` | Quantity snapshot after the event. |
| `reference_type` | Optional application reference type. |
| `reference_id` | Optional referenced record identifier. |
| `notes` | Optional audit explanation. |
| `created_at`, `updated_at` | Laravel timestamps. |

Indexes currently support inventory/movement lookups and chronological audit queries:

- `(inventory_id, movement_type)`
- `created_at`

This table is not a strict polymorphic foreign-key design because `reference_type` and `reference_id` are stored separately without a database-enforced reference.

## 5. Password reset tables

### 5.1 `password_reset_otps`

Stores password-reset OTP challenges.

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `email` | Email address associated with the reset request; indexed. |
| `otp_hash` | Hashed six-digit OTP. |
| `expires_at` | OTP expiration timestamp; indexed. |
| `attempts` | Failed verification count. |
| `used_at` | Nullable timestamp marking successful use. |
| `created_at`, `updated_at` | Laravel timestamps. |

The reset flow does not store a foreign key to `users`. The verification request validates the email against `users.email`, while the OTP table identifies challenges by email.

The controller enforces these conditions when selecting an OTP:

- `used_at` is null
- `attempts` is less than five
- `expires_at` is in the future

## 6. Laravel infrastructure tables

The default Laravel migrations also define infrastructure tables:

- `cache` and `cache_locks` for the database cache store
- `jobs`, `job_batches`, and `failed_jobs` for queued jobs
- the base Laravel `users` table before application-specific columns are added

These tables support framework services rather than inventory business rules.

## 7. Relationship map

```text
roles 1 --- many users
users 1 --- many inventory records (inventory.user_id)
users 1 --- many assigned inventory records (inventory.assigned_to_user_id)
categories 1 --- many inventory records
inventory 1 --- many requests
inventory 1 --- many transactions
inventory 1 --- many stock_movements
users 1 --- many requests as initiator
users 1 --- many requests as target user
transactions 1 --- many requests (optional request link)
users 1 --- many transactions as recipient
users 1 --- many transactions as transfer source
users 1 --- many stock_movements as actor
```

## 8. Inventory lifecycle model

The current database uses a status column plus audit tables rather than a separate state-history table.

```text
stock-in
   |
   v
available ---- assignment approval ----> assigned
   |                                      |
   |                                      +---- return approval ----> available
   |
   +---- send to maintenance -----------> under_maintenance
   |
   +---- dispose -----------------------> disposed
```

The maintenance path currently records entry into maintenance but does not have a dedicated completion event or maintenance record.

## 9. Deletion and integrity rules

The migrations define these important behaviors:

- Deleting a role sets related `users.role_id` values to null.
- Deleting a category referenced by inventory is restricted.
- Deleting an inventory item referenced by transactions is restricted.
- Deleting an inventory item cascades to its stock movements.
- Deleting a user referenced by assigned inventory sets `assigned_to_user_id` to null.
- Deleting a user referenced by stock movements sets `stock_movements.user_id` to null.
- Deleting a user referenced as a transaction source sets `from_user_id` to null.
- Deleting a transaction sets a linked request's `transaction_id` to null.

## 10. Design strengths

- Inventory rows have stable identifiers, QR values, and generated inventory numbers.
- Serialized and non-serialized inventory are supported.
- Foreign keys protect important ownership and workflow relationships.
- Stock movements preserve who performed an action and quantity snapshots.
- OTP values are hashed and expire server-side.
- Assignment, transfer, and return workflows have separate request and transaction concepts.
- Database transactions and row locks are used in important inventory operations.

## 11. Current design gaps and risks

### 11.1 Maintenance has no dedicated record
The database stores only the `under_maintenance` inventory status and a `maintenance` stock movement. It does not store maintenance start, repair completion, technician, cost, diagnosis, or resolution details.

### 11.2 No maintenance-completion state transition
There is currently no dedicated database event or workflow for changing `under_maintenance` back to `available`, or for changing it to `disposed` after an unsuccessful repair.

### 11.3 Request types are implicit
The `requests` table has no explicit `request_type` column. The application infers whether a record is an assignment, transfer, or return from status and the surrounding controller action.

### 11.4 String statuses are not database-constrained
Statuses are stored as free-form strings. Typos or inconsistent naming can create invalid states unless controller validation and tests prevent them.

### 11.5 Inventory assignment is partly duplicated
The system uses both `inventory.assigned_to_user_id` and request/transaction records to represent assignment ownership. This supports legacy and partial-quantity workflows, but it creates a consistency burden because these values must remain synchronized.

### 11.6 OTP challenges are email-based rather than user-linked
`password_reset_otps.email` is indexed but not a foreign key to `users`. This is workable for password reset, but it allows orphaned or duplicate challenge rows and makes user identity changes harder to track.

### 11.7 Audit references are not enforced
`stock_movements.reference_type` and `reference_id` form a flexible reference, but the database cannot enforce that the referenced record exists.

### 11.8 No explicit database constraint for positive quantities
Several quantity columns are unsigned, but the database does not independently enforce a minimum value greater than zero. Business validation currently handles this in application code.

### 11.9 Documentation and migration naming differ
The older database documentation refers to `assignment_requests`, while the active model and later migration use `requests`. The renamed table should be treated as the current schema name.

## 12. Recommended future direction

Future database changes should preserve the existing inventory and audit model while adding explicit maintenance workflow support. A dedicated maintenance table could reference an inventory item and contain:

- maintenance status
- issue description
- opened and completed timestamps
- assigned technician or provider
- repair cost
- resolution notes
- resulting inventory status

A future request-model improvement could also add an explicit `request_type` field and controlled status vocabulary. Any such change should include migration updates, controller workflow updates, and feature tests covering role access, state transitions, audit records, and concurrent updates.

## 13. Source files

Primary schema sources:

- [database/migrations](../database/migrations)
- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Role.php](../app/Models/Role.php)
- [app/Models/Inventory.php](../app/Models/Inventory.php)
- [app/Models/AssignmentRequest.php](../app/Models/AssignmentRequest.php)
- [app/Models/Transaction.php](../app/Models/Transaction.php)
- [app/Models/StockMovement.php](../app/Models/StockMovement.php)
- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)
- [app/Http/Controllers/Auth/PasswordResetController.php](../app/Http/Controllers/Auth/PasswordResetController.php)
- [tests/Feature/InventoryReturnsTest.php](../tests/Feature/InventoryReturnsTest.php)
- [tests/Feature/PasswordResetOtpTest.php](../tests/Feature/PasswordResetOtpTest.php)
