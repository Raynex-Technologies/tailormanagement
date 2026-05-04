<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineBookingItemOption extends Model
{
    protected $fillable = [
        'online_booking_item_id',
        'garment_option_group_id',
        'garment_option_id',
        'custom_value',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingItem::class, 'online_booking_item_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GarmentOptionGroup::class, 'garment_option_group_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(GarmentOption::class, 'garment_option_id');
    }
}
