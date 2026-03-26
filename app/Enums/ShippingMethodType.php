<?php

namespace App\Enums;

enum ShippingMethodType: string
{
    case FlatRate = 'flat_rate';
    case FreeShipping = 'free_shipping';
    case LocalPickup = 'local_pickup';
    case TableRate = 'table_rate';
    case ManualRate = 'manual_rate';
}
