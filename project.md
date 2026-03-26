# Project Rules — Tailor Management

These rules apply to all code and UI in this project. Follow them for new work and fix existing code that does not comply.

---

## Livewire & Forms

- **Form field binding:** Use `wire:model.blur` for form fields (inputs, selects, textareas). Do **not** use `wire:model.live` for form data so updates happen on blur instead of every keystroke.
- **Search/filter inputs:** For search boxes and filters that drive live results, `wire:model.blur.debounce.300ms` or `wire:model.blur` is acceptable (update on blur; use debounce only if you need to trigger on blur after typing).
- **Navigation:** Prefer `wire:navigate` for in-app links for SPA-like behaviour.

---

## Number Inputs

- **Whole numbers:** Use `step="1"` for number inputs so the spinner increments by whole numbers (e.g. quantity, count, per-page).
- **Currency/amounts:** Use `step="1"` for monetary amounts unless the business requires decimal cents, in which case use `step="0.01"`.
- Prefer integer increments in the UI unless decimals are explicitly required.

---

## Icons

- **Use Material Icons** for all UI icons.
- Use the shared component: `<x-icon name="material_icon_name" class="..." />` (e.g. `add`, `delete`, `edit`, `arrow_back`, `check`, `refresh`, `error`, `warning`, `search`, `close`, `download`, `print`, `person`, `description`, `payments`, `visibility`, `expand_more`, `expand_less`, `shield`, `archive`).
- Material Symbols Outlined font is loaded in the layout; use official names (see [Google Material Symbols](https://fonts.google.com/icons)). Do not use `<flux:icon>`; use `<x-icon>` instead.

---

## Code Style & Conventions

- **PHP:** Follow Laravel conventions. Use **Laravel Pint** for formatting (`pint.json` preset).
- **Blade:** Use meaningful `@if`/`@foreach` and keep templates readable; avoid heavy logic in views.
- **Authorization:** Use policies and `@can` / `authorize()`; do not hide sensitive actions without server-side checks.
- **Validation:** Validate on the server (Livewire `validate()` or Form Requests); use `@error` in Blade where appropriate.
- **Branch scoping:** Respect branch context for multi-branch data; use `BranchScoped` and branch selectors where applicable.
- **i18n:** Use `__()` for user-facing strings so they can be translated.

---

## UI & Layout

- **Components:** Use **Flux** components (`flux:card`, `flux:button`, `flux:input`, etc.) for consistency.
- **Layout:** Use `flux:main` for main content area; use existing sidebar layout for app pages.
- **Form placement rule:** Do not place an inline create/edit form beside an index/list on the same page.
- **Simple CRUD:** Use a modal-triggered form from the index/list page.
- **Complex CRUD:** Use dedicated `create` / `edit` pages and keep index pages list-focused.
- **Feedback:** Show success/error via Livewire flash messages or callouts; use `flux:callout` where appropriate.
- **Loading states:** Use `wire:loading` and `wire:loading.attr="disabled"` on submit buttons and key actions.

---

## Data & Models

- **Policies:** One policy per model for `view`, `create`, `update`, `delete` (and custom actions) as needed.
- **Relations:** Define relationships on Eloquent models; avoid N+1 (use `with()` / `load()`).
- **Transactions:** Wrap multi-step writes in `DB::transaction()` when order/payment/stock must stay consistent.

---

## Security & Config

- **Secrets:** Never commit `.env` or secrets; use `config()` and env vars.
- **CSRF:** Rely on Laravel’s CSRF protection for forms.
- **Permissions:** Use Spatie Laravel Permission (roles/permissions) and check permissions in policies and UI.

---

## Content Safety Rules

- **No raw HTML inputs:** Never allow raw HTML input in any form field.
- **Neutral labels:** Do not use labels like `Body (HTML not allowed)`; use plain labels like `Body`.
- **Auto-generated metadata:** Page slugs and excerpts must be auto-generated from the page title.

---

## Summary Checklist

| Rule | Apply |
|------|--------|
| Form fields | `wire:model.blur` (not `.live`) |
| Number inputs | `step="1"` (whole numbers) unless decimals required |
| Icons | Material Icons via `<x-icon name="..." />` |
| PHP style | Laravel Pint |
| UI components | Flux |
| Auth | Policies + `@can` / `authorize()` |
| Strings | `__()` for translatable text |
