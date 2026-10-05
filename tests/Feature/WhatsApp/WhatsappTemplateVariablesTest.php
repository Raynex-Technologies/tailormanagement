<?php

namespace Tests\Feature\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Jobs\SendWhatsappMessage;
use App\Livewire\WhatsApp\Templates\Builder;
use App\Models\SmsTemplate;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappTemplateVariablesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('admin');
        config(['twilio.active' => true]);
        Http::preventStrayRequests();
    }

    public function test_picker_includes_every_default_sms_variable_and_uses_sms_labels(): void
    {
        $options = SmsTemplate::variableOptions();
        $expected = array_keys(SmsTemplate::variableDefinitions());
        foreach (SmsTemplate::CATEGORIES as $category) {
            $expected = [...$expected, ...SmsTemplate::variablesForCategory($category)];
        }
        $this->assertEqualsCanonicalizing(array_unique($expected), array_keys($options));
        $this->assertEqualsCanonicalizing(array_keys($options), array_keys(SmsTemplate::variableExamples()));
        foreach (SmsTemplate::variableExamples() as $example) {
            $this->assertNotSame('', trim($example));
        }
        $this->assertSame(SmsTemplate::variableDefinitions()['customer_name'], $options['customer_name']);
        Livewire::test(Builder::class)
            ->assertSeeInOrder(['Message preview', 'SMS template variables'])
            ->assertDontSee('wire:model="selectedVariable"', false)
            ->assertSee('wire:click="addVariable(\'customer_name\')"', false)
            ->assertSee('{appointment_date}')
            ->assertSee('{message}')
            ->assertSee('{order_number}')
            ->assertSee('{payment_amount}');
    }

    public function test_inserted_sms_mapping_survives_save_and_resolves_to_actual_twilio_variables(): void
    {
        Queue::fake();
        $integration = WhatsappIntegration::forBranch($this->branch->id);
        $integration->update(['enabled' => true, 'twilio_account_sid' => 'AC'.str_repeat('a', 32), 'twilio_auth_token' => 'test-token', 'twilio_from' => '+255712345678']);

        $editor = Livewire::test(Builder::class)
            ->set('name', 'appointment_notice')
            ->set('body', 'Your appointment is on')
            ->call('addVariable', 'appointment_date')
            ->assertSet('body', 'Your appointment is on {{1}}')
            ->assertSet('variable_mappings.1', 'appointment_date')
            ->assertSet('examples.1', SmsTemplate::variableExamples()['appointment_date'])
            ->call('validateTemplate')->assertHasNoErrors();
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $this->assertSame('ready_to_submit', $template->local_state);
        Livewire::test(Builder::class, ['template' => $template->id])
            ->assertSet('variable_mappings.1', 'appointment_date')
            ->assertSet('examples.1', SmsTemplate::variableExamples()['appointment_date']);

        $template->update(['twilio_content_sid' => 'HX'.str_repeat('b', 32), 'twilio_account_sid' => $integration->twilio_account_sid, 'twilio_content_fingerprint' => $template->definitionFingerprint(), 'twilio_status' => 'APPROVED']);
        $queued = app(WhatsAppService::class)->queueTemplate($this->branch->id, '+255700000000', $template->fresh(), ['appointment_date' => '15 October 2026']);
        $this->assertTrue($queued['success']);
        Http::fake(['*/Messages.json' => Http::response(['sid' => 'SM'.str_repeat('c', 32)], 201)]);
        (new SendWhatsappMessage($queued['message_id']))->handle(app(WhatsAppProvider::class), app(WhatsAppPhoneNormalizer::class));
        Http::assertSent(fn ($request) => json_decode($request['ContentVariables'], true) === [1 => '15 October 2026']);
        Http::assertSentCount(1);
    }

    public function test_typed_placeholders_get_options_and_unsupported_mapping_cannot_submit(): void
    {
        $editor = Livewire::test(Builder::class)
            ->set('name', 'order_notice')
            ->set('body', 'Hello {{1}}, your order is ready.')
            ->assertSet('variable_mappings.1', '')
            ->call('addVariable', 'invented_field')
            ->assertHasErrors('variableOptions')
            ->assertSet('body', 'Hello {{1}}, your order is ready.')
            ->set('examples.1', 'Amina')
            ->set('variable_mappings.1', 'invented_field')
            ->call('validateTemplate');
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $this->assertSame('validation_failed', $template->local_state);
        $this->assertContains('sms_variable', array_column($template->validation_result['errors'], 'rule'));
        $this->assertFalse(app(WhatsappTemplateService::class)->submit($template)['success']);
        Livewire::test(Builder::class, ['template' => $template->id])
            ->assertSet('variable_mappings.1', 'invented_field')
            ->assertSee('Unsupported variable: invented_field');
        Http::assertNothingSent();
    }

    public function test_examples_follow_mapping_changes_and_preserve_custom_values(): void
    {
        Livewire::test(Builder::class)
            ->set('name', 'automatic_examples')->set('body', 'Hello')
            ->call('addVariable', 'customer_name')->assertSet('examples.1', 'Amina')
            ->set('variable_mappings.1', 'order_number')->assertSet('examples.1', 'ORD-1001')
            ->set('examples.1', 'Custom reference')
            ->set('variable_mappings.1', 'plan_number')->assertSet('examples.1', 'Custom reference')
            ->set('examples.1', '')->call('validateTemplate')->assertHasNoErrors()
            ->assertSet('examples.1', 'PLAN-1001')->assertSee('Submit to Twilio');
        Http::assertNothingSent();
    }

    public function test_old_drafts_fill_missing_examples_but_remote_content_stays_unchanged(): void
    {
        $editor = Livewire::test(Builder::class)->set('name', 'old_draft')->set('body', 'Hello')
            ->call('addVariable', 'customer_name')->call('validateTemplate')->assertHasNoErrors();
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $components = $template->components;
        $components[0]['examples'][1] = '';
        $template->update(['components' => $components]);
        Livewire::test(Builder::class, ['template' => $template->id])
            ->assertSet('examples.1', 'Amina')->assertDontSee('Submit to Twilio');
        $this->assertSame('', $template->fresh()->components[0]['examples'][1]);

        $template->update(['twilio_content_sid' => 'HX'.str_repeat('b', 32)]);
        Livewire::test(Builder::class, ['template' => $template->id])->assertSet('examples.1', '');
        $template->update(['twilio_content_sid' => null, 'local_state' => 'creation_unknown']);
        Livewire::test(Builder::class, ['template' => $template->id])->assertSet('examples.1', '');
        Http::assertNothingSent();
    }
}
