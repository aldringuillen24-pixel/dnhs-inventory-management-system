# Roles and Access

This project uses a strict role-based access model. Access is enforced at the route layer with middleware and is also reflected in dashboard redirection logic.

## 1. Role model

The system defines roles in the app's role table and associates them with each user through the `role_id` foreign key on the user model.

Relevant files:

- [app/Models/User.php](../app/Models/User.php)
- [app/Models/Role.php](../app/Models/Role.php)
- [routes/web.php](../routes/web.php)

## 2. Supported roles

### Administrator

- Route prefix: `/admin`
- Middleware: `['auth', 'role:Administrator']`
- Dashboard: `admin.dashboard`
- Main responsibilities:
  - manage users
  - generate user accounts
  - export slips
  - manage profile

Routes include:

- `/admin/dashboard`
- `/admin/users-management`
- `/admin/profile`
- `/admin/users-management/export-slips`
- `/admin/users-management/generate-users`

### Property Custodian

- Route prefix: `/property-custodian`
- Middleware: `['auth', 'role:Property Custodian']`
- Dashboard: `propertyCustodian.dashboard`
- Main responsibilities:
  - inventory management
  - stock-in
  - assignment and tracking
  - request approval/decline
  - transfer and return processing
  - reports and metrics

Routes include:

- `/property-custodian/dashboard`
- `/property-custodian/inventory`
- `/property-custodian/inventory/stock-in`
- `/property-custodian/transactions`
- `/property-custodian/reports`
- `/property-custodian/profile`

This is the most operational role in the system and the main source of inventory workflow logic.

### School Head

- Route prefix: `/school-head`
- Middleware: `['auth', 'role:School Head']`
- Dashboard: `schoolHead.dashboard`
- Main responsibilities:
  - monitor inventory overview
  - review reports
  - view school-level inventory insights
  - manage profile

Routes include:

- `/school-head/dashboard`
- `/school-head/inventory/overview`
- `/school-head/reports`
- `/school-head/profile`

### End User

- Route prefix: `/end-user`
- Middleware: `['auth', 'role:End User']`
- Dashboard: `endUser.dashboard`
- Main responsibilities:
  - request items
  - check assigned items
  - request return of assigned items
  - handle transfer requests
  - view personal request history

Routes include:

- `/end-user/dashboard`
- `/end-user/requests`
- `/end-user/assigned-items`
- `/end-user/profile`

## 3. Root route redirection logic

The app redirects users from `/` based on their role and onboarding state.

The root route logic in [routes/web.php](../routes/web.php) checks:

- if the user is an Administrator → redirect to `admin.dashboard`
- if the user is a Property Custodian → redirect to `propertyCustodian.dashboard` or onboarding
- if the user is an End User → redirect to `endUser.dashboard` or onboarding
- if the user is a School Head → redirect to `schoolHead.dashboard` or onboarding

This means onboarding is route-driven and happens before the user enters their main dashboard.

## 4. Onboarding behavior

The app supports a temporary-password onboarding flow for roles that require first-time setup.

This is controlled in the controllers, for example:

- [app/Http/Controllers/PropertyCustodianController.php](../app/Http/Controllers/PropertyCustodianController.php)
- [app/Http/Controllers/EndUserController.php](../app/Http/Controllers/EndUserController.php)
- [app/Http/Controllers/SchoolHeadController.php](../app/Http/Controllers/SchoolHeadController.php)

If `temporary_password` is present, the user is redirected to the onboarding route until they complete profile setup.

## 5. Access rules by feature

### Inventory access

- Property Custodian owns the core inventory management flow.
- School Head can view reporting and overview.
- End User can request or receive assigned items.
- Administrator manages the users behind these roles.

### Approval workflow

The Property Custodian is the approval authority for:

- request approvals
- transfer approvals
- return approvals

These actions are defined in the custodian controller and route definitions.

### AI access

The AI assistant endpoint is accessible to authenticated users, but the assistant responds using role-scoped context. This is implemented in:

- [app/Services/AiInventoryService.php](../app/Services/AiInventoryService.php)
- [app/Http/Controllers/AiAssistantController.php](../app/Http/Controllers/AiAssistantController.php)

## 6. Summary

This project is designed around a clear separation of responsibilities:

- Administrator manages users and governance
- Property Custodian controls inventory operations
- School Head reviews operational visibility
- End User interacts with requests and assigned inventory

The route and middleware setup is the primary enforcement mechanism, while the root redirect and onboarding logic ensure the correct experience for each user type.
