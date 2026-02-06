<?php

use App\Support\Phone;

if (! function_exists('money_tzs')) {
    /**
     * Format a number as Tanzanian Shillings.
     *
     * @param  float|int|string|null  $amount
     * @param  bool  $showPrefix  Whether to show "Tsh" prefix
     * @return string
     */
    function money_tzs(float|int|string|null $amount, bool $showPrefix = true): string
    {
        if ($amount === null) {
            $amount = 0;
        }

        $formatted = number_format((float) $amount, 0, '.', ',');

        return $showPrefix ? "Tsh {$formatted}" : $formatted;
    }
}

if (! function_exists('phone_e164')) {
    /**
     * Convert phone number to E.164 format for Tanzania.
     */
    function phone_e164(?string $phone): ?string
    {
        return Phone::toE164Tz($phone);
    }
}

if (! function_exists('phone_display')) {
    /**
     * Format phone number for display.
     */
    function phone_display(?string $phone): ?string
    {
        return Phone::formatDisplay($phone);
    }
}
