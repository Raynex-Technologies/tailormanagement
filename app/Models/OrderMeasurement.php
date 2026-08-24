<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class OrderMeasurement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_line_id',
        'active_order_line_id',
        'measurements',
        'source_measurement_profile_id',
        'source_profile_lineage',
        'source_profile_revision',
        'source_profile_name',
        'source_measured_at',
        'snapshot_format_version',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'source_profile_revision' => 'integer',
            'source_measured_at' => 'datetime',
            'snapshot_format_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrderMeasurement $measurement): void {
            $measurement->active_order_line_id = $measurement->order_line_id;
        });

        static::deleting(function (OrderMeasurement $measurement): void {
            if (! $measurement->isForceDeleting()) {
                DB::table('order_measurements')->where('id', $measurement->id)->update([
                    'active_order_line_id' => null,
                ]);
                $measurement->active_order_line_id = null;
            }
        });

        static::restoring(function (OrderMeasurement $measurement): void {
            $measurement->active_order_line_id = $measurement->order_line_id;
        });
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function sourceProfile(): BelongsTo
    {
        return $this->belongsTo(MeasurementProfile::class, 'source_measurement_profile_id');
    }
}
