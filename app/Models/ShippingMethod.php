<?php

namespace App\Models;

use App\Enums\ShippingMethodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'code',
        'name',
        'type',
        'amount',
        'currency',
        'min_subtotal',
        'max_subtotal',
        'min_weight',
        'max_weight',
        'min_items',
        'max_items',
        'free_shipping_threshold',
        'estimated_delivery_window',
        'settings',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'amount' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'max_subtotal' => 'decimal:2',
            'min_weight' => 'decimal:3',
            'max_weight' => 'decimal:3',
            'free_shipping_threshold' => 'decimal:2',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
