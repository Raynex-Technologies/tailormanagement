<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'phone',
        'email',
        'address',
        'dob',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->code)) {
                $customer->code = DocNumber::customer();
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }
}
