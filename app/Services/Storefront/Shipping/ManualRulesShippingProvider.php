<?php

namespace App\Services\Storefront\Shipping;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ManualRulesShippingProvider implements ShippingProviderInterface
{
    public function quotes(array $destination, array $cartLines, string $currency): array
    {
        $totals = $this->totals($cartLines);

        $zones = ShippingZone::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $matchingZoneIds = $zones
            ->filter(fn (ShippingZone $zone) => $this->zoneMatches($zone, $destination))
            ->pluck('id')
            ->all();

        if (empty($matchingZoneIds)) {
            $matchingZoneIds = [null];
        }

        $methods = ShippingMethod::query()
            ->where('is_active', true)
            ->where(function ($query) use ($matchingZoneIds) {
                $query->whereNull('shipping_zone_id')
                    ->orWhereIn('shipping_zone_id', $matchingZoneIds);
            })
            ->orderBy('sort_order')
            ->get();

        $quotes = [];

        foreach ($methods as $method) {
            if (! $this->methodMatchesConstraints($method, $totals)) {
                continue;
            }

            $amount = $this->computeMethodAmount($method, $totals);

            if ($amount < 0) {
                continue;
            }

            $quotes[] = [
                'code' => $method->code,
                'name' => $method->name,
                'type' => $method->type?->value ?? ShippingMethodType::FlatRate->value,
                'amount' => round($amount, 2),
                'currency' => $method->currency ?: $currency,
                'estimated_delivery_window' => $method->estimated_delivery_window,
                'zone_id' => $method->shipping_zone_id,
            ];
        }

        return $quotes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartLines
     * @return array<string, float>
     */
    protected function totals(array $cartLines): array
    {
        $subtotal = 0.0;
        $weight = 0.0;
        $itemsCount = 0.0;

        foreach ($cartLines as $line) {
            $qty = (float) Arr::get($line, 'quantity', 0);
            $lineTotal = (float) Arr::get($line, 'line_total', 0);
            $lineWeight = (float) Arr::get($line, 'weight', 0);

            $subtotal += $lineTotal;
            $weight += ($lineWeight * $qty);
            $itemsCount += $qty;
        }

        return [
            'subtotal' => $subtotal,
            'weight' => $weight,
            'items_count' => $itemsCount,
        ];
    }

    protected function zoneMatches(ShippingZone $zone, array $destination): bool
    {
        $country = strtoupper((string) Arr::get($destination, 'country', ''));
        $state = strtolower((string) Arr::get($destination, 'state', ''));
        $postalCode = strtolower((string) Arr::get($destination, 'postal_code', ''));

        $countries = collect($zone->countries ?? [])->map(fn ($v) => strtoupper((string) $v))->filter()->all();
        $regions = collect($zone->regions ?? [])->map(fn ($v) => strtolower((string) $v))->filter()->all();
        $postalPatterns = collect($zone->postal_codes ?? [])->map(fn ($v) => strtolower((string) $v))->filter()->all();

        if (! empty($countries) && ! in_array($country, $countries, true)) {
            return false;
        }

        if (! empty($regions) && ! in_array($state, $regions, true)) {
            return false;
        }

        if (! empty($postalPatterns)) {
            $matched = collect($postalPatterns)->contains(fn ($pattern) => Str::is($pattern, $postalCode));
            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, float>  $totals
     */
    protected function methodMatchesConstraints(ShippingMethod $method, array $totals): bool
    {
        $subtotal = $totals['subtotal'];
        $weight = $totals['weight'];
        $itemsCount = $totals['items_count'];

        if ($method->min_subtotal !== null && $subtotal < (float) $method->min_subtotal) {
            return false;
        }

        if ($method->max_subtotal !== null && $subtotal > (float) $method->max_subtotal) {
            return false;
        }

        if ($method->min_weight !== null && $weight < (float) $method->min_weight) {
            return false;
        }

        if ($method->max_weight !== null && $weight > (float) $method->max_weight) {
            return false;
        }

        if ($method->min_items !== null && $itemsCount < (float) $method->min_items) {
            return false;
        }

        if ($method->max_items !== null && $itemsCount > (float) $method->max_items) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, float>  $totals
     */
    protected function computeMethodAmount(ShippingMethod $method, array $totals): float
    {
        $subtotal = $totals['subtotal'];
        $type = $method->type?->value ?? ShippingMethodType::FlatRate->value;

        if ($type === ShippingMethodType::FreeShipping->value) {
            if ($method->free_shipping_threshold !== null && $subtotal < (float) $method->free_shipping_threshold) {
                return -1;
            }

            return 0;
        }

        if ($method->free_shipping_threshold !== null && $subtotal >= (float) $method->free_shipping_threshold) {
            return 0;
        }

        return (float) $method->amount;
    }
}
