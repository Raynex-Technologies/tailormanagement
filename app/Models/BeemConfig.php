<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeemConfig extends Model
{
    protected $fillable = [
        'api_key',
        'secret_key',
        'sender_name',
        'sms_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sms_enabled' => 'boolean',
        ];
    }

    /**
     * Get the singleton config instance (first row).
     */
    public static function instance(): self
    {
        $config = self::first();
        if (! $config) {
            $config = self::create(['sms_enabled' => false]);
        }

        return $config;
    }
}
