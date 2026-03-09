<?php

namespace App\Enums;

enum InstallmentPlanStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Defaulted => 'Defaulted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Completed => 'green',
            self::Defaulted => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
