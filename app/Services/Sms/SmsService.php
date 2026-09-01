<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Models\BeemConfig;
use App\Models\Customer;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\BranchContext;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected BeemSmsClient $beemClient;

    protected WhatsAppService $whatsappClient;

    public function __construct(BeemSmsClient $beemClient, WhatsAppService $whatsappClient)
    {
        $this->beemClient = $beemClient;
        $this->whatsappClient = $whatsappClient;
    }

    public function sendTemplate(string $templateCode, ?string $to, array $data = [], ?Model $reference = null, ?User $actor = null): SmsLog
    {
        $gate = app(SmsNotificationGate::class);

        $templates = SmsTemplate::instance();
        $templateBody = $templates->templates[$templateCode] ?? '';
        $message = app(SmsTemplateRenderer::class)->render($templateBody, $data);
        $whatsappTemplateBody = (SmsTemplate::supportsWhatsappTemplates() ? ($templates->whatsapp_templates[$templateCode] ?? null) : null) ?: $templateBody;
        $whatsappMessage = app(SmsTemplateRenderer::class)->render($whatsappTemplateBody, $data);

        $smsReason = $gate->reasonSmsDisabled($templateCode);
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::requireId();
        $whatsappConfig = WhatsappIntegration::forBranch($branchId);
        $whatsappReason = $whatsappConfig->enabled
            ? $gate->reasonWhatsappDisabled($templateCode)
            : SmsNotificationGate::WHATSAPP_GLOBAL_DISABLED;

        $smsLog = $smsReason === null
            ? $this->sendIfPhonePresent($to, $message, $reference, $actor, $templateCode)
            : $this->createSkippedLog($templateCode, $to, $message, $smsReason, $reference, $actor, 'beem');

        $whatsappLog = null;
        if ($whatsappConfig->enabled) {
            $whatsappLog = $whatsappReason === null
                ? $this->sendWhatsappTemplateIfPhonePresent($templateCode, $to, $whatsappTemplateBody, $whatsappMessage, $data, $reference, $actor)
                : $this->createSkippedLog($templateCode, $to, $whatsappMessage, $whatsappReason, $reference, $actor, 'meta_whatsapp');
        }

        return $smsReason === null ? $smsLog : ($whatsappLog ?? $smsLog);
    }

    /**
     * Send an SMS message and log the attempt.
     *
     * @param  string  $to  Phone number to send to
     * @param  string  $message  The message content
     * @param  Model|null  $reference  Optional model reference (e.g., Order, OrderPayment)
     * @param  User|null  $actor  The user initiating the SMS (optional)
     * @return SmsLog The SMS log record
     */
    public function send(string $to, string $message, ?Model $reference = null, ?User $actor = null, ?string $templateCode = null): SmsLog
    {
        if ($templateCode !== null) {
            $reason = app(SmsNotificationGate::class)->reasonDisabled($templateCode);

            if ($reason !== null) {
                return $this->createSkippedLog($templateCode, $to, $message, $reason, $reference, $actor, 'beem');
            }
        }

        Log::debug('SmsService::send called', [
            'to_raw' => $to,
            'message_length' => strlen($message),
            'reference' => $reference ? $reference->getMorphClass().'#'.$reference->getKey() : null,
            'actor_id' => $actor?->id,
        ]);

        // Normalize phone number to E.164
        $normalizedPhone = Phone::toE164Tz($to);

        // Determine branch_id from reference or context (use getAttribute: Eloquent models don't have literal branch_id property)
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

        // Create initial log entry with queued status
        $smsLog = SmsLog::create([
            'branch_id' => $branchId,
            'provider' => 'beem',
            'template_code' => $templateCode,
            'to' => $normalizedPhone ?? $to, // Store normalized if available, original otherwise
            'message' => $message,
            'status' => SmsStatus::Queued,
            'skip_reason' => null,
            'provider_message_id' => null,
            'provider_response' => null,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->id : null,
            'created_by' => $actor?->id,
        ]);

        // Check if SMS is enabled. The database setting is the operator-controlled source of truth.
        $beemConfig = BeemConfig::instance();
        $smsEnabled = (bool) $beemConfig->sms_enabled;
        Log::info('SMS enabled check', [
            'beem_config_sms_enabled' => $beemConfig->sms_enabled,
            'env_beem_enabled' => config('beem.enabled', false),
            'sms_enabled_result' => $smsEnabled,
            'log_id' => $smsLog->id,
        ]);
        if (! $smsEnabled) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => 'SMS disabled']),
            ]);

            Log::info('SMS disabled - not sent', ['to' => $to, 'log_id' => $smsLog->id]);

            return $smsLog;
        }

        // Validate phone number
        if (empty($normalizedPhone)) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => 'Invalid phone number format']),
            ]);

            Log::warning('SMS failed - invalid phone', ['to' => $to, 'log_id' => $smsLog->id]);

            return $smsLog;
        }

        // Check if client is properly configured
        $isConfigured = $this->beemClient->isConfigured();
        Log::info('Beem client configured check', ['is_configured' => $isConfigured, 'log_id' => $smsLog->id]);
        if (! $isConfigured) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => 'SMS provider not configured (missing api_key or secret_key)']),
            ]);

            Log::warning('SMS failed - Beem not configured (check Beem Configurations or BEEM_* env vars)', ['log_id' => $smsLog->id]);

            return $smsLog;
        }

        try {
            Log::info('SMS sending to Beem API', ['to' => $normalizedPhone, 'log_id' => $smsLog->id]);
            // Send via Beem client
            $response = $this->beemClient->send($normalizedPhone, $message);

            // Update log based on response
            if ($response['success']) {
                $smsLog->update([
                    'status' => SmsStatus::Sent,
                    'provider_message_id' => $response['message_id'],
                    'provider_response' => json_encode($response['raw_response']),
                ]);

                Log::info('SMS sent successfully', [
                    'to' => $normalizedPhone,
                    'log_id' => $smsLog->id,
                    'message_id' => $response['message_id'],
                ]);
            } else {
                $smsLog->update([
                    'status' => SmsStatus::Failed,
                    'provider_response' => json_encode($response['raw_response']),
                ]);

                Log::warning('SMS sending failed', [
                    'to' => $normalizedPhone,
                    'log_id' => $smsLog->id,
                    'response' => $response['raw_response'],
                ]);
            }
        } catch (\Exception $e) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => $e->getMessage()]),
            ]);

            Log::error('SMS exception', [
                'to' => $normalizedPhone,
                'log_id' => $smsLog->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $smsLog->fresh();
    }

    /**
     * Send SMS if phone number is present, otherwise create failed log.
     */
    public function sendIfPhonePresent(?string $to, string $message, ?Model $reference = null, ?User $actor = null, ?string $templateCode = null): ?SmsLog
    {
        if (empty($to)) {
            Log::info('SMS skipped - no phone number', [
                'reference' => $reference ? $reference->getMorphClass().'#'.$reference->getKey() : null,
            ]);
            // Create a failed log entry for missing phone (use getAttribute: Eloquent models don't have literal branch_id property)
            $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

            return SmsLog::create([
                'branch_id' => $branchId,
                'provider' => 'beem',
                'template_code' => $templateCode,
                'to' => 'missing',
                'message' => $message,
                'status' => SmsStatus::Failed,
                'skip_reason' => null,
                'provider_message_id' => null,
                'provider_response' => json_encode(['error' => 'Missing phone number']),
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference ? $reference->id : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $this->send($to, $message, $reference, $actor, $templateCode);
    }

    public function sendWhatsappIfPhonePresent(?string $to, string $message, ?Model $reference = null, ?User $actor = null, ?string $templateCode = null): SmsLog
    {
        if (empty($to)) {
            $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

            return SmsLog::create([
                'branch_id' => $branchId,
                'provider' => 'meta_whatsapp',
                'template_code' => $templateCode,
                'to' => 'missing',
                'message' => $message,
                'status' => SmsStatus::Failed,
                'skip_reason' => null,
                'provider_message_id' => null,
                'provider_response' => json_encode(['error' => 'Missing phone number']),
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference ? $reference->id : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $this->sendWhatsapp($to, $message, $reference, $actor, $templateCode);
    }

    public function sendWhatsappTemplateIfPhonePresent(string $templateCode, ?string $to, string $templateBody, string $renderedMessage, array $data = [], ?Model $reference = null, ?User $actor = null): SmsLog
    {
        if (empty($to)) {
            $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

            return SmsLog::create([
                'branch_id' => $branchId,
                'provider' => 'meta_whatsapp',
                'template_code' => $templateCode,
                'to' => 'missing',
                'message' => $renderedMessage,
                'status' => SmsStatus::Failed,
                'skip_reason' => null,
                'provider_message_id' => null,
                'provider_response' => json_encode(['error' => 'Missing phone number']),
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference ? $reference->id : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $this->sendWhatsappTemplate($templateCode, $to, $templateBody, $renderedMessage, $data, $reference, $actor);
    }

    public function sendWhatsappTemplate(string $templateCode, string $to, string $templateBody, string $renderedMessage, array $data = [], ?Model $reference = null, ?User $actor = null): SmsLog
    {
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::requireId();
        $normalizedPhone = Phone::toE164Tz($to);
        $settings = SmsTemplate::instance()->settingsFor($templateCode) ?? [];
        $templateName = (string) ($settings['whatsapp_template_name'] ?? $templateCode);
        $templateLanguage = $settings['whatsapp_template_language'] ?? null;
        $template = WhatsappTemplate::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereHas('integration', fn ($query) => $query->where('enabled', true))
            ->where('name', $templateName)
            ->when($templateLanguage, fn ($query, $language) => $query->where('language', $language))
            ->whereRaw('UPPER(meta_status) = ?', ['APPROVED'])
            ->whereNull('deleted_at_meta')
            ->orderByDesc('last_synced_at')
            ->first();
        $customerId = $this->customerIdFor($reference);
        $customer = $customerId ? Customer::withoutGlobalScopes()->where('branch_id', $branchId)->find($customerId) : null;
        $reason = null;
        if (! $template) {
            $reason = 'whatsapp_template_missing_or_unapproved';
        } elseif (! $customer || ! $customer->whatsapp_opted_in_at) {
            $reason = 'whatsapp_opt_in_required';
        } elseif ($template->category === 'MARKETING' && ! $customer->whatsapp_marketing_opted_in_at) {
            $reason = 'whatsapp_marketing_opt_in_required';
        }
        if (! $normalizedPhone || $reason) {
            return $this->createSkippedLog($templateCode, $to, $renderedMessage, $reason ?: 'invalid_phone', $reference, $actor, 'meta_whatsapp');
        }

        $smsLog = SmsLog::create([
            'branch_id' => $branchId, 'provider' => 'meta_whatsapp', 'template_code' => $templateCode,
            'to' => $normalizedPhone, 'message' => $renderedMessage, 'status' => SmsStatus::Queued,
            'skip_reason' => null, 'provider_message_id' => null, 'provider_response' => null,
            'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(), 'created_by' => $actor?->id,
        ]);
        $result = $this->whatsappClient->queueTemplate(
            $branchId, $normalizedPhone, $template, $data, $customerId, $reference,
            ['notification_code' => $templateCode, 'actor_id' => $actor?->id, 'rendered_message' => $renderedMessage, 'sms_log_id' => $smsLog->id]
        );
        if (! $result['success']) {
            $smsLog->update(['status' => SmsStatus::Failed, 'provider_response' => json_encode($result)]);

            return $smsLog->fresh();
        }
        $smsLog->update(['whatsapp_message_id' => $result['message_id'], 'provider_response' => json_encode($result)]);

        return $smsLog->fresh();
    }

    public function sendWhatsapp(string $to, string $message, ?Model $reference = null, ?User $actor = null, ?string $templateCode = null): SmsLog
    {
        $normalizedPhone = Phone::toE164Tz($to);
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::requireId();
        $smsLog = SmsLog::create(['branch_id' => $branchId, 'provider' => 'meta_whatsapp', 'template_code' => $templateCode, 'to' => $normalizedPhone ?? $to, 'message' => $message, 'status' => SmsStatus::Queued, 'skip_reason' => null, 'provider_message_id' => null, 'provider_response' => null, 'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->id, 'created_by' => $actor?->id]);
        if (empty($normalizedPhone)) {
            $smsLog->update(['status' => SmsStatus::Failed, 'provider_response' => json_encode(['error' => 'Invalid phone number format'])]);

            return $smsLog;
        }
        $result = $this->whatsappClient->sendText($branchId, $normalizedPhone, $message);
        $smsLog->update(['status' => $result['success'] ? SmsStatus::Queued : SmsStatus::Failed, 'provider_message_id' => null, 'provider_response' => json_encode($result)]);

        return $smsLog->fresh();
    }

    public function retryFailedLog(SmsLog $log, ?User $actor = null): SmsLog
    {
        $reference = $log->reference;
        $provider = strtolower((string) $log->provider);

        if ($provider === 'meta_whatsapp') {
            return $this->sendWhatsapp(
                to: $log->to,
                message: $log->message,
                reference: $reference,
                actor: $actor,
                templateCode: $log->template_code,
            );
        }

        return $this->send(
            to: $log->to,
            message: $log->message,
            reference: $reference,
            actor: $actor,
            templateCode: $log->template_code,
        );
    }

    protected function createSkippedLog(string $templateCode, ?string $to, string $message, string $reason, ?Model $reference = null, ?User $actor = null, string $provider = 'beem'): SmsLog
    {
        $normalizedPhone = filled($to) ? Phone::toE164Tz($to) : null;
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

        Log::info('Message skipped by notification gate', [
            'provider' => $provider,
            'template_code' => $templateCode,
            'reason' => $reason,
            'reference' => $reference ? $reference->getMorphClass().'#'.$reference->getKey() : null,
        ]);

        return SmsLog::create([
            'branch_id' => $branchId,
            'provider' => $provider,
            'template_code' => $templateCode,
            'to' => $normalizedPhone ?? $to ?? 'missing',
            'message' => $message,
            'status' => SmsStatus::Skipped,
            'skip_reason' => $reason,
            'provider_message_id' => null,
            'provider_response' => json_encode(['skipped' => true, 'reason' => $reason]),
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->id : null,
            'created_by' => $actor?->id,
        ]);
    }

    protected function contentVariablesFor(string $templateBody, array $data): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $templateBody, $matches);

        $variables = [];
        $indexByName = [];

        foreach ($matches[1] ?? [] as $name) {
            if (isset($indexByName[$name])) {
                continue;
            }

            $index = (string) (count($indexByName) + 1);
            $indexByName[$name] = $index;
            $value = $data[$name] ?? '';

            $variables[$index] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value);
        }

        return $variables;
    }

    protected function customerIdFor(?Model $reference): ?int
    {
        if (! $reference) {
            return null;
        }
        if ($reference instanceof Customer) {
            return (int) $reference->getKey();
        }
        $customerId = $reference->getAttribute('customer_id');
        if ($customerId) {
            return (int) $customerId;
        }
        $orderId = $reference->getAttribute('order_id');
        if ($orderId) {
            return \App\Models\Order::withoutGlobalScopes()->whereKey($orderId)->value('customer_id');
        }

        return null;
    }
}
