<?php

namespace App\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
    case ConvertedToPo = 'converted_to_po';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::ConvertedToPo => 'Converted to PO',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Submitted => 'blue',
            self::Approved => 'green',
            self::Declined => 'red',
            self::ConvertedToPo => 'purple',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
