<?php

namespace Tests\Feature\Orders;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Services\Inventory\VariantAdministrationService;
use App\Services\Orders\OrderInventorySelectionService;
use App\Services\Orders\OrderInventorySynchronizationService;
use Tests\TestCase;

class OrderVariantSynchronizationTest extends TestCase
{
    public function test_exact_quantity_deltas_replacement_and_retry(): void
    {
        $actor = $this->actingAsRole('superadmin', $this->branch);
        $item = InventoryItem::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $item->stock->update(['qty_on_hand' => 10, 'qty_reserved' => 0]);
        $admin = app(VariantAdministrationService::class);
        $option = $admin->option($item->id, null, 'Size');
        $small = $admin->value($item->id, $option->id, null, 'Small');
        $large = $admin->value($item->id, $option->id, null, 'Large');
        $a = $admin->combination($item->id, [$small->id], 'SMALL');
        $b = $admin->combination($item->id, [$large->id], 'LARGE');
        $admin->convert($item->id, [$a->id => '5', $b->id => '5'], '10');
        $order = Order::factory()->create(['branch_id' => $this->branch->id, 'status' => \App\Enums\OrderStatus::New]);
        $selection = app(OrderInventorySelectionService::class);
        $unitA = $selection->resolve($item->id, $a->stockUnit->id, $this->branch->id);
        $unitB = $selection->resolve($item->id, $b->stockUnit->id, $this->branch->id);
        $line = $order->lines()->create([...$selection->snapshot($unitA), 'qty' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $sync = app(OrderInventorySynchronizationService::class);
        $sync->synchronize($line, '1', $actor);
        $sync->synchronize($line, '3', $actor);
        $this->assertSame('2.00', $unitA->stock->fresh()->qty_on_hand);
        $sync->synchronize($line, '2', $actor);
        $sync->synchronize($line, '2', $actor);
        $this->assertSame('3.00', $unitA->stock->fresh()->qty_on_hand);
        $line->update($selection->snapshot($unitB));
        $sync->synchronize($line, '2', $actor);
        $this->assertSame('5.00', $unitA->stock->fresh()->qty_on_hand);
        $this->assertSame('3.00', $unitB->stock->fresh()->qty_on_hand);
        $this->assertEquals([-1, -2, 1, 2, -2], InventoryTransaction::where('reference_type', $line->getMorphClass())->where('reference_id', $line->id)->orderBy('id')->pluck('qty')->map(fn ($v) => (float) $v)->all());
        app(\App\Services\Orders\OrderInventoryRestorationService::class)->restoreReference($line, $actor);
        $this->assertSame('5.00', $unitB->stock->fresh()->qty_on_hand);
    }
}
