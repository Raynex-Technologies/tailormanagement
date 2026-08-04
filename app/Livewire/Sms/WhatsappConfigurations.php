<?php

namespace App\Livewire\Sms;

use App\Models\WhatsappIntegration;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Meta WhatsApp')]
class WhatsappConfigurations extends Component
{
    use AuthorizesRequests;

    public bool $enabled = false;

    public string $waba_id = '';

    public string $phone_number_id = '';

    public string $meta_app_id = '';

    public string $access_token = '';

    public string $app_secret = '';

    public string $webhook_verify_token = '';

    public function mount(): void
    {
        $this->authorize('sms-settings.view');
        $i = $this->integration();
        $this->enabled = $i->enabled;
        $this->waba_id = $i->waba_id ?? '';
        $this->phone_number_id = $i->phone_number_id ?? '';
        $this->meta_app_id = $i->meta_app_id ?? '';
    }

    public function save(): void
    {
        $this->authorize('sms-settings.update');
        $data = $this->validate(['enabled' => 'boolean', 'waba_id' => 'required_if:enabled,true|nullable|regex:/^[0-9]+$/|max:64', 'phone_number_id' => 'required_if:enabled,true|nullable|regex:/^[0-9]+$/|max:64', 'meta_app_id' => 'nullable|regex:/^[0-9]+$/|max:64', 'access_token' => 'nullable|string|max:4096', 'app_secret' => 'nullable|string|max:512', 'webhook_verify_token' => 'nullable|string|min:16|max:255']);
        $i = $this->integration();
        $changes = ['enabled' => $data['enabled'], 'waba_id' => $data['waba_id'] ?: null, 'phone_number_id' => $data['phone_number_id'] ?: null, 'meta_app_id' => $data['meta_app_id'] ?: null, 'connection_status' => 'unverified', 'last_error_code' => null, 'last_error_message' => null];
        foreach (['access_token', 'app_secret', 'webhook_verify_token'] as $secret) {
            if (filled($data[$secret])) {
                $changes[$secret] = $data[$secret];
            }
        } $i->update($changes);
        Log::info('Meta WhatsApp integration settings changed', ['branch_id' => $i->branch_id, 'enabled' => $i->enabled, 'changed_fields' => array_values(array_diff(array_keys($changes), ['access_token', 'app_secret', 'webhook_verify_token'])), 'actor_id' => auth()->id()]);
        $this->reset('access_token', 'app_secret', 'webhook_verify_token');
        session()->flash('success', __('Meta WhatsApp settings saved. Run Test Connection to verify them.'));
    }

    public function testConnection(WhatsAppService $service): void
    {
        $this->authorize('sms-settings.update');
        $i = $this->integration();
        if (! $i->isConfigured()) {
            session()->flash('error', __('WABA ID, Phone Number ID, and access token are required.'));

            return;
        } $result = $service->testConnection($i);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Connection verified with Meta.') : __($result['error_message']));
    }

    public function configureWebhooks(WhatsAppService $service): void
    {
        $this->authorize('sms-settings.update');
        $integration = $this->integration();
        if (! $integration->isConfigured() || blank($integration->app_secret) || blank($integration->webhook_verify_token)) {
            session()->flash('error', __('Access token, App Secret, webhook verify token, WABA ID, and Phone Number ID are required.'));

            return;
        }
        $url = route('webhooks.meta-whatsapp', ['webhookKey' => $integration->webhook_key]);
        $result = $service->configureWebhooks($integration, $url);
        Log::info('Meta WhatsApp webhook subscription checked', ['branch_id' => $integration->branch_id, 'success' => $result['success'], 'actor_id' => auth()->id()]);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Meta webhook subscription configured and verified.') : __($result['error_message']));
    }

    protected function integration(): WhatsappIntegration
    {
        return WhatsappIntegration::forBranch(BranchContext::getEffectiveBranchId());
    }

    public function render()
    {
        $i = $this->integration()->fresh();

        return view('livewire.sms.whatsapp-configurations', ['integration' => $i, 'webhookUrl' => route('webhooks.meta-whatsapp', ['webhookKey' => $i->webhook_key]), 'messages' => \App\Models\WhatsappMessage::withoutGlobalScopes()->where('branch_id', $i->branch_id)->latest()->limit(20)->get()]);
    }
}
