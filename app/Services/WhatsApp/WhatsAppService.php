<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Jobs\SendWhatsappMessage;
use App\Models\SmsLog;
use App\Models\WhatsappContact;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Model;

class WhatsAppService
{
    public function __construct(protected WhatsAppProvider $provider, protected WhatsAppPhoneNormalizer $phones) {}

    public function testConnection(WhatsappIntegration $integration): array
    {
        $result = $this->provider->testConnection($integration);
        $now = now();
        $integration->forceFill(['connection_status' => $result['success'] ? 'connected' : 'connection_error', 'last_checked_at' => $now, 'last_connected_at' => $result['success'] ? $now : $integration->last_connected_at, 'display_phone_number' => $result['success'] ? data_get($result, 'data.display_phone_number') : $integration->display_phone_number, 'verified_name' => $result['success'] ? data_get($result, 'data.verified_name') : $integration->verified_name, 'last_error_code' => $result['success'] ? null : $result['error_code'], 'last_error_message' => $result['success'] ? null : $result['error_message']])->save();

        return $result;
    }

    public function queueText(int $branchId, string $recipient, string $message, ?int $customerId = null, ?Model $context = null): array
    {
        $integration = WhatsappIntegration::forBranch($branchId);
        if (! $integration->enabled) {
            return $this->rejected('disabled', 'WhatsApp is disabled.');
        } if (! $integration->isConfigured()) {
            return $this->rejected('not_configured', 'Meta WhatsApp is not configured.');
        } $phone = $this->phones->normalize($recipient);
        if (! $phone) {
            return $this->rejected('invalid_recipient', 'The WhatsApp recipient is invalid.');
        } if (! $this->canSendFreeFormMessage($integration, $phone)) {
            return $this->rejected('template_required', 'An approved template is required outside the 24-hour customer service window.');
        } $contact = WhatsappContact::firstOrCreate(['whatsapp_integration_id' => $integration->id, 'phone' => $phone], ['branch_id' => $branchId, 'customer_id' => $customerId]);
        if ($customerId && ! $contact->customer_id) {
            $contact->update(['customer_id' => $customerId]);
        } $record = WhatsappMessage::create(['branch_id' => $branchId, 'whatsapp_integration_id' => $integration->id, 'whatsapp_contact_id' => $contact->id, 'customer_id' => $customerId ?: $contact->customer_id, 'direction' => 'outbound', 'message_type' => 'text', 'phone' => $phone, 'body' => $message, 'status' => 'queued', 'queued_at' => now(), 'context_type' => $context?->getMorphClass(), 'context_id' => $context?->getKey()]);
        SendWhatsappMessage::dispatch($record->id);

        return ['success' => true, 'queued' => true, 'message_id' => $record->id, 'status' => 'queued'];
    }

    public function sendText(int $branchId, string $recipient, string $message): array
    {
        return $this->queueText($branchId, $recipient, $message);
    }

    public function queueTemplate(int $branchId, string $recipient, WhatsappTemplate $template, array $values, ?int $customerId = null, ?Model $context = null, array $metadata = []): array
    {
        $integration = WhatsappIntegration::forBranch($branchId);
        if (! $integration->enabled) {
            return $this->rejected('disabled', 'WhatsApp is disabled.');
        }
        if (! $integration->isConfigured()) {
            return $this->rejected('not_configured', 'Meta WhatsApp is not configured.');
        }
        if ((int) $template->branch_id !== $branchId || (int) $template->whatsapp_integration_id !== (int) $integration->id) {
            return $this->rejected('template_scope_mismatch', 'The WhatsApp template does not belong to this integration.');
        }
        if (! $template->isSendable()) {
            return $this->rejected('template_not_approved', 'The WhatsApp template is not approved for sending.');
        }
        if (! preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/', $template->language)) {
            return $this->rejected('invalid_template_language', 'The WhatsApp template language is invalid.');
        }
        $phone = $this->phones->normalize($recipient);
        if (! $phone) {
            return $this->rejected('invalid_recipient', 'The WhatsApp recipient is invalid.');
        }
        $resolved = $this->templateComponents($template, $values);
        if (! $resolved['success']) {
            return $resolved;
        }
        $contact = WhatsappContact::firstOrCreate(
            ['whatsapp_integration_id' => $integration->id, 'phone' => $phone],
            ['branch_id' => $branchId, 'customer_id' => $customerId]
        );
        if ($customerId && ! $contact->customer_id) {
            $contact->update(['customer_id' => $customerId]);
        }
        $record = WhatsappMessage::create([
            'branch_id' => $branchId,
            'whatsapp_integration_id' => $integration->id,
            'whatsapp_contact_id' => $contact->id,
            'customer_id' => $customerId ?: $contact->customer_id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'phone' => $phone,
            'body' => $metadata['rendered_message'] ?? null,
            'status' => 'queued',
            'queued_at' => now(),
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'safe_metadata' => [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'language' => $template->language,
                'components' => $resolved['components'],
                'notification_code' => $metadata['notification_code'] ?? null,
                'actor_id' => $metadata['actor_id'] ?? null,
            ],
        ]);
        if (! empty($metadata['sms_log_id'])) {
            SmsLog::withoutGlobalScopes()->where('branch_id', $branchId)->whereKey((int) $metadata['sms_log_id'])->update(['whatsapp_message_id' => $record->id]);
        }
        SendWhatsappMessage::dispatch($record->id);

        return ['success' => true, 'queued' => true, 'message_id' => $record->id, 'status' => 'queued'];
    }

