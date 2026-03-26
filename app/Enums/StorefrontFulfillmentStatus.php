<?php

namespace App\Enums;

enum StorefrontFulfillmentStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case FailedPayment = 'failed_payment';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::ReadyForDispatch => 'Ready for Dispatch',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::FailedPayment => 'Failed Payment',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::AwaitingPayment => 'amber',
            self::Paid => 'green',
            self::Processing => 'blue',
            self::ReadyForDispatch => 'indigo',
            self::Shipped => 'sky',
            self::Delivered => 'emerald',
            self::Cancelled => 'red',
            self::Refunded => 'rose',
            self::FailedPayment => 'red',
        };
    }
}
