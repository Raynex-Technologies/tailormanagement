# TAILOR-INVENTORY-STOCK-UNIT-2

Implemented and exercised locally on 2026-09-13 in `C:/Users/HP/Documents/tmhub/tailormanagement`.

## Implemented

Canonical InventoryStockUnit identities, explicit `variant_mode` (`simple` or `variants`), allocation readiness, unique stock-unit SKU, barcode aliases and one primary barcode, identity-only backfill, movement provenance and structural reconciliation.

A simple item has one simple unit and no fake variant. Existing real variant rows each receive their own identity during backfill. Sparse combinations are supported: the foundation never generates missing combinations. Legacy storefront variants do not automatically enable operational variant mode. Their units remain inactive and require allocation review. Changing mode/branch after identities exist requires a future reviewed transition rather than an ordinary item edit.

## Compatibility Changes

Simple item calls resolve through their sole canonical unit. Receive, adjust, issue, bounded returns, reservations and stock restoration retain decimal arithmetic, branch validation, locking and previous safety rules. POS uses explicit unit price and persists unit provenance. New order inventory lines carry the simple unit; changing a line item updates its identity while returns use original movement identity. Historical unchanged lines stay untouched.

Procurement, stock requests and direct Orders use the canonical movement boundary without new UI. Storefront retains simple-mode legacy variant compatibility; operational variant mode is rejected. Legacy variant generation is blocked for operational variant products. Inventory displays canonical SKU, primary barcode when available and allocation-review information using existing theme conventions. Seeders use canonical initialization; factories retain fixture-only balance setup with identity links.

## Migration Result

The scoped additive migration applied successfully to the local development database. Existing items, stock row IDs, variant identities and historical references were preserved. No reset or destructive migration command was used.

## Backfill Result

Actual initial local apply output:

```json
{
  "dry_run": false,
  "items_checked": 30,
  "simple_units_prepared": 30,
  "variant_units_prepared": 0,
  "allocation_review_items": 0,
  "balances_changed": 0,
  "problems": []
}
```

The preceding dry-run proposed the same 30 units with no problems. The repeat dry-run proposed zero new units. Actual totals: simple products processed 30; simple stock units created 30; variant products detected 0; variant units created 0; allocation review items 0; SKU conflicts 0; barcode conflicts 0; records skipped 0; balances changed 0. The command now exposes these additional counters explicitly. Barcode identities are not invented from missing source data. Existing barcode problems are inspected by reconciliation.

Backfill is atomic per item. A conflict rolls back that item's changes and reports it; other safe items may complete. Repeated runs preserve existing explicit unit prices and identities. No SKU is silently renamed.

## Balance Preservation

Current on-hand and reserved quantities were copied into cutover evidence, never reconstructed from historical movements. Existing stock rows receive only a stock-unit identity link. No receipts or other historical movements are manufactured.

`scripts/evidence/stock-unit-2-preservation.php` captured pre-migration hashes and verified equality afterward, excluding only the newly introduced identity/status columns. Preserved tables and row counts: inventory_items 30, inventory_item_variants 0, inventory_stocks 30, inventory_transactions 0, order_lines 41, pos_sale_items 0, cart_items 0, fabric_variants 0. This checks original prices, reference costs, SKUs, quantities, timestamps and references without storing record payloads. Local evidence is in `storage/app/stock-unit-2-preservation.json`.

## Simple Product Regression

Focused tests cover receive/adjust/issue/return, POS sale and explicit unit price, order identity and replacement, deletion restoration, procurement receipts, stock requests, reservations and storefront compatibility. The existing POS customer-modal test remains a separate verification limitation described below.

## Variant Ambiguity Guards

Item-only physical operations reject operational variant mode. Exact prepared variant identity alone does not authorize stock allocation: pending units cannot mutate stock or resolve as sellable. Parent balances retained as migration evidence are not independently editable operational variant stock. No stock quantity is inferred from legacy variant stock_qty or parent quantities.

## SKU & Barcode

Exact SKU or barcode resolves a sellable, branch-accessible stock unit; prefix, case-changed and whitespace-altered inputs do not resolve. Database uniqueness protects SKU and barcode identity, variant/unit mapping, simple/unit mapping and one primary barcode. Barcode assignment requires existing inventory management permission. Old aliases remain associated with the same unit; changing a barcode does not change SKU. Inactive/unallocated identities are not sellable.

## Movement Provenance

New movements retain inventory_item_id and persist inventory_stock_unit_id. Old movements keep nullable unit provenance and remain valid for historical returns. Reconciliation uses physical cutover baseline plus subsequent canonical movements, independently of legacy ledger disagreement. Baselines are evidence, not valuation or invented transactions.

## Reconciliation Result

Local `php artisan inventory:reconcile`: items 30; stock rows 30; historical ledger/balance mismatches 30. All stock-unit structural counts were zero, including missing/duplicate simple identities, variant-parent mismatch, SKU/barcode duplicates, orphan identity, branch mismatch, invalid balance links, canonical balance mismatch and movement identity mismatch. Allocation-review counts were zero because this local catalogue has no variants. Variant behavior is covered by isolated fixtures.

The 30 pre-existing historical differences remain unresolved and unchanged. They are not classified as new migration errors or automatically repaired.

## Deployment Procedure

Use a reviewed database backup and a maintenance window. From the application root:

