<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarmentOptionGroup extends Model
{
    protected $fillable = [
        'garment_category_id',
        'name',
        'slug',
        'description',
        'input_type',
        'is_required',
        'minimum_selections',
        'maximum_selections',
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

    public function options(): HasMany
    {
        return $this->hasMany(GarmentOption::class);
    }
}
