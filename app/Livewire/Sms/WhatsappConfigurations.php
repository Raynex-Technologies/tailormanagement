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
        $i = $this->integration();
        $data = $this->validate([
            'enabled' => 'boolean',
            'twilio_account_sid' => ['required', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'twilio_auth_token' => ['nullable', 'string', 'max:4096'],
            'twilio_from' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/', Rule::unique('whatsapp_integrations', 'twilio_from')->ignore($i->id)],
        ]);
        if (blank($data['twilio_auth_token']) && (blank($i->twilio_auth_token) || $i->twilio_account_sid !== $data['twilio_account_sid'])) {
            $this->addError('twilio_auth_token', __('Enter the Auth Token for this Twilio account.'));

            return;
        }
        if (blank($data['twilio_auth_token'])) {
            unset($data['twilio_auth_token']);
        }
        $i->update($data + ['connection_status' => 'unverified', 'webhook_status' => 'unverified', 'webhook_checked_at' => null, 'webhook_error_message' => null, 'last_error_code' => null, 'last_error_message' => null]);
        $this->reset('twilio_auth_token');
        session()->flash('success', __('Twilio settings saved. Test the connection and configure the incoming-message URL in Twilio.'));
    }

    public function testConnection(WhatsAppService $service): void
    {
        $this->authorize('sms-settings.update');
        $result = $service->testConnection($this->integration());
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
