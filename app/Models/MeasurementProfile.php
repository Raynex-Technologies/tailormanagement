<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class MeasurementProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'lineage_uuid',
        'revision',
        'is_current',
        'current_lineage_key',
        'current_customer_profile_key',
        'profile_name',
        'gender_scope',
        'measured_at',
        'recorded_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'is_current' => 'boolean',
            'measured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MeasurementProfile $profile): void {
            if ($profile->customer_id !== null && blank($profile->lineage_uuid)) {
                throw new LogicException('Customer measurement revisions must be created through the revision service.');
            }

            if ($profile->customer_id === null && blank($profile->lineage_uuid)) {
                $profile->lineage_uuid = (string) Str::uuid();
                $profile->revision = 1;
                $profile->is_current = true;
                $profile->current_lineage_key = $profile->lineage_uuid;
            }
        });

        static::updating(function (): never {
            throw new LogicException('Saved measurement revisions are immutable.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(MeasurementValue::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        return $query->where('customer_id', $customer instanceof Customer ? $customer->id : $customer);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function orderedValues(): Collection
    {
        return $this->values
            ->sortBy(fn (MeasurementValue $value): string => sprintf(
                '%010d|%s|%s',
                $value->field?->sort_order ?? PHP_INT_MAX,
                $value->field_label_snapshot ?? $value->field?->name ?? '',
                $value->field_code_snapshot ?? $value->field?->code ?? '',
            ))
            ->values();
    }
}
