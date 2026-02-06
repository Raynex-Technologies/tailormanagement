<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
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

    /**
     * Send an SMS message and log the attempt.
     *
     * @param  string  $to  Phone number to send to
     * @param  string  $message  The message content
     * @param  Model|null  $reference  Optional model reference (e.g., Order, OrderPayment)
     * @param  User|null  $actor  The user initiating the SMS (optional)
     * @return SmsLog The SMS log record
     */
    public function send(string $to, string $message, ?Model $reference = null, ?User $actor = null): SmsLog
    {
        // Normalize phone number to E.164
        $normalizedPhone = Phone::toE164Tz($to);

        // Determine branch_id from reference or context
        $branchId = $reference && property_exists($reference, 'branch_id')
            ? $reference->branch_id
            : BranchContext::id();

        // Create initial log entry with queued status
        $smsLog = SmsLog::create([
            'branch_id' => $branchId,
            'provider' => 'beem',
            'to' => $normalizedPhone ?? $to, // Store normalized if available, original otherwise
            'message' => $message,
            'status' => SmsStatus::Queued,
            'provider_message_id' => null,
            'provider_response' => null,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference ? $reference->id : null,
            'created_by' => $actor?->id,
        ]);

        // Check if SMS is enabled
        if (! config('beem.enabled', false)) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => 'SMS disabled in configuration']),
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
        if (! $this->beemClient->isConfigured()) {
            $smsLog->update([
                'status' => SmsStatus::Failed,
                'provider_response' => json_encode(['error' => 'SMS provider not configured']),
            ]);

            Log::error('SMS failed - Beem not configured', ['log_id' => $smsLog->id]);

            return $smsLog;
        }

        try {
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
    public function sendIfPhonePresent(?string $to, string $message, ?Model $reference = null, ?User $actor = null): ?SmsLog
    {
        if (empty($to)) {
            // Create a failed log entry for missing phone
            $branchId = $reference && property_exists($reference, 'branch_id')
                ? $reference->branch_id
                : BranchContext::id();

            return SmsLog::create([
                'branch_id' => $branchId,
                'provider' => 'beem',
                'to' => 'missing',
                'message' => $message,
                'status' => SmsStatus::Failed,
                'provider_message_id' => null,
                'provider_response' => json_encode(['error' => 'Missing phone number']),
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference ? $reference->id : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $this->send($to, $message, $reference, $actor);
    }
}
