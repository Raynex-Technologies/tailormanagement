<?php

namespace Tests\Feature\WhatsApp;

use App\Livewire\Sms\WhatsappConfigurations;
use App\Models\WhatsappIntegration;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TwilioConnectionActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('admin');
        Http::preventStrayRequests();
    }

    public function test_test_connection_saves_current_fields_and_calls_twilio_in_one_action(): void
    {
        $sid = 'AC'.str_repeat('a', 32);
        Http::fake(['*/Accounts/'.$sid.'.json' => Http::response(['sid' => $sid, 'status' => 'active', 'friendly_name' => 'Test account'])]);
        Livewire::test(WhatsappConfigurations::class)
            ->set('twilio_account_sid', $sid)->set('twilio_auth_token', 'new-token')
            ->set('twilio_from', '+255712345678')->call('testConnection')
            ->assertHasNoErrors()->assertSet('twilio_auth_token', '')
            ->assertSee('Twilio account credentials verified.')
            ->assertDontSee('Twilio settings saved.')->assertDontSee('new-token');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.$sid.'.json'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode($sid.':new-token')));
        $integration = WhatsappIntegration::where('branch_id', $this->branch->id)->sole();
        $this->assertSame('connected', $integration->connection_status);
        $this->assertNotNull($integration->last_checked_at);
        $this->assertFalse($integration->enabled);
    }

    public function test_failure_replaces_save_notice_and_uses_existing_token_when_blank(): void
    {
        $sid = 'AC'.str_repeat('b', 32);
        Http::fake(['*/Accounts/'.$sid.'.json' => Http::response(['code' => 20003, 'message' => 'private-provider-text'], 401)]);
        $component = Livewire::test(WhatsappConfigurations::class)
            ->set('twilio_account_sid', $sid)->set('twilio_auth_token', 'saved-token')
            ->set('twilio_from', '+255712345678')->call('save')->assertSee('Twilio settings saved.');
        WhatsappIntegration::where('branch_id', $this->branch->id)->update(['webhook_status' => 'verified']);
        $component->call('testConnection')->assertHasNoErrors()
            ->assertSee('Twilio rejected the account credentials.')
            ->assertDontSee('Twilio settings saved.')->assertDontSee('private-provider-text');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic '.base64_encode($sid.':saved-token')));
        $this->assertSame('connection_error', WhatsappIntegration::where('branch_id', $this->branch->id)->sole()->connection_status);
        $this->assertSame('verified', WhatsappIntegration::where('branch_id', $this->branch->id)->sole()->webhook_status);
    }

    public function test_invalid_or_missing_credentials_do_not_call_twilio(): void
    {
        Livewire::test(WhatsappConfigurations::class)->call('testConnection')->assertHasErrors(['twilio_account_sid', 'twilio_from']);
        Livewire::test(WhatsappConfigurations::class)
            ->set('twilio_account_sid', 'AC'.str_repeat('c', 32))->set('twilio_from', '+255712345678')
            ->call('testConnection')->assertHasErrors(['twilio_auth_token']);
        Http::assertNothingSent();
    }
}
