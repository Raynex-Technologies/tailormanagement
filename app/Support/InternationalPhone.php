<?php

namespace App\Support;

use Giggsey\Locale\Locale;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class InternationalPhone
{
    public static function fromInput(array $input, string $field = 'phone'): mixed
    {
        if (! array_key_exists($field.'_national_number', $input) && ! array_key_exists($field.'_country_code', $input)) {
            return $input[$field] ?? null;
        }
        $number = $input[$field.'_national_number'] ?? '';
        $code = $input[$field.'_country_code'] ?? '+255';
        if ($number === '' || $number === null) {
            return null;
        }
        if (! is_string($number) || ! preg_match('/^[0-9]{1,15}$/D', $number)
            || ! is_string($code) || ! in_array($code, array_column(self::countries(), 'code'), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => __('Choose a country code and enter a phone number containing digits only.')]);
        }

        return $code.$number;
    }

    public static function parts(?string $phone): ?array
    {
        $value = trim((string) $phone);
        if ($value === '' || strlen($value) > 50 || ! preg_match('/^\+?[0-9 ()-]+$/D', $value)) {
            return null;
        }
        $value = preg_replace('/[ ()-]/', '', $value);
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        } elseif (str_starts_with($value, '255')) {
            $value = '+'.$value;
        }
        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse($value, 'TZ');
            if (! $util->isValidNumber($number)) {
                return null;
            }

            return ['e164' => $util->format($number, PhoneNumberFormat::E164),
                'country_code' => '+'.$number->getCountryCode(),
                'national_number' => $util->getNationalSignificantNumber($number)];
        } catch (NumberParseException) {
            return null;
        }
    }

    public static function countries(): array
    {
        static $options;
        if ($options !== null) {
            return $options;
        }
        $names = Locale::getAllCountriesForLocale('en');
        $util = PhoneNumberUtil::getInstance();
        $options = [];
        foreach ($util->getSupportedRegions() as $region) {
            $options[] = ['region' => $region, 'code' => '+'.$util->getCountryCodeForRegion($region), 'name' => $names[$region] ?? $region];
        }
        usort($options, fn ($a, $b) => $a['region'] === 'TZ' ? -1 : ($b['region'] === 'TZ' ? 1 : strcmp($a['name'], $b['name'])));

        return $options;
    }

    public static function storageFields(): array
    {
        return ['customers' => ['phone', 'whatsapp_phone'], 'customer_addresses' => ['phone'],
            'suppliers' => ['phone'], 'branches' => ['phone'],
            'business_settings' => ['phone', 'alternate_phone', 'storefront_contact_phone'],
            'online_bookings' => ['customer_phone', 'customer_whatsapp'],
            'appointments' => ['customer_phone'], 'orders' => ['checkout_phone'],
            'delivery_notes' => ['received_by_phone'], 'whatsapp_integrations' => ['twilio_from']];
    }

    /** One option per calling code so shared codes do not select an arbitrary country. */
    public static function callingCodes(): array
    {
        static $options;
        if ($options !== null) {
            return $options;
        }
        $grouped = [];
        foreach (self::countries() as $country) {
            $grouped[$country['code']][] = $country['name'];
        }
        $options = [];
        foreach ($grouped as $code => $names) {
            $options[] = ['code' => $code, 'name' => implode(' / ', $names)];
        }

        return $options;
    }
}
