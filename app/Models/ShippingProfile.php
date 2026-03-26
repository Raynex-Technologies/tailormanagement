<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShippingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'handling_fee',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'handling_fee' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'shipping_profile_items')
            ->withTimestamps();
    }
}
