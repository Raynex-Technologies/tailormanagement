<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'profile_name',
        'gender_scope',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }
}
