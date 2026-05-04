<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementField extends Model
{
    protected $fillable = [
        'garment_category_id',
        'name',
        'slug',
        'unit',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function garmentCategory(): BelongsTo
    {
        return $this->belongsTo(GarmentCategory::class);
    }
}
