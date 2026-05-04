<?php

namespace App\Services\Sms;

use App\Models\BeemConfig;
use App\Models\SmsTemplate;

class SmsNotificationGate
{
    public const SMS_GLOBAL_DISABLED = 'sms_global_disabled';
    public const TEMPLATE_NOT_FOUND = 'template_not_found';
    public const TEMPLATE_INACTIVE = 'template_inactive';
    public const TEMPLATE_SMS_DISABLED = 'template_sms_disabled';

    public function canSend(string $templateCode): bool
    {
        return $this->reasonDisabled($templateCode) === null;
    }

    public function reasonDisabled(string $templateCode): ?string
    {
        if (! BeemConfig::instance()->sms_enabled) {
            return self::SMS_GLOBAL_DISABLED;
        }

        $templates = SmsTemplate::instance();
        $settings = $templates->settingsFor($templateCode);

        if ($settings === null) {
            return self::TEMPLATE_NOT_FOUND;
        }

        if (! (bool) ($settings['is_active'] ?? false)) {
            return self::TEMPLATE_INACTIVE;
        }

        if (! (bool) ($settings['sms_enabled'] ?? false)) {
            return self::TEMPLATE_SMS_DISABLED;
        }

        return null;
    }
}
