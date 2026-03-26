<?php

namespace App\Enums;

enum PaymentTransactionPurpose: string
{
    case StorefrontOrder = 'storefront_order';
    case TailoringDeposit = 'tailoring_deposit';
    case TailoringBalance = 'tailoring_balance';
}
