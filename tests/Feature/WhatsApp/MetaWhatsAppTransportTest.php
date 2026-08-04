<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\ProcessWhatsappWebhook;
use App\Jobs\SendWhatsappMessage;
use App\Models\Customer;
use App\Models\WhatsappContact;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaWhatsAppTransportTest extends TestCase
{
    public function test_free_form_send_is_queued_inside_window_and_meta_wamid_is_accepted(): void
    {
        Queue::fake();
        $i = $this->integration();
        $this->contact($i, now());
        $result = app(WhatsAppService::class)->queueText($i->branch_id, '0712345678', 'Hello');
        $this->assertTrue($result['queued']);
        Queue::assertPushed(SendWhatsappMessage::class);
        $message = WhatsappMessage::withoutGlobalScopes()->findOrFail($result['message_id']);
        Http::fake(['*/12345/messages' => Http::response(['messages' => [['id' => 'wamid.test']]])]);
        (new SendWhatsappMessage($message->id))->handle(app(\App\Contracts\WhatsAppProvider::class), app(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class));
        $this->assertSame('accepted', $message->fresh()->status);
        $this->assertSame('wamid.test', $message->fresh()->external_message_id);
        Http::assertSent(fn ($request) => $request['messaging_product'] === 'whatsapp' && $request['to'] === '255712345678' && $request['text']['body'] === 'Hello');
    }

    public function test_window_and_recipient_rules_are_explicit(): void
    {
        Queue::fake();
        $i = $this->integration();
        $this->assertSame('invalid_recipient', app(WhatsAppService::class)->queueText($i->branch_id, 'abc', 'Hello')['error_code']);
        $this->contact($i, now()->subDay()->subSecond());
        $this->assertSame('template_required', app(WhatsAppService::class)->queueText($i->branch_id, '0712345678', 'Hello')['error_code']);
        $this->contact($i, now()->subDay());
        $this->assertTrue(app(WhatsAppService::class)->canSendFreeFormMessage($i, '0712345678'));
    }

    public function test_permanent_authentication_failure_is_sanitized_and_marks_integration_unhealthy(): void
    {
        $i = $this->integration();
        $contact = $this->contact($i, now());
        $message = $this->message($i, $contact);
        Http::fake(['*' => Http::response(['error' => ['message' => 'token secret-token', 'code' => 190]], 401)]);
        (new SendWhatsappMessage($message->id))->handle(app(\App\Contracts\WhatsAppProvider::class), app(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class));
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertStringNotContainsString('secret-token', $message->fresh()->failure_reason);
        $this->assertSame('connection_error', $i->fresh()->connection_status);
    }

    public function test_valid_signature_is_durable_and_duplicate_is_idempotent(): void
    {
        Queue::fake();
        $i = $this->integration();
        $payload = $this->payload($i, [['id' => 'wamid.in', 'from' => '255712345678', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Hi']]], []);
        $raw = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $raw, 'app-secret');
        $this->webhook($i, $raw, $signature)->assertOk();
        $this->webhook($i, $raw, $signature)->assertOk();
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        Queue::assertPushed(ProcessWhatsappWebhook::class, 1);
    }

    public function test_missing_invalid_and_mismatched_webhooks_are_rejected(): void
    {
        $i = $this->integration();
        $payload = $this->payload($i, [], []);
        $raw = json_encode($payload);
        $this->webhook($i, $raw, '')->assertForbidden();
        $this->webhook($i, $raw, 'sha256='.str_repeat('0', 64))->assertForbidden();
        $payload['entry'][0]['id'] = 'wrong';
        $bad = json_encode($payload);
        $this->webhook($i, $bad, 'sha256='.hash_hmac('sha256', $bad, 'app-secret'))->assertForbidden();
    }

    public function test_inbound_text_resolves_branch_customer_and_opens_window(): void
    {
        $i = $this->integration();
        $customer = Customer::factory()->for($this->branch)->create(['phone' => '0712345678']);
        $event = WhatsappWebhookEvent::create(['whatsapp_integration_id' => $i->id, 'event_key' => str_repeat('a', 64), 'payload' => $this->payload($i, [['id' => 'wamid.in', 'from' => '255712345678', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Need fitting']]], []), 'accepted_at' => now()]);
        (new ProcessWhatsappWebhook($event->id))->handle(app(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        $message = WhatsappMessage::withoutGlobalScopes()->where('external_message_id', 'wamid.in')->firstOrFail();
        $this->assertSame($customer->id, $message->customer_id);
        $this->assertSame('Need fitting', $message->body);
        $this->assertNotNull($message->contact->last_customer_message_at);
    }

    public function test_unknown_and_unsupported_inbound_messages_are_retained_safely(): void
    {
        $i = $this->integration();
        $event = WhatsappWebhookEvent::create(['whatsapp_integration_id' => $i->id, 'event_key' => str_repeat('b', 64), 'payload' => $this->payload($i, [['id' => 'wamid.image', 'from' => '255713333333', 'timestamp' => (string) now()->timestamp, 'type' => 'image', 'image' => ['id' => 'media']]], []), 'accepted_at' => now()]);
        (new ProcessWhatsappWebhook($event->id))->handle(app(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        $message = WhatsappMessage::withoutGlobalScopes()->where('external_message_id', 'wamid.image')->firstOrFail();
        $this->assertNull($message->customer_id);
        $this->assertSame('image', $message->message_type);
    }

    public function test_out_of_order_lifecycle_never_regresses_and_keeps_timestamps(): void
    {
        $i = $this->integration();
        $contact = $this->contact($i, now());
        $message = $this->message($i, $contact, ['status' => 'accepted', 'external_message_id' => 'wamid.out']);
        $lifecycle = app(WhatsappMessageLifecycle::class);
        $lifecycle->apply($message, 'read', CarbonImmutable::parse('2026-08-03 12:00:00'));
        $lifecycle->apply($message->fresh(), 'sent', CarbonImmutable::parse('2026-08-03 10:00:00'));
        $lifecycle->apply($message->fresh(), 'delivered', CarbonImmutable::parse('2026-08-03 11:00:00'));
        $fresh = $message->fresh();
        $this->assertSame('read', $fresh->status);
        $this->assertNotNull($fresh->sent_at);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNotNull($fresh->read_at);
    }

    public function test_waba_subscription_is_posted_and_verified(): void
    {
        $i = $this->integration();
        Http::fake(fn ($request) => $request->method() === 'POST' ? Http::response(['success' => true]) : Http::response(['data' => [['id' => 'app']]]));
        $result = app(WhatsAppService::class)->configureWebhooks($i, 'https://example.test/callback');
        $this->assertTrue($result['success']);
        $this->assertSame('verified', $i->fresh()->webhook_status);
        Http::assertSentCount(2);
    }

    public function test_transient_server_failure_is_retryable_and_not_finalized_immediately(): void
    {
        $i = $this->integration();
        $message = $this->message($i, $this->contact($i, now()));
        Http::fake(['*' => Http::response(['error' => ['code' => 2]], 500)]);
        $this->expectException(\RuntimeException::class);
        try {
            (new SendWhatsappMessage($message->id))->handle(app(\App\Contracts\WhatsAppProvider::class), app(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class));
        } finally {
            $this->assertSame('submitting', $message->fresh()->status);
        }
    }

    public function test_read_message_cannot_be_regressed_by_late_failure(): void
    {
        $i = $this->integration();
        $message = $this->message($i, $this->contact($i, now()), ['status' => 'read', 'external_message_id' => 'wamid.read', 'read_at' => now()]);
        app(WhatsappMessageLifecycle::class)->apply($message, 'failed', CarbonImmutable::now()->subMinute(), '131026', 'Recipient failed');
        $this->assertSame('read', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->failed_at);
    }

    public function test_mark_as_read_uses_meta_transport(): void
    {
        $i = $this->integration();
        $message = WhatsappMessage::withoutGlobalScopes()->create(['branch_id' => $i->branch_id, 'whatsapp_integration_id' => $i->id, 'external_message_id' => 'wamid.in', 'direction' => 'inbound', 'message_type' => 'text', 'phone' => '+255712345678', 'body' => 'Hi', 'status' => 'delivered']);
        Http::fake(['*' => Http::response(['success' => true])]);
        $this->assertTrue(app(WhatsAppService::class)->markAsRead($message)['success']);
        Http::assertSent(fn ($request) => $request['status'] === 'read' && $request['message_id'] === 'wamid.in');
    }

    public function test_subscription_permission_failure_is_sanitized(): void
    {
        $i = $this->integration();
        Http::fake(['*' => Http::response(['error' => ['code' => 200, 'message' => 'token access-token denied']], 403)]);
        $result = app(WhatsAppService::class)->configureWebhooks($i, 'https://example.test/callback');
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('access-token', $result['error_message']);
        $this->assertSame('subscription_error', $i->fresh()->webhook_status);
    }

    private function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::create(['branch_id' => $this->branch->id, 'webhook_key' => (string) str()->uuid(), 'enabled' => true, 'waba_id' => '67890', 'phone_number_id' => '12345', 'access_token' => 'access-token', 'app_secret' => 'app-secret', 'webhook_verify_token' => 'verify-token-long', 'connection_status' => 'connected']);
    }

    private function contact(WhatsappIntegration $i, $at): WhatsappContact
    {
        return WhatsappContact::withoutGlobalScopes()->updateOrCreate(['whatsapp_integration_id' => $i->id, 'phone' => '+255712345678'], ['branch_id' => $i->branch_id, 'last_customer_message_at' => $at]);
    }

    private function message(WhatsappIntegration $i, WhatsappContact $c, array $extra = []): WhatsappMessage
    {
        return WhatsappMessage::withoutGlobalScopes()->create(array_merge(['branch_id' => $i->branch_id, 'whatsapp_integration_id' => $i->id, 'whatsapp_contact_id' => $c->id, 'direction' => 'outbound', 'message_type' => 'text', 'phone' => $c->phone, 'body' => 'Hello', 'status' => 'queued', 'queued_at' => now()], $extra));
    }

    private function payload(WhatsappIntegration $i, array $messages, array $statuses): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => $i->waba_id, 'changes' => [['field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => $i->phone_number_id], 'messages' => $messages, 'statuses' => $statuses]]]]]];
    }

    private function webhook(WhatsappIntegration $i,string $raw,string $signature)
    {
        return $this->call('POST',route('webhooks.meta-whatsapp',['webhookKey' => $i->webhook_key]),[],[],[],['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'],$raw);
    }
}
