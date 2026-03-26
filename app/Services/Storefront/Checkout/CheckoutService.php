<?php

namespace App\Services\Storefront\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Enums\StorefrontFulfillmentStatus;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\StorefrontCoupon;
use App\Models\StorefrontCouponUsage;
use App\Models\User;
use App\Services\Storefront\CartService;
use App\Services\Storefront\InventoryReservationService;
use App\Services\Storefront\Payments\PaymentTransactionService;
use App\Services\Storefront\Shipping\ShippingQuoteService;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckoutService
{
    public function __construct(
        protected StorefrontContext $storefrontContext,
        protected ShippingQuoteService $shippingQuoteService,
        protected CartService $cartService,
        protected PaymentTransactionService $paymentTransactionService,
        protected InventoryReservationService $inventoryReservationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{order: Order, payment_transaction: ?\App\Models\PaymentTransaction}
     */
    public function placeOrder(Cart $cart, array $payload, ?User $user = null): array
    {
        $cart->loadMissing('items.item.stock', 'items.variant');

        if ($cart->items->isEmpty()) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $currency = $this->storefrontContext->currency();

        return DB::transaction(function () use ($cart, $payload, $user, $currency) {
            $this->cartService->refreshPricing($cart);
            $cart->refresh()->load('items.item.stock', 'items.variant');

            $customer = $this->resolveCustomer($payload, $user);
            $shippingAddress = Arr::get($payload, 'shipping_address', []);
            $billingAddress = Arr::get($payload, 'billing_address', $shippingAddress);

            $quotes = $this->shippingQuoteService->quotesForCart($cart, $shippingAddress, $currency);
            $selectedShipping = collect($quotes)->firstWhere('code', Arr::get($payload, 'shipping_method_code'));

            if (! $selectedShipping) {
                throw new InvalidArgumentException('Selected shipping method is not available for this destination.');
            }

            $methodCode = strtolower(trim((string) Arr::get($payload, 'payment_method_code', 'pesapal')));
            $paymentMethod = PaymentMethod::query()
                ->enabled()
                ->whereRaw('LOWER(code) = ?', [$methodCode])
                ->first();

            if (! $paymentMethod) {
                throw new InvalidArgumentException('Selected payment method is unavailable.');
            }

            if (strtolower((string) $paymentMethod->code) === 'cash' && ! $this->storefrontContext->allowsCashOnDelivery()) {
                throw new InvalidArgumentException('Cash on delivery is currently disabled.');
            }

            $summary = $this->cartService->summary($cart);
            $shippingTotal = (float) ($selectedShipping['amount'] ?? 0);
            $taxTotal = 0.0;
            $coupon = $this->resolveCoupon(
                (string) Arr::get($payload, 'coupon_code', ''),
                $customer,
                (float) ($summary['subtotal'] ?? 0)
            );
            $couponDiscount = $coupon ? $coupon->calculateDiscount((float) ($summary['subtotal'] ?? 0)) : 0.0;
            $discountTotal = (float) ($summary['discount'] ?? 0) + $couponDiscount;
            $grandTotal = (float) ($summary['subtotal'] ?? 0) + $shippingTotal + $taxTotal - $discountTotal;
            $grandTotal = max(0, round($grandTotal, 2));
            $initialFulfillmentStatus = $paymentMethod->is_online
                ? StorefrontFulfillmentStatus::AwaitingPayment
                : StorefrontFulfillmentStatus::Pending;

            $order = Order::create([
                'branch_id' => $customer->branch_id,
                'order_type' => 'storefront',
                'order_source' => 'online',
                'customer_id' => $customer->id,
                'status' => OrderStatus::New,
                'fulfillment_status' => $initialFulfillmentStatus,
                'order_date' => now()->toDateString(),
                'due_date' => null,
                'priority' => Priority::Normal,
                'notes' => null,
                'subtotal' => (float) ($summary['subtotal'] ?? 0),
                'discount' => 0,
                'total' => (float) ($summary['subtotal'] ?? 0),
                'currency' => $currency,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'discount_total' => $discountTotal,
                'storefront_coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'coupon_name' => $coupon?->name,
                'coupon_discount_type' => $coupon?->discount_type,
                'coupon_discount_value' => $couponDiscount > 0 ? $couponDiscount : null,
                'grand_total' => $grandTotal,
                'shipping_method_code' => $selectedShipping['code'],
                'shipping_method_name' => $selectedShipping['name'],
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress,
                'checkout_email' => Arr::get($payload, 'email'),
                'checkout_phone' => Arr::get($payload, 'phone'),
                'customer_note' => Arr::get($payload, 'notes'),
                'placed_at' => now(),
                'payment_status' => PaymentStatus::Unpaid,
                'created_by' => $user?->id,
            ]);

            foreach ($cart->items as $line) {
                $order->lines()->create([
                    'inventory_item_id' => $line->inventory_item_id,
                    'inventory_item_variant_id' => $line->inventory_item_variant_id,
                    'sku' => $line->variant?->sku ?: $line->item?->sku,
                    'item_name' => $line->item?->name ?: 'Item',
                    'qty' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                    'notes' => null,
                    'meta' => [
                        'variant' => $line->variant?->name,
                    ],
                ]);
            }

            Shipment::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'shipping_method_code' => $selectedShipping['code'],
                'shipping_method_name' => $selectedShipping['name'],
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $order->statusHistory()->create([
                'status' => $initialFulfillmentStatus->value,
                'title' => 'Order Placed',
                'note' => $paymentMethod->is_online
                    ? 'Your order has been created and is awaiting payment confirmation.'
                    : 'Your order has been created and is awaiting processing.',
                'is_customer_visible' => true,
                'changed_by' => $user?->id,
            ]);

            if ($coupon && $couponDiscount > 0) {
                StorefrontCouponUsage::query()->create([
                    'storefront_coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'used_at' => now(),
                ]);

                StorefrontCoupon::query()
                    ->withoutBranchScope()
                    ->whereKey($coupon->id)
                    ->increment('used_count');
            }

            $this->inventoryReservationService->reserve($order);

            $transaction = null;
            if ($paymentMethod->is_online && $grandTotal > 0) {
                $transaction = $this->paymentTransactionService->createForOrder(
                    $order,
                    $paymentMethod,
                    $grandTotal,
                    'storefront_order',
                    $user
                );
            }

            $cart->items()->delete();

            return [
                'order' => $order->fresh(['lines', 'customer', 'statusHistory', 'currentShipment']),
                'payment_transaction' => $transaction,
            ];
        });
    }

    protected function resolveCoupon(string $code, Customer $customer, float $subtotal): ?StorefrontCoupon
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        /** @var StorefrontCoupon|null $coupon */
        $coupon = StorefrontCoupon::query()
            ->withoutBranchScope()
            ->where('branch_id', $customer->branch_id)
            ->where('code', $normalized)
            ->first();

        if (! $coupon) {
            throw new InvalidArgumentException('The coupon code is invalid.');
        }

        if (! $coupon->is_active || ! $coupon->isWithinActiveWindow()) {
            throw new InvalidArgumentException('The coupon code is not active.');
        }

        if ($coupon->min_subtotal !== null && $subtotal < (float) $coupon->min_subtotal) {
            throw new InvalidArgumentException('This coupon requires a higher order subtotal.');
        }

        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            throw new InvalidArgumentException('This coupon has reached its usage limit.');
        }

        if ($coupon->per_customer_limit !== null) {
            $usedByCustomer = StorefrontCouponUsage::query()
                ->where('storefront_coupon_id', $coupon->id)
                ->where('customer_id', $customer->id)
                ->count();

            if ($usedByCustomer >= (int) $coupon->per_customer_limit) {
                throw new InvalidArgumentException('You have already used this coupon the maximum number of times.');
            }
        }

        return $coupon;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveCustomer(array $payload, ?User $user): Customer
    {
        if ($user) {
            $customer = $this->storefrontContext->resolveCustomerForUser($user);

            $customer->update([
                'name' => Arr::get($payload, 'full_name', $user->name),
                'email' => Arr::get($payload, 'email', $user->email),
                'phone' => Arr::get($payload, 'phone', $customer->phone),
            ]);

            return $customer;
        }

        $branchId = $this->storefrontContext->defaultBranchId();
        $email = trim((string) Arr::get($payload, 'email', ''));
        $phone = trim((string) Arr::get($payload, 'phone', ''));

        $customerQuery = Customer::query()->where('branch_id', $branchId);

        if ($email !== '') {
            $customer = (clone $customerQuery)
                ->where('email', $email)
                ->first();
        } elseif ($phone !== '') {
            $customer = (clone $customerQuery)
                ->where('phone', $phone)
                ->first();
        } else {
            $customer = null;
        }

        if (! $customer) {
            $customer = Customer::create([
                'branch_id' => $branchId,
                'name' => Arr::get($payload, 'full_name', 'Guest Customer'),
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'address' => Arr::get($payload, 'shipping_address.address_line1'),
            ]);
        } else {
            $customer->update([
                'name' => Arr::get($payload, 'full_name', $customer->name),
                'email' => $email !== '' ? $email : $customer->email,
                'phone' => $phone !== '' ? $phone : $customer->phone,
                'address' => Arr::get($payload, 'shipping_address.address_line1', $customer->address),
            ]);
        }

        return $customer;
    }
}
