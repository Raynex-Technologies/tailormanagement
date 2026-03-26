<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\InventoryItem;
use App\Models\InventoryItemVariant;
use App\Models\StorefrontProductCombo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CartService
{
    public function cartCookieName(): string
    {
        return config('storefront.cart.cookie', 'tailorpro_cart');
    }

    public function guestTokenFromRequest(Request $request): ?string
    {
        $value = $request->cookie($this->cartCookieName());

        return filled($value) ? (string) $value : null;
    }

    public function ensureGuestToken(?string $token = null): string
    {
        if (filled($token)) {
            return (string) $token;
        }

        return Str::random(40);
    }

    public function queueGuestTokenCookie(string $token): void
    {
        $minutes = (int) config('storefront.cart.guest_lifetime_minutes', 60 * 24 * 14);
        $secure = (bool) (config('session.secure') ?? app()->isProduction());
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');
        $sameSite = (string) (config('session.same_site') ?: 'lax');

        cookie()->queue(cookie(
            $this->cartCookieName(),
            $token,
            $minutes,
            $path,
            $domain,
            $secure,
            true,
            false,
            $sameSite
        ));
    }

    public function clearGuestTokenCookie(): void
    {
        cookie()->queue(cookie()->forget($this->cartCookieName()));
    }

    public function resolveCart(?User $user, ?string $guestToken, string $currency): Cart
    {
        if ($user) {
            $userCart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => Str::random(40),
                    'currency' => $currency,
                    'last_activity_at' => now(),
                    'expires_at' => null,
                ]
            );

            if ($guestToken) {
                $guestCart = Cart::query()->where('token', $guestToken)->whereNull('user_id')->first();
                if ($guestCart && $guestCart->id !== $userCart->id) {
                    $this->merge($guestCart, $userCart);
                    $guestCart->delete();
                }
            }

            $userCart->update([
                'currency' => $currency,
                'last_activity_at' => now(),
            ]);

            return $userCart->load('items.item.stock', 'items.variant');
        }

        $token = $this->ensureGuestToken($guestToken);

        $guestCart = Cart::query()->firstOrCreate(
            ['token' => $token],
            [
                'user_id' => null,
                'currency' => $currency,
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes((int) config('storefront.cart.guest_lifetime_minutes', 60 * 24 * 14)),
            ]
        );

        $guestCart->update([
            'currency' => $currency,
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes((int) config('storefront.cart.guest_lifetime_minutes', 60 * 24 * 14)),
        ]);

        return $guestCart->load('items.item.stock', 'items.variant');
    }

    public function addItem(Cart $cart, InventoryItem $item, ?InventoryItemVariant $variant, float $quantity): CartItem
    {
        $quantity = max(1, $quantity);

        $lineKey = $this->lineKey($item->id, $variant?->id);

        $unitPrice = (float) $item->default_sell_price + (float) ($variant?->price_delta ?? 0);
        $lineTotal = $unitPrice * $quantity;

        return CartItem::query()->updateOrCreate(
            [
                'cart_id' => $cart->id,
                'line_key' => $lineKey,
            ],
            [
                'inventory_item_id' => $item->id,
                'inventory_item_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'compare_at_price' => $item->compare_at_price,
                'discount_total' => 0,
                'line_total' => $lineTotal,
                'meta' => [
                    'item_name' => $item->name,
                    'variant_name' => $variant?->name,
                ],
            ]
        );
    }

    public function addCombo(Cart $cart, StorefrontProductCombo $combo, float $quantity): void
    {
        $quantity = max(1, $quantity);

        $combo->loadMissing('items.product.stock');

        $components = $combo->items
            ->filter(fn ($comboItem) => $comboItem->product && (float) $comboItem->quantity > 0)
            ->values();

        if ($components->isEmpty()) {
            throw new InvalidArgumentException('Combo has no available products.');
        }

        $comboPrice = round((float) $combo->price, 2);
        $componentsTotal = (float) $components->sum(
            fn ($comboItem) => (float) $comboItem->product->default_sell_price * (float) $comboItem->quantity
        );
        $allocatedPerCombo = 0.0;

        foreach ($components as $index => $comboItem) {
            $product = $comboItem->product;
            $qtyPerCombo = (float) $comboItem->quantity;
            $lineKey = $this->comboLineKey($combo->id, $product->id);

            if ($componentsTotal > 0) {
                $isLast = $index === $components->count() - 1;
                if ($isLast) {
                    $linePricePerCombo = round($comboPrice - $allocatedPerCombo, 2);
                } else {
                    $linePricePerCombo = round(
                        $comboPrice * (((float) $product->default_sell_price * $qtyPerCombo) / $componentsTotal),
                        2
                    );
                    $allocatedPerCombo += $linePricePerCombo;
                }
            } else {
                $isLast = $index === $components->count() - 1;
                if ($isLast) {
                    $linePricePerCombo = round($comboPrice - $allocatedPerCombo, 2);
                } else {
                    $linePricePerCombo = round($comboPrice / max(1, $components->count()), 2);
                    $allocatedPerCombo += $linePricePerCombo;
                }
            }

            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('line_key', $lineKey)
                ->first();

            $newQuantity = (float) ($existing?->quantity ?? 0) + ($qtyPerCombo * $quantity);
            $unitPrice = $qtyPerCombo > 0 ? round($linePricePerCombo / $qtyPerCombo, 2) : 0;
            $lineTotal = round($unitPrice * $newQuantity, 2);
            $existingMeta = (array) ($existing?->meta ?? []);

            CartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'line_key' => $lineKey,
                ],
                [
                    'inventory_item_id' => $product->id,
                    'inventory_item_variant_id' => null,
                    'quantity' => $newQuantity,
                    'unit_price' => $unitPrice,
                    'compare_at_price' => $product->compare_at_price,
                    'discount_total' => 0,
                    'line_total' => $lineTotal,
                    'meta' => array_merge($existingMeta, [
                        'item_name' => $product->name,
                        'variant_name' => null,
                        'combo' => [
                            'id' => $combo->id,
                            'name' => $combo->name,
                            'quantity_per_combo' => $qtyPerCombo,
                            'unit_price_share' => $unitPrice,
                            'lock_price' => true,
                        ],
                    ]),
                ]
            );
        }
    }

    public function updateQuantity(CartItem $item, float $quantity): void
    {
        $qty = max(1, $quantity);

        $item->update([
            'quantity' => $qty,
            'line_total' => (float) $item->unit_price * $qty,
        ]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function refreshPricing(Cart $cart): void
    {
        $cart->load('items.item', 'items.variant');

        foreach ($cart->items as $line) {
            if (! $line->item) {
                $line->delete();

                continue;
            }

            $meta = (array) ($line->meta ?? []);
            $comboPriceLocked = (bool) data_get($meta, 'combo.lock_price', false);

            if ($comboPriceLocked) {
                $line->update([
                    'compare_at_price' => $line->item->compare_at_price,
                    'line_total' => round((float) $line->unit_price * (float) $line->quantity, 2),
                    'meta' => array_merge($meta, [
                        'item_name' => $line->item->name,
                        'variant_name' => $line->variant?->name,
                    ]),
                ]);

                continue;
            }

            $unitPrice = (float) $line->item->default_sell_price + (float) ($line->variant?->price_delta ?? 0);

            $line->update([
                'unit_price' => $unitPrice,
                'compare_at_price' => $line->item->compare_at_price,
                'line_total' => $unitPrice * (float) $line->quantity,
                'meta' => array_merge($meta, [
                    'item_name' => $line->item->name,
                    'variant_name' => $line->variant?->name,
                ]),
            ]);
        }
    }

    public function merge(Cart $from, Cart $to): void
    {
        $from->load('items');

        foreach ($from->items as $line) {
            $existing = CartItem::query()
                ->where('cart_id', $to->id)
                ->where('line_key', $line->line_key)
                ->first();

            if ($existing) {
                $newQty = (float) $existing->quantity + (float) $line->quantity;
                $existing->update([
                    'quantity' => $newQty,
                    'line_total' => (float) $existing->unit_price * $newQty,
                ]);
            } else {
                $line->cart_id = $to->id;
                $line->save();
            }
        }
    }

    public function summary(Cart $cart): array
    {
        $cart->loadMissing('items');

        $subtotal = (float) $cart->items->sum('line_total');
        $discount = (float) $cart->items->sum('discount_total');
        $itemsCount = (int) $cart->items->sum('quantity');

        return [
            'items_count' => $itemsCount,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
        ];
    }

    protected function lineKey(int $itemId, ?int $variantId): string
    {
        return $itemId.':'.($variantId ?: 'base');
    }

    public function comboLineKey(int $comboId, int $itemId, ?int $variantId = null): string
    {
        return 'combo:'.$comboId.':'.$itemId.':'.($variantId ?: 'base');
    }
}
