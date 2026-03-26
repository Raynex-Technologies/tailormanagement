<?php

namespace App\Livewire\Administration;

use App\Models\BusinessSetting;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app.sidebar')]
#[Title('Email Setup')]
class EmailSetup extends Component
{
    use AuthorizesRequests;

    public string $tab = 'smtp';

    public string $email_from_name = '';

    public string $email_from_address = '';

    public string $email_reply_to = '';

    public string $mail_mailer = 'smtp';

    public string $mail_host = '';

    public ?int $mail_port = null;

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_encryption = 'tls';

    public ?int $mail_timeout = null;

    public bool $incoming_enabled = false;

    public string $incoming_protocol = 'imap';

    public string $incoming_host = '';

    public ?int $incoming_port = null;

    public string $incoming_username = '';

    public string $incoming_password = '';

    public string $incoming_encryption = 'tls';

    /**
     * @var array<string, array{subject:string, body:string}>
     */
    public array $templates = [];

    public function mount(): void
    {
        $this->authorize('roles.manage');

        $settings = BusinessSetting::instance();
        $this->email_from_name = (string) ($settings->email_from_name ?? '');
        $this->email_from_address = (string) ($settings->email_from_address ?? '');
        $this->email_reply_to = (string) ($settings->email_reply_to ?? '');
        $this->mail_mailer = (string) ($settings->mail_mailer ?: config('mail.default', 'smtp'));
        $this->mail_host = (string) ($settings->mail_host ?? '');
        $this->mail_port = $settings->mail_port ? (int) $settings->mail_port : null;
        $this->mail_username = (string) ($settings->mail_username ?? '');
        $this->mail_password = '';
        $this->mail_encryption = (string) ($settings->mail_encryption ?: (config('mail.mailers.smtp.scheme') ?: 'tls'));
        $this->mail_timeout = $settings->mail_timeout ? (int) $settings->mail_timeout : null;

        $this->incoming_enabled = (bool) ($settings->incoming_enabled ?? false);
        $this->incoming_protocol = (string) ($settings->incoming_protocol ?: 'imap');
        $this->incoming_host = (string) ($settings->incoming_host ?? '');
        $this->incoming_port = $settings->incoming_port ? (int) $settings->incoming_port : null;
        $this->incoming_username = (string) ($settings->incoming_username ?? '');
        $this->incoming_password = '';
        $this->incoming_encryption = (string) ($settings->incoming_encryption ?: 'tls');

        $this->loadTemplates();
    }

    public function saveSmtpSettings(): void
    {
        $this->authorize('roles.manage');

        $requiredColumns = [
            'mail_mailer',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_timeout',
            'incoming_enabled',
            'incoming_protocol',
            'incoming_host',
            'incoming_port',
            'incoming_username',
            'incoming_password',
            'incoming_encryption',
        ];

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('business_settings', $column)) {
                session()->flash('error', __('SMTP columns are missing. Please run migrations first.'));

                return;
            }
        }

        $validated = $this->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'sendmail', 'log', 'array'])],
            'mail_host' => [Rule::requiredIf($this->mail_mailer === 'smtp'), 'nullable', 'string', 'max:191'],
            'mail_port' => [Rule::requiredIf($this->mail_mailer === 'smtp'), 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:191'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
            'mail_timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'email_from_name' => ['nullable', 'string', 'max:191'],
            'email_from_address' => ['nullable', 'email', 'max:191'],
            'email_reply_to' => ['nullable', 'email', 'max:191'],
            'incoming_enabled' => ['boolean'],
            'incoming_protocol' => [Rule::requiredIf($this->incoming_enabled), Rule::in(['imap', 'pop3'])],
            'incoming_host' => [Rule::requiredIf($this->incoming_enabled), 'nullable', 'string', 'max:191'],
            'incoming_port' => [Rule::requiredIf($this->incoming_enabled), 'nullable', 'integer', 'min:1', 'max:65535'],
            'incoming_username' => [Rule::requiredIf($this->incoming_enabled), 'nullable', 'string', 'max:191'],
            'incoming_password' => ['nullable', 'string', 'max:500'],
            'incoming_encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
        ]);

        $settings = BusinessSetting::instance();

        $data = [
            'mail_mailer' => $validated['mail_mailer'],
            'mail_host' => $validated['mail_host'] ?: null,
            'mail_port' => $validated['mail_port'] ?: null,
            'mail_username' => $validated['mail_username'] ?: null,
            'mail_encryption' => $validated['mail_encryption'],
            'mail_timeout' => $validated['mail_timeout'] ?: null,
            'email_from_name' => $validated['email_from_name'] ?: null,
            'email_from_address' => $validated['email_from_address'] ?: null,
            'email_reply_to' => $validated['email_reply_to'] ?: null,
            'incoming_enabled' => (bool) $validated['incoming_enabled'],
            'incoming_protocol' => $validated['incoming_enabled']
                ? ($validated['incoming_protocol'] ?: null)
                : null,
            'incoming_host' => $validated['incoming_enabled']
                ? ($validated['incoming_host'] ?: null)
                : null,
            'incoming_port' => $validated['incoming_enabled']
                ? ($validated['incoming_port'] ?: null)
                : null,
            'incoming_username' => $validated['incoming_enabled']
                ? ($validated['incoming_username'] ?: null)
                : null,
            'incoming_encryption' => $validated['incoming_enabled']
                ? $validated['incoming_encryption']
                : null,
        ];

        if (filled($validated['mail_password'])) {
            $data['mail_password'] = $validated['mail_password'];
        }

        if (filled($validated['incoming_password'])) {
            $data['incoming_password'] = $validated['incoming_password'];
        }

        $settings->update($data);

        $this->mail_password = '';
        $this->incoming_password = '';

        session()->flash('success', __('SMTP and mail server settings updated successfully.'));
    }

    public function saveTemplates(): void
    {
        $this->authorize('roles.manage');

        $this->templates = EmailTemplate::normalizeTemplates($this->templates);

        $this->validate([
            'templates' => ['required', 'array'],
            'templates.*.subject' => ['required', 'string', 'max:191'],
            'templates.*.body' => ['required', 'string', 'max:5000'],
        ]);

        if (! Schema::hasTable('email_templates')) {
            session()->flash('error', __('Email templates table is missing. Please run migrations first.'));

            return;
        }

        try {
            $row = EmailTemplate::instance();
            $row->update([
                'templates' => $this->templates,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', __('Failed to save email templates. Please check logs and try again.'));

            return;
        }

        session()->flash('success', __('Email templates saved successfully.'));
    }

    protected function loadTemplates(): void
    {
        $defaults = EmailTemplate::defaultTemplates();

        if (! Schema::hasTable('email_templates')) {
            $this->templates = $defaults;

            return;
        }

        try {
            $row = EmailTemplate::instance();
            $this->templates = EmailTemplate::normalizeTemplates($row->templates);
        } catch (Throwable $exception) {
            report($exception);
            $this->templates = $defaults;
        }
    }

    public function render()
    {
        return view('livewire.administration.email-setup', [
            'categories' => EmailTemplate::CATEGORIES,
            'categoryLabels' => EmailTemplate::categoryLabels(),
            'categoryVariables' => collect(EmailTemplate::CATEGORIES)
                ->mapWithKeys(fn (string $category) => [$category => EmailTemplate::variablesForCategory($category)])
                ->all(),
            'variableDefinitions' => EmailTemplate::variableDefinitions(),
        ]);
    }
}
