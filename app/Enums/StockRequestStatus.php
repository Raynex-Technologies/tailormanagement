<?php

namespace App\Enums;

enum StockRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Declined = 'declined';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Fulfilled => 'Fulfilled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'blue',
            self::Approved => 'amber',
            self::Declined => 'red',
            self::Fulfilled => 'green',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Requested => in_array($newStatus, [self::Approved, self::Declined]),
            self::Approved => $newStatus === self::Fulfilled,
            self::Declined => false, // Terminal state
            self::Fulfilled => false, // Terminal state
        };
    }

    /**
     * Check if this status is terminal (no more transitions allowed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Fulfilled]);
    }

    /**
     * Check if request can be reviewed (approved/declined).
     */
    public function canBeReviewed(): bool
    {
        return $this === self::Requested;
    }

    /**
     * Check if request can be fulfilled.
     */
    public function canBeFulfilled(): bool
    {
        return $this === self::Approved;
    }
}
