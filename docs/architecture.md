# Architecture Overview

This project is a Laravel-based inventory and asset management system for the Department of National High Schools (DNHS). It combines a role-based web application, a transactional inventory model, and an AI assistant that provides role-scoped guidance using local inventory context.

## 1. System purpose

The application supports four primary user roles:

- Administrator
- Property Custodian
- School Head
- End User

Each role has a dedicated dashboard and permissioned routes. The platform manages inventory records, assignment requests, transfers, returns, transaction logs, and inventory reporting.

---

## 2. Technology stack

- PHP 8.2+
- Laravel 12
- Blade templating
- Tailwind CSS
- Alpine.js
- Vite for frontend asset bundling
- MySQL/PostgreSQL/SQLite support
- Pest for testing
- OpenRouter API integration for the AI assistant

The app is structured as a classic Laravel MVC application with role-aware controllers, Eloquent models, Blade views, and service-level orchestration.

---

## 3. High-level application flow

The browser requests are routed through [routes/web.php](../routes/web.php), which defines the main entry points and role middleware.

Typical flow:

1. User signs in via the login/sign-in page.
2. Laravel authenticates the user using the custom auth flow.
3. The app redirects users to an appropriate dashboard based on role and onboarding state.
4. The controller loads data, runs business logic, and returns a Blade view.
5. Forms trigger actions such as stock-in, assignment, transfer, return, or profile update.
6. Eloquent models persist changes and related records such as transactions and stock movements.

---

## 4. Route and role architecture

The route layer is the central control point for access and navigation.

### Main route groupings

- Public auth routes: sign in, login redirect, logout
- Authenticated app routes
- Role-based route groups:
  - Property Custodian
  - End User
  - School Head
  - Administrator

The route definitions are centralized in [routes/web.php](../routes/web.php).

### Role behavior

The application uses route middleware to restrict access:

- `role:Property Custodian`
- `role:End User`
- `role:School Head`
- `role:Administrator`

Role-based redirects are also enforced on the root route, which sends each user to the correct dashboard depending on their role and whether they still need onboarding.

---

## 5. Core application layers

### Controllers

Controllers handle request validation, data retrieval, and business logic. The most important controller is:

- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)

This controller manages:

- onboarding
- dashboard metrics
- inventory listing and summarization
- stock-in operations
- assignment flows
- transaction approval workflows
- transfer and return handling
- reports
- profile updates

Other major controllers include:

- [app/Http/Controllers/AdminDashboardController.php](../app/Http/Controllers/AdminDashboardController.php)
- [app/Http/Controllers/SchoolHeadController.php](../app/Http/Controllers/SchoolHeadController.php)
- [app/Http/Controllers/EndUserController.php](../app/Http/Controllers/EndUserController.php)
- [app/Http/Controllers/UserController.php](../app/Http/Controllers/UserController.php)
- [app/Http/Controllers/AiAssistantController.php](../app/Http/Controllers/AiAssistantController.php)

### Models

The domain model is mostly Eloquent-based and centered around inventory data and user ownership.

#### Primary models

- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Inventory.php](../app/Models/Inventory.php)
- [app/Models/Category.php](../app/Models/Category.php)
- [app/Models/Role.php](../app/Models/Role.php)
- [app/Models/AssignmentRequest.php](../app/Models/AssignmentRequest.php)
- [app/Models/Transaction.php](../app/Models/Transaction.php)
- [app/Models/StockMovement.php](../app/Models/StockMovement.php)

#### Important relationships

- `User` belongs to `Role`
- `User` can have many `Inventory` items
- `Inventory` belongs to `Category`
- `Inventory` belongs to `User` and optionally to `assignedTo`
- `Inventory` has many `StockMovement` records
- `AssignmentRequest` links requesting users, target users, and inventory items

---

## 6. Inventory domain model

The inventory domain is centered on the `Inventory` model at [app/Models/Inventory.php](../app/Models/Inventory.php).

### Inventory characteristics

Inventory items include fields such as:

- category
- item name
- unit
- quantity
- unit cost
- ICS number
- serial number
- inventory item number
- status
- acquisition date
- assigned user
- QR code

### Key statuses

The system clearly distinguishes item state, including values such as:

