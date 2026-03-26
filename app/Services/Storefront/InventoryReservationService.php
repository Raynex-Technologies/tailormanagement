<?php

namespace App\Services\Storefront;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryReservationService
{
    public function reserve(Order $order): void
    {
        if ($order->statusHistory()->where('status', 'inventory_reserved')->exists()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lines = $order->lines()->whereNotNull('inventory_item_id')->get();

            foreach ($lines as $line) {
                $qty = (float) $line->qty;
                if ($qty <= 0) {
                    continue;
                }

                $stock = InventoryStock::withoutBranchScope()
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    throw new RuntimeException('Stock record is missing for one or more items in cart.');
                }

                $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
                if ($available < $qty) {
                    throw new RuntimeException('Requested quantity exceeds available stock for '.$line->item_name.'.');
                }

                $stock->qty_reserved = (float) $stock->qty_reserved + $qty;
                $stock->save();
            }

            $order->statusHistory()->create([
                'status' => 'inventory_reserved',
                'title' => 'Inventory Reserved',
                'note' => 'Stock quantities reserved for checkout.',
                'is_customer_visible' => false,
            ]);
        });
    }

    public function release(Order $order): void
    {
        if ($order->statusHistory()->where('status', 'inventory_released')->exists()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lines = $order->lines()->whereNotNull('inventory_item_id')->get();

            foreach ($lines as $line) {
                $qty = (float) $line->qty;
                if ($qty <= 0) {
                    continue;
                }

                $stock = InventoryStock::withoutBranchScope()
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    continue;
                }

                $stock->qty_reserved = max(0, (float) $stock->qty_reserved - $qty);
                $stock->save();
            }

            $order->statusHistory()->create([
                'status' => 'inventory_released',
                'title' => 'Inventory Released',
                'note' => 'Reserved stock released after payment failure or cancellation.',
                'is_customer_visible' => false,
            ]);
        });
    }

    public function commit(Order $order, ?User $actor = null): void
    {
        if ($order->statusHistory()->where('status', 'inventory_committed')->exists()) {
            return;
        }

        DB::transaction(function () use ($order, $actor) {
            $lines = $order->lines()->whereNotNull('inventory_item_id')->get();

            foreach ($lines as $line) {
                $qty = (float) $line->qty;
                if ($qty <= 0) {
                    continue;
                }

                $stock = InventoryStock::withoutBranchScope()
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock) {
                    continue;
                }

                $stock->qty_reserved = max(0, (float) $stock->qty_reserved - $qty);
                $stock->qty_on_hand = max(0, (float) $stock->qty_on_hand - $qty);
                $stock->save();

                InventoryTransaction::create([
                    'branch_id' => $order->branch_id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'type' => InventoryTransactionType::Issue,
                    'qty' => -$qty,
                    'unit_cost' => $line->unit_price,
                    'total_cost' => $line->line_total,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by' => $actor?->id,
                    'note' => 'Storefront order payment confirmed.',
                ]);
            }

            $order->statusHistory()->create([
                'status' => 'inventory_committed',
                'title' => 'Inventory Committed',
                'note' => 'Reserved stock converted to issued inventory after payment confirmation.',
                'is_customer_visible' => false,
                'changed_by' => $actor?->id,
            ]);
        });
    }
}
