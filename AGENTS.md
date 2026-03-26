# Project Rules

## Hard Rule: AGENTS.md Protection

This file may only be modified after the user gives explicit permission for that specific change in the current conversation.

- NEVER create, edit, replace, rename, or delete `AGENTS.md` unless the user explicitly authorizes it first.
- If a task would require changing `AGENTS.md`, stop and ask for explicit permission before making the edit.

## Hard Rule: Database Safety

This rule is mandatory for every task in this repository and overrides any conflicting implementation preference.

- NEVER delete, purge, truncate, overwrite, or mass-reset existing database data.
- NEVER run or write seeders, tests, commands, scripts, or migrations that remove or rewrite current data.
- NEVER run destructive artisan or database commands such as `migrate:fresh`, `db:wipe`, `migrate:refresh`, `migrate:reset`, rollback flows, truncate flows, or equivalent SQL.
- If a requested task would require schema changes or destructive data changes, stop and tell the user that the task conflicts with this rule.

Treat this as a permanent operating constraint for all future work in this repository.

## Hard Rule: Implementation Priorities

This rule is mandatory for every code implementation in this repository.

- ALWAYS prioritize strong security practices first (authorization, validation, input/output safety, and safe handling of secrets).
- ALWAYS prioritize user experience (clear behavior, accessible interactions, and no avoidable regressions in usability).
- ALWAYS prioritize performance (avoid unnecessary queries, N+1 patterns, wasteful rendering, and inefficient logic paths).
- When tradeoffs are required, do not ship silent compromises: call them out clearly and choose the safest and most maintainable option.