- available
- assigned
- under_inspection
- under_maintenance
- disposed

These statuses are used in metrics, filtering, and reporting. The dashboard and reports frequently calculate totals using grouped item names and status filters.

---

## 7. Transaction and movement model

Inventory changes are not only stored as a single row update; they are also tracked as business events.

### Stock movement tracking

The stock movement table records item-level history for the movement lifecycle. The schema is defined in:

- [database/migrations/2026_08_29_021548_create_stock_movements_table.php](../database/migrations/2026_08_29_021548_create_stock_movements_table.php)

The model used for this behavior is:

- [app/Models/StockMovement.php](../app/Models/StockMovement.php)

This supports auditing and helps answer questions such as:

- what changed
- when it changed
- how much was added or removed
- who initiated the change

### Transactions

The application also stores explicit transaction records via [app/Models/Transaction.php](../app/Models/Transaction.php), which supports official operations like assignment and return flows.

---

## 8. Business workflow architecture

### Inventory workflow

The main operational workflows are implemented in the property custodian controller and are centered on inventory management:

1. Add stock items into inventory
2. Group inventory by item name and category
3. Calculate available vs assigned quantities
4. Process assignment requests from end users
5. Validate inventory before transfer or return
6. Approve or reject requests
7. Generate reports from aggregated item data

### Request lifecycle

The request flow is built around `AssignmentRequest` records:

- user requests an item
- request waits for approval
- custodian may approve or decline
- transfer requests may require additional approval steps
- return requests are processed after assignment

These paths are all implemented in the controller logic and reflected in the dashboards and reporting views.

---

## 9. Data and reporting architecture

The system is designed around aggregated reporting rather than raw transaction-only analytics.

### Report generation

The controller methods build metrics from grouped query results, including:

- total units
- available units
- assigned units
- low stock counts
- category totals
- status totals
- recent transactions

The most important reporting entry is:

- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)

This file builds summary data for views such as dashboards and reports, then passes arrays of chart-ready data to Blade.

---

## 10. AI assistant architecture

The project includes a role-aware AI assistant layer, implemented in:

- [app/Services/AiInventoryService.php](../app/Services/AiInventoryService.php)
- [app/Http/Controllers/AiAssistantController.php](../app/Http/Controllers/AiAssistantController.php)

### Design pattern

The AI service:

- detects the authenticated user role
- builds a role-scoped context array from live database data
- creates a system prompt grounded in the user’s role and inventory state
- calls OpenRouter when an API key is configured
- falls back to a local grounded response if the external API is unavailable

### Why this matters

This is not a generic chatbot. It is tightly tied to the app’s domain data so that prompts can answer questions related to:

- assigned inventory
- warehouse availability
- recent requests
- role-specific responsibilities

---

## 11. Project structure summary

Key folders in the app:

- `app/Http/Controllers` — request handling and business logic
- `app/Models` — domain entities and Eloquent relationships
- `app/Services` — external integrations and orchestration logic
- `app/Helpers` — reusable support helpers
- `database/migrations` — schema definitions
- `resources/views` — Blade templates and UI pages
- `routes` — routing and middleware setup
- `tests` — Pest tests

---

## 12. Architectural conclusions

This project is best understood as a Laravel role-based inventory system with the following architectural pattern:

- Route layer controls access and navigation
- Controller layer handles workflow logic
- Model layer defines inventory and user relationships
- Migration layer defines the persistence schema
- Views render role-specific dashboards and forms
- Service layer provides external AI integration and context building

The dominant business flow is inventory lifecycle management: stock-in, assignment, approval, transfer, return, and reporting. The route and controller structure make the app highly role-driven, while the Eloquent models and transaction tables keep the system auditable and operationally clear.

---

## 13. Best files to read first

For a developer working in this project, the best entry points are:

1. [routes/web.php](../routes/web.php)
2. [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)
3. [app/Models/Inventory.php](../app/Models/Inventory.php)
4. [app/Models/User.php](../app/Models/User.php)
5. [app/Services/AiInventoryService.php](../app/Services/AiInventoryService.php)
6. [database/migrations/2026_08_10_000001_create_inventory_table.php](../database/migrations/2026_08_10_000001_create_inventory_table.php)

These files give the clearest picture of the real architecture and business logic of the application.
