<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Jobs\ProcessWhatsappWebhook;
use App\Jobs\SendWhatsappMessage;
use App\Livewire\Sms\WhatsappConfigurations;
use App\Livewire\WhatsApp\Templates\Builder;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Services\WhatsApp\Templates\WhatsappTemplateSyncService;
use App\Services\WhatsApp\TwilioWhatsAppProvider;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TwilioWhatsAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAsRole('admin');
    }

    public function test_default_provider_and_encrypted_masked_branch_settings(): void
    {
        $this->assertInstanceOf(TwilioWhatsAppProvider::class, app(WhatsAppProvider::class));
        Livewire::test(WhatsappConfigurations::class)->assertSee('Twilio WhatsApp')
            ->set('twilio_account_sid', 'AC'.str_repeat('a', 32))->set('twilio_from', '+255712345678')
            ->set('twilio_auth_token', 'secret-token')->set('enabled', true)->call('save')
            ->assertHasNoErrors()->assertSet('twilio_auth_token', '')->assertDontSee('secret-token');
        $i = WhatsappIntegration::where('branch_id', $this->branch->id)->sole();
        $this->assertNotSame('secret-token', $i->getRawOriginal('twilio_auth_token'));
        $this->assertArrayNotHasKey('twilio_auth_token', $i->toArray());
        Livewire::test(WhatsappConfigurations::class)->set('twilio_account_sid', 'AC'.str_repeat('b', 32))->call('save')->assertHasErrors('twilio_auth_token');
    }

    public function test_content_creation_approval_and_duplicate_submission_use_one_sid(): void
    {
        $t = $this->draft();
        $this->fakeSubmission();
        $result = app(WhatsappTemplateService::class)->submit($t);
        $this->assertTrue($result['success']);
        $this->assertSame('PENDING', $t->fresh()->twilio_status);
        $this->assertFalse($t->fresh()->isSendable());
        $this->assertSame($this->sid('HX'), $t->fresh()->twilio_content_sid);
        Http::assertSent(fn ($r) => $r->url() === 'https://content.twilio.com/v1/Content' && $r['types']['twilio/text']['body'] === 'Hello {{1}}, order {{2}} is ready.' && json_decode($r->body(), true)['variables']['1'] === 'Amina');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/ApprovalRequests/whatsapp') && $r['name'] === 'order_ready' && $r['category'] === 'UTILITY');
        $this->assertTrue(app(WhatsappTemplateService::class)->submit($t)['already_submitted']);
        Http::assertSentCount(3);
    }

    public function test_approval_failure_retains_content_and_retry_does_not_create_again(): void
    {
        $t = $this->draft();
        Http::fake([
            '*/Content' => Http::response(['sid' => $this->sid('HX')], 201),
            '*/ApprovalRequests' => Http::response(['whatsapp' => ['status' => 'unsubmitted']]),
            '*/ApprovalRequests/whatsapp' => Http::sequence()->push(['code' => 20429, 'message' => 'secret-token'], 429)->push(['status' => 'received']),
        ]);
        $failed = app(WhatsappTemplateService::class)->submit($t);
        $this->assertFalse($failed['success']);
        $this->assertStringNotContainsString('secret-token', json_encode($failed));
        $this->assertSame($this->sid('HX'), $t->fresh()->twilio_content_sid);
        $this->assertTrue(app(WhatsappTemplateService::class)->submit($t)['success']);
        Http::assertSentCount(5);
    }

    public function test_invalid_validation_cannot_submit_and_legacy_meta_approval_is_not_sendable(): void
    {
        $t = $this->draft();
        $hugeVariable = new \App\Data\WhatsApp\TemplateDefinition('safe_name', 'en', 'UTILITY', [['type' => 'BODY', 'text' => 'Hello {{999999999999}}']]);
        $this->assertFalse(app(\App\Services\WhatsApp\Templates\TwilioTemplateManager::class)->validate($hugeVariable)->valid());
        $t->update(['category' => 'AUTHENTICATION']);
        $this->assertFalse(app(WhatsappTemplateService::class)->validate($t)->valid());
        $this->assertFalse(app(WhatsappTemplateService::class)->submit($t)['success']);
        $t->update(['meta_status' => 'APPROVED', 'meta_template_id' => '123']);
        $this->assertFalse($t->fresh()->isSendable());
        Http::assertNothingSent();
    }

    public function test_refresh_preserves_local_body_and_mappings_and_blocks_paused_content(): void
    {
        $t = $this->approved();
        $original = $t->components;
        Http::fake(['*/ApprovalRequests' => Http::response(['whatsapp' => ['status' => 'paused', 'rejection_reason' => 'Quality review']])]);
        $this->assertTrue(app(WhatsappTemplateSyncService::class)->sync($t->integration)['success']);
        $this->assertSame($original, $t->fresh()->components);
        $this->assertSame('customer_name', $t->fresh()->variable_mappings[1]);
        $this->assertFalse($t->fresh()->isSendable());
        $this->assertSame('PAUSED', $t->fresh()->twilio_status);
    }

    public function test_exact_content_sid_variables_and_callback_are_sent_without_body_and_no_resend(): void
    {
        Queue::fake();
        $t = $this->approved();
        $result = app(WhatsAppService::class)->queueTemplate($this->branch->id, '+255700000000', $t, ['customer_name' => 'Amina', 'order_number' => 'ORD-42']);
        $this->assertTrue($result['success']);
        Http::fake(['*/Messages.json' => Http::response(['sid' => $this->sid('SM')], 201)]);
        $job = new SendWhatsappMessage($result['message_id']);
        $job->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        $job->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['ContentSid'] === $this->sid('HX') && json_decode($r['ContentVariables'], true) === [1 => 'Amina', 2 => 'ORD-42'] && $r['To'] === 'whatsapp:+255700000000' && $r['From'] === 'whatsapp:+255712345678' && str_contains($r['StatusCallback'], '/webhooks/twilio/whatsapp/') && ! isset($r['Body']));
        $this->assertSame('accepted', WhatsappMessage::find($result['message_id'])->status);
    }

    public function test_changed_account_or_template_after_queue_is_blocked(): void
    {
        Queue::fake();
        $t = $this->approved();
        $queued = app(WhatsAppService::class)->queueTemplate($this->branch->id, '+255700000000', $t, ['customer_name' => 'Amina', 'order_number' => 'ORD-42']);
        $t->update(['twilio_status' => 'PAUSED']);
        (new SendWhatsappMessage($queued['message_id']))->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        $this->assertSame('template_not_approved', WhatsappMessage::find($queued['message_id'])->failure_code);
        Http::assertNothingSent();
    }

    public function test_webhook_signature_account_sender_and_duplicate_protection(): void
    {
        Queue::fake();
        $i = $this->integration();
        $params = ['AccountSid' => $i->twilio_account_sid, 'MessageSid' => $this->sid('SM'), 'From' => 'whatsapp:+255700000000', 'To' => 'whatsapp:'.$i->twilio_from, 'Body' => 'Hello', 'SmsStatus' => 'received'];
        $url = route('webhooks.twilio-whatsapp', ['webhookKey' => $i->webhook_key]);
        $this->post($url, $params, ['Content-Type' => 'application/x-www-form-urlencoded', 'X-Twilio-Signature' => 'invalid'])->assertForbidden();
        $this->webhook($i, $params)->assertOk();
        $this->webhook($i, $params)->assertOk();
        $this->assertSame(1, WhatsappWebhookEvent::count());
        $event = WhatsappWebhookEvent::sole();
        (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        $this->assertSame('Hello', WhatsappMessage::sole()->body);
        $this->assertTrue(app(WhatsAppService::class)->canSendFreeFormMessage($i, '+255700000000'));
        $i->twilio_from = '+255799999999';
        $this->assertFalse(app(WhatsAppService::class)->canSendFreeFormMessage($i, '+255700000000'));
        $i->twilio_from = '+255712345678';
        $this->webhook($i, array_replace($params, ['AccountSid' => 'AC'.str_repeat('b', 32)]))->assertForbidden();
        $this->webhook($i, array_replace($params, ['To' => 'whatsapp:+255799999999']))->assertForbidden();
    }

    public function test_delivery_callback_advances_linked_log_and_does_not_regress_read(): void
    {
        Queue::fake();
        $i = $this->integration();
        $m = WhatsappMessage::create(['branch_id' => $this->branch->id, 'whatsapp_integration_id' => $i->id, 'external_message_id' => $this->sid('SM'), 'direction' => 'outbound', 'message_type' => 'template', 'phone' => '+255700000000', 'status' => 'accepted']);
        $log = \App\Models\SmsLog::create(['branch_id' => $this->branch->id, 'provider' => 'twilio_whatsapp', 'to' => $m->phone, 'message' => 'Hello', 'status' => 'sent', 'whatsapp_message_id' => $m->id]);
        foreach (['read', 'delivered'] as $status) {
            $this->webhook($i, ['AccountSid' => $i->twilio_account_sid, 'MessageSid' => $m->external_message_id, 'From' => 'whatsapp:'.$i->twilio_from, 'To' => 'whatsapp:'.$m->phone, 'MessageStatus' => $status])->assertOk();
            $event = WhatsappWebhookEvent::latest('id')->first();
            (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        }
        $this->assertSame('read', $m->fresh()->status);
        $this->assertSame(\App\Enums\SmsStatus::Read, $log->fresh()->status);
    }

    public function test_branch_and_permission_checks_protect_builder_and_settings(): void
    {
        $t = $this->draft();
        $t->forceFill(['branch_id' => $this->otherBranch->id])->saveQuietly();
        try {
            Livewire::test(Builder::class, ['template' => $t->id]);
            $this->fail('A foreign branch template must not be accessible.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->actingAsRole('sales');
        Livewire::test(WhatsappConfigurations::class)->assertForbidden();
    }

    public function test_edited_submitted_content_requires_duplicate_and_duplicate_clears_provenance(): void
    {
        $t = $this->approved();
        $t->update(['name' => 'changed_name']);
        $this->assertFalse($t->fresh()->isSendable());
        app(WhatsappTemplateService::class)->validate($t);
        $this->assertSame('immutable_content', app(WhatsappTemplateService::class)->submit($t)['error_code']);
        $copy = app(WhatsappTemplateService::class)->duplicate($t);
        $this->assertNull($copy->twilio_content_sid);
        $this->assertNull($copy->twilio_status);
        Http::assertNothingSent();
    }

    public function test_notification_uses_twilio_log_and_consent_and_retry_preserves_template(): void
    {
        Queue::fake();
        $t = $this->approved();
        $t->update(['name' => 'order_created']);
        $t->update(['twilio_content_fingerprint' => $t->definitionFingerprint()]);
        $customer = \App\Models\Customer::factory()->for($this->branch)->create(['phone' => '+255700000000']);
        $order = \App\Models\Order::factory()->for($customer)->create(['branch_id' => $this->branch->id]);
        \App\Models\BeemConfig::instance()->update(['sms_enabled' => false]);
        $settings = \App\Models\SmsTemplate::defaultTemplateSettings();
        $settings['order_created']['whatsapp_enabled'] = true;
        $settings['order_created']['whatsapp_template_name'] = 'order_created';
        $settings['order_created']['whatsapp_template_language'] = 'en';
        \App\Models\SmsTemplate::instance()->update(['template_settings' => $settings]);
        $service = app(\App\Services\Sms\SmsService::class);
        $values = ['customer_name' => $customer->name, 'order_number' => $order->order_no];
        $blocked = $service->sendTemplate('order_created', $customer->phone, $values, $order, auth()->user());
        $this->assertSame('whatsapp_opt_in_required', $blocked->skip_reason);
        $customer->update(['whatsapp_opted_in_at' => now()]);
        $log = $service->sendTemplate('order_created', $customer->phone, $values, $order, auth()->user());
        $this->assertSame('twilio_whatsapp', $log->provider);
        $this->assertNotNull($log->whatsapp_message_id);
        $original = $log->whatsappMessage;
        $original->update(['status' => 'failed', 'failure_code' => '63016']);
        $log->update(['status' => 'failed']);
        $retry = $service->retryFailedLog($log, auth()->user());
        $this->assertSame('template', $retry->whatsappMessage->message_type);
        $this->assertSame($original->safe_metadata['components'], $retry->whatsappMessage->safe_metadata['components']);
        $this->assertNotSame($log->id, $retry->id);
        $this->assertSame($retry->id, $service->retryFailedLog($log, auth()->user())->id);
        Http::assertNothingSent();
    }

    public function test_uncertain_creation_does_not_repeat_and_can_recover_verified_content(): void
    {
        $t = $this->draft();
        Http::fake(['*/Content' => Http::response([], 503)]);
        $this->assertFalse(app(WhatsappTemplateService::class)->submit($t)['success']);
        $this->assertSame('creation_unknown', $t->fresh()->local_state);
        app(WhatsappTemplateService::class)->validate($t->fresh());
        $this->assertSame('creation_unknown', app(WhatsappTemplateService::class)->submit($t)['error_code']);
        Http::assertSentCount(1);
        Http::fake(['*/Content/HX*' => Http::response(['sid' => $this->sid('HX'), 'account_sid' => $this->sid('AC'), 'friendly_name' => $t->name, 'language' => 'en', 'types' => ['twilio/text' => ['body' => $t->components[0]['text']]], 'variables' => $t->components[0]['examples']])]);
        $this->assertTrue(app(\App\Services\WhatsApp\Templates\TwilioTemplateManager::class)->reconcile($t, $this->sid('HX'))['success']);
        $this->assertSame($this->sid('HX'), $t->fresh()->twilio_content_sid);
    }

    public function test_builder_renders_and_unsaved_changes_cannot_submit(): void
    {
        $t = $this->draft();
        Livewire::test(Builder::class, ['template' => $t->id])->assertSee('Submit through Twilio')
            ->set('body', 'Unsaved change')->call('submit')->assertSee('Save and validate your latest changes');
        Http::assertNothingSent();
    }

    public function test_delete_uses_content_sid_and_does_not_mark_failed_deletion_successful(): void
    {
        $t = $this->approved();
        Http::fake(['*/Content/HX*' => Http::sequence()->push(['code' => 20003], 403)->push([], 204)]);
        $this->assertFalse(app(WhatsappTemplateService::class)->delete($t)['success']);
        $this->assertSame('APPROVED', $t->fresh()->twilio_status);
        $this->assertTrue(app(WhatsappTemplateService::class)->delete($t)['success']);
        $this->assertSame('DELETED', $t->fresh()->twilio_status);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), $this->sid('HX')));
    }

    public function test_connection_check_verifies_account_and_sanitizes_authentication_failure(): void
    {
        $i = $this->integration();
        Http::fake(['*/Accounts/AC*.json' => Http::sequence()->push(['sid' => $i->twilio_account_sid, 'status' => 'active', 'friendly_name' => 'Test account'])->push(['code' => 20003, 'message' => 'secret-token'], 401)]);
        $this->assertTrue(app(WhatsAppService::class)->testConnection($i)['success']);
        $this->assertSame('connected', $i->fresh()->connection_status);
        $result = app(WhatsAppService::class)->testConnection($i);
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('secret-token', json_encode($result));
        $this->assertSame('connection_error', $i->fresh()->connection_status);
    }

    public function test_ambiguous_send_is_not_automatically_repeated(): void
    {
        Queue::fake();
        $t = $this->approved();
        $result = app(WhatsAppService::class)->queueTemplate($this->branch->id, '+255700000000', $t, ['customer_name' => 'Amina', 'order_number' => 'ORD-42']);
        Http::fake(['*/Messages.json' => Http::response(['code' => 20500], 503)]);
        $job = new SendWhatsappMessage($result['message_id']);
        $job->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        $job->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        Http::assertSentCount(1);
        $this->assertSame('delivery_unknown', WhatsappMessage::find($result['message_id'])->failure_code);
    }

    public function test_old_meta_queue_and_log_cannot_be_sent_through_twilio(): void
    {
        $i = $this->integration();
        $message = WhatsappMessage::create(['branch_id' => $this->branch->id, 'whatsapp_integration_id' => $i->id, 'direction' => 'outbound', 'message_type' => 'text', 'phone' => '+255700000000', 'body' => 'Historical', 'status' => 'queued']);
        (new SendWhatsappMessage($message->id))->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        $this->assertSame('provider_changed', $message->fresh()->failure_code);
        Http::assertNothingSent();
        $log = \App\Models\SmsLog::create(['branch_id' => $this->branch->id, 'provider' => 'meta_whatsapp', 'to' => $message->phone, 'message' => 'Historical', 'status' => 'failed']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\Sms\SmsService::class)->retryFailedLog($log);
    }

    private function integration(): WhatsappIntegration
    {
        $i = WhatsappIntegration::forBranch($this->branch->id);
        $i->update(['enabled' => true, 'twilio_account_sid' => $this->sid('AC'), 'twilio_auth_token' => 'secret-token', 'twilio_from' => '+255712345678']);

        return $i;
    }

    private function draft(): WhatsappTemplate
    {
        $t = app(WhatsappTemplateService::class)->saveDraft($this->integration(), ['name' => 'order_ready', 'language' => 'en', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, order {{2}} is ready.', 'examples' => [1 => 'Amina', 2 => 'ORD-42']]], 'variable_mappings' => [1 => 'customer_name', 2 => 'order_number']]);
        $this->assertTrue(app(WhatsappTemplateService::class)->validate($t)->valid());

        return $t->fresh();
    }

    private function approved(): WhatsappTemplate
    {
        $t = $this->draft();
        $t->update(['twilio_content_sid' => $this->sid('HX'), 'twilio_account_sid' => $this->sid('AC'), 'twilio_content_fingerprint' => $t->definitionFingerprint(), 'twilio_status' => 'APPROVED']);

        return $t->fresh();
    }

    private function sid(string $prefix): string
    {
        return $prefix.str_repeat('a', 32);
    }

    private function fakeSubmission(): void
    {
        Http::fake(['*/Content' => Http::response(['sid' => $this->sid('HX')], 201), '*/ApprovalRequests' => Http::response(['whatsapp' => ['status' => 'unsubmitted']]), '*/ApprovalRequests/whatsapp' => Http::response(['status' => 'received'])]);
    }

    private function webhook(WhatsappIntegration $i, array $params)
    {
        ksort($params, SORT_STRING);
        $url = rtrim(config('app.url'), '/').route('webhooks.twilio-whatsapp', ['webhookKey' => $i->webhook_key], false);
        $signed = $url;
        foreach ($params as $k => $v) {
            $signed .= $k.$v;
        }

        return $this->post($url, $params, ['Content-Type' => 'application/x-www-form-urlencoded', 'X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $signed, $i->twilio_auth_token, true))]);
    }
}
