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
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // If already starts with +255, return as is
        if (str_starts_with($phone, '+255')) {
            return $phone;
        }

        // If starts with 255, add +
        if (str_starts_with($phone, '255')) {
            return '+' . $phone;
        }

        // If starts with 0, replace with +255
        if (str_starts_with($phone, '0')) {
            return '+255' . substr($phone, 1);
        }

        // If starts with 7 or 6 (Tanzania mobile), add +255
        if (preg_match('/^[67]/', $phone)) {
            return '+255' . $phone;
        }

        // Return original with + if it's a valid length
        if (strlen($phone) >= 9 && strlen($phone) <= 15) {
            return '+' . $phone;
        }

        return null;
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
            $local = '0' . substr($normalized, 4);
            return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
        }

        return $phone;
    }
}
