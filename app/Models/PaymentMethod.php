<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_number',
        'account_holder_name',
    ];

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];

        if (filled($this->account_number)) {
            $parts[] = $this->account_number;
        }

        if (filled($this->account_holder_name)) {
            $parts[] = $this->account_holder_name;
        }

        return implode(' - ', $parts);
    }
}
