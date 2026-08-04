<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Jobs\SendWhatsappMessage;
use App\Models\WhatsappContact;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappMessage;
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
}
