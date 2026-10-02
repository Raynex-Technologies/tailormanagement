# TAILOR-ORDERS-PACKAGE-VARIANTS-5

Implemented locally on 2026-09-13 in `C:/Users/HP/Documents/tmhub/tailormanagement`. Final verification results are recorded below; full-suite and concurrent-session certification are not claimed.

## Implemented

Regular Orders and commercial Order Packages now select exact canonical InventoryStockUnit/InventoryItemVariant identities. OrderCatalogItem remains the tailoring garment/service domain. No alternate inventory model, stock engine or variation administration was introduced.

## Aggregate Preflight

OrderInventoryPreflight retains the requested-versus-available pass. It resolves each prospective selection, groups decimal quantities by stock-unit ID, and compares their combined request with on-hand minus reserved plus outstanding issue-minus-return quantities belonging to this Order's own persisted lines and that exact unit. Removed/replaced owned lines can release their allocation for reuse; foreign OrderLine IDs and duplicate submitted existing-line IDs are rejected before credit is calculated. Legacy null movement provenance is considered only for an unambiguous simple identity, never inferred as a variant.

The pass runs before save and again inside the Order transaction. Parent items lock in sorted order and requested balance rows lock before writes. Reductions, removals and replacements release their original allocations before increases consume availability. StockMovementService retains the final locked branch, identity, readiness, physical balance and reservation checks. Client availability is never authoritative.

## Order Selection UX

Find Inventory Product, then choose a variation only when required. Simple products add directly. Product cards show aggregate availability, ready variation count and canonical price range. Search includes parent name/SKU, variant SKU and option values; Enter supports exact sellable barcode/SKU lookup without selecting an arbitrary fuzzy match.

The shared Flux selector loads detailed combinations only for one selected product. Sparse missing combinations are labelled Not offered, inactive/unready choices Unavailable. Confirmation resolves an exact persisted identity server-side. The summary shows variation, SKU, available quantity and selling price; no internal IDs are displayed. Change selection reopens the focused picker for an ordinary inventory line. Selecting a different product remains available through removing/adding a line.

## Order Provenance

New lines persist item, variant and stock-unit IDs plus canonical item name, variation description and SKU snapshots. Retail initialization uses stock-unit selling_price; the established ability to edit the Order's commercial line price remains. Accepted commercial quantity/price values calculate line totals server-side. Submitted display snapshots are replaced by server-resolved values for new identities.

## Stock Synchronization

OrderInventorySynchronizationService handles net increases, bounded decreases, no-op saves and exact replacement. Replacement reverses outstanding original movement identities before issuing the new unit. A failed issue rolls back returns and earlier deductions. Reduction operation keys include the existing movement version, distinguishing retries from subsequent real reductions. Cancellation is rejected by the synchronization service. Order deletion/restoration follows original unit provenance, with the legacy simple fallback retained.

## Historical Compatibility

Unchanged existing identities preserve saved display snapshots, including after option renames or later retirement. Legacy item-only lines remain readable and retain null unit provenance. Unchanged ambiguous legacy variant lines do not acquire invented identity or trigger a new issue; quantity changes require reconciliation. Unchanged simple historical lines retain normal canonical stock behavior without repeated return/reissue churn. Existing package allocations are reloaded from persisted snapshots when the composition is unchanged, so retired current selections do not rewrite historical presentation.

## Package Fixed Variations

Package administration offers Fixed variation for variation products. The shared selector records exact item/variant/unit identity. Existing fixed IDs survive edits and option renames. Retired/unavailable identities display Requires attention and are not substituted. They cannot be newly composed into an Order.

## Package Deferred Variations

Choose when added to Order stores the parent product and explicit deferred mode, with no placeholder variation. Composition requires all nonzero component quantities to resolve before lines are created. Simple components do not show unnecessary variation controls.

## Package Quantity Splitting

One selection can fulfill the whole component quantity. Split quantity divides the selected quantity across two rows while preserving its total; for example 2 becomes 1 + 1. Staff selects the second variation and can edit/remove allocation rows. The UI shows allocated versus required quantity. Server validation rejects under/over-allocation, zero/negative quantities, repeated identities, unknown components and foreign/unavailable units. Every split line retains package instance, template component and exact inventory provenance.

## Package Pricing

package_unit_price and signed original commercial snapshots remain authoritative. Exact stock identity determines SKU, variation and stock, not a replacement retail price. Split line totals use the same package unit price and decimal arithmetic. Template commercial signatures now include selection mode and exact identity. Configurator signatures are locked; saved original snapshots are verified before composition is regenerated.

## Print / Invoice Display

Order detail shows saved variation description and SKU. Invoice templates use the linked OrderLine's persisted variation snapshot; delivery detail/print use the source line snapshot. Legacy lines without a description render as before. Invoice architecture, commercial totals and historical product labels were not rewritten.

## Permissions

Existing Order create/update policies authorize selection and save. Package administration uses order_catalog.packages.manage. Selecting inventory confers no inventory.items.manage or inventory.stock.adjust permission. Submitted identities and branches are checked server-side. The shared selection initializer is protected rather than an independently callable Livewire action.

## Migration Result

`2026_09_13_000005_add_order_package_variant_identity.php` applied successfully to local MySQL using only its scoped migration path. It adds nullable order_lines.variation_description and nullable package component selection mode/variant/unit fields with restricted foreign keys. Existing Order variant/unit columns are reused. Destructive automatic rollback is refused.

