<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerAddressRequest;
use App\Models\CmsPage;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Storefront\Payments\PaymentTransactionService;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        protected StorefrontContext $storefrontContext,
        protected PaymentTransactionService $paymentTransactionService,
    ) {
    }

    public function dashboard(Request $request)
    {
        $customer = $this->customer($request);

        $recentOrders = Order::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->take(5)
            ->get();

        return view('storefront.account.dashboard', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'orders' => $recentOrders,
            'storefrontOrderCount' => Order::query()
                ->where('customer_id', $customer->id)
                ->where('order_type', 'storefront')
                ->count(),
            'customOrderCount' => Order::query()
                ->where('customer_id', $customer->id)
                ->where('order_type', 'tailoring')
                ->count(),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function orders(Request $request)
    {
        $customer = $this->customer($request);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->where('order_type', 'storefront')
            ->with(['lines', 'currentShipment'])
            ->latest('id')
            ->paginate(15);

        return view('storefront.account.orders.index', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'orders' => $orders,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function showOrder(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order, 'storefront');
        $customer = $this->customer($request);

        return view('storefront.account.orders.show', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'order' => $order->load(['lines', 'statusHistory', 'currentShipment', 'paymentTransactions']),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function customOrders(Request $request)
    {
        if (! $this->storefrontContext->isCustomPortalEnabled()) {
            abort(404);
        }

        $customer = $this->customer($request);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->where('order_type', 'tailoring')
            ->with(['customProgressUpdates', 'payments'])
            ->latest('id')
            ->paginate(15);

        return view('storefront.account.custom-orders.index', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'orders' => $orders,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function showCustomOrder(Request $request, Order $order)
    {
        if (! $this->storefrontContext->isCustomPortalEnabled()) {
            abort(404);
        }

        $this->authorizeOrder($request, $order, 'tailoring');
        $customer = $this->customer($request);

        return view('storefront.account.custom-orders.show', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'order' => $order->load(['lines', 'customProgressUpdates', 'payments', 'paymentTransactions']),
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
            'currency' => $this->storefrontContext->currency(),
        ]);
    }

    public function retryOrderPayment(Request $request, Order $order): RedirectResponse
    {
        $expectedType = $order->order_type === 'tailoring' ? 'tailoring' : 'storefront';
        $this->authorizeOrder($request, $order, $expectedType);

        $amountDue = max(0, $order->payableTotal() - $order->paid_amount);

        if ($amountDue <= 0.01) {
            return back()->with('success', 'This order is already fully paid.');
        }

        $requestedAmount = null;
        if ($request->filled('amount')) {
            $validated = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
            ]);
            $requestedAmount = (float) $validated['amount'];
        }

        $paymentAmount = $requestedAmount !== null
            ? min($amountDue, $requestedAmount)
            : $amountDue;

        $paymentMethod = PaymentMethod::query()->enabled()->online()->where('code', 'pesapal')->first();

        if (! $paymentMethod) {
            return back()->with('error', 'No online payment method is currently enabled.');
        }

        $purpose = 'storefront_order';
        if ($order->order_type === 'tailoring') {
            $purpose = $paymentAmount < $amountDue
                ? 'tailoring_deposit'
                : 'tailoring_balance';
        }

        $transaction = $this->paymentTransactionService->createForOrder(
            $order,
            $paymentMethod,
            $paymentAmount,
            $purpose,
            $request->user()
        );

        $customer = $request->user()->customerProfile;
        $nameParts = preg_split('/\s+/', trim((string) $customer?->name), 2);

        $transaction = $this->paymentTransactionService->initiate($transaction, [
            'callback_url' => route('storefront.payments.callback'),
            'description' => 'Payment retry for order '.$order->order_no,
            'email' => $customer?->email,
            'phone' => $customer?->phone,
            'country' => data_get($order->shipping_address, 'country', 'US'),
            'first_name' => $nameParts[0] ?? 'Customer',
            'last_name' => $nameParts[1] ?? '',
        ]);

        if (filled($transaction->checkout_url)) {
            return redirect()->away($transaction->checkout_url);
        }

        return back()->with('error', 'Unable to start payment retry at the moment.');
    }

    public function addresses(Request $request)
    {
        $customer = $this->customer($request);

        $addresses = $request->user()
            ->customerAddresses()
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->latest('id')
            ->get();

        return view('storefront.account.addresses.index', [
            'settings' => $this->storefrontContext->settings(),
            'customer' => $customer,
            'addresses' => $addresses,
            'headerPages' => CmsPage::query()->published()->where('show_in_header', true)->orderBy('sort_order')->get(),
            'footerPages' => CmsPage::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function storeAddress(CustomerAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $customer = $this->customer($request);

        $payload = $request->validated();

        $address = $user->customerAddresses()->create([
            ...$payload,
            'customer_id' => $customer->id,
        ]);

        $this->handleAddressDefaults($user->id, $address, $payload);

        return back()->with('success', 'Address saved.');
    }

    public function updateAddress(CustomerAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        $user = $request->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $payload = $request->validated();
        $address->update($payload);

        $this->handleAddressDefaults($user->id, $address, $payload);

        return back()->with('success', 'Address updated.');
    }

    public function deleteAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $address->delete();

        return back()->with('success', 'Address deleted.');
    }

    protected function customer(Request $request)
    {
        return $this->storefrontContext->resolveCustomerForUser($request->user());
    }

    protected function authorizeOrder(Request $request, Order $order, string $expectedOrderType): void
    {
        $customer = $this->customer($request);

        if ($order->customer_id !== $customer->id || $order->order_type !== $expectedOrderType) {
            abort(403);
        }
    }

    protected function handleAddressDefaults(int $userId, CustomerAddress $savedAddress, array $payload): void
    {
        if (($payload['is_default_shipping'] ?? false) === true) {
            CustomerAddress::query()
                ->where('user_id', $userId)
                ->where('id', '!=', $savedAddress->id)
                ->update(['is_default_shipping' => false]);
        }

        if (($payload['is_default_billing'] ?? false) === true) {
            CustomerAddress::query()
                ->where('user_id', $userId)
                ->where('id', '!=', $savedAddress->id)
                ->update(['is_default_billing' => false]);
        }
    }
}
