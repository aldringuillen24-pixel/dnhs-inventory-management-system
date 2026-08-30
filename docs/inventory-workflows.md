# Inventory Workflows

This project is centered on the lifecycle of inventory items from stock-in to assignment, transfer, return, and reporting. The business logic is concentrated in the Property Custodian controller, while the inventory model and stock movement table preserve the operational audit trail.

## 1. Inventory lifecycle overview

The life of an item in this system follows a practical warehouse workflow:

1. Stock in new inventory
2. Group and classify items by category and item name
3. Check available quantity versus assigned quantity
4. Receive assignment requests from end users
5. Approve or reject assignment requests
6. Transfer items between users when needed
7. Process returns back to the warehouse
8. Generate operational reports

The main implementation is in:

- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)
- [app/Models/Inventory.php](../app/Models/Inventory.php)
- [app/Models/StockMovement.php](../app/Models/StockMovement.php)
- [app/Models/AssignmentRequest.php](../app/Models/AssignmentRequest.php)

---

## 2. Stock-in workflow

The stock-in workflow is triggered through the property custodian inventory area.

### Entry point

- Route: `/property-custodian/inventory/stock-in`
- Method: `stockIn()`

### Logic

The system validates:

- item name
- category
- description
- ICS number
- unit
- unit cost
- date acquired
- quantity

If the category requires a serial number, it validates an array of serial numbers and enforces uniqueness.

### Important behavior

- A new inventory item is created through `Inventory::create()`
- A `stock_movements` record is inserted with `movement_type = 'stock_in'`
- `inventory_item_no` is generated as `INV-000001` style value
- `qr_code` is generated using the inventory item ID

This is implemented inside the private helper `createInventoryItem()` at the bottom of the controller.

### Result

Items become available in the warehouse for assignment and reporting.

---

## 3. Inventory grouping and visibility

The inventory page summarizes by item name and category, then calculates totals, average cost, and available/assigned counts.

This is handled in `inventory()` where the controller:

- loads categories
- calculates aggregate inventory metrics
- groups inventory by `item_name` + `category_id`
- keeps the original source records available for display

### Metrics calculated

- total inventory quantity
- available quantity
- assigned quantity
- attention count for items under inspection or maintenance

This aggregated view is used to simplify warehousing decisions without losing the raw inventory item records.

---

## 4. Assignment request workflow

The assignment workflow starts from a request to assign available inventory to an end user.

### Entry point

- Route: `/property-custodian/transactions/assign`
- Method: `assignItem()`

### Process

The custodian selects:

- item
- end user
- quantity
- optional transaction date

The system then creates an `AssignmentRequest` with status:

- `waiting for approval`

### Approval flow

When the custodian approves a request in `approveRequest()`:

1. The request is locked for update.
2. Available inventory rows are looked up by matching item name and available status.
3. Quantity is deducted from the available stock.
4. If the item quantity reaches zero, it is marked as assigned.
5. A `Transaction` record is created with status `assigned`.
6. The request is marked `approved`.

### Business rule

The approver cannot approve if total available stock is insufficient for the requested quantity.

---

## 5. Transfer workflow

This project supports peer-to-peer transfer requests after an initial item is assigned.

### Transfer request status

The transfer request remains in status:

- `waiting for custodian approval`

The controller includes logic for:

- `approveTransfer()`
- `declineTransfer()`

### Approval behavior

When a transfer is approved:

- the original assignment is updated as transferred
- a new transaction is created for the recipient
- the transfer request is marked `approved`

### Decline behavior

When a transfer is declined:

- the original assignment is restored to `approved`
- the transfer request becomes `declined`

This is a staged workflow that ensures the sender and recipient states remain consistent.

---

## 6. Return workflow

Returns are handled as a formal part of the assignment lifecycle.

### End-user request

The end user can request a return of an assigned item. The request is recorded with status:

- `return_pending`

### Custodian approval

The custodian approves the return in `approveReturn()`.

Behavior:

- the item is locked
- item is checked to ensure it is still assigned to the correct user
- item status is changed from `assigned` to `available`
- `assigned_to_user_id` is cleared
- a `StockMovement` row is created with `movement_type = 'returned'`
- the request is marked `approved`

### Manual warehouse return

The system also supports a warehouse-level mark-returned action through `markReturned()`, which performs the same type of state reset for assigned items.

---

## 7. Transaction and audit trail

Every significant lifecycle event writes to the database in a way that preserves accountability.

### Transaction table

The `transactions` table records operational actions such as assignments and status changes.

### Stock movement table

The `stock_movements` table records detailed item movement events including:

- `movement_type`
- `quantity`
- `quantity_before`
- `quantity_after`
- `reference_type`
- `reference_id`
- `notes`
- `user_id`

This is used for operational audit and inventory tracking.

### Example movement types

- `stock_in`
- `returned`

The system currently logs these in live inventory updates, especially during stock-in and return handling.

---

## 8. Reporting flow

The custodian dashboard and reports rely on aggregated queries over inventory and transactions.

### Dashboard calculations

The dashboard totals include:

- total inventory quantity
- available quantity
- low-stock count
- pending requests count

### Reporting calculations

The reports compute:

- category totals
- status totals
- recent transaction history
- overall inventory values

This is all designed for operational visibility rather than just simple CRUD views.

---

## 9. Key rules and constraints

The system enforces a number of important rules:

- Assigned inventory cannot be deleted
- Quantity changes to assigned inventory are restricted
- Inventory cannot be assigned more than the available quantity
- Items with required serial numbers must have valid unique serial numbers
- Returns and transfers are validated before processing

These rules protect data integrity and make the inventory workflow more trustworthy.

---

## 10. End-to-end summary

The real workflow is:

- stock-in -> available inventory
- request -> waiting for approval
- approval -> assigned inventory
- transfer -> recipient assignment
- return -> available again
- report -> operational insight

This makes the system a role-driven inventory control workflow rather than a simple asset registry.

The central logic is implemented in [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php), and it is backed by the inventory and stock movement models for reliable state tracking.
