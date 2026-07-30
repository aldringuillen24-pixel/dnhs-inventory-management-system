Task:
Add toast notification after successful sign in, and also for unsuccessful sign in.

Objectives:
1. Add a success toast message after a successful sign-in.
2. Add an error toast message for failed sign-in attempts.
3. Reuse the existing shared toast components and authentication flow where possible.

Implementation Notes:
1. Keep the change scoped to the authentication flow and reusable toast components.
2. Use the existing shared common component structure instead of introducing new design patterns.
3. Ensure the notifications are triggered from the sign-in response flow.

Acceptance Criteria:
1. A success toast appears after a successful sign-in.
2. An error toast appears when sign-in credentials are invalid.
3. The implementation remains scoped to authentication-related changes and reusable UI components.

Requirements:
1. Edit only the necessary authentication-related files.
2. Preserve the current project structure and avoid unnecessary file creation.
3. Reuse existing UI layouts and components where possible instead of introducing new design patterns.
4. Keep the implementation migration-friendly, secure, and compatible with the existing Laravel project.

Rules:
1. Code only.
2. Avoid unrelated changes or unnecessary front-end modifications.