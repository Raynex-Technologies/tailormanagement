<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Models\SmsRetry;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmsRetryQueue
{
    public function enqueue(CarbonInterface $from, CarbonInterface $to, User $actor): int
    {
        $count = 0;
        $success = DB::table('sms_logs as retry_success')->selectRaw('MAX(retry_success.id)')
            ->where('retry_success.status', SmsStatus::Sent->value);
        foreach (['branch_id', 'provider', 'to', 'message', 'template_code', 'reference_type', 'reference_id'] as $column) {
            $success->where(function ($query) use ($column) {
                $query->whereColumn('retry_success.'.$column, 'sms_logs.'.$column)
                    ->orWhere(fn ($query) => $query->whereNull('retry_success.'.$column)->whereNull('sms_logs.'.$column));
            });
        }
        SmsLog::query()->select('sms_logs.*')->selectSub($success, 'last_successful_id')
            ->unresolvedFailedRetries()->where('provider', 'beem')
            ->whereBetween('created_at', [$from, $to])->chunkById(100, function ($logs) use ($actor, &$count) {
                $entries = [];
                foreach ($logs as $log) {
                    $response = json_decode($log->provider_response ?? '{}', true) ?: [];
                    if (! Phone::toE164Tz($log->to) || ($response['outcome_unknown'] ?? false)
                        || preg_match('/timeout|timed out|connection reset|empty reply/i', (string) ($response['error'] ?? ''))) {
                        continue;
                    }
                    // Match historical attempts using the same identity as unresolvedFailedRetries.
                    $fingerprint = hash('sha256', json_encode([
                        $log->branch_id, $log->provider, $log->to, $log->message,
                        $log->template_code, $log->reference_type, $log->reference_id,
                    ]));
                    // Separate later identical notifications from a previously successful generation.
                    if ((int) $log->last_successful_id > $log->id) {
                        continue;
                    }
                    $fingerprint = hash('sha256', $fingerprint.':'.(int) $log->last_successful_id);
                    $entries[] = [
                        'branch_id' => $log->branch_id, 'sms_log_id' => $log->id,
                        'created_by' => $actor->id, 'fingerprint' => $fingerprint,
                        'status' => 'pending', 'attempts' => 0, 'available_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
                if ($entries !== []) {
                    $count += DB::table('sms_retries')->insertOrIgnore($entries);
                }
            });

        return $count;
    }

    public function process(SmsRetry $retry): void
    {
        // Atomic claim is durable even if PHP is terminated after provider acceptance.
        if (! SmsRetry::withoutGlobalScopes()->whereKey($retry->id)->where('status', 'pending')
            ->where('available_at', '<=', now())->update([
                'status' => 'processing', 'claimed_at' => now(), 'attempts' => DB::raw('attempts + 1'),
            ])) {
            return;
        }
        $retry->refresh();
        $previousBranch = BranchContext::id();
        $initialized = BranchContext::isInitialized();
        BranchContext::set((int) $retry->branch_id);
        try {
            $log = SmsLog::withoutBranchScope()->where('branch_id', $retry->branch_id)->find($retry->sms_log_id);
            $actor = User::find($retry->created_by);
            if (! $log || $log->provider !== 'beem' || ! $actor || ! $actor->can('sms.send')
                || ! $actor->can('sms.logs.view') || (! $actor->isGlobalAdmin() && (int) $actor->branch_id !== (int) $retry->branch_id)) {
                $retry->update(['status' => 'skipped', 'reason' => 'Source removed or permission no longer available.']);

                return;
            }
            if ($this->latestSuccessfulId($log) > $log->id
                || ! SmsLog::withoutBranchScope()->whereKey($log->id)->unresolvedFailedRetries()->exists()) {
                $retry->update(['status' => 'skipped', 'reason' => 'Already resolved.']);

                return;
            }
            // Reference resolution must remain inside the recorded branch in a console process.
            $reference = $log->reference;
            if ($reference && (int) $reference->getAttribute('branch_id') !== (int) $retry->branch_id) {
                $retry->update(['status' => 'skipped', 'reason' => 'Reference branch mismatch.']);

                return;
            }
            $attempt = app(SmsService::class)->retryFailedLog($log, $actor, $retry);
            $response = json_decode($attempt->provider_response ?? '{}', true) ?: [];
            $status = match ($attempt->status) {
                SmsStatus::Sent => 'sent',
                SmsStatus::Skipped => 'skipped',
                default => ($response['outcome_unknown'] ?? false) ? 'unknown' : 'failed',
            };
            $retry->update([
                'status' => $status, 'attempt_log_id' => $attempt->id,
                'reason' => in_array($status, ['failed', 'unknown']) ? 'Review the attempt in SMS Logs before resuming.' : null,
            ]);
            // Only an explicit rate-limit rejection is automatically retried; not ambiguous timeouts/5xx.
            if ($status === 'failed' && ($response['http_status'] ?? null) === 429 && $retry->attempts < 3) {
                $retry->update(['status' => 'pending', 'available_at' => now()->addMinutes($retry->attempts * 5)]);
                Cache::put('sms-retries:provider-until', now()->addMinutes($retry->attempts * 5)->timestamp, 900);
            } elseif (in_array($status, ['failed', 'unknown'])) {
                $this->pausePending();
            }
        } catch (\Throwable $exception) {
            $retry->update(['status' => 'unknown', 'reason' => 'Interrupted attempt. Reconcile delivery before retrying.']);
            $this->pausePending();
            report($exception);
        } finally {
            $initialized ? BranchContext::set($previousBranch) : BranchContext::clear();
        }
    }

    public function pausePending(): void
    {
        // Beem credentials are shared across branches; stop the provider backlog on failure.
        SmsRetry::withoutGlobalScopes()->where('status', 'pending')->update(['status' => 'paused']);
    }

    private function latestSuccessfulId(SmsLog $log): int
    {
        return (int) SmsLog::withoutBranchScope()->withTrashed()->where([
            'branch_id' => $log->branch_id, 'provider' => $log->provider,
            'to' => $log->to, 'message' => $log->message,
            'template_code' => $log->template_code, 'reference_type' => $log->reference_type,
            'reference_id' => $log->reference_id, 'status' => SmsStatus::Sent->value,
        ])->max('id');
    }
}
