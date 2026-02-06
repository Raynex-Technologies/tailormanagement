<?php

namespace App\Enums;

enum CapitalAllocationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'green',
            self::Closed => 'zinc',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
