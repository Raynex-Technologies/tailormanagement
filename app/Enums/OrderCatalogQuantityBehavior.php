<?php

namespace App\Enums;

enum OrderCatalogQuantityBehavior: string
{
    case Individual = 'individual';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Bulk => 'Bulk',
        };
    }

    public static function defaultFor(OrderCatalogItemType $type): self
    {
        return match ($type) {
            OrderCatalogItemType::Garment => self::Individual,
            OrderCatalogItemType::Service => self::Bulk,
        };
    }
}
