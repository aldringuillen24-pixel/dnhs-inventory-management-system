---
description: Application security and authorization rules
---

# Security Rules

- Never expose passwords, API keys, tokens, secrets, or credentials in source code.
- Never commit sensitive credentials to version control.
- Use environment variables for secrets and environment-specific configuration.
- Validate all user-controlled input.
- Use the framework's built-in authentication and authorization mechanisms.
- Enforce authorization on the server side.
- Never rely solely on frontend checks for access control.
- Verify that users have permission to perform protected operations.
- Follow the project's role-based access control rules.
- Protect sensitive routes and actions with appropriate middleware or authorization policies.
- Do not expose sensitive database information in responses.
- Do not log passwords, tokens, or other sensitive information.
- Use parameterized queries or the framework's query builder/ORM to prevent SQL injection.
- Escape untrusted output where appropriate to prevent XSS.
- Use CSRF protection for state-changing web requests.
- Do not disable security protections simply to make an error disappear.
- Do not bypass authentication or authorization during implementation.