`scripts/evidence/orders-package-5-preservation.php` captured original-column hashes before migration and verified all nine tables afterward: inventory_items, inventory_item_variants, inventory_stock_units, inventory_stocks, inventory_transactions, order_lines, order_package_template_items, order_package_instances and invoice_lines. Every hash is preserved. Evidence stores hashes/counts/column names, not record payloads. No development product was converted or allocated.

Reconciliation: 30 items, 30 stock rows, zero stock-unit structural anomalies; the 30 known historical ledger differences remain unchanged and require separate review.

## Frontend UI Skill Usage

Applied `C:/Users/HP/.codex/skills/frontend-app-builder/SKILL.md` with the existing Flux, typography and configurable theme rules. Concept: `C:/Users/HP/.codex/generated_images/01a098eb-0610-7123-9918-47222a1c2068/exec-428cfa6e-0f2e-4e8f-931c-93476c2162b3.png`.

The concept informed focused selection, exact summary and contextual quantity splitting. Existing app chrome, actual commercial pricing and configurable colors override illustrative concept colors/prices. This is not a pixel-identical mockup reproduction. view_image retains the environment's stale-directory os267 failure; actual screenshot bytes were displayed through exec for visual inspection.

## Browser Verification

No callable Browser/Chrome connector was available. Installed Playwright Chromium exercised a separate SQLite QA application on 127.0.0.1:8125 with a disposable branch-manager account and queued notifications left unprocessed.

Exercised simple plus variant Order creation, sparse White/Medium rejection, exact Black/Medium selection, quantity increase/decrease, variation replacement, detail and invoice print; deferred same-variation composition, split composition and saved split Order, fixed composition, and reopening saved package selections. Desktop 1440px, tablet 768px and mobile 390px screenshots cover light/dark selectors and package controls. Mobile package content scrolls to its actions. Completed browser scripts reported no JavaScript errors. Browser testing caught and corrected the initial split total behavior; no accessibility certification or true simultaneous cashier/session claim is made.

The temporary QA server was stopped and its database, scripts and login session removed. Selected real screenshots are retained under `docs/evidence/orders-package-variants-5`. Mixed garment/service plus inventory composition and package administration are also covered by Livewire regressions; exhaustive manual browser acceptance of every catalogue combination is not claimed.

## Tests

Focused Orders/package composition and administration, deletion/restoration, Stock Unit, Stock Integrity, Variant Administration and all POS: 97 tests / 530 assertions passed. The final new-phase rerun passed 8 tests / 65 assertions, adding the legacy no-op, split-total and later-inactive package-save checks. These groups overlap and must not be added together. Tests use guarded in-memory SQLite.

The broader 212-test run initially reported seven failures. The two phase-related findings (package branch error ordering and foreign-branch fixture setup) were fixed and the affected 17-test / 110-assertion group passed. Five wider-suite limitations remain: two due-date assertions compare a date string with SQLite's midnight timestamp, two presentation assertions compare decimal SUM string formatting (`1050000.00` versus `1050000`), and one payment-panel test expects an existing obsolete CSS class. The four date/decimal assertions reproduced in isolation (4 tests / 46 assertions). Their tests and unrelated production behavior were not changed to manufacture a full-suite pass.

All 17 phase PHP files passed syntax; Pint formatted them. Blade view:cache and Vite production build passed. No broad database reset was run.

## Files Changed

- Livewire: Orders/Form, OrderCatalog/PackageForm, Concerns/SelectsOrderInventory.
- Models: OrderLine, OrderPackageTemplateItem.
- Services/Orders: OrderInventorySelectionService, OrderInventoryPreflight, OrderInventorySynchronizationService, OrderInventoryRestorationService, OrderPackageInventoryService, OrderCatalogCompositionService, OrderCatalogAdministrationService, OrderPackagePricingService.
- Views: Orders form/display-line, package form, shared inventory selector, delivery show/print, invoice templates.
- Scoped migration 000005; OrderVariantsTest and OrderVariantSynchronizationTest; preservation script; frontend artifacts; screenshots; this report.

## Remaining Limitations

True concurrent MySQL sessions and exhaustive human accessibility acceptance were not exercised. Legacy ambiguous identity/quantity corrections require reviewed reconciliation. The five wider-suite assertion failures above prevent a full-suite green claim. Storefront reservations retain their existing Order-editor restriction.

## Deployment Procedure

1. Back up the database and pause Order/inventory-writing traffic, queue workers and integrations; drain in-flight writes.
2. Deploy after phases 1 through 4 are present. Run `php artisan migrate --path=database/migrations/2026_09_13_000005_add_order_package_variant_identity.php --force`.
3. Run `php artisan inventory:reconcile`; review structural anomalies separately from existing historical ledger differences. Never invent receipts or allocations to clear a report.
4. Build assets, run `php artisan view:cache`, restart application processes and verify a simple Order, exact variation, fixed/deferred package and print snapshot.
5. Resume traffic/workers after verification. Do not destructively roll back populated provenance.

The scoped migration and local verification were exercised; production deployment/supervision was not.

## Next Phase

Variant-aware procurement/receiving can be planned separately. Procurement UI, stock transfers, shared branch catalogue, POS refunds, valuation/FIFO/weighted average/COGS, Storefront retirement and Customer Relations remain outside this phase.
