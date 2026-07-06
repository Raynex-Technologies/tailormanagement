<?php

use App\Support\Phone;
use App\Support\SystemUiSettings;

if (! function_exists('money_tzs')) {
    /**
     * Format a number as Tanzanian Shillings.
     *
     * @param  bool  $showPrefix  Whether to show "Tsh" prefix
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

if (! function_exists('money_currency')) {
    /**
     * Format amount with any currency code.
     */
    function money_currency(float|int|string|null $amount, string $currency = 'TZS'): string
    {
        $amount = (float) ($amount ?? 0);

        return strtoupper($currency).' '.number_format($amount, 2);
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

if (! function_exists('app_ui_color')) {
    function app_ui_color(string $key): string
    {
        return SystemUiSettings::color($key);
    }
}

if (! function_exists('module_enabled')) {
    function module_enabled(string $module): bool
    {
        return (bool) config("modules.{$module}.enabled", true);
    }
}
