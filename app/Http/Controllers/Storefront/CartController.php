<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddToCartRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CmsPage;
use App\Models\InventoryItem;
use App\Models\InventoryItemVariant;
use App\Models\StorefrontProductCombo;
use App\Services\Storefront\CartService;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected StorefrontContext $storefrontContext,
    ) {
    }

    public function index(Request $request)
    {
        $guestToken = $this->cartService->guestTokenFromRequest($request);
        $cart = $this->cartService->resolveCart($request->user(), $guestToken, $this->storefrontContext->currency());

        if (! $request->user()) {
            $this->cartService->queueGuestTokenCookie($cart->token);
        }

        $destination = [
            'country' => old('shipping_country', 'US'),
            'state' => old('shipping_state', ''),
            'postal_code' => old('shipping_postal_code', ''),
        ];

        return view('storefront.cart.index', [
            'settings' => $this->storefrontContext->settings(),
            'cart' => $cart,
            'summary' => $this->cartService->summary($cart),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
            'destination' => $destination,
        ]);
    }

    public function add(AddToCartRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $quantity = (float) $payload['quantity'];

        $guestToken = $this->cartService->guestTokenFromRequest($request);
        $cart = $this->cartService->resolveCart($request->user(), $guestToken, $this->storefrontContext->currency());

        if (! empty($payload['combo_id'])) {
            $combo = StorefrontProductCombo::query()
                ->where('is_active', true)
                ->where('storefront_is_visible', true)
                ->with([
                    'items' => fn ($builder) => $builder->with([
                        'product' => fn ($productQuery) => $productQuery->storefrontVisible()->with('stock'),
                    ]),
                ])
                ->findOrFail($payload['combo_id']);

            $comboItems = $combo->items
                ->filter(fn ($comboItem) => $comboItem->product && (float) $comboItem->quantity > 0)
                ->values();

            if ($comboItems->isEmpty()) {
                return back()->with('error', 'This combo is currently unavailable.');
            }

            foreach ($comboItems as $comboItem) {
                $product = $comboItem->product;
                $requiredQty = (float) $comboItem->quantity * $quantity;

                if (! $product->allow_backorders && $product->track_stock) {
                    $maxAllowed = $this->availableQuantity($product, null);
                    $projectedQty = $this->projectedCartQuantity($cart, $product->id, null) + $requiredQty;

                    if ($projectedQty > $maxAllowed) {
                        return back()->with(
                            'error',
                            "Only {$maxAllowed} item(s) of {$product->name} are currently available for this combo."
                        );
                    }
                }
            }

            $this->cartService->addCombo($cart, $combo, $quantity);

            if (! $request->user()) {
                $this->cartService->queueGuestTokenCookie($cart->token);
            }

            return back()->with('success', 'Combo added to cart.');
        }

        $item = InventoryItem::query()
            ->storefrontVisible()
            ->with('stock')
            ->findOrFail($payload['inventory_item_id']);

        $variant = null;
        if (! empty($payload['inventory_item_variant_id'])) {
            $variant = InventoryItemVariant::query()
                ->where('inventory_item_id', $item->id)
                ->findOrFail($payload['inventory_item_variant_id']);
        }

        $projectedQty = $this->projectedCartQuantity($cart, $item->id, $variant?->id) + $quantity;

        if (! $item->allow_backorders && $item->track_stock) {
            $maxAllowed = $this->availableQuantity($item, $variant);
            if ($projectedQty > $maxAllowed) {
                return back()->with('error', "Only {$maxAllowed} item(s) are currently available.");
            }
        }

        $existing = $cart->items()->where('line_key', $item->id.':'.($variant?->id ?: 'base'))->first();
        $finalQuantity = $existing ? ((float) $existing->quantity + $quantity) : $quantity;

        $this->cartService->addItem($cart, $item, $variant, $finalQuantity);

        if (! $request->user()) {
            $this->cartService->queueGuestTokenCookie($cart->token);
        }

        return back()->with('success', 'Item added to cart.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->resolveAuthorizedCart($request);

        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $cartItem->load('item.stock', 'variant');
        $quantity = (float) $request->validated('quantity');

        if ($cartItem->item && ! $cartItem->item->allow_backorders && $cartItem->item->track_stock) {
            $maxAllowed = $this->availableQuantity($cartItem->item, $cartItem->variant);
            if ($quantity > $maxAllowed) {
                return back()->with('error', "Only {$maxAllowed} item(s) are currently available.");
            }
        }

        $this->cartService->updateQuantity($cartItem, $quantity);

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->resolveAuthorizedCart($request);

        if ($cartItem->cart_id !== $cart->id) {
            abort(403);
        }

        $this->cartService->removeItem($cartItem);

        return back()->with('success', 'Item removed from cart.');
    }

    protected function resolveAuthorizedCart(Request $request)
    {
        $guestToken = $this->cartService->guestTokenFromRequest($request);

        return $this->cartService->resolveCart($request->user(), $guestToken, $this->storefrontContext->currency());
    }

    protected function availableQuantity(InventoryItem $item, ?InventoryItemVariant $variant): float
    {
        if ($variant && $variant->stock_qty !== null) {
            return max(0, (float) $variant->stock_qty);
        }

        return max(0, (float) ($item->stock?->available ?? 0));
    }

    protected function projectedCartQuantity(Cart $cart, int $itemId, ?int $variantId): float
    {
        $normalizedVariantId = $variantId ?: 0;

        return (float) $cart->items
            ->filter(function ($line) use ($itemId, $normalizedVariantId) {
                $lineVariantId = (int) ($line->inventory_item_variant_id ?: 0);

                return (int) $line->inventory_item_id === $itemId
                    && $lineVariantId === $normalizedVariantId;
            })
            ->sum(fn ($line) => (float) $line->quantity);
    }
}
