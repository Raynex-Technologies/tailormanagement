<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'price',
        'duration_value',
        'duration_unit',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackageItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    public function durationLabel(): string
    {
        $unit = str($this->duration_unit)->singular()->toString();

        if ($this->duration_value !== 1) {
            $unit = str($unit)->plural()->toString();
        }

        return "{$this->duration_value} {$unit}";
    }
}
