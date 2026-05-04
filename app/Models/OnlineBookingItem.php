<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineBookingItem extends Model
{
    protected $fillable = [
        'online_booking_id',
        'garment_category_id',
        'garment_name',
        'quantity',
        'fabric_source',
        'fabric_type',
        'primary_color',
        'secondary_color',
        'preferred_fit',
        'occasion',
        'style_description',
        'special_instructions',
        'estimated_budget_min',
        'estimated_budget_max',
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget_min' => 'decimal:2',
            'estimated_budget_max' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(OnlineBooking::class, 'online_booking_id');
    }

    public function garmentCategory(): BelongsTo
    {
        return $this->belongsTo(GarmentCategory::class);
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(OnlineBookingItemOption::class);
    }
}
