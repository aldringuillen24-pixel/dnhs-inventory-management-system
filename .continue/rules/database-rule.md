---
description: Database design and data management rules
---

# Database Rules

- Follow the existing database schema and relationships before making changes.
- Inspect existing migrations and models before creating new database structures.
- Use migrations for all database schema changes.
- Use appropriate primary keys and foreign keys.
- Maintain proper referential integrity between related tables.
- Use Eloquent relationships when working with Laravel models.
- Avoid unnecessary raw SQL queries.
- Use database transactions for operations that modify multiple related records.
- Validate data before inserting or updating database records.
- Do not duplicate data when an existing relationship can be used.
- Use appropriate data types for each database column.
- Avoid storing calculated values when they can reliably be calculated from existing data.
- Do not delete or modify existing columns without checking their dependencies.
- Never modify production data directly during development.
- Keep database changes backward-compatible when practical.