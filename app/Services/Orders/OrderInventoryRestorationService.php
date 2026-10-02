<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Services\Storefront\InventoryReservationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderInventoryRestorationService
{
    public function restore(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $order = Order::withoutBranchScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $legacyOrderReturns = InventoryTransaction::withoutBranchScope()->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)->where('type', \App\Enums\InventoryTransactionType::Return->value)->exists();
            $orderIssues = InventoryTransaction::withoutBranchScope()->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)->where('type', \App\Enums\InventoryTransactionType::Issue->value)->exists();
            if ($legacyOrderReturns && ! $orderIssues) {
                throw \Illuminate\Validation\ValidationException::withMessages(['stock' => 'Legacy order-level returns require reconciliation before restoring request stock.']);
            }
            app(InventoryReservationService::class)->release($order);
            foreach ([$order, ...$order->lines()->orderBy('id')->get(), ...$order->stockRequests()->orderBy('id')->get()] as $reference) {
                $this->restoreReference($reference, $actor);
            }
        });
    }

    public function restoreReference(Model $reference, User $actor): void
    {
        $identities = InventoryTransaction::withoutBranchScope()->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->select('inventory_item_id', 'inventory_stock_unit_id')->distinct()
            ->orderBy('inventory_item_id')->orderBy('inventory_stock_unit_id')->get();
        foreach ($identities as $identity) {
            $source = $identity->inventory_stock_unit_id
                ? \App\Models\InventoryStockUnit::findOrFail($identity->inventory_stock_unit_id)
                : InventoryItem::withoutBranchScope()->findOrFail($identity->inventory_item_id);
            app(StockMovementService::class)->reverseOutstanding($source, $reference, $actor, 'Outstanding order inventory restored.');
        }
    }

    public function cancel(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $locked = Order::withoutBranchScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Cancelled) {
                return;
            }
            // Delivered goods require an explicit physical return, not automatic restocking.
            if (! in_array($locked->status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
                $this->restore($locked, $actor);
            }
            $locked->update(['status' => OrderStatus::Cancelled]);
        });
    }
}
