<?php

namespace App\Support;

class Phone
{
    /**
     * Convert a phone number to E.164 format for Tanzania (+255...).
     *
     * @param  string|null  $phone  The phone number to normalize
     * @return string|null The normalized phone number or null if invalid
     */
    public static function toE164Tz(?string $phone): ?string
    {
        return InternationalPhone::parts($phone)['e164'] ?? null;
    }

    /**
     * Check if a phone number is valid for Tanzania.
     */
    public static function isValidTz(?string $phone): bool
    {
        $normalized = self::toE164Tz($phone);

        if (empty($normalized)) {
            return false;
        }

        // Tanzania numbers should be +255 followed by 9 digits
        return preg_match('/^\+255[67]\d{8}$/', $normalized) === 1;
    }

    /**
     * Format for display (local format).
     */
    public static function formatDisplay(?string $phone): ?string
    {
        $normalized = self::toE164Tz($phone);

        if (empty($normalized)) {
            return $phone;
        }

        // Format as 0XXX XXX XXX
        if (str_starts_with($normalized, '+255')) {
            $local = '0'.substr($normalized, 4);

            return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
        }

        return $phone;
    }
}
