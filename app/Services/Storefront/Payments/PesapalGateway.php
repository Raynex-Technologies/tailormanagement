<?php

namespace App\Services\Storefront\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PesapalGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'pesapal';
    }

    public function initiate(PaymentTransaction $transaction, array $context): array
    {
        $notificationId = $this->notificationId($transaction, $context);

        $payload = [
            'id' => $transaction->merchant_reference,
            'currency' => $transaction->currency,
            'amount' => (float) $transaction->amount,
            'description' => (string) Arr::get($context, 'description', 'TailorPro payment'),
            'callback_url' => (string) Arr::get($context, 'callback_url'),
            'notification_id' => $notificationId,
            'billing_address' => [
                'email_address' => Arr::get($context, 'email'),
                'phone_number' => Arr::get($context, 'phone'),
                'country_code' => Arr::get($context, 'country', 'US'),
                'first_name' => Arr::get($context, 'first_name', 'Customer'),
                'last_name' => Arr::get($context, 'last_name', ''),
            ],
        ];

        $response = $this->request($transaction)
            ->post('/Transactions/SubmitOrderRequest', $payload)
            ->throw()
            ->json();

        Log::info('Pesapal initiate response', [
            'merchant_reference' => $transaction->merchant_reference,
            'response' => $response,
        ]);

        return [
            'gateway_reference' => Arr::get($response, 'order_tracking_id') ?: Arr::get($response, 'tracking_id'),
            'redirect_url' => Arr::get($response, 'redirect_url') ?: Arr::get($response, 'payment_redirect_url'),
            'raw' => $response,
        ];
    }

    public function verify(PaymentTransaction $transaction, array $payload = []): array
    {
        $trackingId = Arr::get($payload, 'OrderTrackingId')
            ?: Arr::get($payload, 'order_tracking_id')
            ?: $transaction->gateway_reference;

        if (! $trackingId) {
            throw new RuntimeException('Pesapal tracking ID is required for verification.');
        }

        $response = $this->request($transaction)
            ->get('/Transactions/GetTransactionStatus', [
                'orderTrackingId' => $trackingId,
            ])
            ->throw()
            ->json();

        $statusRaw = strtoupper((string) (
            Arr::get($response, 'payment_status_description')
            ?: Arr::get($response, 'status')
            ?: Arr::get($response, 'payment_status')
        ));

        $successStates = ['COMPLETED', 'SUCCESS', 'PAID'];
        $pendingStates = ['PENDING', 'PROCESSING'];
        $cancelledStates = ['CANCELLED', 'INVALID', 'FAILED'];

        $normalized = 'failed';
        if (in_array($statusRaw, $successStates, true)) {
            $normalized = 'verified';
        } elseif (in_array($statusRaw, $pendingStates, true)) {
            $normalized = 'pending';
        } elseif (in_array($statusRaw, $cancelledStates, true)) {
            $normalized = 'failed';
        }

        Log::info('Pesapal verify response', [
            'merchant_reference' => $transaction->merchant_reference,
            'tracking_id' => $trackingId,
            'response' => $response,
            'normalized_status' => $normalized,
        ]);

        return [
            'status' => $normalized,
            'gateway_reference' => $trackingId,
            'raw' => $response,
        ];
    }

    protected function request(PaymentTransaction $transaction): PendingRequest
    {
        $token = $this->token($transaction);

        return Http::baseUrl($this->baseUrl($transaction))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(30);
    }

    protected function token(PaymentTransaction $transaction): string
    {
        [$consumerKey, $consumerSecret] = $this->credentials($transaction);
        $mode = $this->mode($transaction);
        $cacheKey = 'storefront:pesapal:token:'.$mode.':'.substr(hash('sha256', $consumerKey), 0, 16);

        return Cache::remember($cacheKey, now()->addMinutes(4), function () use ($transaction, $consumerKey, $consumerSecret) {
            $response = Http::baseUrl($this->baseUrl($transaction))
                ->acceptJson()
                ->asJson()
                ->post('/Auth/RequestToken', [
                    'consumer_key' => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                ])
                ->throw()
                ->json();

            $token = Arr::get($response, 'token');

            if (! $token) {
                throw new RuntimeException('Pesapal token was not returned from auth endpoint.');
            }

            return (string) $token;
        });
    }

    protected function baseUrl(PaymentTransaction $transaction): string
    {
        $mode = $this->mode($transaction);

        if ($mode === 'live') {
            return rtrim((string) config('services.pesapal.live_url', 'https://pay.pesapal.com/v3/api'), '/');
        }

        return rtrim((string) config('services.pesapal.sandbox_url', 'https://cybqa.pesapal.com/pesapalv3/api'), '/');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function credentials(PaymentTransaction $transaction): array
    {
        $settings = $this->gatewaySettings($transaction);

        $consumerKey = trim((string) (
            $settings['consumer_key']
            ?? $settings['merchant_consumer_key']
            ?? config('services.pesapal.consumer_key')
            ?? ''
        ));

        $consumerSecret = trim((string) (
            $settings['consumer_secret']
            ?? $settings['merchant_consumer_secret']
            ?? config('services.pesapal.consumer_secret')
            ?? ''
        ));

        if ($consumerKey === '' || $consumerSecret === '') {
            throw new RuntimeException('Pesapal merchant consumer key/secret are not configured for this payment method.');
        }

        return [$consumerKey, $consumerSecret];
    }

    protected function notificationId(PaymentTransaction $transaction, array $context = []): string
    {
        $settings = $this->gatewaySettings($transaction);

        $notificationId = trim((string) (
            $settings['notification_id']
            ?? config('services.pesapal.notification_id')
            ?? ''
        ));

        if ($notificationId !== '') {
            return $notificationId;
        }

        $ipnUrl = trim((string) (
            Arr::get($context, 'ipn_url')
            ?? config('services.pesapal.ipn_url')
            ?? ''
        ));

        if ($ipnUrl === '') {
            throw new RuntimeException('Pesapal notification_id is not configured and no IPN URL was provided for registration.');
        }

        return $this->registerIpn($transaction, $ipnUrl);
    }

    protected function registerIpn(PaymentTransaction $transaction, string $ipnUrl): string
    {
        $response = $this->request($transaction)
            ->post('/URLSetup/RegisterIPN', [
                'url' => $ipnUrl,
                'ipn_notification_type' => 'GET',
            ])
            ->throw()
            ->json();

        Log::info('Pesapal register IPN response', [
            'merchant_reference' => $transaction->merchant_reference,
            'ipn_url' => $ipnUrl,
            'response' => $response,
        ]);

        $notificationId = trim((string) (
            Arr::get($response, 'ipn_id')
            ?: Arr::get($response, 'notification_id')
            ?: Arr::get($response, 'ipn_notification_id')
        ));

        if ($notificationId === '') {
            throw new RuntimeException('Pesapal did not return an IPN notification ID.');
        }

        $transaction->loadMissing('paymentMethod');

        if ($transaction->paymentMethod) {
            $settings = (array) ($transaction->paymentMethod->settings ?? []);
            $settings['notification_id'] = $notificationId;

            $transaction->paymentMethod->forceFill([
                'settings' => $settings,
            ])->save();
        }

        return $notificationId;
    }

    protected function mode(PaymentTransaction $transaction): string
    {
        $settings = $this->gatewaySettings($transaction);
        $mode = strtolower(trim((string) ($settings['mode'] ?? config('services.pesapal.mode', 'sandbox'))));

        return $mode === 'live' ? 'live' : 'sandbox';
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatewaySettings(PaymentTransaction $transaction): array
    {
        $transaction->loadMissing('paymentMethod');

        return (array) ($transaction->paymentMethod?->settings ?? []);
    }
}
