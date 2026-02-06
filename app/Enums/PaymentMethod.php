<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::MobileMoney => 'Mobile Money',
            self::Bank => 'Bank Transfer',
            self::Card => 'Card',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'green',
            self::MobileMoney => 'blue',
            self::Bank => 'purple',
            self::Card => 'amber',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
