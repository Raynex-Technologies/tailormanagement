<?php

namespace App\Services\Storefront;

use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function __construct(protected StockMovementService $stock) {}

    public function reserve(Order $order): void
    {
        $this->transition($order, 'reserve');
    }

    public function release(Order $order): void
    {
        $this->transition($order, 'release');
    }

    public function commit(Order $order, ?User $actor = null): void
    {
        $this->transition($order, 'commit', $actor);
    }

    protected function transition(Order $order, string $action, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $action, $actor) {
            $locked = Order::withoutBranchScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $history = $locked->statusHistory()->whereIn('status', ['inventory_reserved', 'inventory_released', 'inventory_committed'])->orderByDesc('id')->first();
            $state = $history?->status;
            if ($state === 'inventory_committed' || ($action === 'reserve' && $state === 'inventory_reserved') || ($action === 'release' && $state !== 'inventory_reserved')) {
                return;
            }
            if ($action !== 'release' && $locked->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['stock' => 'Cancelled orders cannot reserve or commit stock.']);
            }
            // A late successful payment after release must acquire stock again.
            if ($action === 'commit' && $state !== 'inventory_reserved') {
                $this->reserve($locked);
                $history = $locked->statusHistory()->where('status', 'inventory_reserved')->orderByDesc('id')->firstOrFail();
                $state = 'inventory_reserved';
            }
            if ($action === 'reserve') {
                $quantities = [];
                foreach ($locked->lines()->whereNotNull('inventory_item_id')->orderBy('inventory_item_id')->get() as $line) {
                    $item = InventoryItem::withoutBranchScope()->whereKey($line->inventory_item_id)->where('branch_id', $locked->branch_id)->firstOrFail();
                    if (! $item->track_stock) {
                        continue;
                    }
                    $qty = BigDecimal::of((string) $line->qty);
                    if (! $qty->isPositive()) {
                        continue;
                    }
                    $quantities[$item->id] = (string) BigDecimal::of($quantities[$item->id] ?? '0')->plus($qty);
                }
            } else {
                $quantities = $history->metadata['inventory_quantities'] ?? null;
                if (! is_array($quantities)) {
                    throw ValidationException::withMessages(['stock' => 'Legacy reservation lacks a quantity snapshot; run inventory:reconcile --detailed before resolving it.']);
                }
            }
            ksort($quantities, SORT_NUMERIC);
            foreach ($quantities as $itemId => $qty) {
                $item = InventoryItem::withoutBranchScope()->whereKey($itemId)->where('branch_id', $locked->branch_id)->firstOrFail();
                // Backorder admission never authorizes negative physical/reserved stock.
                $this->stock->reservation($item, (string) $qty, $action, $locked, $actor);
            }
            $status = ['reserve' => 'inventory_reserved', 'release' => 'inventory_released', 'commit' => 'inventory_committed'][$action];
            $locked->statusHistory()->create([
                'status' => $status, 'title' => ucfirst(str_replace('_', ' ', $status)),
                'note' => 'Inventory reservation '.$action.'.', 'is_customer_visible' => false,
                'changed_by' => $actor?->id, 'metadata' => ['inventory_quantities' => $quantities],
            ]);
        });
    }
}
