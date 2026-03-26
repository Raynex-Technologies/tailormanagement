<?php

namespace App\Services\Storefront\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        protected PesapalGateway $pesapalGateway,
    ) {
    }

    public function resolve(string $code): PaymentGateway
    {
        $normalized = strtolower(trim($code));

        return match ($normalized) {
            'pesapal' => $this->pesapalGateway,
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$code}]."),
        };
    }
}
