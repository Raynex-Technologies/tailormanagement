<?php

namespace App\Support;

final class MoneyInputNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(
            [',', ' ', "\u{00A0}", "\u{202F}"],
            '',
            trim($value),
        );
    }
}
