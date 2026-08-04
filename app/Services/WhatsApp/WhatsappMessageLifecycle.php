<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use Carbon\CarbonImmutable;

class WhatsappMessageLifecycle
{
    private const RANK = ['queued' => 0, 'submitting' => 1, 'accepted' => 2, 'sent' => 3, 'delivered' => 4, 'read' => 5, 'failed' => 6];

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
        $canFail = $status === 'failed' && ! in_array($message->status, ['delivered', 'read'], true);
        if (($status !== 'failed' && $newRank >= $currentRank) || $canFail) {
            $updates['status'] = $status;
        } if ($status === 'failed') {
            $updates['failure_code'] = $failureCode;
            $updates['failure_reason'] = $failureReason;
        } $message->update($updates);

        return $message->fresh();
    }
}
