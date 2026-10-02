<?php

namespace Tests\Feature\Inventory;

use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\InventoryItemVariant;
use App\Models\InventoryStockUnit;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\PosSale;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\StockUnitBackfillService;
use App\Services\Inventory\StockUnitBarcodeService;
use App\Services\Inventory\StockUnitReconciliationService;
use App\Services\Inventory\StockUnitResolver;
use App\Services\Pos\PosSaleService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockUnitFoundationTest extends TestCase
{
    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->actingAsRole('superadmin', $this->branch);
    }

    private function item(array $values = []): InventoryItem
    {
        $item = InventoryItem::factory()->create([...['branch_id' => $this->branch->id, 'default_sell_price' => '25.50', 'default_buy_price' => '12.25', 'is_active' => true], ...$values]);
        $item->stock->update(['qty_on_hand' => '20.00', 'qty_reserved' => '3.00']);

        return $item;
    }

    private function variant(InventoryItem $item, string $name, string $sku): InventoryItemVariant
    {
        return $item->variants()->create(['name' => $name, 'sku' => $sku, 'size' => $name, 'option_values' => ['size' => $name], 'price_delta' => '4.50', 'stock_qty' => 99, 'is_active' => true]);
    }

    private function rejected(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected rejection');
        } catch (ValidationException|QueryException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    private function legacyItem(): InventoryItem
    {
        $id = DB::table('inventory_items')->insertGetId(['branch_id' => $this->branch->id, 'sku' => 'LEGACY-001', 'name' => 'Legacy', 'unit' => 'pcs', 'default_sell_price' => '33.50', 'default_buy_price' => '17.25', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_stocks')->insert(['branch_id' => $this->branch->id, 'inventory_item_id' => $id, 'qty_on_hand' => '80.00', 'qty_reserved' => '5.00', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_transactions')->insert(['branch_id' => $this->branch->id, 'inventory_item_id' => $id, 'type' => 'receive', 'qty' => 60, 'unit_cost' => 12, 'created_at' => now(), 'updated_at' => now()]);

        return InventoryItem::withoutBranchScope()->findOrFail($id);
    }

    public function test_simple_identity_is_unique_and_has_no_fake_variant(): void
    {
        $item = $this->item();
        $unit = app(StockUnitResolver::class)->forItem($item);
        $this->assertSame(1, $item->stockUnits()->count());
        $this->assertSame(0, $item->variants()->count());
        $this->assertSame($item->sku, $unit->sku);
        $this->assertSame('25.50', $unit->selling_price);
        $this->assertSame('12.25', $unit->reference_cost);
        $this->rejected(fn () => DB::table('inventory_stock_units')->insert(['inventory_item_id' => $item->id, 'sku' => 'DIFFERENT', 'is_active' => true, 'allocation_status' => 'ready']));
    }

    public function test_sparse_variants_receive_only_their_real_identities_without_stock_allocation(): void
    {
        $item = $this->item(['variant_mode' => 'variants']);
        $a = $this->variant($item, 'Black S', 'BLACK-S');
        $b = $this->variant($item, 'White L', 'WHITE-L');
        $report = app(StockUnitBackfillService::class)->run(false);
        $this->assertSame([], $report['problems']);
        $this->assertSame(2, $report['variant_units_prepared']);
        $this->assertSame(2, $item->variants()->count());
        $this->assertSame(2, $item->stockUnits()->count());
        $this->assertNull($item->simpleStockUnit);
        $this->assertSame('20.00', $item->stock->fresh()->qty_on_hand);
        foreach ([$a, $b] as $variant) {
            $unit = app(StockUnitResolver::class)->forVariant($variant);
            $this->assertSame('30.00', $unit->selling_price);
            $this->assertNull($unit->stock);
            $this->assertFalse($unit->is_active);
            $this->assertSame('allocation_required', $unit->allocation_status);
        }
        $this->assertSame('allocation_required', $item->fresh()->stock_identity_status);
    }

    public function test_duplicate_variant_identity_and_parent_mismatch_are_rejected_by_database(): void
    {
        $a = $this->item(['variant_mode' => 'variants']);
        $b = $this->item(['variant_mode' => 'variants']);
        $variant = $this->variant($a, 'M', 'M-SKU');
        app(StockUnitBackfillService::class)->run(false);
        $this->rejected(fn () => DB::table('inventory_stock_units')->insert(['inventory_item_id' => $a->id, 'inventory_item_variant_id' => $variant->id, 'sku' => 'DUPLICATE']));
        $other = $this->variant($a, 'L', 'L-SKU');
        $this->rejected(fn () => DB::table('inventory_stock_units')->insert(['inventory_item_id' => $b->id, 'inventory_item_variant_id' => $other->id, 'sku' => 'WRONG-PARENT']));
    }

    public function test_backfill_preserves_balances_prices_sku_and_old_transactions_and_is_idempotent(): void
    {
        $item = $this->legacyItem();
        $stockId = $item->stock->id;
        $old = DB::table('inventory_transactions')->get()->toJson();
        $service = app(StockUnitBackfillService::class);
        $dry = $service->run();
        $this->assertSame(1, $dry['simple_units_prepared']);
        $this->assertSame(0, InventoryStockUnit::count());
        $report = $service->run(false);
        $this->assertSame([], $report['problems']);
        $unit = app(StockUnitResolver::class)->forItem($item);
        $this->assertSame('LEGACY-001', $unit->sku);
        $this->assertSame('33.50', $unit->selling_price);
        $this->assertSame('17.25', $unit->reference_cost);
        $this->assertSame($stockId, $unit->stock->id);
        $this->assertSame('80.00', $unit->stock->qty_on_hand);
        $this->assertSame('5.00', $unit->stock->qty_reserved);
        $this->assertSame($old, DB::table('inventory_transactions')->get()->toJson());
        $again = $service->run(false);
        $this->assertSame(0, $again['simple_units_prepared']);
        $this->assertSame(1, InventoryStockUnit::count());
        $this->assertSame(1, DB::table('inventory_stock_unit_baselines')->count());
        $this->assertSame(0, app(StockUnitReconciliationService::class)->inspect()['summary']['unit_balance_mismatches']);
    }

    public function test_backfill_refuses_invalid_reservations_without_attaching_identity(): void
    {
        $item = $this->legacyItem();
        $item->stock->update(['qty_reserved' => 90]);
        $report = app(StockUnitBackfillService::class)->run(false);
        $this->assertCount(1, $report['problems']);
        $this->assertSame(0, InventoryStockUnit::count());
        $this->assertSame('80.00', $item->stock->fresh()->qty_on_hand);
    }

    public function test_unprepared_legacy_item_fails_before_mutating_stock(): void
    {
        $item = $this->legacyItem();
        $this->rejected(fn () => app(StockMovementService::class)->issue($item, 1, null, $this->actor));
        $this->assertSame('80.00', $item->stock->fresh()->qty_on_hand);
    }

    public function test_conflicting_variant_skus_are_reported_not_renamed(): void
    {
        $item = $this->item();
        $a = $this->variant($item, 'S', 'COLLISION');
        $b = $this->variant($item, 'M', 'COLLISION');
        $report = app(StockUnitBackfillService::class)->run(false);
        $this->assertCount(1, $report['problems']);
        $this->assertStringContainsString('SKU conflict', $report['problems'][0]['message']);
        $this->assertSame('COLLISION', $a->fresh()->sku);
        $this->assertSame('COLLISION', $b->fresh()->sku);
        $this->assertNull($a->stockUnit);
        $this->assertNull($b->stockUnit);
    }

    public function test_explicit_variant_price_is_not_recomputed_when_parent_or_label_changes(): void
    {
        $item = $this->item();
        $variant = $this->variant($item, 'XXL', 'SIZE-XXL');
        app(StockUnitBackfillService::class)->run(false);
        $unit = $variant->stockUnit;
        $variant->update(['name' => '2XL']);
        $item->update(['name' => 'Renamed', 'default_sell_price' => 100]);
        app(StockUnitBackfillService::class)->run(false);
        $this->assertSame($unit->id, $variant->fresh()->stockUnit->id);
        $this->assertSame('30.00', $unit->fresh()->selling_price);
        $this->assertSame('SIZE-XXL', $unit->fresh()->sku);
    }

    public function test_exact_sku_and_barcode_resolution_reject_fuzzy_input_and_preserve_sku(): void
    {
        $item = $this->item(['sku' => 'NDL-001']);
        $unit = $item->simpleStockUnit;
        $resolver = app(StockUnitResolver::class);
        $barcode = app(StockUnitBarcodeService::class)->assign($unit, '6290012345');
        $this->assertSame($unit->id, $resolver->bySku('NDL-001')->id);
        $this->assertSame($unit->id, $resolver->byBarcode('6290012345')->id);
        foreach (['NDL', 'ndl-001', ' NDL-001'] as $sku) {
            $this->assertNull($resolver->bySku($sku));
        }
        $this->assertNull($resolver->byBarcode('629001'));
        $this->assertSame('NDL-001', $unit->fresh()->sku);
        app(StockUnitBarcodeService::class)->assign($unit, '6290099999');
        $this->assertSame(1, $unit->barcodes()->where('is_primary', true)->count());
        $this->assertFalse($barcode->fresh()->is_primary);
        $other = $this->item();
        $this->rejected(fn () => app(StockUnitBarcodeService::class)->assign($other->simpleStockUnit, '6290012345'));
        $this->rejected(fn () => DB::table('inventory_stock_unit_barcodes')->insert(['inventory_stock_unit_id' => $unit->id, 'barcode' => 'SECOND-PRIMARY', 'is_primary' => true]));
    }

    public function test_barcode_requires_inventory_permission(): void
    {
        $item = $this->item();
        $this->actingAsRole('tailor', $this->branch);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(StockUnitBarcodeService::class)->assign($item->simpleStockUnit, 'FORBIDDEN');
    }

    public function test_branch_identity_cannot_be_reassigned_or_resolved_from_another_branch(): void
    {
        $item = $this->item();
        $unit = $item->simpleStockUnit;
        $this->rejected(fn () => $item->update(['branch_id' => $this->otherBranch->id]));
        $this->actingAsRole('sales', $this->otherBranch);
        $this->assertNull(app(StockUnitResolver::class)->bySku($unit->sku));
        $this->rejected(fn () => app(StockUnitResolver::class)->forItem($item));
    }

    public function test_receive_issue_adjust_return_record_both_identities_and_reconcile_against_baseline(): void
    {
        $item = $this->legacyItem();
        app(StockUnitBackfillService::class)->run(false);
        $unit = $item->simpleStockUnit;
        $service = app(StockMovementService::class);
        $service->receive($item, 2, '12.25', 'Receipt', $this->actor);
        $service->adjust($item, 1, 'Adjustment', $this->actor);
        $service->issue($item, 4, 'Issue', $this->actor, $item);
        $service->return($item, 4, 'Return', $this->actor, $item);
        $this->assertSame('83.00', $item->stock->fresh()->qty_on_hand);
        $this->assertSame(4, InventoryTransaction::where('inventory_stock_unit_id', $unit->id)->where('inventory_item_id', $item->id)->count());
        $this->assertSame(0, app(StockUnitReconciliationService::class)->inspect()['summary']['unit_balance_mismatches']);
        $item->stock->update(['qty_on_hand' => 84]);
        $this->assertSame(1, app(StockUnitReconciliationService::class)->inspect()['summary']['unit_balance_mismatches']);
    }

    public function test_variant_mode_blocks_parent_mutation_and_simple_pos_sale(): void
    {
        $item = $this->item(['variant_mode' => 'variants']);
        $this->variant($item, 'M', 'VAR-M');
        app(StockUnitBackfillService::class)->run(false);
        $service = app(StockMovementService::class);
        $this->rejected(fn () => $service->receive($item, 1, null, null, $this->actor));
        $this->rejected(fn () => $service->issue($item, 1, null, $this->actor));
        $this->rejected(fn () => app(PosSaleService::class)->complete([['inventory_item_id' => $item->id, 'quantity' => 1]], $this->actor, null, 0, 0, 100, 'cash'));
        $this->assertSame('20.00', $item->stock->fresh()->qty_on_hand);
        $this->assertSame(0, PosSale::count());
    }

    public function test_simple_pos_uses_explicit_unit_price_and_records_identity(): void
    {
        $item = $this->item();
        $unit = $item->simpleStockUnit;
        $unit->update(['selling_price' => 44]);
        $sale = app(PosSaleService::class)->complete([['inventory_item_id' => $item->id, 'quantity' => 1]], $this->actor, null, 0, 0, 44, 'cash');
        $this->assertSame('44.00', $sale->items->first()->unit_price);
        $this->assertSame($unit->id, $sale->items->first()->inventory_stock_unit_id);
        $this->assertSame($unit->id, InventoryTransaction::first()->inventory_stock_unit_id);
    }

    public function test_new_order_line_gets_simple_provenance_without_rewriting_old_rows(): void
    {
        $item = $this->item();
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => OrderStatus::New]);
        $line = $order->lines()->create(['inventory_item_id' => $item->id, 'item_name' => 'Historic name', 'sku' => 'SNAPSHOT', 'qty' => 1, 'unit_price' => 10, 'line_total' => 10]);
        $this->assertSame($item->simpleStockUnit->id, $line->inventory_stock_unit_id);
        DB::table('order_lines')->where('id', $line->id)->update(['inventory_stock_unit_id' => null]);
        app(StockUnitBackfillService::class)->run(false);
        $this->assertNull($line->fresh()->inventory_stock_unit_id);
        $this->assertSame('SNAPSHOT', $line->fresh()->sku);
    }

    public function test_changed_order_line_can_reverse_original_identity_and_issue_replacement(): void
    {
        $original = $this->item();
        $replacement = $this->item();
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => OrderStatus::New]);
        $line = $order->lines()->create(['inventory_item_id' => $original->id, 'item_name' => 'Item', 'sku' => 'SNAPSHOT', 'qty' => 1, 'unit_price' => 10, 'line_total' => 10]);
        $service = app(StockMovementService::class);
        $service->issue($original, 1, null, $this->actor, $line);
        $line->update(['inventory_item_id' => $replacement->id]);
        $this->assertSame($replacement->simpleStockUnit->id, $line->inventory_stock_unit_id);
        $service->reverseOutstanding($original, $line, $this->actor);
        $service->issue($replacement, 1, null, $this->actor, $line);
        $this->assertSame('20.00', $original->stock->fresh()->qty_on_hand);
        $this->assertSame('19.00', $replacement->stock->fresh()->qty_on_hand);
    }

    public function test_inactive_variant_identity_is_preserved_and_not_scannable(): void
    {
        $item = $this->item();
        $variant = $this->variant($item, 'Old M', 'OLD-M');
        $variant->update(['is_active' => false]);
        app(StockUnitBackfillService::class)->run(false);
        $unit = $variant->stockUnit;
        $this->assertNotNull($unit);
        $this->assertFalse($unit->is_active);
        $this->assertNull(app(StockUnitResolver::class)->bySku('OLD-M'));
        $this->assertSame(99.0, (float) $variant->fresh()->stock_qty);
    }
}