1. Put the application in maintenance with `php artisan down`. Stop queue workers, scheduled stock jobs and any integrations that write inventory using their deployment supervisor. Drain in-flight writes before replacing code. Maintenance mode alone does not stop workers.
2. Deploy this compatible code and migration while traffic remains paused.
3. Run `php artisan migrate --path=database/migrations/2026_09_13_000002_create_inventory_stock_unit_foundation.php --force`. The preceding stock-integrity phase must already be deployed, including its return-operation-key migration.
4. Run `php artisan inventory:stock-units:backfill --dry-run --json`; inspect every problem. Keep writes paused on failure; resolve source ambiguity through reviewed reconciliation, never invented allocations.
5. Run `php artisan inventory:stock-units:backfill --apply --json`.
6. Repeat `php artisan inventory:stock-units:backfill --dry-run --json`; expect zero additional units. Run `php artisan inventory:reconcile --detailed` and inspect the stock-unit section. Historical ledger differences require separate review; structural failures block resumption.
7. Run `php artisan view:cache`, restart application processes so they use the new code, resume supervised workers/scheduled integrations, then `php artisan up` only after successful validation.

The migrate/dry-run/apply/repeat/reconcile sequence was exercised locally. Production process supervision was not exercised. Existing unprepared identities or unlinked balances fail before mutation; runtime never silently backfills old physical balances. Do not deploy this code into live stock-writing traffic before schema/backfill. Do not roll back the schema after units exist; the migration refuses destructive removal of populated identities.

## Migrations

`database/migrations/2026_09_13_000002_create_inventory_stock_unit_foundation.php` adds stock units, barcode aliases, cutover baselines, explicit item mode/status and nullable provenance to stocks, movements, order lines and POS items. Generated unique keys enforce one simple unit and one primary barcode. A composite foreign key enforces variant parent consistency. Local MySQL migration and SQLite test migration both succeeded.

## Files Changed

- Models: InventoryItem, InventoryItemVariant, InventoryStock, InventoryStockUnit, InventoryStockUnitBarcode, InventoryTransaction, OrderLine, PosSaleItem.
- Inventory services: StockMovementService, StockUnitResolver, StockUnitBarcodeService, StockUnitBackfillService, StockUnitReconciliationService, InventoryReconciliationService, VariantSynchronizationService.
- Consumers: Services/Pos/PosSaleService, Services/Storefront/CartService, Livewire/Pos/PosTerminal, Livewire/Inventory/Items/Index, resources/views/livewire/inventory/items/index.blade.php.
- Commands: InventoryStockUnitsBackfill, InventoryReconcile.
- Fixtures: InventoryItemFactory, DemoSeeder, InventoryCategoriesAndItemsSeeder.
- Migration listed above; tests/Feature/Inventory/StockUnitFoundationTest.php; scripts/evidence/stock-unit-2-preservation.php; this document.

## Verification

Stock-unit foundation: 17 tests / 88 assertions passed. Final compatibility group: 84 tests / 385 assertions passed across Inventory, Order deletion, Order Catalogue composition, Procurement, Storefront and POS, excluding only the separately isolated customer-modal snapshot test. PHP syntax passed for all 27 phase PHP files; Pint formatted the same files. Blade view:cache passed. Final local migration status is [9] Ran; repeat dry-run reports 30 simple products processed, zero new units/conflicts/skips/balance changes; all eight preservation hashes still match. Tests use guarded in-memory SQLite; no production database refresh was run.

The full focused run before the final order-line regression contained 84 tests / 382 assertions with one error: PosTerminalTest::test_new_customer_can_be_created_from_pos_flow, Invalid Livewire snapshot. It fails on the first openCustomerModal request, where Livewire rejects missing snapshot data/memo before invoking the customer action. The same failure was recorded in STOCK-INTEGRITY-1 before this phase; the isolated rerun reproduced the same error with 1 test / 0 assertions, without modifying the test. This evidence does not support attributing it to stock-unit writes, but full POS sign-off remains withheld.

## Remaining Limitations

No full variant UI, allocation, scanner UX, normalized option/value CRUD, POS variant selector, variant procurement/package selection, transfers, shared multi-branch catalogue or inventory valuation/COGS is implemented. Reference cost is not valuation.

The legacy unique inventory_stocks.inventory_item_id constraint remains deliberately in place while variant allocation is blocked. The next allocation phase must replace it with the reviewed unit-level multi-balance structure before creating operational balances for multiple variants. Existing raw/new legacy variant rows need identity backfill/review; the future variant creation workflow must create each real variant and unit atomically.

True concurrent MySQL sessions were not exercised. Browser visual acceptance is not claimed; the small theme-based Inventory status additions require interactive light/dark review. The known POS Livewire snapshot error remains unresolved. Historical ledger differences require reviewed data reconciliation.

## Next Phase

Ready to begin TAILOR-INVENTORY-VARIANT-ADMIN-3 design and implementation against this identity foundation; operational variant selling remains disabled pending explicit stock allocation and consumer support.

Preserve the future UX contract: Product -> Variations -> Stock; only real sold combinations exist; product and option totals are calculated from units; reusable option/value IDs support label renames without recreating variants; dependent identities are archived rather than deleted. No default variant for simple products and no spreadsheet matrix as the default experience. Use the installed frontend UI skill with TailorManagement's existing theme, Flux and accessibility rules. Do not expose stock-unit internals to administrators.
