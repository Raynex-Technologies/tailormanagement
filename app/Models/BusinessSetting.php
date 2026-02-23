<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'phone',
        'alternate_phone',
        'email',
        'tin_number',
        'address',
        'logo_path',
        'email_from_name',
        'email_from_address',
        'email_reply_to',
        'tax_enabled',
        'tax_name',
        'tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
        ];
    }

    /**
     * Singleton settings row.
     */
    public static function instance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'business_name' => config('app.name', 'Tailoring Business'),
                'tax_enabled' => false,
                'tax_name' => 'VAT',
                'tax_rate' => 0,
            ]
        );
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        $url = Storage::disk('public')->url($this->logo_path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
