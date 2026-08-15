<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'code',
        'name',
        'phone',
        'whatsapp_phone',
        'whatsapp_opted_in_at',
        'whatsapp_marketing_opted_in_at',
        'email',
        'address',
        'dob',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'whatsapp_opted_in_at' => 'datetime',
            'whatsapp_marketing_opted_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->code)) {
                $customer->code = DocNumber::customer();
            }
        });
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('phone') || blank($customer->whatsapp_phone)) {
                $customer->whatsapp_phone = \App\Support\Phone::toE164Tz($customer->phone);
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
