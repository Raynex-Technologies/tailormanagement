# UI Design Rules

Follow these rules when building or modifying any UI in this application.

## Card Patterns

### Primary Container Card
```html
<div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
```
Use for all major content sections. Prefer this over `flux:card` when you need full control over styling. Always add `overflow-hidden` when the card contains charts or dynamically-sized content.

### Inner Item Card
```html
<div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
```
Use for list items inside a container card (order lines, material rows, stock requests). Always use `rounded-xl` (never `rounded-lg`).

### KPI / Stat Card (with icon)
```html
<div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
    <div class="flex items-start justify-between">
        <div class="flex items-center justify-center size-11 rounded-xl bg-{color}-50 dark:bg-{color}-900/30">
            <i class="fa-duotone fa-{icon} size-5 text-{color}-500"></i>
        </div>
    </div>
    <div class="mt-3">
        <p class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{VALUE}</p>
        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{LABEL}</p>
    </div>
</div>
```

### Section Card (with icon header)
```html
<div class="rounded-2xl bg-white dark:bg-zinc-800/50 p-5 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
    <div class="mb-4 flex items-center gap-3">
        <div class="flex size-10 items-center justify-center rounded-xl bg-{color}-100 dark:bg-{color}-900/30">
            <i class="fa-duotone fa-{icon} text-{color}-600 dark:text-{color}-400" aria-hidden="true"></i>
        </div>
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{TITLE}</h3>
    </div>
    <!-- content -->
</div>
```

## Color Usage

### Status Colors — Use Enum Methods
ALWAYS use `$model->status->color()` or `$model->priority->color()` instead of inline `match()` blocks.

```php
{{-- GOOD --}}
<flux:badge color="{{ $order->status->color() }}" size="lg">

{{-- BAD — never do this --}}
@php $color = match($order->status) { ... }; @endphp
<flux:badge color="{{ $color }}" size="lg">
```

Enum color mappings (for reference only — always call `->color()`):
- **OrderStatus**: New=blue, InProgress=amber, Ready=emerald, Delivered=indigo, Completed=green, Cancelled=red
- **PaymentStatus**: Unpaid=red, Partial=amber, Paid=green
- **Priority**: Low=zinc, Normal=blue, High=amber, Urgent=red
- **StockRequestStatus**: Requested=blue, Approved=amber, Declined=red, Fulfilled=green

### Financial Colors
- Total/Invoice amount: `text-indigo-600 dark:text-indigo-400`
- Paid/Positive: `text-green-600 dark:text-green-400`
- Balance Due (> 0): `text-red-600 dark:text-red-400`
- Balance Due (= 0): `text-green-600 dark:text-green-400`
- Discount: `text-red-600` (always negative)

### Brand Colors
- **Tailex Lime** — `#A3E635` / `#84CC16` (gradient) — sidebar active links, brand accent buttons, logo background
- **Tailex Navy** — `#1E1F2E` / `#252637` (gradient) — sidebar background, order header card, dark surfaces
- **Tailex Orange** — `#fe6328` — reserved for primary CTA (`flux:button variant="primary"`)
- **Tailex Cream** — `#FBF6F1` — auth page backgrounds, warm neutral surfaces
- **Tailex Peach** — `#D4A574` — auth buttons, warm accent (login, decorative elements)

## Icon Conventions

### Library
Font Awesome 6 Pro **Duotone** (`fa-duotone`). Assets are located in `public/vendor/fontawesome/`. Always use `fa-duotone` for **all** icons — content icons, section headers, KPI cards, inline info rows, empty states, alerts, and sidebar navigation. Never use `fa-duotone`, `fa-regular`, or `fa-light`.

### Always Use Colored Containers
Never show a bare icon. Wrap in a colored container:
```html
<div class="flex items-center justify-center size-{10|11} rounded-xl bg-{color}-50 dark:bg-{color}-900/30">
    <i class="fa-duotone fa-{icon} size-{4|5} text-{color}-500"></i>
</div>
```

### Small Inline Icons (info rows)
For definition lists and info rows, small icons are acceptable without containers:
```html
<i class="fa-duotone fa-calendar size-3.5 text-zinc-400 dark:text-zinc-500"></i>
```

## Dark Mode

Every visible element MUST have `dark:` variants:
- Backgrounds: `bg-white` → `dark:bg-zinc-800/50`
- Inner backgrounds: `bg-zinc-50` → `dark:bg-zinc-800/50`
- Borders: `border-zinc-200/50` → `dark:border-zinc-700/50`
- Text primary: `text-zinc-900` → `dark:text-white`
- Text secondary: `text-zinc-500` → `dark:text-zinc-400`
- Colored backgrounds: `bg-{color}-50` → `dark:bg-{color}-900/30`
- Colored text: `text-{color}-600` → `dark:text-{color}-400`

## Spacing & Typography

- Page padding: `p-6` on `flux:main`
- Section spacing: `space-y-6` between major cards
- Grid gaps: `gap-4` for stat cards, `gap-6` for main content grids
- Item spacing within a card: `space-y-3` or `space-y-4`
- Financial values: always use `font-mono`
- Large numbers: `text-3xl font-bold tracking-tight`

## Grid Columns

Always add `min-w-0` to CSS Grid column children to prevent content overflow:
```html
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 min-w-0 space-y-6">...</div>
    <div class="lg:col-span-1 min-w-0 space-y-6">...</div>
</div>
```

## Empty States

Use this consistent pattern:
```html
<div class="py-10 text-center">
    <div class="flex items-center justify-center size-14 rounded-2xl mx-auto mb-3 bg-zinc-100 dark:bg-zinc-800">
        <i class="fa-duotone fa-{icon} size-7 text-zinc-400 dark:text-zinc-500"></i>
    </div>
    <p class="text-sm font-medium text-zinc-900 dark:text-white">{TITLE}</p>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{DESCRIPTION}</p>
</div>
```

## Action Button Hierarchy

From most to least prominent:
1. `variant="danger"` — Delete, destructive actions
2. `variant="primary"` — Mark Completed, main CTA
3. No variant (default) — Create Delivery Note, secondary actions
4. `variant="subtle"` — Edit, View, navigational
5. `variant="ghost"` — Cancel, dismiss

## Alert / Banner Pattern

```html
<div class="rounded-2xl p-4 border border-{color}-200 dark:border-{color}-800/50 bg-{color}-50 dark:bg-{color}-900/20">
    <div class="flex items-center gap-3">
        <div class="flex items-center justify-center size-10 rounded-xl bg-{color}-100 dark:bg-{color}-900/50 shrink-0">
            <i class="fa-duotone fa-{icon} size-5 text-{color}-500"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-{color}-700 dark:text-{color}-300">{TITLE}</p>
            <p class="text-sm text-{color}-600 dark:text-{color}-400">{MESSAGE}</p>
        </div>
    </div>
</div>
```
