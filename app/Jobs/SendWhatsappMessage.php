<?php

namespace App\Jobs;

use App\Contracts\WhatsAppProvider;
use App\Models\WhatsappMessage;
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

    public function handle(WhatsAppProvider $provider, WhatsAppPhoneNormalizer $phones): void
    {
        $message = WhatsappMessage::withoutGlobalScopes()->with('integration')->findOrFail($this->messageId);
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
        $result = $provider->sendText($integration, $recipient, (string) $message->body);
        if ($result['success']) {
            $message->update(['status' => 'accepted', 'external_message_id' => $result['message_id'], 'accepted_at' => now(), 'failure_code' => null, 'failure_reason' => null]);

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
    }
}
