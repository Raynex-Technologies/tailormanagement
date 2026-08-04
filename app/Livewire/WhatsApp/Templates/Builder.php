<?php

namespace App\Livewire\WhatsApp\Templates;

use App\Contracts\WhatsAppProvider;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')] #[Title('WhatsApp Template Builder')] class Builder extends Component
{
    use AuthorizesRequests,WithFileUploads;

    public ?int $templateId = null;

    public string $name = '';

    public string $category = 'UTILITY';

    public string $language = 'en_US';

    public string $header_format = 'NONE';

    public string $header_text = '';

    public string $body = '';

    public string $footer = '';

    public array $examples = [];

    public array $variable_mappings = [];

    public array $buttons = [];

    public $media_example;

    public ?string $example_handle = null;

    public array $validation = [];

    public function mount(?int $template = null): void
    {
        $this->authorize('sms-templates.view');
        if ($template) {
            $t = WhatsappTemplate::findOrFail($template);
            $this->templateId = $t->id;
            $this->name = $t->name;
            $this->category = $t->category;
            $this->language = $t->language;
            $this->variable_mappings = $t->variable_mappings ?: [];
            $this->hydrateComponents($t->components ?: []);
            $this->validation = $t->validation_result ?: [];
        }
    }

    public function saveDraft(WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        $this->validateBasic();
        $t = $service->saveDraft($this->integration(), $this->definition(), $this->template());
        $this->templateId = $t->id;
        Log::info('WhatsApp template draft saved', ['branch_id' => $t->branch_id, 'template_id' => $t->id, 'actor_id' => auth()->id()]);
        session()->flash('success', __('Draft saved locally. Nothing was submitted to Meta.'));
    }

    public function validateTemplate(WhatsappTemplateService $service): void
    {
        $this->saveDraft($service);
        $result = $service->validate($this->template());
        $this->validation = $result->toArray();
        session()->flash($result->valid() ? 'success' : 'error', $result->valid() ? __('No known structural problems found. Ready to submit.') : __('Resolve the blocking validation errors.'));
    }

    public function submit(WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        if (! $this->templateId) {
            session()->flash('error', __('Save and validate the draft first.'));

            return;
        }$result = $service->submit($this->template());
        Log::info('WhatsApp template submitted', ['branch_id' => $this->integration()->branch_id, 'template_id' => $this->templateId, 'success' => $result['success'], 'actor_id' => auth()->id()]);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Template submitted. Meta status: :status', ['status' => $result['template']->meta_status]) : __($result['error_message']));
    }

    public function uploadExample(WhatsAppProvider $provider): void
    {
        $this->authorize('sms-templates.update');
        $this->validate(['media_example' => 'required|file|max:16384']);
        $format = strtoupper($this->header_format);
        $allowed = config("meta-whatsapp-templates.media.$format", []);
        if (! in_array($this->media_example->getMimeType(), $allowed, true)) {
            $this->addError('media_example', __('Unsupported file type for this header.'));

            return;
        }$result = $provider->uploadTemplateMedia($this->integration(), $this->media_example->getRealPath(), $this->media_example->getMimeType(), $this->media_example->getClientOriginalName());
        if ($result['success']) {
            $this->example_handle = $result['handle'];
            session()->flash('success', __('Example media uploaded to Meta.'));
        } else {
            session()->flash('error', __($result['error_message']));
        }
    }

    public function addVariable(): void
    {
        $next = count($this->examples) + 1;
        $this->examples[(string) $next] = '';
        $this->variable_mappings[(string) $next] = '';
    }

    public function addButton(): void
    {
        $this->buttons[] = ['type' => 'QUICK_REPLY', 'text' => 'Reply'];
    }

    protected function definition(): array
    {
        $components = [];
        if ($this->header_format !== 'NONE') {
            $components[] = ['type' => 'HEADER', 'format' => $this->header_format, 'text' => $this->header_format === 'TEXT' ? $this->header_text : null, 'examples' => $this->examples, 'example_handle' => $this->example_handle];
        }$components[] = ['type' => 'BODY', 'text' => $this->body, 'examples' => $this->examples];
        if (filled($this->footer)) {
            $components[] = ['type' => 'FOOTER', 'text' => $this->footer];
        }if ($this->buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $this->buttons];
        }

        return ['name' => $this->name, 'language' => $this->language, 'category' => $this->category, 'components' => $components, 'variable_mappings' => $this->variable_mappings];
    }

    protected function validateBasic(): void
    {
        $this->validate(['name' => 'required|string|max:512', 'category' => 'required|in:UTILITY,MARKETING,AUTHENTICATION', 'language' => 'required|string|max:16', 'header_format' => 'required|in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT,LOCATION', 'body' => 'required|string|max:1024', 'footer' => 'nullable|string|max:60', 'buttons' => 'array']);
    }

    protected function template(): ?WhatsappTemplate
    {
        return $this->templateId ? WhatsappTemplate::findOrFail($this->templateId) : null;
    }

    protected function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::forBranch(BranchContext::getEffectiveBranchId());
    }

    protected function hydrateComponents(array $components): void
    {
        foreach ($components as $c) {
            $type = strtoupper($c['type'] ?? '');
            if ($type === 'HEADER') {
                $this->header_format = strtoupper($c['format'] ?? 'TEXT');
                $this->header_text = $c['text'] ?? '';
                $this->example_handle = $c['example_handle'] ?? null;
                $this->examples = $c['examples'] ?? [];
            } elseif ($type === 'BODY') {
                $this->body = $c['text'] ?? '';
                $this->examples = $c['examples'] ?? $this->examples;
            } elseif ($type === 'FOOTER') {
                $this->footer = $c['text'] ?? '';
            } elseif ($type === 'BUTTONS') {
                $this->buttons = $c['buttons'] ?? [];
            }
        }
    }

    public function render()
    {
        return view('livewire.whatsapp.templates.builder', ['template' => $this->template(), 'preview' => $this->preview()]);
    }

    protected function preview(): string
    {
        $text = $this->body;
        foreach ($this->examples as $n => $value) {
            $text = str_replace('{{'.$n.'}}', (string) $value, $text);
        }

        return $text;
    }
}
