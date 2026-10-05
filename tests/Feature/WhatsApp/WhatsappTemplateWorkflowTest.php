<?php

namespace Tests\Feature\WhatsApp;

use App\Livewire\WhatsApp\Templates\Builder;
use App\Livewire\WhatsApp\Templates\Index;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappTemplateWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('admin');
        Http::preventStrayRequests();
    }

    public function test_save_highlights_unmapped_variables_and_automatically_fills_known_examples(): void
    {
        $editor = Livewire::test(Builder::class)
            ->assertSee('Save and validate')->assertDontSee('Submit to Twilio')
            ->set('name', 'Order Created')->set('body', 'Hello {{1}}, your order is ready.')
            ->call('validateTemplate')->assertHasErrors(['variable_mappings.1', 'examples.1'])
            ->assertHasNoErrors('name')->assertSet('name', 'order_created')
            ->assertSee('Please correct the highlighted fields')
            ->assertSee('Add a representative example')
            ->assertSee('aria-invalid="true"', false)
            ->assertDontSee('Draft saved locally')->assertDontSee('Submit to Twilio');
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $this->assertSame('validation_failed', $template->local_state);
        $this->assertSame('order_created', $template->name);
        Livewire::test(Builder::class, ['template' => $template->id])
            ->assertHasErrors(['examples.1'])->assertHasNoErrors('name')->assertDontSee('Submit to Twilio');

        $editor->set('variable_mappings.1', 'customer_name')->assertSet('examples.1', 'Amina')
            ->call('validateTemplate')->assertHasNoErrors()
            ->assertSee('Draft saved and validated.')->assertSee('Submit to Twilio')
            ->assertDontSee('Please correct the highlighted fields');
        $this->assertSame('ready_to_submit', $template->fresh()->local_state);
        Livewire::test(Builder::class, ['template' => $template->id])->assertSee('Submit to Twilio');
        $editor->set('examples.1', 'Changed example')->assertDontSee('Submit to Twilio')
            ->call('submit')->assertSee('Save and validate your latest changes');
        Http::assertNothingSent();
    }

    public function test_basic_errors_are_visible_and_client_validation_cannot_reveal_submit(): void
    {
        Livewire::test(Builder::class)
            ->set('validation', ['valid' => true, 'errors' => []])
            ->assertDontSee('Submit to Twilio')
            ->call('validateTemplate')->assertHasErrors(['name', 'body'])
            ->assertSee('Please correct the highlighted fields')
            ->assertDontSee('Submit to Twilio');
        $this->assertSame(0, WhatsappTemplate::count());
        Http::assertNothingSent();
    }

    public function test_name_cleanup_preserves_digits_and_underscores_and_rejects_an_empty_result(): void
    {
        $editor = Livewire::test(Builder::class)
            ->set('name', '  Order   READY_2! @#$%  ')
            ->assertSet('name', 'order_ready_2')
            ->set('body', 'Your order is ready.')
            ->call('validateTemplate')->assertHasNoErrors()->assertSee('Submit to Twilio');
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $this->assertSame('order_ready_2', $template->name);
        $editor->set('name', '!@#$%')->assertSet('name', '')
            ->call('validateTemplate')->assertHasErrors('name')->assertDontSee('Submit to Twilio');
        $this->assertSame('order_ready_2', $template->fresh()->name);
        Http::assertNothingSent();
    }

    public function test_index_displays_header_right_hand_actions_and_both_workflow_and_approval_status(): void
    {
        $editor = Livewire::test(Builder::class)->set('name', 'order_ready')
            ->set('body', 'Your order is ready.')->call('validateTemplate')->assertHasNoErrors();
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        Livewire::test(Index::class)->assertSee('data-whatsapp-templates-header', false)
            ->assertSee('data-template-actions', false)->assertSee('Ready to submit')
            ->assertSee('Not submitted')->assertSee('View / Edit')->assertSee('Duplicate');
        $template->update(['twilio_status' => 'APPROVED']);
        Livewire::test(Index::class)->assertSee('Twilio: Approved');
        Livewire::test(Builder::class, ['template' => $template->id])->assertDontSee('Submit to Twilio');
    }

    public function test_mysql_json_key_order_does_not_hide_submit_or_discard_validation(): void
    {
        $editor = Livewire::test(Builder::class)->set('name', 'mysql_order')
            ->set('body', 'Hello')->call('addVariable', 'customer_name')
            ->call('validateTemplate')->assertHasNoErrors();
        $template = WhatsappTemplate::findOrFail($editor->get('templateId'));
        $body = $template->components[0];
        // MySQL's JSON storage reorders object keys; SQLite leaves their order intact.
        $template->update(['components' => [['text' => $body['text'], 'type' => $body['type'], 'examples' => $body['examples']]]]);
        app(WhatsappTemplateService::class)->validate($template->fresh());
        Livewire::test(Builder::class, ['template' => $template->id])
            ->assertSet('validation.valid', true)->assertSee('Submit to Twilio')
            ->call('submit')->assertSee('Configure this branch')
            ->assertDontSee('Save and validate your latest changes')
            ->set('examples.1', 'Different customer')->assertDontSee('Submit to Twilio')
            ->call('submit')->assertSee('Save and validate your latest changes');
        Http::assertNothingSent();
    }
}
