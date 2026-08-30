# Database Architecture

This project uses a Laravel relational database with a small but important inventory domain model. The schema is built around user roles, inventory records, assignment requests, transactions, and stock movement history.

## 1. Core tables

### users

The base Laravel users table is extended by multiple migrations to support this app’s role-based inventory workflow.

Key additions include:

- `role_id` — links the user to a role
- `first_name` and `last_name`
- `username`
- `email`
- `status` — active/inactive
- `temporary_password` — used for onboarding flow

Relevant migration:

- [database/migrations/2026_07_30_100615_add_role_and_profile_fields_to_users_table.php](../database/migrations/2026_07_30_100615_add_role_and_profile_fields_to_users_table.php)
- [database/migrations/2026_07_31_000000_add_temporary_password_to_users_table.php](../database/migrations/2026_07_31_000000_add_temporary_password_to_users_table.php)

### roles

The app defines roles in a dedicated table.

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id('role_id');
    $table->string('role_name')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

Relevant migration:

- [database/migrations/2026_07_30_100552_create_roles_table.php](../database/migrations/2026_07_30_100552_create_roles_table.php)

### categories

Categories are used to group inventory items and support item-level classification.

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id('category_id');
    $table->string('category_name')->unique();
    $table->boolean('requires_serial_number')->default(false);
    $table->timestamps();
});
```

Relevant migration:

- [database/migrations/2026_08_10_000000_create_categories_table.php](../database/migrations/2026_08_10_000000_create_categories_table.php)
- [database/migrations/2026_08_10_000002_add_requires_serial_number_to_categories_table.php](../database/migrations/2026_08_10_000002_add_requires_serial_number_to_categories_table.php)

---

## 2. Inventory model

The main inventory table is `inventory` and is the centerpiece of the application.

Key fields include:

- `item_id` primary key
- `category_id`
- `unit`
- `user_id` — owner or recorder
- `item_name`
- `description`
- `quantity`
- `unit_cost`
- `ics_no`
- `serial_number`
- `date_acquired`
- `inventory_item_no`
- `assigned_to_user_id`
- `status`
- `qr_code`

Relevant migration:

- [database/migrations/2026_08_10_000001_create_inventory_table.php](../database/migrations/2026_08_10_000001_create_inventory_table.php)
- [database/migrations/2026_08_10_000003_add_unit_cost_and_serial_number_index_to_inventory_table.php](../database/migrations/2026_08_10_000003_add_unit_cost_and_serial_number_index_to_inventory_table.php)
- [database/migrations/2026_08_10_000004_add_inventory_tracking_fields_to_inventory_table.php](../database/migrations/2026_08_10_000004_add_inventory_tracking_fields_to_inventory_table.php)

### Inventory design notes

- `inventory_item_no` is unique and auto-generated for each item.
- `qr_code` is also unique and used as a tracking identifier.
- `status` distinguishes between available, assigned, under inspection, maintenance, and disposed states.
- `assigned_to_user_id` links inventory to the user currently holding it.

The model is implemented in:

- [app/Models/Inventory.php](../app/Models/Inventory.php)

---

## 3. Requests and assignment workflow

The app stores request transactions in a table named `requests`, renamed from the original `assignment_requests` table.

Relevant migration:

- [database/migrations/2026_08_11_000001_create_assignment_requests_table.php](../database/migrations/2026_08_11_000001_create_assignment_requests_table.php)
- [database/migrations/2026_08_12_000000_rename_assignment_requests_to_requests_table.php](../database/migrations/2026_08_12_000000_rename_assignment_requests_to_requests_table.php)

### Request fields

- `item_id`
- `user_id` — requesting user
- `target_user_id` — assigned/target recipient
- `transaction_id` — linked transaction record if applicable
- `quantity`
- `status`
- `notes`
- `requested_at`
- `responded_at`

This table supports the workflow for:

- item request approvals
- assignment approvals
- transfer approvals
- return requests

The model is in:

- [app/Models/AssignmentRequest.php](../app/Models/AssignmentRequest.php)

---

## 4. Transactions table

The system logs inventory actions into the `transactions` table.

Key fields:

- `user_id`
- `item_id`
- `quantity`
- `transaction_date`
- `return_date`
- `status`
- `created_at`
- `updated_at`

Relevant migrations:

- [database/migrations/2026_08_10_000005_create_transactions_table.php](../database/migrations/2026_08_10_000005_create_transactions_table.php)
- [database/migrations/2026_08_10_000006_add_timestamps_to_transactions_table.php](../database/migrations/2026_08_10_000006_add_timestamps_to_transactions_table.php)
- [database/migrations/2026_08_11_000000_add_status_to_transactions_table.php](../database/migrations/2026_08_11_000000_add_status_to_transactions_table.php)

This model records concrete transaction events used in reports and operational review.

---

## 5. Stock movements table

The app also includes `stock_movements`, which gives a much finer-grained audit trail than a simple inventory update.

```php
Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inventory_id')->constrained('inventory', 'item_id')->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('movement_type');
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('quantity_before')->default(0);
    $table->unsignedInteger('quantity_after')->default(0);
    $table->string('reference_type')->nullable();
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

Relevant migration:

- [database/migrations/2026_08_29_021548_create_stock_movements_table.php](../database/migrations/2026_08_29_021548_create_stock_movements_table.php)

### Why this matters

This table supports auditing and operational traceability:

- before/after quantity snapshots
- type of movement
- user responsible
- linked reference object
- timestamp history

The model is:

- [app/Models/StockMovement.php](../app/Models/StockMovement.php)

---

## 6. Relationship map

The key relationships are:

- `User` belongs to `Role`
- `User` has many `Inventory` records
- `Inventory` belongs to `Category`
- `Inventory` may be assigned to a user through `assigned_to_user_id`
- `Inventory` has many `StockMovement` rows
- `Inventory` may be linked to many `Transaction` rows
- `AssignmentRequest` links a user and target user to an inventory item

This relationship structure supports both operational workflow and reporting.

---

## 7. Summary

The database is designed around an inventory lifecycle rather than a generic CRUD app:

- roles manage access
- categories classify inventory
- inventory tracks item-level stock and assignment state
- requests capture pending actions and approvals
- transactions capture official recorded changes
- stock movements provide audit-level history

This makes the database suitable for a school asset inventory workflow with approval, accountability, and reporting requirements.
