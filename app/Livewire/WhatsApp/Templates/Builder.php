<?php

namespace App\Livewire\WhatsApp\Templates;

use App\Contracts\WhatsAppProvider;
use App\Data\WhatsApp\TemplateDefinition;
use App\Models\SmsTemplate;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')] #[Title('WhatsApp Template Builder')] class Builder extends Component
{
    use AuthorizesRequests,WithFileUploads;

    public ?int $templateId = null;

    public string $recovery_content_sid = '';

    public string $name = '';

    public string $category = 'UTILITY';

    public string $language = 'en';

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
        abort_unless(BranchContext::hasBranch(), 403, 'Select an active branch before managing WhatsApp templates.');
        if ($template) {
            $t = WhatsappTemplate::where('branch_id', BranchContext::requireId())->findOrFail($template);
            $this->templateId = $t->id;
            $this->name = $t->name;
            $this->category = $t->category;
            $this->language = $t->language;
            $this->variable_mappings = $t->variable_mappings ?: [];
            $this->hydrateComponents($t->components ?: []);
            $this->updatedBody();
            $this->validation = $t->validation_result ?: [];
            $this->fillVariableExamples($t);
            if (! $this->matchesSavedDefinition($t)) {
                $this->validation = [];
            }
            $this->showValidationErrors();
        }
    }

    public function saveDraft(WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        session()->forget(['success', 'error']);
        $this->resetErrorBag();
        $this->updatedName();
        $this->fillVariableExamples($this->template());
        $this->validateBasic();
        $t = $service->saveDraft($this->integration(), $this->definition(), $this->template());
        $this->templateId = $t->id;
        Log::info('WhatsApp template draft saved', ['branch_id' => $t->branch_id, 'template_id' => $t->id, 'actor_id' => auth()->id()]);
        session()->flash('success', __('Draft saved locally. Nothing was submitted to Twilio.'));
    }

    public function validateTemplate(WhatsappTemplateService $service): void
    {
        $this->saveDraft($service);
        $result = $service->validate($this->template());
        $this->validation = $result->toArray();
        session()->forget(['success', 'error']);
        $this->showValidationErrors();
        if ($result->valid()) {
            session()->flash('success', __('Draft saved and validated. Ready to submit to Twilio.'));
        }
    }

    public function submit(WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        session()->forget(['success', 'error']);
        if (! $this->templateId) {
            session()->flash('error', __('Save and validate the draft first.'));

            return;
        }$persisted = $this->template();
        if (! $this->matchesSavedDefinition($persisted)) {
            session()->flash('error', __('Save and validate your latest changes before submitting.'));

            return;
        }
        $result = $service->submit($persisted);
        Log::info('WhatsApp template submitted', ['branch_id' => $this->integration()->branch_id, 'template_id' => $this->templateId, 'success' => $result['success'], 'actor_id' => auth()->id()]);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Template submitted through Twilio. Approval status: :status', ['status' => $result['template']->twilio_status]) : __($result['error_message']));
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

    public function recoverContent(\App\Services\WhatsApp\Templates\TwilioTemplateManager $manager): void
    {
        $this->authorize('sms-templates.update');
        $this->validate(['recovery_content_sid' => ['required', 'regex:/^HX[0-9a-fA-F]{32}$/']]);
        $template = $this->template();
        abort_unless($template, 404);
        $result = $manager->reconcile($template, $this->recovery_content_sid);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Content recovered. Validate and submit to continue the approval request.') : __($result['error_message']));
    }

    public function useTextOnly(): void
    {
        $this->authorize('sms-templates.update');
        $this->header_format = 'NONE';
        $this->header_text = '';
        $this->footer = '';
        $this->buttons = [];
        $this->example_handle = null;
    }

    public function addVariable(string $variable): void
    {
        $this->authorize('sms-templates.update');
        if (! array_key_exists($variable, SmsTemplate::variableOptions())) {
            throw ValidationException::withMessages(['variableOptions' => __('Choose a default SMS template variable.')]);
        }
        $this->resetErrorBag('variableOptions');
        preg_match_all('/\{\{(\d+)\}\}/', $this->body, $matches);
        $next = max([0, ...array_map('intval', $matches[1]), ...array_map('intval', array_keys($this->examples))]) + 1;
        if ($next > 1024) {
            $this->addError('body', __('Fix the placeholder numbering before adding another variable.'));

            return;
        }
        $this->examples[(string) $next] = SmsTemplate::variableExamples()[$variable] ?? '';
        $this->variable_mappings[(string) $next] = $variable;
        $this->body .= (filled($this->body) && ! preg_match('/\s$/', $this->body) ? ' ' : '').'{{'.$next.'}}';
    }

    public function updatedName(): void
    {
        $name = preg_replace('/[^a-z0-9_\s\p{Z}]/u', '', strtolower($this->name)) ?? '';
        $this->name = preg_replace('/[\s\p{Z}]+/u', '_', trim($name)) ?? '';
        $this->resetErrorBag('name');
    }

    public function updatingVariableMappings($value, $key): void
    {
        $template = $this->template();
        if ($template && ($template->twilio_content_sid || $template->local_state === 'creation_unknown')) {
            return;
        }
        $samples = SmsTemplate::variableExamples();
        $old = $this->variable_mappings[$key] ?? null;
        if (is_string($value) && isset($samples[$value]) &&
            (blank($this->examples[$key] ?? null) || (is_string($old) && ($this->examples[$key] ?? null) === ($samples[$old] ?? null)))) {
            $this->examples[$key] = $samples[$value];
        }
    }

    public function updatedVariableMappings(): void
    {
        $this->fillVariableExamples($this->template());
    }

    protected function fillVariableExamples(?WhatsappTemplate $template): void
    {
        if ($template && ($template->twilio_content_sid || $template->local_state === 'creation_unknown')) {
            return;
        }
        $samples = SmsTemplate::variableExamples();
        foreach ($this->variable_mappings as $number => $variable) {
            if (is_string($variable) && isset($samples[$variable]) && blank($this->examples[$number] ?? null)) {
                $this->examples[$number] = $samples[$variable];
                $this->resetErrorBag('examples.'.$number);
            }
        }
    }

    public function updatedBody(): void
    {
        preg_match_all('/\{\{(\d+)\}\}/', $this->body, $matches);
        foreach (array_unique($matches[1]) as $number) {
            if ((int) $number < 1 || (int) $number > 1024) {
                continue;
            }
            $this->examples[$number] ??= '';
            $this->variable_mappings[$number] ??= '';
        }
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
        return $this->templateId ? WhatsappTemplate::where('branch_id', BranchContext::requireId())->findOrFail($this->templateId) : null;
    }

    protected function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::forBranch(BranchContext::requireId());
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
        $template = $this->template();

        return view('livewire.whatsapp.templates.builder', [
            'template' => $template,
            'preview' => $this->preview(),
            'variableOptions' => SmsTemplate::variableOptions(),
            'canSubmit' => $template
                && $template->local_state === 'ready_to_submit'
                && data_get($template->validation_result, 'valid') === true
                && $this->matchesSavedDefinition($template)
                && $template->validation_fingerprint === $template->definitionFingerprint()
                && ! in_array($template->twilio_status, ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'DELETED'], true),
        ]);
    }

    protected function showValidationErrors(): void
    {
        foreach ($this->validation['errors'] ?? [] as $error) {
            $field = $error['field'];
            if (preg_match('/^components\.\d+\.examples\.(\d+)$/', $field, $matches)) {
                $field = 'examples.'.$matches[1];
            } elseif (str_starts_with($field, 'components')) {
                $field = 'body';
            }
            $this->addError($field, $error['message']);
        }
    }

    protected function matchesSavedDefinition(WhatsappTemplate $template): bool
    {
        return TemplateDefinition::fromArray($this->definition())->matches(
            TemplateDefinition::fromArray($template->only(['name', 'language', 'category', 'components', 'variable_mappings']))
        );
    }

    protected function preview(): string
    {
        $text = $this->body;
        foreach ($this->examples as $n => $value) {
            if (filled($value)) {
                $text = str_replace('{{'.$n.'}}', (string) $value, $text);
            }
        }

        return $text;
    }
}
