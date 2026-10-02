# TAILOR-POS-VARIANTS-4

Implemented 2026-09-13 in tmhub/tailormanagement.

## Implemented

POS now sells simple products and exact InventoryItemVariant/InventoryStockUnit identities. No new product or stock model was introduced. Inventory remains responsible for configuration. Manage Variations links were also added to Inventory item actions and the Edit Item modal after the user found the existing product-name link too difficult to discover.

## POS Product Discovery

Simple products retain one-click Add. Variant cards show available on-hand minus reserved totals, ready active variation count and explicit minimum/range-based pricing. Aggregate queries load up to 60 products; detailed options/variants load only for the selected product. Product/category/variant SKU and option-value text support discovery. Product cards never silently add the first or last-used variation.

## Variation Selection UX

The focused Flux modal presents product-local option buttons, then an exact variation description, SKU, price and available quantity before Add to Cart. Changing an earlier choice clears later choices. Selection resets on every product click. Controls use 44px minimum targets and configurable application accent; primary actions retain the project action theme.

## Sparse Combination Behavior

Only persisted real combinations participate. For White with no Medium, Medium is labelled Not offered and disabled. Existing inactive/unallocated identities show Unavailable; ready identities without stock show Out of stock. No missing combination is created or substituted. Exact addition is revalidated server-side even if public selection state is altered.

## Barcode / SKU Scanning

Enter resolves exact barcode first, exact SKU second, then leaves unmatched input as product discovery with a clear message. Known unavailable identifiers do not fall through to another product. Successful scans add directly, clear the input and restore focus. Enter passes the current DOM input value to avoid a debounce race. Two immediate scans were verified in the browser to create one exact cart line with quantity two.

## Cart Identity

Named unit_<id> keys prevent both parent-based variant merging and sparse JavaScript array serialization. Different variations remain separate; repeated exact identities merge. The cashier sees product name, variation description and secondary SKU, never internal identifiers. Existing discount state remains attached to the exact line.

## Pricing

Checkout uses InventoryStockUnit.selling_price, never runtime parent-plus-delta pricing. Missing or invalid pricing is rejected. UI submits its expected price; a changed price blocks checkout with instructions to remove/re-add the affected line for review. Server recalculates totals rather than trusting client price/total fields. No reference-cost snapshot or COGS was introduced.

## Stock & Concurrency

Checkout locks parent items in sorted order before identity/price reads, locks exact units and balances, revalidates sellability and effective branch, then issues through StockMovementService inside the sale transaction. Reserved quantity is excluded. Duplicate payload lines cannot overdraw stock: a failing later issue rolls back the entire sale and earlier deductions. The last-unit sequential/stale availability and transaction rollback cases are tested. True simultaneous MySQL cashier sessions were not exercised; no claim of live concurrency certification is made.

Existing POS semantics continue to require physical stock even for track_stock=false simple products. This behavior is explicitly tested rather than introducing a new untracked-sale policy.

## POS Sale Provenance

New lines persist inventory_item_id, inventory_item_variant_id and inventory_stock_unit_id plus item name, variation description, SKU, price, quantity, discount and total snapshots. Movements issue the exact unit and retain the existing POS sale reference. Historical item-only rows remain untouched. This provides identity for a future exact return without adding a refund subsystem.

## Receipt Changes

The shared receipt slip displays the immutable variation description below item name. Renaming option values later does not change historical receipts. Browser checkout produced three distinct lines: Needles, Black/Medium and White/Large, using their explicit prices. Simple historical receipts remain covered by existing POS tests.

## Permissions

pos.view and pos.sell remain authoritative. PosSaleService checks the supplied cashier's selling permission; exact unit branch validation remains mandatory. POS does not expose Inventory configuration, barcode editing, cost editing or stock adjustment. Customer creation still requires customers.create independently.

## Frontend UI Skill Usage

Applied the installed frontend-app-builder skill with existing Flux, typography, theme and POS layout rules. Generated selector concept: C:/Users/HP/.codex/generated_images/01a098eb-0610-7123-9918-47222a1c2068/exec-3a3875db-899d-4b37-a537-d9dcb354b05d.png. The concept guides the focused selector, touch targets, summary and unavailable states. Existing chrome, actual product data and configurable colors remain authoritative; illustrative product imagery and invented navigation were not adopted.

