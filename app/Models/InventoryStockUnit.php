<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class InventoryStockUnit extends Model
{
    protected $fillable = ['inventory_item_id', 'inventory_item_variant_id', 'sku', 'selling_price', 'reference_cost', 'is_active', 'allocation_status'];

    protected function casts(): array
    {
        return ['selling_price' => 'decimal:2', 'reference_cost' => 'decimal:2', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $unit) {
            if ($unit->exists && $unit->isDirty(['inventory_item_id', 'inventory_item_variant_id'])) {
                throw ValidationException::withMessages(['stock_unit' => 'Stock identity cannot be reassigned. Retire it instead.']);
            }
            $item = InventoryItem::withoutBranchScope()->findOrFail($unit->inventory_item_id);
            if ($unit->inventory_item_variant_id) {
                InventoryItemVariant::where('inventory_item_id', $item->id)->whereKey($unit->inventory_item_variant_id)->firstOrFail();
            } elseif ($item->variant_mode !== 'simple') {
                throw ValidationException::withMessages(['stock_unit' => 'Variant products cannot have a simple stock unit.']);
            }
            if (trim((string) $unit->sku) === '' || strlen($unit->sku) > 100) {
                throw ValidationException::withMessages(['sku' => 'A stock unit needs an exact nonempty SKU of at most 100 characters.']);
            }
            if (self::whereRaw('LOWER(sku) = ?', [mb_strtolower($unit->sku)])->when($unit->exists, fn ($q) => $q->where('id', '!=', $unit->id))->exists()) {
                throw ValidationException::withMessages(['sku' => 'SKU already identifies another stock unit.']);
            }
            foreach (['selling_price', 'reference_cost'] as $field) {
                if ($unit->$field !== null && \Brick\Math\BigDecimal::of($unit->$field)->isNegative()) {
                    throw ValidationException::withMessages([$field => 'Value cannot be negative.']);
                }
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['stock_unit' => 'Retire stock identities; historical provenance must be preserved.']));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(InventoryItemVariant::class, 'inventory_item_variant_id');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(InventoryStockUnitBarcode::class);
    }

    public function primaryBarcode(): HasOne
    {
        return $this->hasOne(InventoryStockUnitBarcode::class)->where('is_primary', true);
    }
}
