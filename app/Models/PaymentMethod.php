<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'account_number',
        'account_holder_name',
        'type',
        'is_enabled',
        'is_online',
        'sort_order',
        'description',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_online' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function scopeForInvoiceDocument(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $documentQuery) {
                $documentQuery->where('id', '!=', 1)
                    ->orWhereNotNull('account_number')
                    ->orWhereNotNull('account_holder_name')
                    ->orWhere('name', '!=', 'Default');
            })
            ->orderBy('name');
    }

    public static function forInvoiceDocument(int $limit = 3): Collection
    {
        return static::query()
            ->forInvoiceDocument()
            ->limit($limit)
            ->get();
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

    public function scopeEnabled($query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOnline($query): Builder
    {
        return $query->where('is_online', true)->enabled();
    }
}
