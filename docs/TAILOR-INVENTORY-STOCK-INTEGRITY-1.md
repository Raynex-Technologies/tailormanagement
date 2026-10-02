# TAILOR-INVENTORY-STOCK-INTEGRITY-1

Implemented 2026-09-13 in the canonical tmhub/tailormanagement application.

## Implemented

Canonical runtime balance changes now live in StockMovementService. Both storefront product editors initialize missing zero balances through that service and ignore submitted stock quantities. Existing cost and unit data survive storefront edits. The dedicated form directs staff to Inventory Receive/Adjust. Product retirement preserves stock, media and historical identity.

## Variant Stability

VariantSynchronizationService matches normalized, sorted existing option_values with size/color fields taking precedence. Case and surrounding whitespace do not create new combinations. Existing matches retain ID, labels, SKU, price delta and legacy stock. New combinations get new rows; removed combinations are inactive, never deleted. Persistent order/cart/fabric references are counted before retirement and preserved. Ambiguous duplicate existing combinations fail with a reconciliation error. Reintroducing a combination reactivates its existing row. Active-only editor loading prevents retired combinations from silently reappearing. Cart add and price refresh reject retired selections.

## Stock Mutation Rules

Transactions lock the parent item first (also serializing missing-stock initialization), then the balance. Branch ownership is checked against the fresh item, stock, actor/authenticated user and branch-bearing reference. Receive/adjust/issue/return validation, mutation and movement persistence are atomic. Decimal quantity arithmetic uses Brick BigDecimal at two decimal places. Invalid existing balances fail closed pending reconciliation.

Permissions remain at existing entry points. No new transfer, pricing or variant permissions were introduced. Seeders/factories retain their fixture initialization behavior; no historical movements were manufactured.

## Reservation Rules

Physical balance invariants are on_hand >= 0, reserved >= 0, reserved <= on_hand. Ordinary issues and downward adjustments cannot consume reserved stock. Reservation transitions serialize on the order row; status history stores an immutable per-item quantity snapshot. Repeated reserve/release/commit is safe. Commit consumes its own held quantity once, with no clamping. Late payment after release reacquires available inventory before commitment. Cancellation prevents reacquisition.

Untracked storefront items do not reserve or issue physical stock. Backorder admission does not authorize negative physical balances: tracked checkout still requires stock, consistent with its former reservation check. POS retains its existing tracked-stock requirement. No generalized backorder fulfillment engine was added.

Legacy active reservations without snapshots fail closed; their allocations cannot be safely inferred from editable order lines. The command reports them. No live legacy reservations were found in the local run.

## Returns & Order Deletion

Returns match the original reference_type/reference_id/item identity and cannot exceed outstanding issue minus return movements. Partial return callers should supply a durable operationKey; retries reuse the original movement. Distinct partial returns need distinct keys. The compatibility fallback identifies a return by reference/item/quantity. reverseOutstanding rechecks the net under the stock lock and returns only the remaining amount; a repeated reversal is a no-op, while a later genuine reissue can be reversed again.

Order deletion restores order-level, direct-line and stock-request issues by original movement reference. Line removal/edit uses the same reversal service. Cancellation restores undelivered order issues and releases reservations. Delivered/completed goods are not automatically restocked by cancellation; physical return is a distinct operation. Deletion retains the existing administrator-authorized restoration semantics.

Order edits and stock-request fulfillment lock the order and reject cancelled orders. The regular tailoring editor rejects rewriting inventory for orders with storefront reservation history, preventing its return/reissue path from double-consuming checkout stock. Legacy order-level returns without order-level issues fail closed because their request allocation is ambiguous.

## Reconciliation Tool

Commands:

```sh
php artisan inventory:reconcile
php artisan inventory:reconcile --detailed
php artisan inventory:reconcile --json
```

Read-only, across branches, with no repair option. Reports missing/duplicate/orphan stock, invalid quantities/reservations, branch mismatches, orphan movements, ledger differences, variant SKUs/combinations/legacy quantities, parent mismatches, missing references, and reservation snapshot discrepancies. Detailed/JSON output includes per-variant reference counts. Run during a quiet operational window for migration evidence: reads are not a database-wide transactionally consistent snapshot. Large catalogues currently load item/stock/variant maps in memory; movements and references stream through cursors.

Actual local summary (not example data):

```text
Items checked:              30
Stock rows checked:         30
Balance mismatches:         30
Invalid reservations:        0
Branch mismatches:           0
Legacy active reservations:  0
Variant SKU duplicates:      0
No data changed.
```

Other reported anomaly counts were zero. A balance difference indicates review is needed, not that a missing receipt should be invented. Historical direct/seeded balances are possible explanations and were not automatically classified or repaired.

## Cost Correction

New issue/return/commit movements use null unit_cost/total_cost because true valuation is unknown. Genuine receipt unit costs and decimal total costs are retained. Historical misleading costs, POS snapshots and order financial snapshots were not rewritten. Full costing is deferred.

## Files Changed

- app/Services/Inventory/StockMovementService.php
- app/Services/Inventory/VariantSynchronizationService.php
- app/Services/Inventory/InventoryReconciliationService.php
- app/Console/Commands/InventoryReconcile.php
- app/Services/Storefront/InventoryReservationService.php and CartService.php
- app/Services/Orders/OrderInventoryRestorationService.php, OrderDeletionService.php and StockRequestService.php
- app/Livewire/Orders/Form.php and Show.php
- app/Livewire/Inventory/Items/Index.php
- app/Livewire/Storefront/Admin/ProductForm.php and ProductManager.php
- app/Http/Controllers/Storefront/CartController.php
- app/Models/InventoryTransaction.php
- app/Reports/InventoryReport.php
- resources/views/livewire/storefront/admin/product-form.blade.php and product-manager.blade.php
- tests/Feature/Inventory/StockIntegrityFoundationTest.php
- tests/Feature/Orders/OrderDeleteFlowTest.php

The movement report now uses canonical enum values and bound subqueries for date filtering.

## Migrations

One additive migration: database/migrations/2026_09_13_000001_add_inventory_return_operation_key.php. Adds nullable unique inventory_transactions.operation_key for durable partial-return retry identity. Existing rows remain null; no historical values are rewritten. Applied locally using only this migration path. Reservation snapshots reuse existing status-history metadata. Variant retirement uses existing is_active; no variant/schema redesign.

## Verification

- Final focused group: Inventory, OrderDeleteFlow, OrderCatalogOrderComposition, ProcurementFlow, StorefrontModule: 57 tests / 261 assertions passed.
- Earlier group including POS: 45 passed, one error. Isolated rerun reproduced PosTerminalTest::test_new_customer_can_be_created_from_pos_flow with Invalid Livewire snapshot before customer/stock action execution. POS component/view were not modified in this phase; this remains a separate verification limitation, not full POS sign-off.
- PHP syntax: 20 changed PHP files passed.
- Pint: changed PHP files formatted.
- Blade view:cache passed.
- Reconciliation command executed successfully against local data without balance changes.
- Tests use guarded in-memory SQLite. Transaction rollback/retry behavior is covered; true concurrent MySQL sessions were not exercised.
- No frontend build required: no frontend assets changed.

## Deferred

Canonical InventoryStockUnit, normalized variant options, variant barcode subsystem, variant pricing, POS variant selection, shared multi-branch product catalogue, stock transfers, full inventory valuation/COGS, Storefront retirement. Historical balance/reservation/cost correction remains a reviewed data-reconciliation step, never an automatic repair.
