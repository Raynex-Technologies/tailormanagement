<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineBookingImage extends Model
{
    protected $fillable = [
        'online_booking_id',
        'online_booking_item_id',
        'uploaded_by_type',
        'path',
        'disk',
        'original_name',
        'mime_type',
        'size',
        'caption',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(OnlineBooking::class, 'online_booking_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingItem::class, 'online_booking_item_id');
    }

    public function getUrlAttribute(): ?string
    {
        return app(ImageUploadService::class)->publicUrl($this->path);
    }
}
