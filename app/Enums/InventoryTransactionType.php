<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Receive = 'receive';
    case Issue = 'issue';
    case Adjust = 'adjust';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Receive',
            self::Issue => 'Issue',
            self::Adjust => 'Adjust',
            self::Return => 'Return',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Receive => 'green',
            self::Issue => 'red',
            self::Adjust => 'amber',
            self::Return => 'blue',
        };
    }

    /**
     * Determines the sign of the transaction for stock calculations.
     * Positive = adds to stock, Negative = removes from stock.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Receive, self::Return => 1,
            self::Issue => -1,
            self::Adjust => 0, // Adjustment can be positive or negative
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
