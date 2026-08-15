<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;

class WhatsappMessageLifecycle
{
    private const RANK = ['queued' => 0, 'submitting' => 1, 'accepted' => 2, 'sent' => 3, 'delivered' => 4, 'read' => 5, 'failed' => 6];

    public function accepted(WhatsappMessage $message, string $externalMessageId): WhatsappMessage
    {
        if ($message->external_message_id && $message->external_message_id !== $externalMessageId) {
            return $message->fresh();
        }
        $message->update(['status' => 'accepted', 'external_message_id' => $externalMessageId, 'accepted_at' => now(), 'failure_code' => null, 'failure_reason' => null]);
        $message->smsLog()->update(['status' => 'sent', 'provider_message_id' => $externalMessageId, 'provider_response' => json_encode(['success' => true, 'status' => 'accepted'])]);

        return $message->fresh();
    }

    public function apply(WhatsappMessage $message, string $status, CarbonImmutable $at, ?string $failureCode = null, ?string $failureReason = null): WhatsappMessage
    {
        $column = match ($status) {
            'sent' => 'sent_at','delivered' => 'delivered_at','read' => 'read_at','failed' => 'failed_at',default => null
        };
        $updates = ['meta_timestamp' => $message->meta_timestamp === null || $at->greaterThan($message->meta_timestamp) ? $at : $message->meta_timestamp];
        if ($column && (! $message->{$column} || $at->lessThan($message->{$column}))) {
            $updates[$column] = $at;
        } $currentRank = self::RANK[$message->status] ?? 0;
        $newRank = self::RANK[$status] ?? 0;
        $canFail = $status === 'failed'
            && ! in_array($message->status, ['delivered', 'read'], true)
            && ($message->meta_timestamp === null || $at->greaterThanOrEqualTo($message->meta_timestamp));
        if (($status !== 'failed' && $newRank >= $currentRank) || $canFail) {
            $updates['status'] = $status;
        } if ($status === 'failed') {
            $updates['failure_code'] = $failureCode;
            $updates['failure_reason'] = $failureReason;
        } $message->update($updates);

        $logStatus = match ($message->fresh()->status) {
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            'accepted', 'sent' => 'sent',
            default => 'queued',
        };
        $logUpdates = ['status' => $logStatus];
        if ($message->external_message_id) {
            $logUpdates['provider_message_id'] = $message->external_message_id;
        }
        if ($logStatus === 'failed') {
            $logUpdates['provider_response'] = json_encode(['error_code' => $failureCode, 'error_message' => $failureReason]);
        }
        $message->smsLog()->update($logUpdates);

        return $message->fresh();
    }
}
