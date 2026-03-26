<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Verified,
            self::Failed,
            self::Cancelled,
            self::Refunded,
        ], true);
    }
}
