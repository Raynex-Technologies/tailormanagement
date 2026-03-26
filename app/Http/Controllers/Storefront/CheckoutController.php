<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\CmsPage;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Notifications\StorefrontOrderPlacedNotification;
use App\Services\Storefront\CartService;
use App\Services\Storefront\Checkout\CheckoutService;
use App\Services\Storefront\Payments\PaymentTransactionService;
use App\Services\Storefront\Shipping\ShippingQuoteService;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected StorefrontContext $storefrontContext,
        protected ShippingQuoteService $shippingQuoteService,
        protected CheckoutService $checkoutService,
        protected PaymentTransactionService $paymentTransactionService,
    ) {
    }

    public function show(Request $request)
    {
        if (! $request->user() && ! $this->storefrontContext->isGuestCheckoutEnabled()) {
            return redirect()->route('login')->with('error', 'Guest checkout is disabled. Please sign in to continue.');
        }

        $guestToken = $this->cartService->guestTokenFromRequest($request);
        $cart = $this->cartService->resolveCart($request->user(), $guestToken, $this->storefrontContext->currency());

        if ($cart->items->isEmpty()) {
            return redirect()->route('storefront.cart.index')->with('error', 'Your cart is empty.');
        }

        $user = $request->user();
        $customer = $user?->customerProfile;

        $defaultAddress = $user
            ? $user->customerAddresses()->orderByDesc('is_default_shipping')->orderByDesc('id')->first()
            : null;

        $destination = [
            'country' => old('shipping_address.country', $defaultAddress?->country ?: 'US'),
            'state' => old('shipping_address.state', $defaultAddress?->state),
            'postal_code' => old('shipping_address.postal_code', $defaultAddress?->postal_code),
        ];

        $shippingQuotes = $this->shippingQuoteService->quotesForCart($cart, $destination, $this->storefrontContext->currency());

        return view('storefront.checkout.index', [
            'settings' => $this->storefrontContext->settings(),
            'cart' => $cart,
            'summary' => $this->cartService->summary($cart),
            'shippingQuotes' => $shippingQuotes,
            'paymentMethods' => PaymentMethod::query()->enabled()->orderBy('sort_order')->orderBy('name')->get(),
            'customer' => $customer,
            'defaultAddress' => $defaultAddress,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function place(CheckoutRequest $request): RedirectResponse
    {
        if (! $request->user() && ! $this->storefrontContext->isGuestCheckoutEnabled()) {
            return redirect()->route('login')->with('error', 'Guest checkout is disabled. Please sign in to continue.');
        }

        $guestToken = $this->cartService->guestTokenFromRequest($request);
        $cart = $this->cartService->resolveCart($request->user(), $guestToken, $this->storefrontContext->currency());

        $validated = $request->validated();

        if (($validated['billing_same_as_shipping'] ?? false) === true) {
            $validated['billing_address'] = $validated['shipping_address'];
        }

        try {
            $result = $this->checkoutService->placeOrder($cart, $validated, $request->user());
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $errorKey = str_contains(strtolower($message), 'coupon') ? 'coupon_code' : 'checkout';

            return back()
                ->withInput()
                ->withErrors([
                    $errorKey => $message,
                ])
                ->with('error', $message);
        }

        $order = $result['order'];
        $transaction = $result['payment_transaction'];

        $this->notifyCustomerOrderPlaced($request, $order);

        session(['storefront_last_order_id' => $order->id]);

        if ($transaction) {
            $firstAndLast = preg_split('/\s+/', trim((string) $validated['full_name']), 2);
            $firstName = $firstAndLast[0] ?? 'Customer';
            $lastName = $firstAndLast[1] ?? '';

            try {
                $transaction = $this->paymentTransactionService->initiate($transaction, [
                    'callback_url' => route('storefront.payments.callback'),
                    'ipn_url' => route('storefront.payments.ipn'),
                    'description' => 'Payment for order '.$order->order_no,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'country' => strtoupper((string) ($validated['shipping_address']['country'] ?? 'US')),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return redirect()
                    ->route('storefront.checkout.confirmation', $order)
                    ->with('error', 'Order placed, but payment could not be started right now. Please retry payment from your order page.');
            }

            $checkoutUrl = trim((string) ($transaction->checkout_url ?? ''));
            if ($checkoutUrl !== '' && filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
                return redirect()->away($checkoutUrl);
            }

            return redirect()
                ->route('storefront.checkout.confirmation', $order)
                ->with('error', 'Payment gateway response did not include a valid redirect URL. You can retry payment from your order page.');
        }

        return redirect()->route('storefront.checkout.confirmation', $order)->with('success', 'Order placed successfully.');
    }

    public function confirmation(Order $order, Request $request)
    {
        $sessionOrderId = (int) $request->session()->get('storefront_last_order_id');

        if ($request->user()) {
            $customerId = $request->user()->customerProfile?->id;
            if ($customerId && $order->customer_id !== $customerId) {
                abort(403);
            }
        } elseif ($sessionOrderId !== $order->id) {
            abort(403);
        }

        return view('storefront.checkout.confirmation', [
            'settings' => $this->storefrontContext->settings(),
            'order' => $order->load(['lines', 'customer', 'currentShipment']),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    protected function notifyCustomerOrderPlaced(Request $request, Order $order): void
    {
        $customerUser = $request->user()?->customerProfile?->user;

        if (! $customerUser) {
            $order->loadMissing('customer.user');
            $customerUser = $order->customer?->user;
        }

        if ($customerUser) {
            $customerUser->notify(new StorefrontOrderPlacedNotification($order));
        }
    }
}
