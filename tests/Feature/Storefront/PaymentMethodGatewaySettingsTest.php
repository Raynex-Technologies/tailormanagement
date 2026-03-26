<?php

namespace Tests\Feature\Storefront;

use App\Enums\PaymentTransactionPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Livewire\Administration\BusinessSettings;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\Storefront\Payments\PesapalGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodGatewaySettingsTest extends TestCase
{
    public function test_online_pesapal_method_requires_merchant_consumer_credentials(): void
    {
        $this->actingAsRole('superadmin', $this->branch);

        $pesapal = $this->ensurePesapalMethod();

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'payment_methods')
            ->set('editingPaymentMethodId', $pesapal->id)
            ->set('paymentMethodName', 'Pesapal')
            ->set('paymentMethodCode', 'pesapal')
            ->set('paymentMethodType', 'online')
            ->set('paymentMethodOnline', true)
            ->set('paymentMethodConsumerKey', '')
            ->set('paymentMethodConsumerSecret', '')
            ->call('savePaymentMethod')
            ->assertHasErrors([
                'paymentMethodConsumerKey',
                'paymentMethodConsumerSecret',
            ]);
    }

    public function test_online_pesapal_method_saves_merchant_consumer_credentials_to_settings(): void
    {
        $this->actingAsRole('superadmin', $this->branch);

        $pesapal = $this->ensurePesapalMethod();

        Livewire::test(BusinessSettings::class)
            ->set('tab', 'payment_methods')
            ->set('editingPaymentMethodId', $pesapal->id)
            ->set('paymentMethodName', 'Pesapal')
            ->set('paymentMethodCode', 'pesapal')
            ->set('paymentMethodType', 'online')
            ->set('paymentMethodOnline', true)
            ->set('paymentMethodConsumerKey', 'merchant_key_123')
            ->set('paymentMethodConsumerSecret', 'merchant_secret_456')
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $pesapal->refresh();

        $this->assertSame('merchant_key_123', data_get($pesapal->settings, 'consumer_key'));
        $this->assertSame('merchant_secret_456', data_get($pesapal->settings, 'consumer_secret'));
        $this->assertSame('online', $pesapal->type);
        $this->assertTrue($pesapal->is_online);
    }

    public function test_pesapal_gateway_uses_payment_method_credentials_over_env_credentials(): void
    {
        $actor = $this->actingAsRole('superadmin', $this->branch);

        config([
            'services.pesapal.mode' => 'sandbox',
            'services.pesapal.consumer_key' => 'env_consumer_key',
            'services.pesapal.consumer_secret' => 'env_consumer_secret',
            'services.pesapal.notification_id' => 'ipn-test-id',
        ]);

        $pesapal = $this->ensurePesapalMethod([
            'settings' => [
                'consumer_key' => 'method_consumer_key',
                'consumer_secret' => 'method_consumer_secret',
                'mode' => 'sandbox',
            ],
        ]);

        $transaction = PaymentTransaction::query()->create([
            'branch_id' => $this->branch->id,
            'payment_method_id' => $pesapal->id,
            'gateway' => 'pesapal',
            'merchant_reference' => 'TXN-METHOD-CREDS-001',
            'amount' => 150.00,
            'currency' => 'KES',
            'status' => PaymentTransactionStatus::Initiated,
            'purpose' => PaymentTransactionPurpose::StorefrontOrder,
            'initiated_at' => now(),
            'created_by' => $actor->id,
        ]);

        Http::fake([
            '*Auth/RequestToken' => Http::response(['token' => 'token-123'], 200),
            '*Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'TRACK-001',
                'redirect_url' => 'https://example.test/redirect/TRACK-001',
            ], 200),
        ]);

        app(PesapalGateway::class)->initiate($transaction, [
            'description' => 'Storefront checkout',
            'callback_url' => 'https://example.test/storefront/payments/callback',
            'email' => 'customer@example.test',
            'phone' => '+1555000111',
            'country' => 'US',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/Auth/RequestToken')) {
                return false;
            }

            return data_get($request->data(), 'consumer_key') === 'method_consumer_key'
                && data_get($request->data(), 'consumer_secret') === 'method_consumer_secret';
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function ensurePesapalMethod(array $overrides = []): PaymentMethod
    {
        $method = PaymentMethod::query()->firstOrCreate(
            ['code' => 'pesapal'],
            [
                'name' => 'Pesapal',
                'type' => 'online',
                'is_enabled' => true,
                'is_online' => true,
                'sort_order' => 10,
            ]
        );

        if ($overrides !== []) {
            $method->fill($overrides)->save();
        }

        return $method->fresh();
    }
}
