<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorefrontBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'link_text',
        'link_url',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeVisible($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($inner) {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($inner) {
                $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order');
    }
}
