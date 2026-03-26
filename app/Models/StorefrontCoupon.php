<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorefrontCoupon extends Model
{
    use BranchScoped, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_subtotal',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'per_customer_limit',
        'is_active',
        'is_auto_generated',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_customer_limit' => 'integer',
            'is_active' => 'boolean',
            'is_auto_generated' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(StorefrontCouponUsage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isWithinActiveWindow(?CarbonInterface $at = null): bool
    {
        $at = $at ?: now();

        if ($this->starts_at && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $subtotal = max(0, $subtotal);

        if ($this->discount_type === 'percent') {
            $discount = round($subtotal * ((float) $this->discount_value / 100), 2);
        } else {
            $discount = (float) $this->discount_value;
        }

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return max(0, min($discount, $subtotal));
    }
}
