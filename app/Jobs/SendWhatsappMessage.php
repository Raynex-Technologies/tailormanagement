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
        if (! config('twilio.active')) {
            $this->sendLocked($provider, $phones, $lifecycle);

            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($provider, $phones, $lifecycle) {
            WhatsappMessage::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->messageId);
            $this->sendLocked($provider, $phones, $lifecycle);
        });
    }

    private function sendLocked(WhatsAppProvider $provider, WhatsAppPhoneNormalizer $phones, ?WhatsappMessageLifecycle $lifecycle): void
    {
        $lifecycle ??= app(WhatsappMessageLifecycle::class);
        $message = WhatsappMessage::withoutGlobalScopes()->with('integration')->findOrFail($this->messageId);
        if ($message->external_message_id && in_array($message->status, ['accepted', 'sent', 'delivered', 'read'], true)) {
            return;
        }
        if (! in_array($message->status, ['queued', 'submitting'], true)) {
            return;
        }
        if (config('twilio.active') && (($message->safe_metadata['provider'] ?? 'meta') !== 'twilio')) {
            $this->failMessage($message, 'provider_changed', 'This message was queued for Meta. Review it before sending through Twilio.');

            return;
        }
        $integration = $message->integration;
        if (! $integration->enabled || ! $integration->isConfigured()) {
            $this->failMessage($message, 'not_configured', 'WhatsApp is disabled or not configured.');

            return;
        }
        if (config('twilio.active') && (($message->safe_metadata['twilio_account_sid'] ?? null) !== $integration->twilio_account_sid || ($message->safe_metadata['twilio_from'] ?? null) !== $integration->twilio_from)) {
            $this->failMessage($message, 'sender_changed', 'The Twilio account or sender changed after this message was queued.');

            return;
        }
        if (config('twilio.active') && $message->message_type === 'text' && ! app(\App\Services\WhatsApp\WhatsAppService::class)->canSendFreeFormMessage($integration, $message->phone)) {
            $this->failMessage($message, 'template_required', 'The customer service window has closed. Use an approved template.');

            return;
        }
        $recipient = $phones->metaRecipient($message->phone);
        if (! $recipient) {
            $this->failMessage($message, 'invalid_recipient', 'The WhatsApp recipient is invalid.');

            return;
        } $message->update(['status' => 'submitting', 'submitted_at' => now()]);
        if ($message->message_type === 'template') {
            $snapshot = $message->safe_metadata ?: [];
            $template = \App\Models\WhatsappTemplate::withoutGlobalScopes()->where('whatsapp_integration_id', $integration->id)->find($snapshot['template_id'] ?? 0);
            if (config('twilio.active') && (! $template || ! $template->isSendable() || $template->twilio_content_sid !== ($snapshot['twilio_content_sid'] ?? null) || $integration->twilio_account_sid !== ($snapshot['twilio_account_sid'] ?? null))) {
                $this->failMessage($message, 'template_not_approved', 'The queued Twilio template is no longer approved for this account.');

                return;
            }
            $result = $provider->sendTemplate($integration, $recipient, (string) ($snapshot[config('twilio.active') ? 'twilio_content_sid' : 'template_name'] ?? ''), (string) ($snapshot['language'] ?? ''), $snapshot['components'] ?? []);
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
        } $this->failMessage($message, ($result['outcome_unknown'] ?? false) ? 'delivery_unknown' : $result['error_code'], $result['error_message']);
    }

    public function failed(?\Throwable $exception): void
    {
        $message = WhatsappMessage::withoutGlobalScopes()->find($this->messageId);
        if ($message && ! in_array($message->status, ['accepted', 'sent', 'delivered', 'read'], true)) {
            $this->failMessage($message, 'retries_exhausted', 'WhatsApp remained temporarily unavailable after limited retries.');
        }
    }

    protected function failMessage(WhatsappMessage $message, string $code, string $reason): void
    {
        $message->update(['status' => 'failed', 'failure_code' => $code, 'failure_reason' => $reason, 'failed_at' => now()]);
        $message->smsLog()->update(['status' => 'failed', 'provider_response' => json_encode(['error_code' => $code, 'error_message' => $reason])]);
    }
}
