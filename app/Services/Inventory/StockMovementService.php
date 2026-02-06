<?php

namespace App\Services\Inventory;

use App\Enums\InventoryTransactionType;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Receive stock into inventory.
     *
     * @param  InventoryItem  $item  The item to receive stock for
     * @param  float  $qty  Quantity to receive (must be positive)
     * @param  float|null  $unitCost  Cost per unit (optional)
     * @param  string|null  $note  Optional note
     * @param  User  $actor  User performing the operation
     * @param  Model|null  $reference  Reference model (e.g., GoodsReceipt)
     * @return InventoryTransaction The created transaction
     *
     * @throws ValidationException
     */
    public function receive(
        InventoryItem $item,
        float $qty,
        ?float $unitCost = null,
        ?string $note = null,
        User $actor,
        ?Model $reference = null
    ): InventoryTransaction {
        // Validate quantity
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'qty' => ['Quantity to receive must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($item, $qty, $unitCost, $note, $actor, $reference) {
            // Get or create stock record with lock
            $stock = $this->getOrCreateStockWithLock($item);

            // Update stock quantity
            $stock->qty_on_hand += $qty;
            $stock->save();

            // Calculate total cost if unit cost provided
            $totalCost = $unitCost !== null ? $qty * $unitCost : null;

            // Prepare reference data
            $referenceType = $reference ? get_class($reference) : null;
            $referenceId = $reference?->id;

            // Create transaction record
            return InventoryTransaction::create([
                'branch_id' => $item->branch_id ?? BranchContext::id(),
                'inventory_item_id' => $item->id,
                'type' => InventoryTransactionType::Receive,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $actor->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Adjust stock quantity (can be positive or negative).
     *
     * @param  InventoryItem  $item  The item to adjust stock for
     * @param  float  $qtyDelta  Quantity change (positive to add, negative to remove)
     * @param  string|null  $note  Optional note (recommended for adjustments)
     * @param  User  $actor  User performing the operation
     * @return InventoryTransaction The created transaction
     *
     * @throws ValidationException
     */
    public function adjust(
        InventoryItem $item,
        float $qtyDelta,
        ?string $note = null,
        User $actor
    ): InventoryTransaction {
        // Validate non-zero adjustment
        if ($qtyDelta == 0) {
            throw ValidationException::withMessages([
                'qty' => ['Adjustment quantity cannot be zero.'],
            ]);
        }

        return DB::transaction(function () use ($item, $qtyDelta, $note, $actor) {
            // Get or create stock record with lock
            $stock = $this->getOrCreateStockWithLock($item);

            // Check if adjustment would result in negative stock
            $newQty = $stock->qty_on_hand + $qtyDelta;
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty' => [
                        "Cannot adjust by {$qtyDelta}. Current stock is {$stock->qty_on_hand}. " .
                        'Adjustment would result in negative stock which is not allowed.',
                    ],
                ]);
            }

            // Update stock quantity
            $stock->qty_on_hand = $newQty;
            $stock->save();

            // Create transaction record
            return InventoryTransaction::create([
                'branch_id' => $item->branch_id ?? BranchContext::id(),
                'inventory_item_id' => $item->id,
                'type' => InventoryTransactionType::Adjust,
                'qty' => $qtyDelta, // Store signed value
                'unit_cost' => null,
                'total_cost' => null,
                'created_by' => $actor->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Issue stock for an order (reduce available stock).
     *
     * @param  InventoryItem  $item  The item to issue stock from
     * @param  float  $qty  Quantity to issue (must be positive)
     * @param  string|null  $note  Optional note
     * @param  User  $actor  User performing the operation
     * @param  Model|null  $reference  Reference model (e.g., OrderStockRequest)
     * @return InventoryTransaction The created transaction
     *
     * @throws ValidationException
     */
    public function issue(
        InventoryItem $item,
        float $qty,
        ?string $note,
        User $actor,
        ?Model $reference = null
    ): InventoryTransaction {
        // Validate quantity
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                'qty' => ['Quantity to issue must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use ($item, $qty, $note, $actor, $reference) {
            // Get or create stock record with lock
            $stock = $this->getOrCreateStockWithLock($item);

            // Check sufficient stock
            if ($stock->qty_on_hand < $qty) {
                throw ValidationException::withMessages([
                    'qty' => [
                        "Insufficient stock. Available: {$stock->qty_on_hand}, Requested: {$qty}.",
                    ],
                ]);
            }

            // Update stock quantity
            $stock->qty_on_hand -= $qty;
            $stock->save();

            // Prepare reference data
            $referenceType = $reference ? get_class($reference) : null;
            $referenceId = $reference?->id;

            // Create transaction record
            return InventoryTransaction::create([
                'branch_id' => $item->branch_id ?? BranchContext::id(),
                'inventory_item_id' => $item->id,
                'type' => InventoryTransactionType::Issue,
                'qty' => -$qty, // Store as negative for issues
                'unit_cost' => $item->default_sell_price,
                'total_cost' => $qty * ($item->default_sell_price ?? 0),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $actor->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Get current stock level for an item.
     */
    public function getStockLevel(InventoryItem $item): array
    {
        $stock = $item->stock;

        return [
            'qty_on_hand' => $stock?->qty_on_hand ?? 0,
            'qty_reserved' => $stock?->qty_reserved ?? 0,
            'qty_available' => ($stock?->qty_on_hand ?? 0) - ($stock?->qty_reserved ?? 0),
            'reorder_level' => $item->reorder_level,
            'is_low_stock' => ($stock?->qty_on_hand ?? 0) <= $item->reorder_level,
        ];
    }

    /**
     * Get or create stock record with database lock.
     */
    protected function getOrCreateStockWithLock(InventoryItem $item): InventoryStock
    {
        // Try to get existing stock with lock
        $stock = InventoryStock::withoutBranchScope()
            ->where('inventory_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        // If no stock record exists, create one
        if (! $stock) {
            $stock = InventoryStock::create([
                'branch_id' => $item->branch_id ?? BranchContext::id(),
                'inventory_item_id' => $item->id,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
            ]);

            // Re-lock the newly created record
            $stock = InventoryStock::withoutBranchScope()
                ->where('id', $stock->id)
                ->lockForUpdate()
                ->first();
        }

        return $stock;
    }
}
