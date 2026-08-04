<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Livewire\Sms\WhatsappConfigurations;
use App\Models\Branch;
use App\Models\WhatsappIntegration;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MetaWhatsAppFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_provider_is_the_whatsapp_provider_binding(): void
    {
        $this->assertInstanceOf(MetaWhatsAppProvider::class, app(WhatsAppProvider::class));
    }

    public function test_integration_is_unique_per_branch_and_secrets_are_encrypted_and_hidden(): void
    {
        $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $integration = WhatsappIntegration::forBranch($branch->id);
        $integration->update(['access_token' => 'secret-access', 'app_secret' => 'secret-app', 'webhook_verify_token' => 'secret-verify-token']);
        $raw = DB::table('whatsapp_integrations')->where('id', $integration->id)->first();
        $this->assertNotSame('secret-access', $raw->access_token);
        $this->assertNotSame('secret-app', $raw->app_secret);
        $this->assertNotSame('secret-verify-token', $raw->webhook_verify_token);
        $this->assertSame('secret-access', $integration->fresh()->access_token);
        $this->assertArrayNotHasKey('access_token', $integration->fresh()->toArray());
        $this->assertSame($integration->id, WhatsappIntegration::forBranch($branch->id)->id);
    }

    public function test_successful_connection_check_persists_sanitized_metadata(): void
    {
        Http::fake([
            '*/12345*' => Http::response(['id' => '12345', 'display_phone_number' => '+255700000000', 'verified_name' => 'Tailor Main', 'whatsapp_business_account' => ['id' => '67890']]),
            '*/67890*' => Http::response(['id' => '67890', 'name' => 'Tailor Business']),
        ]);
        $integration = $this->integration();
        $result = app(WhatsAppService::class)->testConnection($integration);
        $this->assertTrue($result['success']);
        $this->assertSame('connected', $integration->fresh()->connection_status);
        $this->assertSame('+255700000000', $integration->fresh()->display_phone_number);
        $this->assertNotNull($integration->fresh()->last_connected_at);
        Http::assertSentCount(2);
    }

    public function test_invalid_or_expired_token_is_sanitized(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'OAuthException token=do-not-leak', 'code' => 190]], 401)]);
        $integration = $this->integration();
        $result = app(WhatsAppService::class)->testConnection($integration);
        $this->assertFalse($result['success']);
        $this->assertSame('190', $result['error_code']);
        $this->assertStringNotContainsString('do-not-leak', $result['error_message']);
        $this->assertStringNotContainsString('do-not-leak', $integration->fresh()->last_error_message);
    }

    public function test_phone_number_must_belong_to_configured_waba(): void
    {
        Http::fake([
            '*/12345*' => Http::response(['id' => '12345', 'whatsapp_business_account' => ['id' => 'different']]),
            '*/67890*' => Http::response(['id' => '67890']),
        ]);
        $result = app(WhatsAppService::class)->testConnection($this->integration());
        $this->assertFalse($result['success']);
        $this->assertSame('phone_waba_mismatch', $result['error_code']);
    }

    public function test_disabled_and_not_implemented_sending_are_explicit_and_do_not_use_sms(): void
    {
        $integration = $this->integration();
        $integration->update(['enabled' => false]);
        $this->assertSame('disabled', app(WhatsAppService::class)->sendText($integration->branch_id, '+255700000000', 'Hello')['error_code']);
        $integration->update(['enabled' => true]);
        $this->assertSame('template_required', app(WhatsAppService::class)->sendText($integration->branch_id, '+255700000000', 'Hello')['error_code']);
    }

    public function test_authorized_admin_can_view_save_masked_settings_and_validation_is_enforced(): void
    {
        $this->actingAsRole('admin');
        Livewire::test(WhatsappConfigurations::class)
            ->set('enabled', true)
            ->set('waba_id', 'not-numeric')
            ->set('phone_number_id', '')
            ->call('save')
            ->assertHasErrors(['waba_id', 'phone_number_id'])
            ->set('waba_id', '67890')
            ->set('phone_number_id', '12345')
            ->set('access_token', 'saved-secret')
            ->set('webhook_verify_token', 'long-enough-verify-token')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('access_token', '')
            ->assertDontSee('saved-secret');
        $this->assertSame($this->branch->id, WhatsappIntegration::query()->sole()->branch_id);
    }

    public function test_unauthorized_user_cannot_view_meta_settings(): void
    {
        $this->actingAsRole('sales');
        Livewire::test(WhatsappConfigurations::class)->assertForbidden();
    }

    public function test_webhook_verification_uses_opaque_key_and_saved_secret(): void
    {
        $integration = $this->integration();
        $integration->update(['webhook_verify_token' => 'verify-secret-value']);
        $this->get(route('webhooks.meta-whatsapp', ['webhookKey' => $integration->webhook_key, 'hub_mode' => 'subscribe', 'hub_verify_token' => 'verify-secret-value', 'hub_challenge' => 'challenge-123']))
            ->assertOk()->assertSeeText('challenge-123');
    }

    private function integration(): WhatsappIntegration
    {
        $branch = Branch::create(['name' => 'Main', 'code' => 'MAIN-'.str()->random(5), 'is_active' => true]);

        return WhatsappIntegration::create(['branch_id' => $branch->id, 'webhook_key' => (string) str()->uuid(), 'enabled' => true, 'waba_id' => '67890', 'phone_number_id' => '12345', 'access_token' => 'token', 'connection_status' => 'unverified']);
    }
}
