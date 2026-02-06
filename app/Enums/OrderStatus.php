<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In Progress',
            self::Ready => 'Ready',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::InProgress => 'amber',
            self::Ready => 'emerald',
            self::Delivered => 'indigo',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
