# Task: Implement Role-Based Sidebar Menus

## Goal
Make the sidebar show different menu items for each of the 5 existing roles in the system:
- Administrator
- School Head
- Property Custodian
- Inspector
- End User

## Scope
- Update the sidebar/menu rendering so each role sees only the menu items relevant to their responsibilities.
- Keep the implementation aligned with the current Laravel structure and existing role setup.

## Expected Role Access and Sidebar Navigation
Sidebar Navigation:

Administrator
- Dashboard
- User Management
- Inventory Overview
- Transaction Overview
- Reports
- System Settings

School Head
- Dashboard
- Approval Requests
- Asset Overview
- Inventory Overview
- Transaction Overview
- Reports
- Profile

Property Custodian
- Dashboard 
- Inventory
- Transaction
- Reports
- Profile

Inspector
- Dashboard
- Items Inspections
- Inspection History
- Reports
- Profile

End User
- Dashboard
- My Requests
- My Assigned Assets
- Request Asset
- Request History
- Profile

## Implementation Notes
- Use the authenticated user role from the existing role system, matching on the role identifier that the app already uses (for example, role `name` ).
- Prefer a maintainable filtering approach in the menu helper/view layer instead of hardcoding separate sidebars.
- Keep the menu structure clean and easy to extend for future roles or permissions.
- If possible, map sidebar items to known route names or route groups so the asset list remains consistent and easy to verify.

## Acceptance Criteria
- Each role sees the correct sidebar items.
- Unauthorized menu items are hidden.
- The UI stays consistent and works for all 5 roles.
- The solution is easy to maintain and fits the current project structure.

## Suggested Files to Update
- resources/views/layouts/sidebar.blade.php
- app/Helpers/MenuHelper.php
- routes/web.php

## Rules
- Keep the change focused on role-based sidebar access.
- Avoid unrelated UI changes.
- Preserve the current project structure.
- Code only