<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Models\BeemConfig;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected BeemSmsClient $beemClient;

    public function __construct(BeemSmsClient $beemClient)
    {
        $this->beemClient = $beemClient;
    }

    public function sendTemplate(string $templateCode, ?string $to, array $data = [], ?Model $reference = null, ?User $actor = null): SmsLog
    {
        $gate = app(SmsNotificationGate::class);
        $reason = $gate->reasonDisabled($templateCode);

        $templates = SmsTemplate::instance();
        $templateBody = $templates->templates[$templateCode] ?? '';
        $message = app(SmsTemplateRenderer::class)->render($templateBody, $data);

        if ($reason !== null) {
            return $this->createSkippedLog($templateCode, $to, $message, $reason, $reference, $actor);
        }

        return $this->sendIfPhonePresent($to, $message, $reference, $actor, $templateCode);
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
                return $this->createSkippedLog($templateCode, $to, $message, $reason, $reference, $actor);
            }
        }

        Log::debug('SmsService::send called', [
            'to_raw' => $to,
            'message_length' => strlen($message),
            'reference' => $reference ? $reference->getMorphClass() . '#' . $reference->getKey() : null,
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
                'reference' => $reference ? $reference->getMorphClass() . '#' . $reference->getKey() : null,
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

    protected function createSkippedLog(string $templateCode, ?string $to, string $message, string $reason, ?Model $reference = null, ?User $actor = null): SmsLog
    {
        $normalizedPhone = filled($to) ? Phone::toE164Tz($to) : null;
        $branchId = $reference?->getAttribute('branch_id') ?? BranchContext::id();

        Log::info('SMS skipped by notification gate', [
            'template_code' => $templateCode,
            'reason' => $reason,
            'reference' => $reference ? $reference->getMorphClass() . '#' . $reference->getKey() : null,
        ]);

        return SmsLog::create([
            'branch_id' => $branchId,
            'provider' => 'beem',
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
}