    public function canSendFreeFormMessage(WhatsappIntegration $integration, string $phone, ?\DateTimeInterface $at = null): bool
    {
        $normalized = $this->phones->normalize($phone);
        if (! $normalized) {
            return false;
        } $last = WhatsappContact::withoutGlobalScopes()->where('whatsapp_integration_id', $integration->id)->where('phone', $normalized)->value('last_customer_message_at');

        return $last && now()->setTimestamp(($at?->getTimestamp()) ?? now()->timestamp)->diffInSeconds($last, false) >= -86400;
    }

    public function markAsRead(WhatsappMessage $message): array
    {
        if ($message->direction !== 'inbound' || ! $message->external_message_id) {
            return $this->rejected('invalid_message', 'Only persisted inbound messages can be marked read.');
        }

        return $this->provider->markAsRead($message->integration, $message->external_message_id);
    }

    public function configureWebhooks(WhatsappIntegration $integration, string $callbackUrl): array
    {
        $result = $this->provider->configureWebhooks($integration, $callbackUrl);
        $integration->update(['webhook_status' => $result['success'] ? 'verified' : 'subscription_error', 'webhook_checked_at' => now(), 'webhook_error_message' => $result['success'] ? null : $result['error_message']]);

        return $result;
    }

    protected function rejected(string $code, string $message): array
    {
        return ['success' => false, 'error_code' => $code, 'error_message' => $message, 'retryable' => false];
    }

    protected function templateComponents(WhatsappTemplate $template, array $values): array
    {
        $components = [];
        $mappings = $template->variable_mappings ?: [];
        foreach ($template->components ?: [] as $component) {
            $type = strtolower((string) ($component['type'] ?? ''));
            $format = strtolower((string) ($component['format'] ?? ''));
            if ($type === 'header' && in_array($format, ['image', 'video', 'document'], true)) {
                $media = $values['header_'.$format] ?? $values['header_media'] ?? null;
                if (! is_string($media) || blank($media)) {
                    return $this->rejected('template_variables_missing', 'Required WhatsApp header media is missing.');
                }
                $parameter = ['type' => $format, $format => ['link' => $media]];
                if ($format === 'document' && filled($values['header_filename'] ?? null)) {
                    $parameter[$format]['filename'] = (string) $values['header_filename'];
                }
                $components[] = ['type' => 'header', 'parameters' => [$parameter]];

                continue;
            }
            if (in_array($type, ['header', 'body'], true)) {
                preg_match_all('/\{\{(\d+)\}\}/', (string) ($component['text'] ?? ''), $matches);
                $parameters = [];
                foreach (array_values(array_unique($matches[1] ?? [])) as $position) {
                    $key = $mappings[$position] ?? $mappings[(int) $position] ?? null;
                    if (! is_string($key) || ! array_key_exists($key, $values) || ! is_scalar($values[$key])) {
                        return $this->rejected('template_variables_missing', 'Required WhatsApp template variables are missing.');
                    }
                    $parameters[] = ['type' => 'text', 'text' => (string) $values[$key]];
                }
                if ($parameters !== []) {
                    $components[] = ['type' => $type, 'parameters' => $parameters];
                }
            }
            if ($type === 'buttons') {
                foreach ($component['buttons'] ?? [] as $index => $button) {
                    $subType = strtolower((string) ($button['type'] ?? ''));
                    $dynamic = (string) ($button['url'] ?? $button['payload'] ?? '');
                    if (! in_array($subType, ['url', 'quick_reply'], true) || ! str_contains($dynamic, '{{')) {
                        continue;
                    }
                    preg_match('/\{\{(\d+)\}\}/', $dynamic, $match);
                    $position = $match[1] ?? null;
                    $key = $position ? ($mappings[$position] ?? $mappings[(int) $position] ?? null) : null;
                    if (! is_string($key) || ! array_key_exists($key, $values) || ! is_scalar($values[$key])) {
                        return $this->rejected('template_variables_missing', 'Required WhatsApp button variables are missing.');
                    }
                    $parameters = [[
                        'type' => $subType === 'quick_reply' ? 'payload' : 'text',
                        $subType === 'quick_reply' ? 'payload' : 'text' => (string) $values[$key],
                    ]];
                    $components[] = ['type' => 'button', 'sub_type' => $subType, 'index' => (string) $index, 'parameters' => $parameters];
                }
            }
        }

        return ['success' => true, 'components' => $components];
    }
}
