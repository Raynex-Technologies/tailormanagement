<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'inventory_item_id',
        'inventory_stock_unit_id',
        'variation_description',
        'inventory_item_variant_id',
        'order_catalog_item_id',
        'order_package_instance_id',
        'order_package_template_item_id',
        'assigned_tailor_id',
        'sku',
        'item_name',
        'qty',
        'unit_price',
        'line_total',
        'notes',
        'meta',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if ($line->exists && $line->isDirty('inventory_item_id') && ! $line->isDirty('inventory_stock_unit_id')) {
                $line->inventory_stock_unit_id = null;
            }
            if ((! $line->exists || $line->isDirty('inventory_item_id')) && $line->inventory_item_id && ! $line->inventory_stock_unit_id) {
                $item = InventoryItem::withoutBranchScope()->findOrFail($line->inventory_item_id);
                if ($item->variant_mode === 'simple') {
                    $line->inventory_stock_unit_id = app(\App\Services\Inventory\StockUnitResolver::class)->forItem($item)->id;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'inventory_item_id' => 'integer',
            'inventory_item_variant_id' => 'integer',
            'order_catalog_item_id' => 'integer',
            'order_package_instance_id' => 'integer',
            'order_package_template_item_id' => 'integer',
            'assigned_tailor_id' => 'integer',
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function measurement(): HasOne
    {
        return $this->hasOne(OrderMeasurement::class)->latestOfMany();
    }

    public function assignedTailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_tailor_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(InventoryItemVariant::class, 'inventory_item_variant_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(OrderCatalogItem::class, 'order_catalog_item_id');
    }

    public function packageInstance(): BelongsTo
    {
        return $this->belongsTo(OrderPackageInstance::class, 'order_package_instance_id');
    }

    public function sourcePackageComponent(): BelongsTo
    {
        return $this->belongsTo(OrderPackageTemplateItem::class, 'order_package_template_item_id');
    }

    public function stockUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryStockUnit::class, 'inventory_stock_unit_id');
    }
}
