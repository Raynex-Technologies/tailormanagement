<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'red',
            self::Partial => 'amber',
            self::Paid => 'green',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
