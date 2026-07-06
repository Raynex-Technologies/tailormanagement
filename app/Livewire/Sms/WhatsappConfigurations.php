<?php

namespace App\Livewire\Sms;

use App\Models\SmsTemplate;
use App\Models\TwilioWhatsappConfig;
use App\Services\Sms\TwilioWhatsappClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('WhatsApp Settings')]
class WhatsappConfigurations extends Component
{
    use AuthorizesRequests;

    public string $tab = 'credentials';

    public bool $whatsapp_enabled = false;

    public string $account_sid = '';

    public string $auth_token = '';

    public string $from_number = '';

    public string $messaging_service_sid = '';

    public string $content_template_language = 'en';

    public array $whatsappTemplates = [];

    public array $templateEnabled = [];

    public array $templateCategories = [];

    public function mount(): void
    {
        $this->authorize('sms-settings.view');

        $config = TwilioWhatsappConfig::instance();
        $this->whatsapp_enabled = $config->whatsapp_enabled;
        $this->account_sid = $config->account_sid ?? '';
        $this->auth_token = $config->auth_token ?? '';
        $this->from_number = $config->from_number ?? '';
        $this->messaging_service_sid = $config->messaging_service_sid ?? '';
        $this->content_template_language = $config->content_template_language ?: 'en';

        $templates = SmsTemplate::instance();
        $this->whatsappTemplates = SmsTemplate::normalizeTemplates(
            SmsTemplate::supportsWhatsappTemplates()
                ? ($templates->whatsapp_templates ?? [])
                : ($templates->templates ?? [])
        );

        $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom($templates));
        $this->templateEnabled = collect($settings)
            ->map(fn (array $setting) => (bool) ($setting['whatsapp_enabled'] ?? false))
            ->all();
        $this->templateCategories = collect($settings)
            ->map(fn (array $setting) => $setting['twilio_category'] ?? 'UTILITY')
            ->all();
    }

    public function saveCredentials(): void
    {
        $this->authorize('sms-settings.update');

        $this->account_sid = trim($this->account_sid);
        $this->auth_token = trim($this->auth_token);
        $this->from_number = trim($this->from_number);
        $this->messaging_service_sid = trim($this->messaging_service_sid);
        $this->content_template_language = strtolower(trim($this->content_template_language ?: 'en'));

        $this->validate([
            'whatsapp_enabled' => 'boolean',
            'account_sid' => 'nullable|string|max:255',
            'auth_token' => 'nullable|string|max:255',
            'from_number' => 'nullable|string|max:50',
            'messaging_service_sid' => 'nullable|string|max:255',
            'content_template_language' => 'required|string|max:10',
        ]);

        TwilioWhatsappConfig::instance()->update([
            'whatsapp_enabled' => $this->whatsapp_enabled,
            'account_sid' => $this->account_sid ?: null,
            'auth_token' => $this->auth_token ?: null,
            'from_number' => $this->from_number ?: null,
            'messaging_service_sid' => $this->messaging_service_sid ?: null,
            'content_template_language' => $this->content_template_language,
        ]);

        session()->flash('success', __('Twilio WhatsApp settings saved.'));
    }

    public function saveNotificationSettings(): void
    {
        $this->authorize('sms-settings.update');

        $this->validate([
            'whatsapp_enabled' => 'boolean',
            'templateEnabled' => 'array',
            'templateEnabled.*' => 'boolean',
        ]);

        DB::transaction(function (): void {
            TwilioWhatsappConfig::instance()->update([
                'whatsapp_enabled' => $this->whatsapp_enabled,
            ]);

            $row = SmsTemplate::instance();
            $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom($row));

            foreach ($settings as $code => $setting) {
                $settings[$code]['whatsapp_enabled'] = (bool) ($this->templateEnabled[$code] ?? false);
            }

            $row->update(['template_settings' => $settings]);
        });

        session()->flash('success', __('WhatsApp notification settings updated successfully.'));
    }

    public function saveTemplates(): void
    {
        $this->authorize('sms-templates.update');

        $this->persistTemplates();

        session()->flash('success', __('WhatsApp templates saved.'));
    }

    protected function persistTemplates(): void
    {
        $this->validate([
            'whatsappTemplates' => 'array',
            'whatsappTemplates.*' => 'nullable|string|max:1000',
            'templateCategories' => 'array',
            'templateCategories.*' => 'nullable|in:UTILITY,MARKETING,AUTHENTICATION',
        ]);

        $row = SmsTemplate::instance();
        $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom($row));
        $existingTemplates = SmsTemplate::normalizeTemplates($row->whatsapp_templates ?? []);
        $newTemplates = SmsTemplate::normalizeTemplates($this->whatsappTemplates);

        foreach ($settings as $code => $setting) {
            $settings[$code]['twilio_category'] = $this->templateCategories[$code] ?? ($setting['twilio_category'] ?? 'UTILITY');

            if (($existingTemplates[$code] ?? null) !== ($newTemplates[$code] ?? null)) {
                $settings[$code]['whatsapp_status'] = 'not_submitted';
                $settings[$code]['twilio_content_sid'] = null;
                $settings[$code]['twilio_approval_request_sid'] = null;
                $settings[$code]['twilio_template_name'] = null;
                $settings[$code]['twilio_rejection_reason'] = null;
            }
        }

        $row->update([
            'whatsapp_templates' => $newTemplates,
            'template_settings' => $settings,
        ]);
    }

    public function submitTemplate(string $code): void
    {
        $this->authorize('sms-templates.update');

        if (! array_key_exists($code, SmsTemplate::defaultTemplates())) {
            abort(404);
        }

        $this->persistTemplates();

        $client = app(TwilioWhatsappClient::class);

        if (! $client->isConfigured()) {
            session()->flash('error', __('Twilio WhatsApp credentials are incomplete.'));

            return;
        }

        $row = SmsTemplate::instance();
        $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom($row));
        $body = SmsTemplate::normalizeTemplates($row->whatsapp_templates ?? [])[$code] ?? '';
        $category = $settings[$code]['twilio_category'] ?? 'UTILITY';
        $response = $client->submitTemplate($code, $body, TwilioWhatsappConfig::instance()->content_template_language ?: 'en', $category);

        $settings[$code]['whatsapp_status'] = $response['status'];
        $settings[$code]['twilio_content_sid'] = $response['content_sid'];
        $settings[$code]['twilio_approval_request_sid'] = $response['approval_request_sid'];
        $settings[$code]['twilio_template_name'] = Str::of($code)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->limit(64, '')->toString();
        $settings[$code]['twilio_rejection_reason'] = $response['success'] ? null : json_encode($response['raw_response']);

        $row->update(['template_settings' => $settings]);

        session()->flash(
            $response['success'] ? 'success' : 'error',
            $response['success']
                ? __('WhatsApp template submitted to Twilio for approval.')
                : __('Twilio rejected the template submission. Review the template status details.')
        );
    }

    public function refreshTemplateStatus(string $code): void
    {
        $this->authorize('sms-templates.update');

        $row = SmsTemplate::instance();
        $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom($row));
        $contentSid = $settings[$code]['twilio_content_sid'] ?? null;

        if (! $contentSid) {
            session()->flash('error', __('Submit this template to Twilio before refreshing status.'));

            return;
        }

        $response = app(TwilioWhatsappClient::class)->fetchTemplateStatus($contentSid);
        $settings[$code]['whatsapp_status'] = $response['status'];
        $settings[$code]['twilio_rejection_reason'] = $response['rejection_reason'];

        $row->update(['template_settings' => $settings]);

        session()->flash(
            $response['success'] ? 'success' : 'error',
            $response['success']
                ? __('WhatsApp template status refreshed.')
                : __('Unable to refresh template status from Twilio.')
        );
    }

    public function render()
    {
        $settings = SmsTemplate::normalizeTemplateSettings($this->templateSettingsFrom(SmsTemplate::instance()));

        return view('livewire.sms.whatsapp-configurations', [
            'categoryLabels' => SmsTemplate::categoryLabels(),
            'categories' => SmsTemplate::CATEGORIES,
            'templateSettings' => $settings,
            'categoryVariables' => collect(SmsTemplate::CATEGORIES)
                ->mapWithKeys(fn (string $category) => [$category => SmsTemplate::variablesForCategory($category)])
                ->all(),
            'variableDefinitions' => SmsTemplate::variableDefinitions(),
        ]);
    }

    protected function templateSettingsFrom(SmsTemplate $smsTemplates): array
    {
        return SmsTemplate::supportsTemplateSettings() ? ($smsTemplates->template_settings ?? []) : [];
    }
}
