<?php

namespace App\Jobs;

use App\Contracts\WhatsAppProvider;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppProvider $provider, WhatsAppPhoneNormalizer $phones, ?WhatsappMessageLifecycle $lifecycle = null): void
    {
        $lifecycle ??= app(WhatsappMessageLifecycle::class);
        $message = WhatsappMessage::withoutGlobalScopes()->with('integration')->findOrFail($this->messageId);
        if ($message->external_message_id && in_array($message->status, ['accepted', 'sent', 'delivered', 'read'], true)) {
            return;
        }
        if (! in_array($message->status, ['queued', 'submitting'], true)) {
            return;
        } $integration = $message->integration;
        if (! $integration->enabled || ! $integration->isConfigured()) {
            $this->failMessage($message, 'not_configured', 'Meta WhatsApp is disabled or not configured.');

            return;
        } $recipient = $phones->metaRecipient($message->phone);
        if (! $recipient) {
            $this->failMessage($message, 'invalid_recipient', 'The WhatsApp recipient is invalid.');

            return;
        } $message->update(['status' => 'submitting', 'submitted_at' => now()]);
        if ($message->message_type === 'template') {
            $snapshot = $message->safe_metadata ?: [];
            $result = $provider->sendTemplate($integration, $recipient, (string) ($snapshot['template_name'] ?? ''), (string) ($snapshot['language'] ?? ''), $snapshot['components'] ?? []);
        } else {
            $result = $provider->sendText($integration, $recipient, (string) $message->body);
        }
        if ($result['success']) {
            $lifecycle->accepted($message, $result['message_id']);

            return;
        } if ($result['integration_failure'] ?? false) {
            $integration->update(['connection_status' => 'connection_error', 'last_error_code' => $result['error_code'], 'last_error_message' => $result['error_message']]);
        } if ($result['retryable'] ?? false) {
            throw new RuntimeException($result['error_code']);
        } $this->failMessage($message, $result['error_code'], $result['error_message']);
    }

    public function failed(?\Throwable $exception): void
    {
        $message = WhatsappMessage::withoutGlobalScopes()->find($this->messageId);
        if ($message && ! in_array($message->status, ['accepted', 'sent', 'delivered', 'read'], true)) {
            $this->failMessage($message, 'retries_exhausted', 'Meta remained temporarily unavailable after limited retries.');
        }
    }

    protected function failMessage(WhatsappMessage $message, string $code, string $reason): void
    {
        $message->update(['status' => 'failed', 'failure_code' => $code, 'failure_reason' => $reason, 'failed_at' => now()]);
        $message->smsLog()->update(['status' => 'failed', 'provider_response' => json_encode(['error_code' => $code, 'error_message' => $reason])]);
    }
}
