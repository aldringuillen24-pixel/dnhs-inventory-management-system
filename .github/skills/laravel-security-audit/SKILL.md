---
name: laravel-security-audit
description: "Use when auditing DNHS Laravel authentication, role authorization, middleware, CSRF, validation, mass assignment, route model binding, SQL exposure, sessions, uploads, secrets, exports, or inventory privacy."
---
# Laravel Security Audit

Use this workflow for read-only security reviews unless the user explicitly requests fixes.

## Trace
- Start at the route and middleware.
- Follow request input through validation, authorization, controller logic, model persistence, and response rendering.
- Inspect policies, relationships, Form Requests, migrations, logs, exports, and nearby tests.

## Check
- Authentication and role middleware cover every protected route.
- Object access cannot be changed by guessing IDs.
- CSRF, validation, mass assignment, and file handling are present.
- Inventory quantities cannot become negative or bypass transaction history.
- Sensitive fields are absent from views, logs, AI prompts, and responses.
- Errors do not reveal secrets or unnecessary internal details.
- Multi-record changes are transactional and concurrency-aware.

## Report
Order findings by severity. Each finding must include a file and line reference, concrete impact or abuse path, and concise remediation. Separate confirmed findings from assumptions and test gaps. Do not modify files or commit during an audit.
