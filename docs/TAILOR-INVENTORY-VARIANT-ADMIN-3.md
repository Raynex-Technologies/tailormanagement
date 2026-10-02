# TAILOR-INVENTORY-VARIANT-ADMIN-3

Implemented 2026-09-13 in tmhub/tailormanagement.

## Implemented

Inventory now has a dedicated product variation workspace at `/inventory/items/{item}/variations`, linked from the product name in Inventory Items. Canonical InventoryItemVariant and InventoryStockUnit remain the identities; no parallel variant model was introduced.

## Product Variation UX

Open a product for its overview. Simple products show stock, SKU and price without dominant variation navigation. Configure Variations opens focused Options, Combinations and Pricing & Identity sections. Stock and Movements are separate. Add one combination by choosing one value from each active option; only the confirmed choice is created. Forms open contextually with clear save/close actions. Existing Inventory Receive/Adjust buttons route variant products to this workspace.

## Option Types & Values

Product-local options support arbitrary names, stable value IDs, rename, numeric display order, activation and safe deletion. Values are reused through normalized relationships. Renames change displayed labels without recreating variants. Legacy variations can be explicitly mapped to selected values while retaining their IDs, stock units, SKUs, prices and FabricVariant references. Unmapped legacy variations block new combination creation/conversion until reviewed.

## Sparse Combination Management

A sorted stable-value identity key prevents duplicate combinations independently of labels. Creation of a real variant, its selected values and its stock unit is atomic. No Cartesian product is generated. Retired combinations are reactivated with their existing identities. New combinations on an allocated variant product receive an explicit zero balance and baseline.

## SKU / Barcode

Edit each variation's unique SKU and optional primary barcode. Canonical exact lookup and barcode alias protection remain in effect. Blank barcode input preserves existing aliases; no barcode is invented. Duplicate SKU/barcode changes roll back atomically.

## Pricing

Each new variation initializes selling price and reference purchase cost from the parent defaults. Subsequent prices are explicit; parent changes do not reprice existing variations. Zero values are preserved. Reference cost is not FIFO, weighted-average valuation or historical COGS. Bulk price overwrites and SKU generation were not added; parent-default initialization provides the non-destructive pricing assistance.

## Stock Allocation

Review & Enable Variations shows current SKU, stock and price plus allocations to actual active variations. Confirmation requires exactly the locked current on-hand quantity, no reservations and no unresolved issue/return quantities. Stale totals, over-allocation, under-allocation, foreign/retired variants and unknown identities fail without mutation.

An allocation audit row records source identity, actor, quantity and explicit destinations. Canonical adjustment movements debit the old simple balance and credit exact variant balances in the same transaction; their net product quantity is zero. No historical movement is rewritten. The source simple identity and zero balance are retained as inactive historical provenance, not editable parent stock.

## Product Stock Summaries

The isolated acceptance fixture starts with Plain T-Shirt: Black/Small 2, Black/Medium 3, Black/Large 1, White/Small 1, White/Large 3. Tests verify 10 total, Black 6 and White 4. Product and option totals are computed from actual variation balances. The browser then added White/Medium at zero and received 3 units into an explicitly selected variation; later screenshots therefore show 6 variations and 13 units, not the initial fixture.

Inventory list shows aggregate quantities and a From selling price for variant products. Low-stock filtering checks actual active, ready child balances against the existing reorder level. The Stock Overview uses aggregates and no longer assumes a scalar single-row subquery.

## Conversion from Simple Products

Create a normal product, configure its options and real combinations, then explicitly review and confirm conversion. Until confirmation, its simple stock behavior remains. Conversion requires both product-management and stock-adjustment permissions. Ordinary mode toggles remain blocked. Outstanding simple issues/returns block conversion because existing consumers cannot safely choose a variant for their reversal. Completed historical rows are preserved. Conversion back to simple is intentionally not offered.

## Retirement / Deletion Rules

Option/value deletion is allowed only without dependent variations; otherwise deactivate. Variants are retired rather than hard-deleted. On-hand or reserved stock blocks retirement. Historical variant/unit/SKU/barcode and Fabric references survive retirement/reactivation. No transfer capability was added.

## Permissions

Configuration and prices require inventory.items.manage. Receive requires inventory.stock.receive; adjust and allocation require inventory.stock.adjust. Server-side services check permissions independently of visible controls. Product ownership is reloaded and branch checked, including submitted variant/value identities. No cross-branch catalogue or transfer is introduced.

## UI Skill Usage

