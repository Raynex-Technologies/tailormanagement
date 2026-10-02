<?php

namespace App\Services\WhatsApp\Templates;

use App\Data\WhatsApp\TemplateDefinition;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\TwilioWhatsAppProvider;
use Illuminate\Support\Facades\DB;

class TwilioTemplateManager
{
    public function __construct(private TwilioWhatsAppProvider $provider) {}

    public function validate(TemplateDefinition $definition): TemplateValidationResult
    {
        $base = app(MetaWhatsappTemplateValidator::class)->validate($definition);
        $errors = $base->errors;
        if (count($definition->components) !== 1 || ($definition->components[0]['type'] ?? '') !== 'BODY') {
            $errors[] = ['field' => 'components', 'rule' => 'text_only', 'message' => 'Twilio submission supports a text body with variables. Headers, footers, media, and buttons are not supported in this editor.'];
        }
        if (! in_array($definition->category, ['UTILITY', 'MARKETING'], true)) {
            $errors[] = ['field' => 'category', 'rule' => 'category', 'message' => 'Choose Utility or Marketing for a text notification.'];
        }
        $body = collect($definition->components)->firstWhere('type', 'BODY')['text'] ?? '';
        if (blank($body) || mb_strlen($body) > 1024) {
            $errors[] = ['field' => 'body', 'rule' => 'body_length', 'message' => 'Enter a message body of up to 1024 characters.'];
        }

        return new TemplateValidationResult($errors, $base->warnings);
    }

