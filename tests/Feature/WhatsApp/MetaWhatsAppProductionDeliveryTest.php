<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\SmsStatus;
use App\Jobs\ProcessWhatsappWebhook;
use App\Jobs\SendWhatsappMessage;
use App\Models\BeemConfig;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SmsTemplate;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\Sms\SmsService;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaWhatsAppProductionDeliveryTest extends TestCase
{
    public function test_enabled_automation_queues_real_template_with_context_linkage_and_wamid(): void
    {
        Queue::fake();
        $this->actingAsRole('admin');
        $customer = Customer::factory()->for($this->branch)->create([
            'phone' => '0712345678',
            'whatsapp_opted_in_at' => now(),
        ]);
        $order = Order::factory()->for($customer)->create(['branch_id' => $this->branch->id]);
        $integration = $this->integration();
        $this->enableNotification('order_created');
        $this->template($integration, 'APPROVED');

        $log = app(SmsService::class)->sendTemplate('order_created', $customer->phone, [
            'customer_name' => $customer->name,
            'order_number' => $order->order_no,
        ], $order, auth()->user());

        $this->assertSame('meta_whatsapp', $log->provider);
        $this->assertNotNull($log->whatsapp_message_id);
        $message = $log->whatsappMessage;
        $this->assertSame($customer->id, $message->customer_id);
        $this->assertTrue($message->context->is($order));
        $this->assertSame('order_created', $message->safe_metadata['notification_code']);
        $this->assertSame('order_created', $message->safe_metadata['template_name']);

        Http::fake(['*/12345/messages' => Http::response(['messages' => [['id' => 'wamid.production']]])]);
        (new SendWhatsappMessage($message->id))->handle(app(\App\Contracts\WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));

        Http::assertSent(fn ($request) => $request['type'] === 'template'
            && $request['template']['name'] === 'order_created'
            && $request['template']['language']['code'] === 'en_US'
            && $request['template']['components'][0]['parameters'][0]['text'] === $customer->name
            && $request['template']['components'][0]['parameters'][1]['text'] === $order->order_no
            && ! isset($request['text']));
        $this->assertSame('wamid.production', $message->fresh()->external_message_id);
        $this->assertSame('wamid.production', $log->fresh()->provider_message_id);
        $this->assertSame(SmsStatus::Sent, $log->fresh()->status);
    }

    public function test_missing_unapproved_and_unconsented_templates_fail_safely(): void
    {
        $this->actingAsRole('admin');
        $customer = Customer::factory()->for($this->branch)->create(['phone' => '0712345678']);
        $this->integration();
        $this->enableNotification('order_created');

        $missing = app(SmsService::class)->sendTemplate('order_created', $customer->phone, ['customer_name' => $customer->name, 'order_number' => 'O-1'], $customer);
        $this->assertSame(SmsStatus::Skipped, $missing->status);
        $this->assertSame('whatsapp_template_missing_or_unapproved', $missing->skip_reason);

        $template = $this->template(WhatsappIntegration::forBranch($this->branch->id), 'PENDING');
        $pending = app(SmsService::class)->sendTemplate('order_created', $customer->phone, ['customer_name' => $customer->name, 'order_number' => 'O-1'], $customer);
        $this->assertSame('whatsapp_template_missing_or_unapproved', $pending->skip_reason);

        $template->update(['meta_status' => 'APPROVED']);
        $noConsent = app(SmsService::class)->sendTemplate('order_created', $customer->phone, ['customer_name' => $customer->name, 'order_number' => 'O-1'], $customer);
        $this->assertSame('whatsapp_opt_in_required', $noConsent->skip_reason);
    }

    public function test_lifecycle_updates_linked_log_idempotently_without_regression(): void
    {
        $this->actingAsRole('admin');
        [$message, $log] = $this->linkedAcceptedMessage();
        $this->processStatus($message, 'read', CarbonImmutable::parse('2026-08-10 12:00:00'));
        $this->processStatus($message, 'delivered', CarbonImmutable::parse('2026-08-10 11:00:00'));
        $this->processStatus($message, 'read', CarbonImmutable::parse('2026-08-10 12:00:00'));

        $this->assertSame('read', $message->fresh()->status);
        $this->assertSame(SmsStatus::Read, $log->fresh()->status);

        $failed = $this->linkedAcceptedMessage('wamid.failed');
        $this->processStatus($failed[0], 'failed', CarbonImmutable::now(), [['code' => 131026]]);
        $this->assertSame(SmsStatus::Failed, $failed[1]->fresh()->status);
    }

    public function test_marketing_template_requires_separate_marketing_opt_in(): void
    {
        Queue::fake();
        $this->actingAsRole('admin');
        $customer = Customer::factory()->for($this->branch)->create([
            'phone' => '0712345678', 'whatsapp_opted_in_at' => now(),
        ]);
        $integration = $this->integration();
        $this->enableNotification('order_created');
        $this->template($integration, 'APPROVED')->update(['category' => 'MARKETING']);

        $blocked = app(SmsService::class)->sendTemplate('order_created', $customer->phone, [
            'customer_name' => $customer->name, 'order_number' => 'O-1',
        ], $customer);
        $this->assertSame('whatsapp_marketing_opt_in_required', $blocked->skip_reason);

        $customer->update(['whatsapp_marketing_opted_in_at' => now()]);
        $queued = app(SmsService::class)->sendTemplate('order_created', $customer->phone, [
            'customer_name' => $customer->name, 'order_number' => 'O-1',
        ], $customer);
        $this->assertNotNull($queued->whatsapp_message_id);
    }

    public function test_job_does_not_resend_an_already_accepted_wamid(): void
    {
        $this->actingAsRole('admin');
        [$message] = $this->linkedAcceptedMessage();
        Http::fake();
        (new SendWhatsappMessage($message->id))->handle(app(\App\Contracts\WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        Http::assertNothingSent();
    }

    public function test_webhook_failure_is_sanitized_and_persisted_for_retry(): void
    {
        $this->actingAsRole('admin');
        $integration = $this->integration();
        $message = WhatsappMessage::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id, 'whatsapp_integration_id' => $integration->id,
            'external_message_id' => 'wamid.failure', 'direction' => 'outbound', 'message_type' => 'text',
            'phone' => '+255700000000', 'body' => 'Existing', 'status' => 'accepted',
        ]);
        $event = WhatsappWebhookEvent::create([
            'whatsapp_integration_id' => $integration->id, 'event_key' => str_repeat('d', 64),
            'payload' => [
                'entry' => [[
                    'changes' => [[
                        'field' => 'messages',
                        'value' => ['statuses' => [[
                            'id' => $message->external_message_id,
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                        ]]],
                    ]],
                ]],
            ],
            'accepted_at' => now(),
        ]);
        $lifecycle = $this->createMock(WhatsappMessageLifecycle::class);
        $lifecycle->method('apply')->willThrowException(new \RuntimeException('secret-token-must-not-leak'));

        try {
            (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), $lifecycle);
            $this->fail('Expected webhook processing to fail.');
        } catch (\RuntimeException) {
            $this->assertStringContainsString('RuntimeException', $event->fresh()->processing_error);
            $this->assertStringNotContainsString('secret-token', $event->fresh()->processing_error);
            $this->assertNull($event->fresh()->processed_at);
        }
    }

    public function test_inbound_customer_lookup_uses_normalized_index_value(): void
    {
        $this->actingAsRole('admin');
        $integration = $this->integration();
        $customer = Customer::factory()->for($this->branch)->create(['phone' => 'not-a-phone']);
        $customer->forceFill(['whatsapp_phone' => '+255712345678'])->saveQuietly();
        $event = WhatsappWebhookEvent::create([
            'whatsapp_integration_id' => $integration->id, 'event_key' => str_repeat('e', 64),
            'payload' => [
                'entry' => [[
                    'changes' => [[
                        'field' => 'messages',
                        'value' => ['messages' => [[
                            'id' => 'wamid.inbound', 'from' => '255712345678',
                            'timestamp' => (string) now()->timestamp, 'type' => 'text',
                            'text' => ['body' => 'Hello'],
                        ]]],
                    ]],
                ]],
            ],
            'accepted_at' => now(),
        ]);
        (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        $this->assertSame($customer->id, WhatsappMessage::withoutGlobalScopes()->where('external_message_id', 'wamid.inbound')->value('customer_id'));
    }

    private function integration(): WhatsappIntegration
    {
        return tap(WhatsappIntegration::forBranch($this->branch->id), fn ($integration) => $integration->update([
            'enabled' => true, 'waba_id' => '67890', 'phone_number_id' => '12345', 'access_token' => 'access-token',
            'app_secret' => 'app-secret', 'webhook_verify_token' => 'verify-token-long',
        ]));
    }

    private function enableNotification(string $code): void
    {
        BeemConfig::instance()->update(['sms_enabled' => false]);
        $settings = SmsTemplate::defaultTemplateSettings();
        $settings[$code]['whatsapp_enabled'] = true;
        $settings[$code]['whatsapp_template_name'] = $code;
        $settings[$code]['whatsapp_template_language'] = 'en_US';
        SmsTemplate::instance()->update(['template_settings' => $settings]);
    }

    private function template(WhatsappIntegration $integration, string $status): WhatsappTemplate
    {
        return WhatsappTemplate::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id, 'whatsapp_integration_id' => $integration->id,
            'name' => 'order_created', 'language' => 'en_US', 'category' => 'UTILITY',
            'local_state' => 'synced', 'meta_status' => $status,
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, order {{2}}']],
            'variable_mappings' => ['1' => 'customer_name', '2' => 'order_number'],
        ]);
    }

    private function linkedAcceptedMessage(string $wamid = 'wamid.linked'): array
    {
        $integration = WhatsappIntegration::forBranch($this->branch->id);
        if (! $integration->isConfigured()) {
            $integration = $this->integration();
        }
        $message = WhatsappMessage::withoutGlobalScopes()->create([
            'branch_id' => $this->branch->id, 'whatsapp_integration_id' => $integration->id,
            'external_message_id' => $wamid, 'direction' => 'outbound', 'message_type' => 'template',
            'phone' => '+255712345678', 'body' => 'Hello', 'status' => 'accepted', 'accepted_at' => now(),
        ]);
        $log = \App\Models\SmsLog::create([
            'branch_id' => $this->branch->id, 'provider' => 'meta_whatsapp', 'template_code' => 'order_created',
            'to' => '+255712345678', 'message' => 'Hello', 'status' => SmsStatus::Sent,
            'provider_message_id' => $wamid, 'whatsapp_message_id' => $message->id,
        ]);

        return [$message, $log];
    }

    private function processStatus(WhatsappMessage $message, string $status, CarbonImmutable $at, array $errors = []): void
    {
        $event = WhatsappWebhookEvent::create([
            'whatsapp_integration_id' => $message->whatsapp_integration_id,
            'event_key' => hash('sha256', $message->external_message_id.$status.$at->timestamp.str()->random()),
            'payload' => [
                'entry' => [[
                    'changes' => [[
                        'field' => 'messages',
                        'value' => ['statuses' => [[
                            'id' => $message->external_message_id,
                            'status' => $status,
                            'timestamp' => (string) $at->timestamp,
                            'errors' => $errors,
                        ]]],
                    ]],
                ]],
            ],
            'accepted_at' => now(),
        ]);
        (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
    }
}
