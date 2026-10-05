<?php

namespace App\Livewire\Sms;

use App\Models\WhatsappIntegration;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Twilio WhatsApp')]
class WhatsappConfigurations extends Component
{
    use \App\Livewire\Concerns\ValidatesPhoneNumbers;
    use AuthorizesRequests;

    public bool $enabled = false;

    public string $twilio_account_sid = '';

    public string $twilio_auth_token = '';

    public string $twilio_from = '';

    public function mount(): void
    {
        $this->authorize('sms-settings.view');
        $i = $this->integration();
        $this->enabled = $i->enabled && $i->twilioConfigured();
        $this->twilio_account_sid = $i->twilio_account_sid ?? '';
        $this->twilio_from = $i->twilio_from ?? '';
    }

    public function save(): void
    {
        $this->authorize('sms-settings.update');
        session()->forget(['success', 'error']);
        if ($this->persistSettings()) {
            session()->flash('success', __('Twilio settings saved.'));
        }
    }

    protected function persistSettings(): ?WhatsappIntegration
    {
        $i = $this->integration();
        $data = $this->validate([
            'enabled' => 'boolean',
            'twilio_account_sid' => ['required', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'twilio_auth_token' => ['nullable', 'string', 'max:4096'],
            'twilio_from' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/', Rule::unique('whatsapp_integrations', 'twilio_from')->ignore($i->id)],
        ]);
        if (blank($data['twilio_auth_token']) && (blank($i->twilio_auth_token) || $i->twilio_account_sid !== $data['twilio_account_sid'])) {
            $this->addError('twilio_auth_token', __('Enter the Auth Token for this Twilio account.'));

            return null;
        }
        if (blank($data['twilio_auth_token'])) {
            unset($data['twilio_auth_token']);
        }
        $i->fill($data);
        if ($i->isDirty(['twilio_account_sid', 'twilio_auth_token', 'twilio_from'])) {
            $i->fill(['connection_status' => 'unverified', 'webhook_status' => 'unverified', 'webhook_checked_at' => null, 'webhook_error_message' => null, 'last_error_code' => null, 'last_error_message' => null]);
        }
        $i->save();
        $this->reset('twilio_auth_token');

        return $i;
    }

    public function testConnection(WhatsAppService $service): void
    {
        $this->authorize('sms-settings.update');
        session()->forget(['success', 'error']);
        $integration = $this->persistSettings();
        if (! $integration) {
            return;
        }
        $result = $service->testConnection($integration);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Twilio account credentials verified. Sender registration and live delivery still require a test message.') : __($result['error_message']));
    }

    protected function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::forBranch(BranchContext::requireId());
    }

    public function render()
    {
        $i = $this->integration()->fresh();

        return view('livewire.sms.whatsapp-configurations', [
            'integration' => $i,
            'webhookUrl' => rtrim(config('app.url'), '/').route('webhooks.twilio-whatsapp', ['webhookKey' => $i->webhook_key], false),
            'messages' => \App\Models\WhatsappMessage::withoutGlobalScopes()->where('branch_id', $i->branch_id)->latest()->limit(20)->get(),
        ]);
    }
}
