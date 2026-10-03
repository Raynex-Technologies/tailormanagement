<?php

namespace App\Console\Commands;

use App\Models\SmsRetry;
use App\Services\Sms\SmsRetryQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WorkSmsRetries extends Command
{
    protected $signature = 'sms:work-retries';

    protected $description = 'Process a bounded, paced portion of the persistent SMS retry queue';

    public function handle(SmsRetryQueue $queue): int
    {
        $lock = Cache::lock('sms-retries:worker', 300);
        if (! $lock->get()) {
            $this->info('An SMS retry worker is already running.');

            return self::SUCCESS;
        }
        $oldConfig = ['beem.retry_times' => config('beem.retry_times'), 'beem.timeout' => config('beem.timeout')];
        try {
            // One HTTP attempt only: automatic POST retries can duplicate accepted SMS.
            config(['beem.retry_times' => 1, 'beem.timeout' => 10]);
            $stale = 0;
            SmsRetry::withoutGlobalScopes()->where('status', 'processing')
                ->where('claimed_at', '<', now()->subMinutes(5))
                ->chunkById(100, function ($retries) use ($queue, &$stale) {
                    foreach ($retries as $retry) {
                        if (SmsRetry::withoutGlobalScopes()->whereKey($retry->id)->where('status', 'processing')
                            ->update(['status' => 'unknown', 'reason' => 'Worker interrupted. Reconcile delivery before retrying.'])) {
                            $stale++;
                            $queue->logEvent($retry->refresh(), 'sms.retry.interrupted');
                        }
                    }
                });
            if ($stale) {
                $queue->pausePending();
            }
            $started = microtime(true);
            $processed = 0;
            $seconds = max(1, min(45, (int) config('sms-retries.max_seconds')));
            $maximum = max(1, min(100, (int) config('sms-retries.max_messages')));
            $spacing = max(1, min(30, (int) config('sms-retries.spacing_seconds')));
            while ($processed < $maximum && microtime(true) - $started < $seconds) {
                if ((int) Cache::get('sms-retries:provider-until', 0) > now()->timestamp) {
                    break;
                }
                $retry = SmsRetry::withoutGlobalScopes()->where('status', 'pending')
                    ->where('available_at', '<=', now())->orderBy('id')->first();
                if (! $retry) {
                    break;
                }
                // Persist pacing between separate cron invocations as well as within one run.
                $next = (float) Cache::get('sms-retries:next-send', 0);
                if ($next > microtime(true)) {
                    break;
                }
                $queue->process($retry);
                Cache::put('sms-retries:next-send', microtime(true) + $spacing, 300);
                $processed++;
                if ($processed < $maximum && microtime(true) - $started + $spacing < $seconds) {
                    sleep($spacing);
                }
            }
            $this->info("Processed {$processed} SMS retry entries.");

            return self::SUCCESS;
        } finally {
            config($oldConfig);
            $lock->release();
        }
    }
}
