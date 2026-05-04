<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'amber',
            self::Sent => 'green',
            self::Failed => 'red',
            self::Skipped => 'zinc',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
