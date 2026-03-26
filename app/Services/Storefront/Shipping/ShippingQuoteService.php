<?php

namespace App\Services\Storefront\Shipping;

use App\Models\Cart;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ShippingQuoteService
{
    public function __construct(
        protected ManualRulesShippingProvider $provider,
    ) {
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return array<int, array<string, mixed>>
     */
    public function quotesForCart(Cart $cart, array $destination, string $currency): array
    {
        $cart->loadMissing('items.item', 'items.variant');

        $linePayload = $cart->items->map(function ($line) {
            return [
                'quantity' => (float) $line->quantity,
                'line_total' => (float) $line->line_total,
                'weight' => (float) ($line->item?->weight ?? 0),
            ];
        })->all();

        $cacheKey = $this->cacheKey($cart->id, $destination, $linePayload, $currency);
        $ttl = now()->addMinutes((int) config('storefront.shipping.quote_cache_minutes', 10));

        return Cache::remember($cacheKey, $ttl, function () use ($destination, $linePayload, $currency) {
            return $this->provider->quotes($destination, $linePayload, $currency);
        });
    }

    protected function cacheKey(int $cartId, array $destination, array $linePayload, string $currency): string
    {
        $dest = [
            'country' => Arr::get($destination, 'country'),
            'state' => Arr::get($destination, 'state'),
            'postal_code' => Arr::get($destination, 'postal_code'),
        ];

        return 'storefront:shipping:'.$cartId.':'.sha1(json_encode([
            'destination' => $dest,
            'lines' => $linePayload,
            'currency' => $currency,
        ]));
    }
}
