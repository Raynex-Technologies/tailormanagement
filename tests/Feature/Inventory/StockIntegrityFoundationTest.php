<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryTransactionType as Type;
use App\Enums\OrderStatus;
use App\Livewire\Storefront\Admin\ProductForm;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Fabric;
use App\Models\FabricVariant;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Reports\InventoryReport;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\VariantSynchronizationService;
use App\Services\Orders\OrderDeletionService;
use App\Services\Orders\OrderInventoryRestorationService;
use App\Services\Storefront\InventoryReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockIntegrityFoundationTest extends TestCase
{
    private InventoryItem $item;

    private StockMovementService $stock;

    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->actingAsRole('superadmin', $this->branch);
        $this->item = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'default_buy_price' => '12.50', 'default_sell_price' => '25.00', 'track_stock' => true]);
        $this->item->stock->update(['qty_on_hand' => 20, 'qty_reserved' => 0]);
        $this->stock = app(StockMovementService::class);
    }

    private function order(string $qty = '4'): Order
    {
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => OrderStatus::New]);
        $order->lines()->create(['inventory_item_id' => $this->item->id, 'item_name' => 'Snapshot shirt', 'sku' => 'OLD-SKU', 'qty' => $qty, 'unit_price' => '25', 'line_total' => '100']);

        return $order;
    }

    private function payload(string $size = 'M', string $color = 'White'): array
    {
        return ['name' => $size.' / '.$color, 'size' => $size, 'color' => $color, 'sku' => 'TEST-'.$size.'-'.$color, 'price_delta' => 0, 'stock_qty' => null, 'option_values' => ['size' => $size, 'color' => $color], 'is_active' => true];
    }

    private function rejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_variants_are_stable_retired_and_reactivated_with_references_intact(): void
    {
        $sync = app(VariantSynchronizationService::class);
        $sync->sync($this->item, [$this->payload()]);
        $variant = $this->item->variants()->firstOrFail();
        $variant->update(['price_delta' => 10, 'stock_qty' => 7]);
        $order = $this->order();
        $line = $order->lines()->where('inventory_item_id', $this->item->id)->first();
        $line->update(['inventory_item_variant_id' => $variant->id]);
        $fabric = Fabric::create(['name' => 'Fabric', 'code' => 'FAB']);
        $swatch = FabricVariant::create(['fabric_id' => $fabric->id, 'name' => 'White', 'inventory_item_variant_id' => $variant->id]);
        $cart = Cart::create(['token' => 'test-cart', 'currency' => 'TZS']);
        $cartLine = CartItem::create(['cart_id' => $cart->id, 'inventory_item_id' => $this->item->id, 'inventory_item_variant_id' => $variant->id, 'line_key' => 'test', 'quantity' => 1, 'unit_price' => 25, 'line_total' => 25]);
        $sync->sync($this->item, [$this->payload(), $this->payload('L')]);
        $this->assertCount(2, $this->item->variants()->get());
        $this->assertEquals(10, $variant->fresh()->price_delta);
        $this->assertEquals(7, $variant->fresh()->stock_qty);
        $retired = $sync->sync($this->item, [$this->payload('L')]);
        $this->assertFalse($variant->fresh()->is_active);
        $this->assertSame(1, $retired[$variant->id]['fabric_variants']);
        foreach ([$line, $swatch, $cartLine] as $reference) {
            $this->assertEquals($variant->id, $reference->fresh()->inventory_item_variant_id);
        }
        $sync->sync($this->item, [$this->payload(), $this->payload('L')]);
        $this->assertTrue($variant->fresh()->is_active);
        $this->assertSame('OLD-SKU', $line->fresh()->sku);
    }

    public function test_product_edit_preserves_physical_stock_cost_unit_and_variant_id(): void
    {
        app(VariantSynchronizationService::class)->sync($this->item, [$this->payload()]);
        $id = $this->item->variants()->first()->id;
        $unit = $this->item->unit;
        Livewire::test(ProductForm::class, ['product' => $this->item])->set('productName', 'Updated shirt')->set('productStockQuantity', 999)->call('save')->assertHasNoErrors();
        $this->assertEquals(20, $this->item->stock->fresh()->qty_on_hand);
        $this->assertEquals('12.50', $this->item->fresh()->default_buy_price);
        $this->assertEquals($unit, $this->item->fresh()->unit);
        $this->assertEquals($id, $this->item->variants()->where('is_active', true)->first()->id);
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_issue_and_adjust_cannot_consume_reservations(): void
    {
        $this->item->stock->update(['qty_reserved' => 15]);
        $this->rejected(fn () => $this->stock->issue($this->item, 6, 'test', $this->actor));
        $this->rejected(fn () => $this->stock->adjust($this->item, -6, 'test', $this->actor));
        $movement = $this->stock->issue($this->item, 5, 'test', $this->actor);
        $this->assertEquals(15, $this->item->stock->fresh()->qty_on_hand);
        $this->assertNull($movement->unit_cost);
        $this->assertNull($movement->total_cost);
    }

    public function test_receive_adjust_and_report_use_decimal_movements(): void
    {
        $received = $this->stock->receive($this->item, '0.10', '12.50', 'test', $this->actor);
        $this->stock->adjust($this->item, '0.20', 'test', $this->actor);
        $this->assertSame('20.30', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame('1.25', $received->total_cost);
        $row = (new InventoryReport)->movementSummary()->first();
        $this->assertEquals(0.2, $row->adjusted_qty);
    }

    public function test_partial_returns_are_bounded_and_retry_safe(): void
    {
        $order = $this->order();
        $this->stock->issue($this->item, 8, 'test', $this->actor, $order);
        $first = $this->stock->return($this->item, 3, 'test', $this->actor, $order, 'return-a');
        $again = $this->stock->return($this->item, 3, 'test', $this->actor, $order, 'return-a');
        $this->assertEquals($first->id, $again->id);
        $this->rejected(fn () => $this->stock->return($this->item, 6, 'test', $this->actor, $order, 'return-b'));
        $this->stock->reverseOutstanding($this->item, $order, $this->actor);
        $this->stock->reverseOutstanding($this->item, $order, $this->actor);
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame(2, InventoryTransaction::where('type', Type::Return)->count());
    }

    public function test_reserve_commit_and_release_are_idempotent_and_snapshot_based(): void
    {
        $order = $this->order();
        $reservations = app(InventoryReservationService::class);
        $reservations->reserve($order);
        $reservations->reserve($order);
        $this->assertSame('4.00', $this->item->stock->fresh()->qty_reserved);
        $order->lines()->where('inventory_item_id', $this->item->id)->update(['qty' => 12]);
        $reservations->commit($order, $this->actor);
        $reservations->commit($order, $this->actor);
        $reservations->release($order);
        $this->assertSame('16.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame('0.00', $this->item->stock->fresh()->qty_reserved);
        $this->assertSame(1, InventoryTransaction::where('type', Type::Issue)->count());
        $this->assertNull(InventoryTransaction::first()->unit_cost);
    }

    public function test_release_retry_does_not_consume_another_orders_reservation(): void
    {
        $first = $this->order();
        $second = $this->order('6');
        $service = app(InventoryReservationService::class);
        $service->reserve($first);
        $service->reserve($second);
        $service->release($first);
        $service->release($first);
        $this->assertSame('6.00', $this->item->stock->fresh()->qty_reserved);
        $service->commit($first, $this->actor); // Reacquires its own released reservation.
        $this->assertSame('6.00', $this->item->stock->fresh()->qty_reserved);
        $this->assertSame('16.00', $this->item->stock->fresh()->qty_on_hand);
    }

    public function test_legacy_reservation_is_not_guessed(): void
    {
        $order = $this->order();
        $this->item->stock->update(['qty_reserved' => 4]);
        $order->statusHistory()->create(['status' => 'inventory_reserved', 'title' => 'Legacy']);
        $this->rejected(fn () => app(InventoryReservationService::class)->commit($order, $this->actor));
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
    }

    public function test_untracked_items_do_not_reserve_but_backorders_cannot_create_negative_physical_stock(): void
    {
        $order = $this->order('30');
        $this->item->update(['track_stock' => false]);
        app(InventoryReservationService::class)->reserve($order);
        app(InventoryReservationService::class)->commit($order, $this->actor);
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->item->update(['track_stock' => true, 'allow_backorders' => true]);
        $this->rejected(fn () => app(InventoryReservationService::class)->reserve($this->order('30')));
    }

    public function test_order_delete_restores_direct_and_request_issues_without_double_return(): void
    {
        $order = $this->order();
        $line = $order->lines()->where('inventory_item_id', $this->item->id)->first();
        $request = OrderStockRequest::create(['order_id' => $order->id, 'requested_by' => $this->actor->id, 'status' => 'approved']);
        $this->stock->issue($this->item, 4, 'direct', $this->actor, $line);
        $this->stock->issue($this->item, 3, 'request', $this->actor, $request);
        $this->stock->return($this->item, 1, 'partial', $this->actor, $request);
        app(OrderDeletionService::class)->delete($order, $this->actor);
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertEquals(7, InventoryTransaction::where('type', Type::Return)->sum('qty'));
    }

    public function test_line_removal_and_cancellation_restore_once(): void
    {
        $order = $this->order();
        $line = $order->lines()->where('inventory_item_id', $this->item->id)->first();
        $this->stock->issue($this->item, 4, 'direct', $this->actor, $line);
        $service = app(OrderInventoryRestorationService::class);
        $service->restoreReference($line, $this->actor);
        $service->restoreReference($line, $this->actor);
        $service->cancel($order, $this->actor);
        $service->cancel($order, $this->actor);
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame(1, InventoryTransaction::where('type', Type::Return)->count());
    }

    public function test_branch_mismatch_and_invalid_balances_fail_closed(): void
    {
        DB::table('inventory_stocks')->where('inventory_item_id', $this->item->id)->update(['branch_id' => $this->otherBranch->id]);
        $this->rejected(fn () => $this->stock->issue($this->item, 1, 'test', $this->actor));
        DB::table('inventory_stocks')->where('inventory_item_id', $this->item->id)->update(['branch_id' => $this->branch->id, 'qty_reserved' => 21]);
        $this->rejected(fn () => $this->stock->receive($this->item, 1, null, 'test', $this->actor));
        $this->assertSame(0, InventoryTransaction::count());
    }

    public function test_reconciliation_detects_discrepancies_and_never_writes(): void
    {
        $this->item->stock->update(['qty_reserved' => 21]);
        $a = $this->item->variants()->create($this->payload());
        $b = $this->item->variants()->create($this->payload('L'));
        $b->update(['sku' => $a->sku, 'stock_qty' => 7]);
        $this->item->variants()->create([...$this->payload('S'), 'sku' => null]);
        FabricVariant::create(['fabric_id' => 999, 'name' => 'Orphan link', 'inventory_item_variant_id' => 99999]);
        $before = [DB::table('inventory_stocks')->get()->toJson(), DB::table('inventory_transactions')->get()->toJson(), DB::table('inventory_item_variants')->get()->toJson()];
        $report = app(InventoryReconciliationService::class)->inspect();
        foreach (['balance_mismatches', 'invalid_reservations', 'duplicate_variant_skus', 'null_variant_skus', 'variants_with_legacy_stock', 'missing_variant_references'] as $key) {
            $this->assertGreaterThan(0, $report['summary'][$key], $key);
        }
        $this->artisan('inventory:reconcile', ['--detailed' => true])->expectsOutputToContain('No data changed.')->assertSuccessful();
        $this->assertSame($before, [DB::table('inventory_stocks')->get()->toJson(), DB::table('inventory_transactions')->get()->toJson(), DB::table('inventory_item_variants')->get()->toJson()]);
    }

    public function test_legacy_product_editor_preserves_stock_and_variant_identity(): void
    {
        app(VariantSynchronizationService::class)->sync($this->item, [$this->payload()]);
        $id = $this->item->variants()->first()->id;
        Livewire::test(\App\Livewire\Storefront\Admin\ProductManager::class)
            ->call('editProduct', $this->item->id)->set('productName', 'Edited legacy product')
            ->set('productStockQuantity', 900)->call('saveProduct')->assertHasNoErrors();
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame($id, $this->item->variants()->where('is_active', true)->first()->id);
        $this->assertSame('12.50', $this->item->fresh()->default_buy_price);
    }

    public function test_reservation_failure_rolls_back_all_items_and_history(): void
    {
        $order = $this->order();
        $other = InventoryItem::factory()->create(['branch_id' => $this->branch->id]);
        $other->stock->update(['qty_on_hand' => 0]);
        $order->lines()->create(['inventory_item_id' => $other->id, 'item_name' => 'Empty', 'qty' => 1, 'unit_price' => 1, 'line_total' => 1]);
        $this->rejected(fn () => app(InventoryReservationService::class)->reserve($order));
        $this->assertSame('0.00', $this->item->stock->fresh()->qty_reserved);
        $this->assertFalse($order->statusHistory()->where('status', 'inventory_reserved')->exists());
    }

    public function test_cancelled_order_cannot_reacquire_released_stock(): void
    {
        $order = $this->order();
        $reservations = app(InventoryReservationService::class);
        $reservations->reserve($order);
        app(OrderInventoryRestorationService::class)->cancel($order, $this->actor);
        $this->rejected(fn () => $reservations->commit($order, $this->actor));
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
        $this->assertSame('0.00', $this->item->stock->fresh()->qty_reserved);
    }

    public function test_duplicate_combinations_are_reported_and_not_reassigned(): void
    {
        $first = $this->item->variants()->create($this->payload());
        $second = $this->item->variants()->create([...$this->payload(), 'name' => 'Legacy duplicate', 'sku' => 'OTHER']);
        $this->rejected(fn () => app(VariantSynchronizationService::class)->sync($this->item, [$this->payload()]));
        $this->assertTrue($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, app(InventoryReconciliationService::class)->inspect()['summary']['duplicate_combinations']);
    }

    public function test_reconciliation_json_is_valid_and_migration_is_additive(): void
    {
        $this->artisan('inventory:reconcile', ['--json' => true])->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('inventory_transactions', 'operation_key'));
        $this->assertSame(0, InventoryTransaction::count());
        $this->assertSame('20.00', $this->item->stock->fresh()->qty_on_hand);
    }
}
