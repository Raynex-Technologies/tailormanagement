<?php

namespace App\Enums;

enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Normal => 'blue',
            self::High => 'amber',
            self::Urgent => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
