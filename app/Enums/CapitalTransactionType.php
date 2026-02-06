<?php

namespace App\Enums;

enum CapitalTransactionType: string
{
    case Debit = 'debit';
    case Credit = 'credit';
    case Adjustment = 'adjustment';
    case Closing = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Debit',
            self::Credit => 'Credit',
            self::Adjustment => 'Adjustment',
            self::Closing => 'Closing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Debit => 'red',
            self::Credit => 'green',
            self::Adjustment => 'amber',
            self::Closing => 'zinc',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
