<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransaction extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'inventory_item_id',
        'type',
        'qty',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
