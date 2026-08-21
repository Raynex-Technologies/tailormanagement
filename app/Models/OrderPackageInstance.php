<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPackageInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_package_template_id',
        'source_template_revision',
        'package_name',
        'package_description',
        'cover_image_path',
        'original_package_total',
        'configured_package_total',
        'component_snapshot',
        'original_component_snapshot',
        'configured_component_snapshot',
        'configured_by',
    ];

    protected function casts(): array
    {
        return [
            'source_template_revision' => 'integer',
            'original_package_total' => 'decimal:2',
            'configured_package_total' => 'decimal:2',
            'component_snapshot' => 'array',
            'original_component_snapshot' => 'array',
            'configured_component_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(OrderPackageTemplate::class, 'order_package_template_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}
