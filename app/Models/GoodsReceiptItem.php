<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'inventory_item_id',
        'inventory_stock_unit_id',
        'inventory_item_variant_id',
        'variation_description',
        'sku',
        'item_name',
        'qty_received',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryStockUnit::class, 'inventory_stock_unit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
