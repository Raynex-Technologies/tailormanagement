<?php

namespace App\Services\WhatsApp;

use App\Support\Phone;

class WhatsAppPhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        $normalized = Phone::toE164Tz($phone);
        if (! $normalized || preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            return null;
        }

return $normalized;
    }

    public function metaRecipient(?string $phone): ?string
    {
        $normalized = $this->normalize($phone);

        return $normalized ? ltrim($normalized, '+') : null;
    }
}
