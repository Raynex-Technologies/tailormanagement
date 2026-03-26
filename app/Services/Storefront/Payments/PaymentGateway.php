<?php

namespace App\Services\Storefront\Payments;

use App\Models\PaymentTransaction;

interface PaymentGateway
{
    public function code(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function initiate(PaymentTransaction $transaction, array $context): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function verify(PaymentTransaction $transaction, array $payload = []): array;
}
