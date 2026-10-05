<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Support\DocNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use \App\Models\Concerns\StoresPhoneNumbers;
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
                $parts = \App\Support\InternationalPhone::parts($customer->whatsapp_phone);
                $customer->whatsapp_phone_country_code = $parts['country_code'] ?? null;
                $customer->whatsapp_phone_national_number = $parts['national_number'] ?? null;
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeWherePhoneNumber(\Illuminate\Database\Eloquent\Builder $query, string $phone): \Illuminate\Database\Eloquent\Builder
    {
        $parts = \App\Support\InternationalPhone::parts($phone);
        if (! $parts) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn ($query) => $query->where('phone', $parts['e164'])
            ->orWhere(fn ($query) => $query->where('phone_country_code', $parts['country_code'])->where('phone_national_number', $parts['national_number'])));
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

    public function measurementProfiles(): HasMany
    {
        return $this->hasMany(MeasurementProfile::class);
    }

    public function currentMeasurementProfile(): HasOne
    {
        return $this->hasOne(MeasurementProfile::class)
            ->where('profile_name', 'Default')
            ->where('is_current', true);
    }
}