Read and applied C:/Users/HP/.codex/skills/frontend-app-builder/SKILL.md. Generated workflow reference: C:/Users/HP/.codex/generated_images/01a098eb-0610-7123-9918-47222a1c2068/exec-e881038d-7467-48af-a05a-be7b5aab6976.png.

The concept informed summary-first hierarchy, grouped disclosures and focused configuration. Existing TailorManagement Flux components, configurable hero/action/accent tokens, navigation and typography override the concept's illustrative palette. Intentional deviations: actual app chrome; separate sections rather than simultaneous overview/configuration; required SKU; no duplicated Product Details panel; no fabricated catalogue descriptions. This is not a pixel-identical reproduction of the generated reference.

## Responsive / Dark-Light Verification

No callable browser connector was available, so installed Playwright Chromium exercised an isolated SQLite QA application on 127.0.0.1:8123. Verified desktop 1440x1050, mobile 390x844 and tablet 768x1024; light and dark overview; option creation and used-option deletion guard; sparse combination creation; price/barcode saving; exact variation receiving; simple Needles overview with 120 stock. The completed workflow emitted no browser JavaScript errors.

Visual inspection compared heading/copy hierarchy, hero spacing, theme roles, disclosure groups, responsive navigation and readable quantity alignment. Browser inspection caught and fixed a Livewire property collision and numeric form-key serialization; a regression now covers combination creation and allocation through Livewire. Resize screenshots must wait for the existing layout animation; settled mobile has full-width content and no horizontal overflow.

The view_image tool could not open the canonical checkout due to its stale working-directory error (os 267); screenshots were instead read as image data through exec and visually inspected. Selected screenshots are retained in docs/evidence/variant-admin-3. The temporary QA server was stopped and its test database, browser sessions and scripts removed. Full manual human acceptance and exhaustive accessibility testing are not claimed.

## Schema

inventory_item_options; inventory_item_option_values; inventory_item_variant_option_value; inventory_item_variants.combination_key; inventory_variant_allocations. Stock balance uniqueness now remains on inventory_stock_unit_id, with an item lookup index replacing the old unique item constraint. Parent-level duplicate detection in reconciliation is restricted to simple products; canonical unit reconciliation still checks per-unit balances and cutover evidence. Reviewed retired simple identities are excluded from operational duplicate checks.

## Migrations

2026_09_13_000003_create_inventory_variant_administration.php applied locally. Initial MySQL execution exposed an overlong generated foreign-key name; short explicit names and resumable schema guards allowed completion without dropping populated tables or resetting the database. SQLite focused migrations also pass. Rollback refuses automatic identity destruction.

Deployment: pause stock-writing traffic/workers, deploy code, run `php artisan migrate --path=database/migrations/2026_09_13_000003_create_inventory_variant_administration.php --force`, run `php artisan inventory:reconcile`, build assets and cache views, then resume after structural checks. This migration does not convert products or allocate any development stock automatically.

## Files Changed

- app/Models/InventoryItemOption.php, InventoryItemOptionValue.php, InventoryItem.php, InventoryItemVariant.php
- app/Services/Inventory/VariantAdministrationService.php, VariantSynchronizationService.php, StockUnitBackfillService.php, StockUnitReconciliationService.php, InventoryReconciliationService.php
- app/Livewire/Inventory/Items/Variations.php, Items/Index.php, Stock/Index.php
- resources/views/livewire/inventory/items/variations.blade.php, items/index.blade.php, stock/index.blade.php
- routes/web.php
- database/migrations/2026_09_13_000003_create_inventory_variant_administration.php
- tests/Feature/Inventory/VariantAdministrationTest.php
- generated frontend build artifacts and this report

## Tests

Focused Inventory + simple POS group: 65 tests / 280 assertions passed, excluding the separately established POS customer-modal Invalid Livewire snapshot error. After the browser form-key fix, VariantAdministrationTest: 12 tests / 62 assertions passed, including Livewire combination/allocation. These runs overlap and should not be added together. Foundation, integrity, Fabric-reference mapping, pricing, branch and independent stock-permission coverage are included.

15 phase PHP files passed syntax and Pint. Blade view:cache and Vite production build passed. Local preservation verification reports all eight existing-data hashes preserved. Reconciliation still reports the 30 existing historical ledger differences separately from zero stock-unit structural anomalies. True concurrent MySQL sessions were not tested.

## Deferred

POS variant selection/scanning, Orders and package variant selection, procurement bulk variant receiving, transfers, shared branch products, FIFO/weighted average/COGS and Storefront retirement remain deferred. Current item-only consumers continue to reject ambiguous operational variants. Product option management is local, not a global attribute catalogue. Existing simple products with unresolved issues or reservations must resolve them before conversion.
