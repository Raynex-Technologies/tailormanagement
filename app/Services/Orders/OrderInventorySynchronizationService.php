<?php

namespace App\Services\Orders;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryStockUnit;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class OrderInventorySynchronizationService
{
    public function synchronize(OrderLine $line, string $quantity, User $actor): void
    {
        DB::transaction(function () use ($line, $quantity, $actor) {
            $order = Order::withoutBranchScope()->whereKey($line->order_id)->lockForUpdate()->firstOrFail();
            if ($order->status === \App\Enums\OrderStatus::Cancelled) {
                throw \Illuminate\Validation\ValidationException::withMessages(['lines' => 'Cancelled orders cannot change inventory.']);
            }
            $line = $order->lines()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $targetUnit = $line->inventory_item_id
                ? ($line->inventory_stock_unit_id ? InventoryStockUnit::findOrFail($line->inventory_stock_unit_id)
                    : app(\App\Services\Inventory\StockUnitResolver::class)->forItem(InventoryItem::withoutBranchScope()->findOrFail($line->inventory_item_id)))
                : null;
            $movements = InventoryTransaction::withoutBranchScope()
                ->where('reference_type', $line->getMorphClass())->where('reference_id', $line->id)
                ->whereIn('type', [InventoryTransactionType::Issue->value, InventoryTransactionType::Return->value]);
            $identities = (clone $movements)->select('inventory_item_id', 'inventory_stock_unit_id')->distinct()
                ->orderBy('inventory_item_id')->orderBy('inventory_stock_unit_id')->get();
            $movement = app(StockMovementService::class);
            foreach ($identities as $identity) {
                $same = (int) $identity->inventory_item_id === (int) $line->inventory_item_id
                    && ((int) $identity->inventory_stock_unit_id === (int) $targetUnit?->id
                        || (! $identity->inventory_stock_unit_id && ! $line->inventory_item_variant_id));
                if (! $same) {
                    $source = $identity->inventory_stock_unit_id
                        ? InventoryStockUnit::findOrFail($identity->inventory_stock_unit_id)
                        : InventoryItem::withoutBranchScope()->findOrFail($identity->inventory_item_id);
                    $movement->reverseOutstanding($source, $line, $actor, 'Order line selection replaced.');
                }
            }
            if (! $line->inventory_item_id) {
                return;
            }
            $unit = $targetUnit;
            $current = (clone $movements)->where('inventory_item_id', $line->inventory_item_id)
                ->where(function ($query) use ($unit) {
                    $query->where('inventory_stock_unit_id', $unit->id);
                    if (! $unit->inventory_item_variant_id) {
                        $query->orWhereNull('inventory_stock_unit_id');
                    }
                });
            $held = BigDecimal::of((string) (clone $current)->sum('qty'))->negated();
            $delta = BigDecimal::of($quantity)->minus($held);
            if ($delta->isPositive()) {
                app(OrderInventorySelectionService::class)->resolve($line->inventory_item_id, $unit->id, $order->branch_id);
                $movement->issue($unit, (string) $delta, 'Order '.$order->order_no, $actor, $line);
            } elseif ($delta->isNegative()) {
                // The order lock and ledger version distinguish successive real reductions from retries.
                $key = 'order-line-reduce:'.((clone $current)->max('id') ?? 0).':'.$quantity;
                $movement->return($unit, (string) $delta->negated(), 'Order quantity reduced.', $actor, $line, $key);
            }
        });
    }
}