## Responsive / Dark-Light Verification

No callable browser connector was available; installed Playwright Chromium tested a cashier-only account against a separate SQLite QA database on 127.0.0.1:8124. Desktop 1440x1000, mobile 390x844 and tablet 768x1024 selector screenshots were captured; light and dark states were exercised. Verified simple Add, Black/Medium selection, White/Medium Not offered, exact barcode/SKU addition, distinct cart lines, checkout, receipt and repeated rapid scans. Browser workflow emitted no JavaScript errors. Screenshots visually reviewed for modal width, copy, selected contrast, quantity/price hierarchy, touch spacing and receipt descriptions. Existing POS shell now permits mobile vertical scrolling. Full manual accessibility certification is not claimed.

view_image has a stale-directory os267 limitation in this environment, so image bytes were displayed through exec for visual inspection. Selected evidence is retained in docs/evidence/pos-variants-4. The generated concept is a workflow reference, not a pixel-identical design target.

## Known POS Customer Modal Issue

Diagnosed and fixed in the test fixture, without changing production authorization. The sales role does not have customers.create. openCustomerModal returned 403; the subsequent set request surfaced an Invalid Livewire snapshot because the preceding response had no valid snapshot. The success test now explicitly grants customers.create and continues asserting actual creation. A separate test proves cashiers without this permission receive 403. No test was deleted or weakened to ignore a failure. Earlier reports attributing the error to the first modal request were incomplete: the visible snapshot exception occurs on the next field-update request.

## Files Changed

- app/Services/Pos/PosSaleService.php
- app/Livewire/Pos/PosTerminal.php
- app/Models/PosSaleItem.php
- resources/views/livewire/pos/pos-terminal.blade.php
- resources/views/pos/partials/receipt-slip.blade.php
- resources/views/layouts/pos.blade.php
- resources/views/livewire/inventory/items/index.blade.php (discoverable Manage Variations links)
- database/migrations/2026_09_13_000004_add_pos_variant_snapshots.php
- tests/Feature/Pos/PosVariantsTest.php, PosTerminalTest.php, PosSnapshotDiagnosticTest.php (permission regression)
- generated frontend build artifacts, evidence and this report

## Migrations

2026_09_13_000004_add_pos_variant_snapshots.php applied locally successfully. Adds nullable inventory_item_variant_id with restricted deletion and nullable variation_description to pos_sale_items. No historical rows are rewritten. Automatic destructive rollback is refused.

Deployment: pause POS writes, deploy code, run `php artisan migrate --path=database/migrations/2026_09_13_000004_add_pos_variant_snapshots.php --force`, build frontend assets, cache views and verify reconciliation before resuming. Prior stock-unit and variant-administration migrations must already be applied. No development products are automatically converted or allocated.

## Tests

Final focused group: 67 tests / 316 assertions passed with no exclusions. Includes all POS tests, StockUnitFoundation, StockIntegrityFoundation, VariantAdministration and InventoryCatalogCrud. Seven phase PHP files passed syntax and Pint. Blade compilation and Vite build are verified. Barcode/SKU, sparse selection, distinct/merged cart identities, expected-price changes, rollback, last-unit exhaustion, branch restrictions, retired/unallocated identities, simple tracked semantics, sale snapshots and renamed-value receipt stability are covered.

Final local preservation verification passed for all eight original-data hashes. Reconciliation checked 30 items and 30 stock rows: zero stock-unit structural anomalies; the 30 known historical ledger differences remain unchanged. Temporary QA database, session and scripts were removed after stopping the server. Known historical ledger differences remain reviewed legacy evidence, not automatically repaired. No Git bookkeeping is reported.

## Deferred

Orders/package variant selectors, procurement bulk variant receiving, stock transfers, shared multi-branch catalogue, weighted-average/FIFO/COGS, full refunds and Storefront retirement remain deferred. True multi-session MySQL concurrency and exhaustive human accessibility acceptance remain validation limitations.
