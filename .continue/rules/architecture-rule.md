---
description: Rules for maintaining a clean and consistent application architecture
---

# Architecture Rules

- Follow the existing project architecture before introducing new patterns.
- Keep each layer responsible for one clear purpose.
- Keep controllers thin and move complex business logic into appropriate services.
- Keep database queries out of views.
- Avoid putting business logic directly inside UI components.
- Reuse existing services, components, utilities, and helpers when possible.
- Do not create duplicate implementations of existing functionality.
- Keep modules loosely coupled where practical.
- Prefer composition and reusable abstractions over unnecessary inheritance.
- Do not introduce an architectural pattern unless it provides a clear benefit.
- Keep changes localized to the feature being developed.
- Maintain clear separation between presentation, business logic, data access, and external services.