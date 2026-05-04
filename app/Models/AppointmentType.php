<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'default_duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'requires_approval',
        'is_public',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function availabilityWindows(): HasMany
    {
        return $this->hasMany(OfficeAvailabilityWindow::class);
    }
}
