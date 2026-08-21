<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPackageTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_package_template_id',
        'order_catalog_item_id',
        'inventory_item_id',
        'default_quantity',
        'minimum_quantity',
        'maximum_quantity',
        'package_unit_price',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(fn (OrderPackageTemplateItem $item) => $item->assertValidConfiguration());
    }

    protected function casts(): array
    {
        return [
            'order_catalog_item_id' => 'integer',
            'inventory_item_id' => 'integer',
            'default_quantity' => 'decimal:2',
            'minimum_quantity' => 'decimal:2',
            'maximum_quantity' => 'decimal:2',
            'package_unit_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OrderPackageTemplate::class, 'order_package_template_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(OrderCatalogItem::class, 'order_catalog_item_id');
    }

    public function inventoryItem(): BelongsTo
    {
        // Historical package components must remain resolvable even when the
        // active branch differs. Administration validates access on selection.
        return $this->belongsTo(InventoryItem::class)->withoutGlobalScopes();
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function isRequired(): bool
    {
        return BigDecimal::of((string) $this->minimum_quantity)->isGreaterThan(0);
    }

    public function sourceType(): string
    {
        return $this->order_catalog_item_id !== null ? 'catalog_item' : 'inventory_item';
    }

    public function source(): OrderCatalogItem|InventoryItem
    {
        $source = $this->order_catalog_item_id !== null
            ? $this->catalogItem
            : $this->inventoryItem;

        if (! $source) {
            throw new DomainException('Package component source is unavailable.');
        }

        return $source;
    }

    public function standardUnitPrice(): string
    {
        $source = $this->source();

        return $source instanceof OrderCatalogItem
            ? (string) $source->default_selling_price
            : (string) ($source->default_sell_price ?? '0.00');
    }

    public function assertValidConfiguration(): void
    {
        $hasCatalogItem = filled($this->order_catalog_item_id);
        $hasInventoryItem = filled($this->inventory_item_id);

        if ($hasCatalogItem === $hasInventoryItem) {
            throw new DomainException('A package component must reference exactly one catalog item or inventory item.');
        }

        $minimum = BigDecimal::of((string) ($this->minimum_quantity ?? '0'));
        $default = BigDecimal::of((string) ($this->default_quantity ?? '0'));
        $maximum = $this->maximum_quantity === null
            ? null
            : BigDecimal::of((string) $this->maximum_quantity);
        $price = BigDecimal::of((string) ($this->package_unit_price ?? '0'));

        if ($minimum->isNegative() || $default->isNegative() || $price->isNegative()) {
            throw new DomainException('Package quantities and prices cannot be negative.');
        }

        if ($default->isLessThan($minimum)) {
            throw new DomainException('Default quantity cannot be less than minimum quantity.');
        }

        if ($maximum !== null && ($maximum->isNegative() || $default->isGreaterThan($maximum))) {
            throw new DomainException('Maximum quantity must be greater than or equal to default quantity.');
        }
    }
}
