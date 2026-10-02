<?php

namespace App\Services\Inventory;

use App\Enums\InventoryTransactionType as Type;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryStockUnit;
use App\Models\InventoryTransaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function receive(InventoryItem|InventoryStockUnit $item, float|string $qty, float|string|null $unitCost, ?string $note, User $actor, ?Model $reference = null): InventoryTransaction
    {
        $quantity = $this->positive($qty);
        $cost = $unitCost === null ? null : $this->decimal($unitCost);
        if ($cost?->isNegative()) {
            $this->fail('Receipt cost cannot be negative.');
        }

        return $this->locked($item, $actor, $reference, function ($product, $unit, $stock) use ($quantity, $cost, $actor, $note, $reference) {
            $stock->qty_on_hand = (string) $this->decimal($stock->qty_on_hand)->plus($quantity);
            $stock->save();

            return $this->movement($product, $unit, Type::Receive, $quantity, $actor, $note, $reference, $cost);
        });
    }

    public function adjust(InventoryItem|InventoryStockUnit $item, float|string $qtyDelta, ?string $note, User $actor): InventoryTransaction
    {
        $quantity = $this->decimal($qtyDelta);
        if ($quantity->isZero()) {
            $this->fail('Adjustment quantity cannot be zero.');
        }

        return $this->locked($item, $actor, null, function ($product, $unit, $stock) use ($quantity, $actor, $note) {
            $next = $this->decimal($stock->qty_on_hand)->plus($quantity);
            if ($next->isLessThan($stock->qty_reserved)) {
                $this->fail('Adjustment cannot reduce stock below reserved quantity.');
            }
            $stock->qty_on_hand = (string) $next;
            $stock->save();

            return $this->movement($product, $unit, Type::Adjust, $quantity, $actor, $note);
        });
    }

    public function issue(InventoryItem|InventoryStockUnit $item, float|string $qty, ?string $note, User $actor, ?Model $reference = null): InventoryTransaction
    {
        $quantity = $this->positive($qty);

        return $this->locked($item, $actor, $reference, function ($product, $unit, $stock) use ($quantity, $actor, $note, $reference) {
            if (! $unit->is_active || ! $product->is_active) {
                $this->fail('Inactive stock identity cannot be issued.');
            }
            if ($this->decimal($stock->qty_on_hand)->minus($stock->qty_reserved)->isLessThan($quantity)) {
                $this->fail('Insufficient available stock. Reserved stock cannot be issued.');
            }
            $stock->qty_on_hand = (string) $this->decimal($stock->qty_on_hand)->minus($quantity);
            $stock->save();

            return $this->movement($product, $unit, Type::Issue, $quantity->negated(), $actor, $note, $reference);
        });
    }

    public function return(InventoryItem|InventoryStockUnit $item, float|string $qty, ?string $note, User $actor, ?Model $reference = null, ?string $operationKey = null): ?InventoryTransaction
    {
        $quantity = $this->positive($qty);
        if (! $reference?->exists) {
            $this->fail('A return must reference the original issue.');
        }

        return $this->locked($item, $actor, $reference, function ($product, $unit, $stock) use ($quantity, $actor, $note, $reference, $operationKey) {
            // Preserve the phase-1 key for simple identities so pre-cutover retries remain safe.
            $identity = $unit->inventory_item_variant_id ? 'unit:'.$unit->id : $product->id;
            $key = hash('sha256', implode(':', [$reference->getMorphClass(), $reference->getKey(), $identity, $operationKey ?? 'return:'.$quantity]));
            if ($previous = InventoryTransaction::withoutBranchScope()->where('operation_key', $key)->first()) {
                if (! $quantity->isEqualTo($previous->qty)) {
                    $this->fail('Return key already used for a different quantity.');
                }

                return $previous;
            }
            if ($quantity->isGreaterThan($this->outstanding($product, $unit, $reference))) {
                $this->fail('Return exceeds the outstanding quantity issued against this reference.');
            }
            $stock->qty_on_hand = (string) $this->decimal($stock->qty_on_hand)->plus($quantity);
            $stock->save();

            return $this->movement($product, $unit, Type::Return, $quantity, $actor, $note, $reference, operationKey: $key);
        });
    }

    public function reverseOutstanding(InventoryItem|InventoryStockUnit $item, Model $reference, User $actor, ?string $note = null): ?InventoryTransaction
    {
        return $this->locked($item, $actor, $reference, function ($product, $unit, $stock) use ($actor, $note, $reference) {
            $quantity = $this->outstanding($product, $unit, $reference);
            if ($quantity->isZero()) {
                return null;
            }
            $stock->qty_on_hand = (string) $this->decimal($stock->qty_on_hand)->plus($quantity);
            $stock->save();

            return $this->movement($product, $unit, Type::Return, $quantity, $actor, $note, $reference);
        });
    }

    /** Called by the order-locked lifecycle with its durable item snapshot. */
    public function reservation(InventoryItem|InventoryStockUnit $item, string $qty, string $transition, Model $reference, ?User $actor = null): void
    {
        $quantity = $this->positive($qty);
        $this->locked($item, $actor, $reference, function ($product, $unit, $stock) use ($quantity, $transition, $actor, $reference) {
            $onHand = $this->decimal($stock->qty_on_hand);
            $reserved = $this->decimal($stock->qty_reserved);
            if ($transition === 'reserve') {
                if (! $unit->is_active || ! $product->is_active) {
                    $this->fail('Inactive stock cannot be reserved.');
                }
                if ($onHand->minus($reserved)->isLessThan($quantity)) {
                    $this->fail('Requested quantity exceeds available stock.');
                }
                $stock->qty_reserved = (string) $reserved->plus($quantity);
            } elseif (in_array($transition, ['release', 'commit'], true)) {
                if ($reserved->isLessThan($quantity)) {
                    $this->fail('Reservation balance is inconsistent; reconciliation required.');
                }
                $stock->qty_reserved = (string) $reserved->minus($quantity);
                if ($transition === 'commit') {
                    $stock->qty_on_hand = (string) $onHand->minus($quantity);
                    $this->movement($product, $unit, Type::Issue, $quantity->negated(), $actor, 'Storefront reservation committed.', $reference);
                }
            } else {
                $this->fail('Unknown reservation transition.');
            }
            $stock->save();
        });
    }

    public function initialize(InventoryItem|InventoryStockUnit $item): InventoryStock
    {
        return $this->locked($item, null, null, fn ($product, $unit, $stock) => $stock);
    }

    protected function locked(InventoryItem|InventoryStockUnit $source, ?User $actor, ?Model $reference, callable $operation): mixed
    {
        return DB::transaction(function () use ($source, $actor, $reference, $operation) {
            $itemId = $source instanceof InventoryStockUnit ? $source->inventory_item_id : $source->id;
            $item = InventoryItem::withoutBranchScope()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($source instanceof InventoryItem && (int) $source->branch_id !== (int) $item->branch_id) {
                $this->fail('Item branch changed; reload it.');
            }
            $resolver = app(StockUnitResolver::class);
            $resolver->authorizeItem($item);
            foreach (array_filter([$actor, auth()->user()]) as $user) {
                if (! $user->isGlobalAdmin() && (int) $user->branch_id !== (int) $item->branch_id) {
                    $this->fail('Stock operation is outside the user branch.');
                }
            }
            $unit = $source instanceof InventoryStockUnit ? InventoryStockUnit::whereKey($source->id)->lockForUpdate()->firstOrFail() : $resolver->forItem($item);
            if ($unit->inventory_item_id !== $item->id || $unit->allocation_status !== 'ready') {
                $this->fail('Stock allocation requires review before this identity can be used.');
            }
            if (($item->variant_mode === 'variants') !== ($unit->inventory_item_variant_id !== null)) {
                $this->fail('Stock unit does not match the intentional product mode.');
            }
            $referenceBranch = $reference?->getAttribute('branch_id');
            if ($reference instanceof \App\Models\OrderLine) {
                $referenceBranch = $reference->order?->branch_id;
            }
            if ($referenceBranch !== null && (int) $referenceBranch !== (int) $item->branch_id) {
                $this->fail('Stock reference belongs to a different branch.');
            }
            $stock = InventoryStock::withoutBranchScope()->where('inventory_stock_unit_id', $unit->id)->lockForUpdate()->first();
            if (! $stock && ! $unit->inventory_item_variant_id) {
                $stock = InventoryStock::withoutBranchScope()->where('inventory_item_id', $item->id)->lockForUpdate()->first();
                // Existing physical balances must be explicitly backfilled before writes.
                if ($stock) {
                    $this->fail('Existing balance is not backfilled. Run inventory:stock-units:backfill before writing stock.');
                }
                $stock ??= InventoryStock::create(['branch_id' => $item->branch_id, 'inventory_item_id' => $item->id, 'qty_on_hand' => 0, 'qty_reserved' => 0]);
                app(StockUnitBackfillService::class)->attachBalance($item, $unit, $stock);
            }
            if (! $stock) {
                $this->fail('Variant balance allocation is not implemented in this phase.');
            }
            if ((int) $stock->inventory_item_id !== $item->id || (int) $stock->inventory_stock_unit_id !== $unit->id) {
                $this->fail('Stock balance identity mismatch.');
            }
            app(StockUnitBackfillService::class)->validateBalance($item, $stock);
            if (! $unit->inventory_item_variant_id) {
                app(StockUnitBackfillService::class)->attachBalance($item, $unit, $stock);
            }

            return $operation($item, $unit, $stock);
        });
    }

    protected function outstanding(InventoryItem $item, InventoryStockUnit $unit, Model $reference): BigDecimal
    {
        $query = InventoryTransaction::withoutBranchScope()->where('inventory_item_id', $item->id)
            ->where('reference_type', $reference->getMorphClass())->where('reference_id', $reference->getKey())
            ->whereIn('type', [Type::Issue->value, Type::Return->value])
            ->where(function ($q) use ($unit) {
                $q->where('inventory_stock_unit_id', $unit->id);
                if (! $unit->inventory_item_variant_id) {
                    $q->orWhereNull('inventory_stock_unit_id');
                }
            });
        $net = BigDecimal::zero();
        foreach ($query->get() as $movement) {
            if ((int) $movement->branch_id !== (int) $item->branch_id) {
                $this->fail('Movement branch mismatch; reconciliation required.');
            }
            $net = $net->plus($movement->qty);
        }
        if ($net->isPositive()) {
            $this->fail('Historical returns exceed issues; reconciliation required.');
        }

        return $net->negated();
    }

    protected function movement(InventoryItem $item, InventoryStockUnit $unit, Type $type, BigDecimal $qty, ?User $actor, ?string $note, ?Model $reference = null, ?BigDecimal $cost = null, ?string $operationKey = null): InventoryTransaction
    {
        // Returns follow original movement identity even after an order line changes item.
        if ($type !== Type::Return && $reference?->getAttribute('inventory_stock_unit_id') !== null && (int) $reference->getAttribute('inventory_stock_unit_id') !== $unit->id) {
            $this->fail('Reference points to a different stock identity.');
        }

        return InventoryTransaction::create([
            'branch_id' => $item->branch_id, 'inventory_item_id' => $item->id, 'inventory_stock_unit_id' => $unit->id,
            'type' => $type, 'qty' => (string) $qty, 'unit_cost' => $cost === null ? null : (string) $cost,
            'total_cost' => $cost === null ? null : (string) $qty->multipliedBy($cost)->toScale(2, RoundingMode::HALF_UP),
            'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(),
            'created_by' => $actor?->id, 'note' => $note, 'operation_key' => $operationKey,
        ]);
    }

    protected function decimal(float|string $value): BigDecimal
    {
        try {
            return BigDecimal::of((string) $value)->toScale(2, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            $this->fail('Quantity/cost must be a finite decimal with at most two decimal places.');
        }
    }

    protected function positive(float|string $value): BigDecimal
    {
        $number = $this->decimal($value);
        if (! $number->isPositive()) {
            $this->fail('Quantity must be greater than zero.');
        }

return $number;
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['qty' => $message]);
    }

    public function getStockLevel(InventoryItem $item): array
    {
        $unit = app(StockUnitResolver::class)->forItem($item);
        $stock = $unit->stock;

        return ['qty_on_hand' => $stock?->qty_on_hand ?? 0, 'qty_reserved' => $stock?->qty_reserved ?? 0,
            'qty_available' => (string) BigDecimal::of($stock?->qty_on_hand ?? 0)->minus($stock?->qty_reserved ?? 0),
            'reorder_level' => $item->reorder_level, 'is_low_stock' => ($stock?->qty_on_hand ?? 0) <= $item->reorder_level];
    }
}
