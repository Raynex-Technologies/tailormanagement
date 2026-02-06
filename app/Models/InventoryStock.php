<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'inventory_item_id',
        'qty_on_hand',
        'qty_reserved',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:2',
            'qty_reserved' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get available quantity (on_hand - reserved).
     */
    public function getAvailableAttribute(): float
    {
        return $this->qty_on_hand - $this->qty_reserved;
    }
}
