<?php

namespace App\Services\Storefront\Shipping;

interface ShippingProviderInterface
{
    /**
     * @param  array<string, mixed>  $destination
     * @param  array<int, array<string, mixed>>  $cartLines
     * @return array<int, array<string, mixed>>
     */
    public function quotes(array $destination, array $cartLines, string $currency): array;
}
