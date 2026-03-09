<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum InstallmentFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
            self::Monthly => 'Monthly',
        };
    }

    public function addTo(CarbonImmutable $date, int $steps = 1): CarbonImmutable
    {
        return match ($this) {
            self::Weekly => $date->addWeeks($steps),
            self::Biweekly => $date->addWeeks($steps * 2),
            self::Monthly => $date->addMonthsNoOverflow($steps),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
