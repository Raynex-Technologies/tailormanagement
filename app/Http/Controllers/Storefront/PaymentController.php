<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Storefront\Payments\PaymentTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentTransactionService $paymentTransactionService,
    ) {
    }

    public function callback(Request $request)
    {
        $transaction = $this->resolveTransaction($request);

        if (! $transaction) {
            Log::warning('Storefront payment callback could not resolve transaction', [
                'merchant_reference' => $this->merchantReference($request),
                'tracking_id' => $this->trackingId($request),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('storefront.cart.index')->with('error', 'Unable to locate payment transaction from gateway callback.');
        }

        $transaction = $this->paymentTransactionService->verifyAndApply($transaction, $request->all());
        $order = $transaction->order;

        if (! $order) {
            return redirect()->route('storefront.catalog.index')->with('error', 'Payment was processed but the related order could not be loaded.');
        }

        if ($transaction->status->value === 'verified') {
            if (auth()->check() && auth()->user()->customerProfile?->id === $order->customer_id) {
                return redirect()->route('storefront.account.orders.show', $order)->with('success', 'Payment confirmed successfully.');
            }

            session(['storefront_last_order_id' => $order->id]);

            return redirect()->route('storefront.checkout.confirmation', $order)->with('success', 'Payment confirmed successfully.');
        }

        if (auth()->check() && auth()->user()->customerProfile?->id === $order->customer_id) {
            return redirect()->route('storefront.account.orders.show', $order)->with('error', 'Payment was not successful. Please retry.');
        }

        return redirect()->route('storefront.checkout.confirmation', $order)->with('error', 'Payment was not successful. Please retry.');
    }

    public function ipn(Request $request)
    {
        $transaction = $this->resolveTransaction($request);

        if (! $transaction) {
            Log::warning('Storefront payment IPN could not resolve transaction', [
                'merchant_reference' => $this->merchantReference($request),
                'tracking_id' => $this->trackingId($request),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'transaction_not_found'], 404);
        }

        $verified = $this->paymentTransactionService->verifyAndApply($transaction, $request->all());

        return response()->json([
            'status' => $verified->status->value,
            'merchant_reference' => $verified->merchant_reference,
        ]);
    }

    protected function resolveTransaction(Request $request): ?PaymentTransaction
    {
        $merchantReference = $this->merchantReference($request);
        $trackingId = $this->trackingId($request);

        if (! $merchantReference && ! $trackingId) {
            return null;
        }

        $transaction = null;

        if ($merchantReference) {
            $transaction = PaymentTransaction::query()
                ->where('merchant_reference', $merchantReference)
                ->first();

            if ($transaction && $trackingId && filled($transaction->gateway_reference) && ! hash_equals((string) $transaction->gateway_reference, (string) $trackingId)) {
                return null;
            }
        }

        if (! $transaction && $trackingId) {
            $transaction = PaymentTransaction::query()
                ->where('gateway_reference', $trackingId)
                ->first();

            if ($transaction && $merchantReference && ! hash_equals((string) $transaction->merchant_reference, (string) $merchantReference)) {
                return null;
            }
        }

        if (! $transaction) {
            return null;
        }

        $transaction->loadMissing('paymentMethod');

        $gateway = strtolower(trim((string) ($transaction->paymentMethod?->code ?: $transaction->gateway)));

        return $gateway === 'pesapal' ? $transaction : null;
    }

    protected function merchantReference(Request $request): ?string
    {
        $value = $request->query('OrderMerchantReference')
            ?: $request->query('merchant_reference')
            ?: $request->input('order_merchant_reference')
            ?: $request->input('merchant_reference');

        return filled($value) ? trim((string) $value) : null;
    }

    protected function trackingId(Request $request): ?string
    {
        $value = $request->query('OrderTrackingId')
            ?: $request->query('order_tracking_id')
            ?: $request->input('order_tracking_id')
            ?: $request->input('OrderTrackingId');

        return filled($value) ? trim((string) $value) : null;
    }
}
