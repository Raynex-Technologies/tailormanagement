<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderDeletionService
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}

    /**
     * Soft-delete an order and all directly related records, while reinstating
     * any inventory that had been issued via order stock requests.
     */
    public function delete(Order $order, User $actor): void
    {
        if (! $actor->hasRole('superadmin')) {
            throw new DomainException('Only superadmins can delete orders.');
        }

        DB::transaction(function () use ($order, $actor) {
            $order = Order::withoutBranchScope()
                ->with([
                    'lines.measurement',
                    'payments',
                    'comments',
                    'watchers',
                    'stockRequests.items.inventoryItem',
                    'deliveryNote',
                    'invoice.lines',
                    'orderExpenses',
                ])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $this->reinstateIssuedInventory($order, $actor);

            foreach ($order->stockRequests as $stockRequest) {
                $stockRequest->items->each->delete();
                $stockRequest->delete();
            }

            if ($order->invoice) {
                $order->invoice->lines->each->delete();
                $order->invoice->delete();
            }

            $order->payments->each->delete();
            $order->comments->each->delete();
            $order->watchers->each->delete();
            $order->orderExpenses->each->delete();

            if ($order->deliveryNote) {
                $order->deliveryNote->delete();
            }

            foreach ($order->lines as $line) {
                if ($line->measurement) {
                    $line->measurement->delete();
                }
                $line->delete();
            }

            $order->delete();
        });
    }

    protected function reinstateIssuedInventory(Order $order, User $actor): void
    {
        foreach ($order->stockRequests as $stockRequest) {
            foreach ($stockRequest->items as $item) {
                $issuedQty = (float) $item->qty_issued;
                if ($issuedQty <= 0) {
                    continue;
                }

                $inventoryItem = $item->inventoryItem;
                if (! $inventoryItem) {
                    continue;
                }

                $this->stockMovementService->return(
                    item: $inventoryItem,
                    qty: $issuedQty,
                    note: "Inventory reinstated from deleted Order #{$order->order_no}",
                    actor: $actor,
                    reference: $order
                );
            }
        }
    }
}
