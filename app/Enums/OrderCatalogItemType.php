<?php

namespace App\Enums;

enum OrderCatalogItemType: string
{
    case Garment = 'garment';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Garment => 'Garment',
            self::Service => 'Service',
        };
    }
}
