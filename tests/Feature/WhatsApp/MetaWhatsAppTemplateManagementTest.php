<?php

namespace Tests\Feature\WhatsApp;

use App\Data\WhatsApp\TemplateDefinition;
use App\Jobs\ProcessWhatsappWebhook;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\Templates\MetaWhatsappTemplateValidator;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Services\WhatsApp\Templates\WhatsappTemplateSyncService;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppTemplateManagementTest extends TestCase
{
    public function test_valid_template_and_risk_warnings_are_structured(): void
    {
        $validator = app(MetaWhatsappTemplateValidator::class);
        $valid = $validator->validate(TemplateDefinition::fromArray($this->definition()));
        $this->assertTrue($valid->valid());
        $risky = $this->definition();
        $risky['components'][0]['text'] = 'Special discount offer {{1}}';
        $result = $validator->validate(TemplateDefinition::fromArray($risky));
        $this->assertTrue($result->valid());
        $this->assertNotEmpty($result->warnings);
    }

    public function test_invalid_identity_variables_examples_and_media_are_blocking(): void
    {
        $d = $this->definition();
        $d['name'] = 'Invalid Name';
        $d['category'] = 'OTHER';
        $d['language'] = 'xx_BAD';
        $d['components'][0] = ['type' => 'BODY', 'text' => 'Hello {{2}}', 'examples' => []];
        $d['components'][] = ['type' => 'HEADER', 'format' => 'DOCUMENT'];
        $result = app(MetaWhatsappTemplateValidator::class)->validate(TemplateDefinition::fromArray($d));
        $this->assertFalse($result->valid());
        $this->assertGreaterThanOrEqual(5, count($result->errors));
    }

    public function test_saving_editing_and_duplicating_draft_never_calls_meta_and_invalidates_validation(): void
    {
        Http::preventStrayRequests();
        $i = $this->integration();
        $service = app(WhatsappTemplateService::class);
        $t = $service->saveDraft($i, $this->definition());
        $this->assertSame('draft', $t->local_state);
        $service->validate($t);
        $changed = $this->definition();
        $changed['components'][0]['text'] = 'Changed {{1}}';
        $t = $service->saveDraft($i, $changed, $t);
        $this->assertNull($t->validation_fingerprint);
        $copy = $service->duplicate($t);
        $this->assertSame('draft', $copy->local_state);
        $this->assertNull($copy->meta_template_id);
    }

    public function test_valid_submission_persists_meta_id_pending_status_and_prevents_duplicate_submission(): void
    {
        $i = $this->integration();
        $service = app(WhatsappTemplateService::class);
        $t = $service->saveDraft($i, $this->definition());
        $service->validate($t);
        Http::fake(['*/message_templates' => Http::response(['id' => 'meta-1', 'status' => 'PENDING', 'category' => 'UTILITY'])]);
        $result = $service->submit($t);
        $this->assertTrue($result['success']);
        $this->assertSame('meta-1', $t->fresh()->meta_template_id);
        $this->assertSame('PENDING', $t->fresh()->meta_status);
        $this->assertFalse($t->fresh()->isSendable());
        $again = $service->submit($t->fresh());
        $this->assertTrue($again['already_submitted']);
        Http::assertSentCount(1);
    }

    public function test_http_success_only_uses_returned_authoritative_status(): void
    {
        $i = $this->integration();
        $s = app(WhatsappTemplateService::class);
        $t = $s->saveDraft($i, $this->definition());
        $s->validate($t);
        Http::fake(['*' => Http::response(['id' => 'meta-2', 'status' => 'APPROVED'])]);
        $s->submit($t);
        $this->assertTrue($t->fresh()->isSendable());
    }

    public function test_meta_submission_failure_is_sanitized(): void
    {
        $i = $this->integration();
        $s = app(WhatsappTemplateService::class);
        $t = $s->saveDraft($i, $this->definition());
        $s->validate($t);
        Http::fake(['*' => Http::response(['error' => ['code' => 100, 'message' => 'token access-token leaked']], 400)]);
        $result = $s->submit($t);
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('access-token', $result['error_message']);
        $this->assertNull($t->fresh()->meta_template_id);
    }

    public function test_paginated_sync_imports_and_updates_without_touching_local_draft(): void
    {
        $i = $this->integration();
        $draft = app(WhatsappTemplateService::class)->saveDraft($i, array_replace($this->definition(), ['name' => 'local_draft']));
        $page = 0;
        Http::fake(function ($request) use (&$page) {
            $page++;

            return $page === 1 ? Http::response(['data' => [['id' => 'm1', 'name' => 'external_one', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi']]]], 'paging' => ['cursors' => ['after' => 'next'], 'next' => 'url']]) : Http::response(['data' => [['id' => 'm2', 'name' => 'external_two', 'language' => 'sw', 'category' => 'MARKETING', 'status' => 'PENDING', 'components' => [['type' => 'BODY', 'text' => 'Habari']]]]]);
        });
        $result = app(WhatsappTemplateSyncService::class)->sync($i);
        $this->assertSame(2, $result['count']);
        $this->assertDatabaseCount('whatsapp_templates', 3);
        $this->assertSame('draft', $draft->fresh()->local_state);
        app(WhatsappTemplateSyncService::class)->sync($i);
        $this->assertSame(1, WhatsappTemplate::withoutGlobalScopes()->where('meta_template_id', 'm1')->count());
    }

    public function test_template_status_webhook_records_rejection_history_and_unknown_status(): void
    {
        $i = $this->integration();
        $t = WhatsappTemplate::withoutGlobalScopes()->create(['branch_id' => $i->branch_id, 'whatsapp_integration_id' => $i->id, 'meta_template_id' => 'm1', 'name' => 'order_ready', 'language' => 'en_US', 'category' => 'UTILITY', 'local_state' => 'synced', 'meta_status' => 'PENDING', 'components' => []]);
        $payload = ['entry' => [['changes' => [['field' => 'message_template_status_update', 'value' => ['message_template_id' => 'm1', 'event' => 'REJECTED', 'reason' => 'Body category mismatch']]]]]];
        $event = WhatsappWebhookEvent::create(['whatsapp_integration_id' => $i->id, 'event_key' => str_repeat('c', 64), 'payload' => $payload, 'accepted_at' => now()]);
        (new ProcessWhatsappWebhook($event->id))->handle(app(WhatsAppPhoneNormalizer::class), app(WhatsappMessageLifecycle::class));
        $this->assertSame('REJECTED', $t->fresh()->meta_status);
        $this->assertSame('Body category mismatch', $t->fresh()->rejection_reason);
        $this->assertDatabaseHas('whatsapp_template_status_histories', ['whatsapp_template_id' => $t->id, 'source' => 'webhook']);
    }

    public function test_failed_meta_deletion_does_not_mark_template_deleted(): void
    {
        $i = $this->integration();
        $t = WhatsappTemplate::withoutGlobalScopes()->create(['branch_id' => $i->branch_id, 'whatsapp_integration_id' => $i->id, 'meta_template_id' => 'm1', 'name' => 'order_ready', 'language' => 'en_US', 'category' => 'UTILITY', 'local_state' => 'synced', 'meta_status' => 'APPROVED', 'components' => []]);
        Http::fake(['*' => Http::response(['error' => ['code' => 200]], 403)]);
        $result = app(WhatsappTemplateService::class)->delete($t);
        $this->assertFalse($result['success']);
        $this->assertSame('APPROVED', $t->fresh()->meta_status);
    }

    public function test_authorization_and_branch_isolation_protect_template_management(): void
    {
        $this->actingAsRole('admin');
        $i = $this->integration();
        $this->get(route('whatsapp-templates.index'))->assertOk();
        $this->get(route('whatsapp-templates.create'))->assertOk();
        $other = WhatsappIntegration::create(['branch_id' => $this->otherBranch->id, 'webhook_key' => (string) str()->uuid(), 'enabled' => false]);
        WhatsappTemplate::withoutGlobalScopes()->create(['branch_id' => $this->otherBranch->id, 'whatsapp_integration_id' => $other->id, 'name' => 'other', 'language' => 'en_US', 'category' => 'UTILITY', 'local_state' => 'draft', 'components' => []]);
        $this->assertNull(WhatsappTemplate::where('name', 'other')->first());
        $this->actingAsRole('sales');
        $this->get(route('whatsapp-templates.index'))->assertForbidden();
    }

    public function test_media_header_examples_validate_for_image_video_and_pdf_handles(): void
    {
        $validator = app(MetaWhatsappTemplateValidator::class);
        foreach (['IMAGE', 'VIDEO', 'DOCUMENT'] as $format) {
            $d = $this->definition();
            array_unshift($d['components'], ['type' => 'HEADER', 'format' => $format, 'example_handle' => 'handle-'.$format]);
            $this->assertTrue($validator->validate(TemplateDefinition::fromArray($d))->valid(), $format);
        }
    }

    public function test_explicit_meta_edit_and_successful_deletion_are_stateful(): void
    {
        $i = $this->integration();
        $s = app(WhatsappTemplateService::class);
        $t = $s->saveDraft($i, $this->definition());
        $s->validate($t);
        Http::fake(['*' => Http::response(['id' => 'meta-edit', 'status' => 'PENDING'])]);
        $s->submit($t);
        $changed = $this->definition();
        $changed['components'][0]['text'] = 'Updated hello {{1}}, order {{2}}.';
        $t = $s->saveDraft($i, $changed, $t->fresh());
        $s->validate($t);
        Http::fake(['*' => Http::response(['success' => true])]);
        $this->assertTrue($s->submit($t)['success']);
        $this->assertTrue($t->fresh()->hasLocalDivergence() === false);
        $this->assertTrue($s->delete($t->fresh())['success']);
        $this->assertSame('DELETED', $t->fresh()->meta_status);
    }

    private function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::firstOrCreate(['branch_id' => $this->branch->id], ['webhook_key' => (string) str()->uuid(), 'enabled' => true, 'waba_id' => '67890', 'phone_number_id' => '12345', 'meta_app_id' => 'app1', 'access_token' => 'access-token', 'app_secret' => 'app-secret', 'webhook_verify_token' => 'verify-token-long', 'connection_status' => 'connected']);
    }

    private function definition(): array
    {
        return ['name' => 'order_ready', 'language' => 'en_US', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, order {{2}} is ready.', 'examples' => ['1' => 'Amina', '2' => 'ORD-42']]], 'variable_mappings' => ['1' => 'customer_name', '2' => 'order_number']];
    }
}
