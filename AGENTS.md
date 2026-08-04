# Project Rules

## Hard Rule: AGENTS.md Protection

This file may only be modified after the user gives explicit permission for that specific change in the current conversation.

- NEVER create, edit, replace, rename, or delete `AGENTS.md` unless the user explicitly authorizes it first.
- If a task would require changing `AGENTS.md`, stop and ask for explicit permission before making the edit.

## Hard Rule: Database Safety

This rule is mandatory for every task in this repository and overrides any conflicting implementation preference.

- NEVER make arbitrary or unrelated database changes.
- Schema changes must only be introduced when required by the explicitly authorized task.
- For this pre-production application, migrations, table replacement, column changes, and destructive cleanup of development-only schema are permitted when explicitly required by the requested implementation.
- Keep schema changes scoped to the task and validate them with appropriate migration and focused tests.
- NEVER run broad destructive artisan or database commands such as `migrate:fresh`, `db:wipe`, `migrate:refresh`, or equivalent whole-database reset flows unless the user explicitly requests that exact operation.

## Hard Rule: Implementation Priorities

This rule is mandatory for every code implementation in this repository.

- ALWAYS prioritize strong security practices first (authorization, validation, input/output safety, and safe handling of secrets).
- ALWAYS prioritize user experience (clear behavior, accessible interactions, and no avoidable regressions in usability).
- ALWAYS prioritize performance (avoid unnecessary queries, N+1 patterns, wasteful rendering, and inefficient logic paths).
- When tradeoffs are required, do not ship silent compromises: call them out clearly and choose the safest and most maintainable option.
