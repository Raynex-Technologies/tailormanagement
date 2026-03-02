# Project Rules

## Hard Rule: AGENTS.md Protection

This file may only be modified after the user gives explicit permission for that specific change in the current conversation.

- NEVER create, edit, replace, rename, or delete `AGENTS.md` unless the user explicitly authorizes it first.
- If a task would require changing `AGENTS.md`, stop and ask for explicit permission before making the edit.

## Hard Rule: Database Safety

This rule is mandatory for every task in this repository and overrides any conflicting implementation preference.

- NEVER delete, drop, truncate, reset, refresh, rollback, or otherwise destroy existing database schema.
- NEVER alter existing database schema in any way. Do not create or modify migrations that change tables, columns, indexes, constraints, or relationships.
- NEVER delete, purge, truncate, overwrite, or mass-reset existing database data.
- NEVER run or write seeders, tests, commands, scripts, or migrations that remove or rewrite current data.
- NEVER run destructive artisan or database commands such as `migrate:fresh`, `db:wipe`, `migrate:refresh`, `migrate:reset`, rollback flows, truncate flows, or equivalent SQL.
- If a requested task would require schema changes or destructive data changes, stop and tell the user that the task conflicts with this rule.

Treat this as a permanent operating constraint for all future work in this repository.
