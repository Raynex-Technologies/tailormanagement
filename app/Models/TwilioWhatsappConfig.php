<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwilioWhatsappConfig extends Model
{
    protected $fillable = [
        'whatsapp_enabled',
        'account_sid',
        'auth_token',
        'from_number',
        'messaging_service_sid',
        'content_template_language',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
        ];
    }

    public static function instance(): self
    {
        $config = self::first();

        if (! $config) {
            $config = self::create([
                'whatsapp_enabled' => false,
                'content_template_language' => 'en',
            ]);
        }

        return $config;
    }
}
