<?php

namespace App\Services\Mail;

use App\Models\BusinessSetting;

class MailConfiguration
{
    public function apply(BusinessSetting $settings, bool $purgeResolvedMailers = false): void
    {
        if (filled($settings->mail_mailer)) {
            config()->set('mail.default', $settings->mail_mailer);
        }

        if (filled($settings->mail_host)) {
            config()->set('mail.mailers.smtp.host', $settings->mail_host);
        }

        if ($settings->mail_port) {
            config()->set('mail.mailers.smtp.port', (int) $settings->mail_port);
        }

        if (filled($settings->mail_username)) {
            config()->set('mail.mailers.smtp.username', $settings->mail_username);
        }

        if (filled($settings->mail_password)) {
            config()->set('mail.mailers.smtp.password', $settings->mail_password);
        }

        if ($settings->mail_timeout) {
            config()->set('mail.mailers.smtp.timeout', (int) $settings->mail_timeout);
        }

        if ($settings->mail_encryption !== null) {
            $encryption = $settings->mail_encryption;
            config()->set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : 'smtp');
            config()->set('mail.mailers.smtp.auto_tls', $encryption !== 'none');
            config()->set('mail.mailers.smtp.require_tls', $encryption === 'tls');
        }

        if (filled($settings->email_from_address)) {
            config()->set('mail.from.address', $settings->email_from_address);
        }

        if (filled($settings->email_from_name)) {
            config()->set('mail.from.name', $settings->email_from_name);
        }

        if (filled($settings->email_reply_to)) {
            config()->set('mail.reply_to.address', $settings->email_reply_to);
        }

        $this->configureLocalCertificateAuthority();

        if ($purgeResolvedMailers && app()->bound('mail.manager')) {
            app('mail.manager')->purge();
        }
    }

    public function localCertificateAuthority(?string $environment = null): ?string
    {
        $environment ??= app()->environment();
        if (! in_array($environment, ['local', 'testing'], true)) {
            return null;
        }

        $path = storage_path('ssl/cacert.pem');

        return is_file($path) ? $path : null;
    }

    protected function configureLocalCertificateAuthority(): void
    {
        $path = $this->localCertificateAuthority();
        if ($path) {
            ini_set('openssl.cafile', $path);
        }
    }
}
