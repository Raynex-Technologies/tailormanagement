<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\Templates\WhatsappTemplateStatusService;
use App\Services\WhatsApp\WhatsappMessageLifecycle;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsappWebhook implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(WhatsAppPhoneNormalizer $phones, WhatsappMessageLifecycle $lifecycle, ?WhatsappTemplateStatusService $templateStatuses = null): void
    {
        $templateStatuses ??= app(WhatsappTemplateStatusService::class);
        $event = WhatsappWebhookEvent::with('integration')->findOrFail($this->eventId);
        if ($event->processed_at) {
            return;
        } $integration = $event->integration;
        foreach ($event->payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (($change['field'] ?? null) === 'message_template_status_update') {
                    $this->templateStatus($integration, $value, $templateStatuses);

                    continue;
                }
                foreach ($value['messages'] ?? [] as $incoming) {
                    $this->inbound($integration, $incoming, $phones);
                } foreach ($value['statuses'] ?? [] as $status) {
                    $this->status($status, $lifecycle);
                }
            }
        }
        $event->update(['processed_at' => now(), 'processing_error' => null]);
    }

    protected function templateStatus($integration, array $value, WhatsappTemplateStatusService $statuses): void
    {
        $metaId = (string) ($value['message_template_id'] ?? $value['template_id'] ?? '');
        $name = (string) ($value['message_template_name'] ?? $value['name'] ?? '');
        $language = (string) ($value['message_template_language'] ?? $value['language'] ?? '');
        $template = WhatsappTemplate::withoutGlobalScopes()->where('whatsapp_integration_id', $integration->id)
            ->where(function ($query) use ($metaId, $name, $language) {
                if ($metaId !== '') {
                    $query->where('meta_template_id', $metaId);
                }
                if ($name !== '') {
                    $query->orWhere(function ($query) use ($name, $language) {
                        $query->where('name', $name);
                        if ($language !== '') {
                            $query->where('language', $language);
                        }
                    });
                }
            })->first();
        if (! $template) {
            return;
        }
        $status = (string) ($value['event'] ?? $value['status'] ?? 'UNKNOWN');
        $reason = $value['reason'] ?? $value['rejection_reason'] ?? null;
        $statuses->apply($template, $status, 'webhook', is_string($reason) ? $reason : json_encode($reason), now(), $value['quality_score'] ?? null);
    }

    protected function inbound($integration, array $incoming, WhatsAppPhoneNormalizer $phones): void
    {
        $external = (string) ($incoming['id'] ?? '');
        if ($external === '' || WhatsappMessage::withoutGlobalScopes()->where('external_message_id', $external)->exists()) {
            return;
        } $phone = $phones->normalize($incoming['from'] ?? null);
        if (! $phone) {
            return;
        } $customer = Customer::withoutGlobalScopes()->where('branch_id', $integration->branch_id)->get(['id', 'phone'])->first(fn ($candidate) => $phones->normalize($candidate->phone) === $phone);
        $at = CarbonImmutable::createFromTimestampUTC((int) ($incoming['timestamp'] ?? now()->timestamp));
        $contact = WhatsappContact::withoutGlobalScopes()->firstOrCreate(['whatsapp_integration_id' => $integration->id, 'phone' => $phone], ['branch_id' => $integration->branch_id]);
        $contact->update(['customer_id' => $customer?->id ?: $contact->customer_id, 'last_customer_message_at' => $at]);
        $type = (string) ($incoming['type'] ?? 'unknown');
        WhatsappMessage::withoutGlobalScopes()->create(['branch_id' => $integration->branch_id, 'whatsapp_integration_id' => $integration->id, 'whatsapp_contact_id' => $contact->id, 'customer_id' => $customer?->id, 'external_message_id' => $external, 'direction' => 'inbound', 'message_type' => $type, 'phone' => $phone, 'body' => $type === 'text' ? data_get($incoming, 'text.body') : null, 'status' => 'delivered', 'delivered_at' => $at, 'meta_timestamp' => $at, 'safe_metadata' => $type === 'text' ? null : ['type' => $type]]);
    }

    protected function status(array $status, WhatsappMessageLifecycle $lifecycle): void
    {
        $id = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');
        if ($id === '' || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        } $message = WhatsappMessage::withoutGlobalScopes()->where('external_message_id', $id)->first();
        if (! $message) {
            return;
        } $at = CarbonImmutable::createFromTimestampUTC((int) ($status['timestamp'] ?? now()->timestamp));
        $error = $status['errors'][0] ?? [];
        $lifecycle->apply($message, $state, $at, isset($error['code']) ? (string) $error['code'] : null, $state === 'failed' ? $this->safeFailure($error) : null);
    }

    protected function safeFailure(array $error): string
    {
        $code = (string) ($error['code'] ?? 'unknown');

        return match ((int) $code) {
            131026 => 'The recipient could not receive this message.',131047 => 'The customer service window has closed; an approved template is required.',default => 'Meta reported that the message failed.'
        };
    }
}
