<?php

namespace App\Livewire\Sms;

use App\Models\BeemConfig;
use App\Models\Customer;
use App\Models\SmsTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Beem Configurations')]
class BeemConfigurations extends Component
{
    use AuthorizesRequests;

    public string $tab = 'credentials';

    // Credentials
    public bool $sms_enabled = false;
    public string $api_key = '';
    public string $secret_key = '';
    public string $sender_name = '';

    // Templates (key => body)
    public array $templates = [];

    // Marketing
    public array $selectedCustomerIds = [];
    public string $marketingMessage = '';

    public function mount(): void
    {
        $this->authorize('sms.templates.manage');

        $config = BeemConfig::instance();
        $this->sms_enabled = $config->sms_enabled;
        $this->api_key = $config->api_key ?? '';
        $this->secret_key = $config->secret_key ?? '';
        $this->sender_name = $config->sender_name ?? '';

        $smsTemplates = SmsTemplate::instance();
        $this->templates = SmsTemplate::normalizeTemplates($smsTemplates->templates ?? []);
    }

    public function saveCredentials(): void
    {
        $this->authorize('sms.templates.manage');
        $this->api_key = trim($this->api_key);
        $this->secret_key = trim($this->secret_key);
        $this->sender_name = trim($this->sender_name);

        $this->validate([
            'sender_name' => 'nullable|string|max:50',
            'api_key' => 'nullable|string|max:255',
            'secret_key' => 'nullable|string|max:255',
        ]);

        $config = BeemConfig::instance();
        $config->update([
            'sms_enabled' => $this->sms_enabled,
            'api_key' => $this->api_key ?: null,
            'secret_key' => $this->secret_key ?: null,
            'sender_name' => $this->sender_name ?: null,
        ]);

        session()->flash('success', __('Beem credentials saved.'));
    }

    public function saveTemplates(): void
    {
        $this->authorize('sms.templates.manage');
        $this->validate([
            'templates' => 'array',
            'templates.*' => 'nullable|string|max:1000',
        ]);

        $data = SmsTemplate::normalizeTemplates($this->templates);

        $row = SmsTemplate::instance();
        $row->update(['templates' => $data]);

        session()->flash('success', __('SMS templates saved.'));
    }

    public function sendMarketing(): void
    {
        $this->authorize('sms.templates.manage');
        $this->validate([
            'selectedCustomerIds' => 'required|array|min:1',
            'selectedCustomerIds.*' => 'exists:customers,id',
            'marketingMessage' => 'required|string|max:1000',
        ]);

        // Placeholder: enqueue or send via your SMS service. For now just flash success.
        $count = count($this->selectedCustomerIds);
        session()->flash('success', __('Marketing message will be sent to :count customer(s). (Sending can be wired to your SMS service.)', ['count' => $count]));

        $this->selectedCustomerIds = [];
        $this->marketingMessage = '';
    }

    public function getCustomersProperty()
    {
        $query = Customer::query()->orderBy('name');
        if (! auth()->user()->isGlobalAdmin()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        return $query->get(['id', 'name', 'phone']);
    }

    public function render()
    {
        return view('livewire.sms.beem-configurations', [
            'categoryLabels' => SmsTemplate::categoryLabels(),
            'categories' => SmsTemplate::CATEGORIES,
            'categoryVariables' => collect(SmsTemplate::CATEGORIES)
                ->mapWithKeys(fn (string $category) => [$category => SmsTemplate::variablesForCategory($category)])
                ->all(),
            'variableDefinitions' => SmsTemplate::variableDefinitions(),
        ]);
    }
}