    public function submit(WhatsappTemplate $template): array
    {
        return DB::transaction(function () use ($template) {
            $t = WhatsappTemplate::withoutGlobalScopes()->lockForUpdate()->findOrFail($template->id);
            $d = new TemplateDefinition($t->name, $t->language, $t->category, $t->components ?: [], $t->variable_mappings ?: []);
            $fingerprint = $d->fingerprint();
            if (! $this->validate($d)->valid() || $t->validation_fingerprint !== $fingerprint) {
                return $this->failure('validation_required', 'Save and successfully validate the current draft before submitting.');
            }
            $i = $t->integration;
            if (! $i->twilioConfigured()) {
                return $this->failure('not_configured', 'Configure this branch’s Twilio credentials first.');
            }
            if ($t->twilio_content_sid && ($t->twilio_content_fingerprint !== $fingerprint || $t->twilio_account_sid !== $i->twilio_account_sid)) {
                return $this->failure('immutable_content', 'Duplicate this template with a new name to submit revised content or use another Twilio account.');
            }
            if ($t->twilio_content_sid && in_array($t->twilio_status, ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'DELETED'], true)) {
                return ['success' => true, 'template' => $t, 'already_submitted' => true];
            }
            if ($t->local_state === 'creation_unknown') {
                return $this->failure('creation_unknown', 'The earlier content creation outcome is unknown. Reconcile its Content SID with Twilio before retrying.');
            }
            if (! $t->twilio_content_sid) {
                $result = $this->provider->createTemplate($i, $d);
                $sid = data_get($result, 'data.sid');
                if (! $result['success'] || ! is_string($sid) || ! preg_match('/^HX[0-9a-fA-F]{32}$/', $sid)) {
                    $t->update(['local_state' => ($result['success'] || ($result['outcome_unknown'] ?? false)) ? 'creation_unknown' : 'ready_to_submit']);

                    return $result['success'] ? $this->failure('missing_content_sid', 'Twilio returned no Content SID. Reconcile with Twilio before retrying.') : $result;
                }
                // Retain this SID even when the subsequent approval request fails.
                $t->update(['twilio_content_sid' => $sid, 'twilio_account_sid' => $i->twilio_account_sid, 'twilio_content_fingerprint' => $fingerprint, 'twilio_status' => 'UNSUBMITTED', 'local_state' => 'ready_to_submit']);
            }
            $approval = $this->provider->approval($i, $t->twilio_content_sid);
            if (! $approval['success']) {
                return $approval;
            }
            $remote = data_get($approval, 'data.whatsapp', []);
            if (! in_array(strtolower((string) ($remote['status'] ?? '')), ['received', 'pending', 'approved', 'rejected', 'paused', 'disabled'], true)) {
                $approval = $this->provider->submitApproval($i, $t->twilio_content_sid, $d);
                if (! $approval['success']) {
                    return $approval;
                }
                $remote = $approval['data'];
            }
            $this->apply($t, $remote, 'twilio_submission');
            $t->update(['submission_fingerprint' => $fingerprint, 'synced_fingerprint' => $fingerprint, 'submitted_at' => now(), 'local_state' => 'synced']);

            return ['success' => true, 'template' => $t->fresh()];
        });
    }

    public function sync(WhatsappIntegration $integration): array
    {
        $count = 0;
        foreach (WhatsappTemplate::withoutGlobalScopes()->where('whatsapp_integration_id', $integration->id)->where('twilio_account_sid', $integration->twilio_account_sid)->whereNotNull('twilio_content_sid')->where('twilio_status', '!=', 'DELETED')->get() as $t) {
            $result = $this->provider->approval($integration, $t->twilio_content_sid);
            if (! $result['success']) {
                return $result;
            }
            $this->apply($t, data_get($result, 'data.whatsapp', []), 'twilio_sync');
            $count++;
        }

        return ['success' => true, 'count' => $count];
    }

    public function reconcile(WhatsappTemplate $template, string $sid): array
    {
        return DB::transaction(function () use ($template, $sid) {
            $t = WhatsappTemplate::withoutGlobalScopes()->lockForUpdate()->findOrFail($template->id);
            if ($t->twilio_content_sid || $t->local_state !== 'creation_unknown') {
                return $this->failure('recovery_not_required', 'Only an uncertain content creation can be reconciled.');
            }
            $result = $this->provider->fetchContent($t->integration, $sid);
            if (! $result['success']) {
                return $result;
            }
            $remote = $result['data'];
            $body = collect($t->components)->firstWhere('type', 'BODY');
            if (($remote['sid'] ?? '') !== $sid || ($remote['account_sid'] ?? '') !== $t->integration->twilio_account_sid
                || ($remote['friendly_name'] ?? '') !== $t->name || ($remote['language'] ?? '') !== $t->language
                || array_keys($remote['types'] ?? []) !== ['twilio/text']
                || data_get($remote, 'types.twilio/text.body') !== ($body['text'] ?? null)
                || ($remote['variables'] ?? []) != ($body['examples'] ?? [])) {
                return $this->failure('content_mismatch', 'This Content SID does not match the saved template and Twilio account.');
            }
            $t->update(['twilio_content_sid' => $sid, 'twilio_account_sid' => $t->integration->twilio_account_sid, 'twilio_content_fingerprint' => $t->definitionFingerprint(), 'twilio_status' => 'UNSUBMITTED', 'local_state' => 'ready_to_submit']);

            return ['success' => true];
        });
    }

    public function delete(WhatsappTemplate $template): array
    {
        if (! $template->twilio_content_sid) {
            if ($template->meta_template_id) {
                return $this->failure('legacy_template', 'This is a historical Meta template. It cannot be deleted through Twilio.');
            }
            $template->delete();

            return ['success' => true];
        }
        if ($template->twilio_account_sid !== $template->integration->twilio_account_sid) {
            return $this->failure('account_mismatch', 'Restore the owning Twilio account before deleting this content.');
        }
        $result = $this->provider->deleteTemplate($template->integration, $template->twilio_content_sid);
        if ($result['success']) {
            $this->apply($template, ['status' => 'deleted'], 'twilio_delete');
        }

        return $result;
    }

    private function apply(WhatsappTemplate $t, array $remote, string $source): void
    {
        $raw = strtoupper((string) ($remote['status'] ?? 'UNKNOWN'));
        $status = match ($raw) {
            'RECEIVED', 'PENDING' => 'PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'DELETED', 'UNSUBMITTED' => $raw, default => 'UNKNOWN'
        };
        $reason = is_string($remote['rejection_reason'] ?? null) ? mb_substr($remote['rejection_reason'], 0, 2000) : null;
        if ($t->twilio_status !== $status) {
            $t->histories()->create(['previous_status' => $t->twilio_status, 'new_status' => $status, 'source' => $source, 'reason' => $reason, 'occurred_at' => now()]);
        }
        $t->update(['twilio_status' => $status, 'rejection_reason' => $reason, 'last_synced_at' => now()]);
    }

    private function failure(string $code, string $message): array
    {
        return ['success' => false, 'error_code' => $code, 'error_message' => $message];
    }
}
